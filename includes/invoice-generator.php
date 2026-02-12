<?php

namespace BillingoFluentCart;

if ( ! defined( 'ABSPATH' ) ) exit;

use FluentCart\App\Models\Cart;


function get_taxpayer_data($order_id, $api_key, $vat_number) {
    try {
        write_log($order_id, 'Checking tax number via Billingo API', 'Tax number', $vat_number);
        
        $result = check_tax_number_api($order_id, $api_key, $vat_number);
        
        if (\is_wp_error($result)) {
            write_log($order_id, 'Failed to check tax number', 'Error', $result->get_error_message());
            return null;
        }
        
        if (isset($result['result']) && $result['result'] === 'validation_ok') {
            write_log($order_id, 'Tax number is valid');
            return array(
                'valid' => true,
                'vat_id' => $vat_number,
            );
        }
        
        write_log($order_id, 'Tax number validation result', $result['result'] ?? 'unknown');
        return null;
        
    } catch (\Exception $e) {
        write_log($order_id, 'Failed to check tax number', 'Error', $e->getMessage());
        return null;
    }
}

function create_buyer_data($order, $current_order_id, $api_key, $vat_number, $billing_company_name) {
    $order_id = $order->id;
    
    $billing = $order->billing_address;
    if (!$billing) {
        return create_error($current_order_id, 'no_billing_address', "No billing address found for order " . absint($order_id));
    }

    $buyer_name = (isset($billing_company_name) && trim($billing_company_name) !== '')
        ? $billing_company_name
        : $billing->name;
    $buyer_postcode = $billing->postcode;
    $buyer_city = $billing->city;
    $buyer_address = $billing->address_1 . ($billing->address_2 ? ' ' . $billing->address_2 : '');
    $buyer_country = $billing->country ?? 'HU';
    $buyer_vat_id = null;
    
    if (!empty($vat_number)) {
        $taxpayer_data = get_taxpayer_data($order_id, $api_key, $vat_number);
        
        if ($taxpayer_data && isset($taxpayer_data['vat_id'])) {
            $buyer_vat_id = $taxpayer_data['vat_id'];
        } else {
            // Use the provided VAT number even if validation fails
            $buyer_vat_id = $vat_number;
        }
    }
    
    $buyer_data = array(
        'name' => $buyer_name,
        'postcode' => $buyer_postcode,
        'city' => $buyer_city,
        'address' => $buyer_address,
        'country' => $buyer_country,
    );
    
    if (!empty($buyer_vat_id)) {
        $buyer_data['tax_number'] = $buyer_vat_id;
    }
    
    $meta = $billing->meta;
    if (isset($meta['other_data']['email'])) {
        $buyer_data['email'] = $meta['other_data']['email'];
    }
    
    if (!empty($billing->phone)) {
        $buyer_data['phone'] = $billing->phone;
    }
    
    write_log($current_order_id, 'Buyer data created', 'Name', $buyer_name, 'City', $buyer_city);
    
    return $buyer_data;
}

function get_or_create_partner($order_id, $api_key, $buyer_data) {
    write_log($order_id, 'Getting or creating partner');
    
    // Try to find existing partner by tax number
    if (!empty($buyer_data['tax_number'])) {
        write_log($order_id, 'Searching for existing partner by tax number', $buyer_data['tax_number']);
        
        $existing_partner = find_partner_by_tax_number($order_id, $api_key, $buyer_data['tax_number']);
        
        if (\is_wp_error($existing_partner)) {
            write_log($order_id, 'Error searching for partner', $existing_partner->get_error_message());
        } elseif ($existing_partner !== null) {
            write_log($order_id, 'Found existing partner', 'ID', $existing_partner['id'], 'Name', $existing_partner['name']);
            return $existing_partner;
        } else {
            write_log($order_id, 'No existing partner found with this tax number');
        }
    }
    
    // Create new partner
    write_log($order_id, 'Creating new partner');
    
    $partner_payload = build_partner_data($buyer_data);
    $new_partner = create_partner_api($order_id, $api_key, $partner_payload);
    
    if (\is_wp_error($new_partner)) {
        write_log($order_id, 'Failed to create partner', $new_partner->get_error_message());
        return $new_partner;
    }
    
    write_log($order_id, 'Partner created successfully', 'ID', $new_partner['id'], 'Name', $new_partner['name']);
    
    return $new_partner;
}

function get_document_block($order_id, $api_key) {
    write_log($order_id, 'Fetching document blocks');
    
    // Try to get from settings first
    $configured_block_id = \get_option('billingo_fluentcart_document_block_id', '');
    
    if (!empty($configured_block_id)) {
        write_log($order_id, 'Using configured document block ID', $configured_block_id);
        return intval($configured_block_id);
    }
    
    // Otherwise, fetch the first available invoice block
    $blocks_result = get_document_blocks_api($order_id, $api_key, 'invoice');
    
    if (\is_wp_error($blocks_result)) {
        write_log($order_id, 'Failed to fetch document blocks', $blocks_result->get_error_message());
        return $blocks_result;
    }
    
    if (empty($blocks_result['data']) || !is_array($blocks_result['data'])) {
        return create_error($order_id, 'no_blocks', 'No document blocks found. Please create an invoice pad in Billingo.');
    }
    
    $first_block = $blocks_result['data'][0];
    write_log($order_id, 'Using first available document block', 'ID', $first_block['id'], 'Name', $first_block['name']);
    
    return intval($first_block['id']);
}

function generate_invoice($order, $current_order_id) {
    $order_id = $order->id;
    
    write_log($current_order_id, 'Starting invoice generation with Billingo v3 API', 'Currency', $order->currency);
    
    $api_key = \get_option('billingo_fluentcart_agent_api_key', '');
    
    if (empty($api_key)) {
        return create_error($current_order_id, 'api_error', 'API Key not configured.');
    }

    $checkout_data = Cart::where('order_id', $order_id)->first()['checkout_data'];
    $vat_number = $checkout_data['tax_data']['vat_number'] ?? null;
    $billing_company_name = $checkout_data['form_data']['billing_company_name'] ?? null;
    
    if ($vat_number) {
        write_log($current_order_id, 'VAT number found', $vat_number);
    } else {
        write_log($current_order_id, 'No VAT number provided');
    }

    $buyer_data = create_buyer_data($order, $current_order_id, $api_key, $vat_number, $billing_company_name);
    if (\is_wp_error($buyer_data)) {
        return $buyer_data;
    }

    $partner = get_or_create_partner($current_order_id, $api_key, $buyer_data);
    if (\is_wp_error($partner)) {
        return $partner;
    }

    $block_id = get_document_block($current_order_id, $api_key);
    if (\is_wp_error($block_id)) {
        return $block_id;
    }
    $payload = getPayload($order, $current_order_id, $partner['id'], $block_id);

    write_log($current_order_id, 'Creating document via Billingo v3 API');

    $result = create_billingo_document($current_order_id, $api_key, $payload);
    
    if (\is_wp_error($result)) {
        write_log($current_order_id, 'Document creation failed', $result->get_error_message());
        return $result;
    }
    
    write_log($current_order_id, 'Document created successfully', 'ID', $result['id'], 'Invoice number', $result['invoice_number']);

    return array(
        'success' => true,
        'invoice_number' => $result['invoice_number'],
        'document_id' => $result['id'],
        'gross_total' => $result['gross_total'] ?? null,
    );
}

function cancel_invoice($order, $reason) {
    $order_id = $order->id;
    
    write_log($order_id, 'Invoice cancellation triggered', 'Order ID', $order_id);
    
    init_paths();
    
    $api_key = \get_option('billingo_fluentcart_agent_api_key', '');
    
    if (empty($api_key)) {
        $error_message = 'API Key not configured';
        write_log($order_id, 'Invoice cancellation failed', 'Error', $error_message);
        log_activity($order_id, false, $error_message);
        return;
    }
    
    $document_id = get_document_id_by_order_id($order_id);
    
    if (empty($document_id) || $document_id == -1) {
        $error_message = 'No invoice found for this order';
        write_log($order_id, 'Invoice cancellation failed', 'Error', $error_message);
        log_activity($order_id, false, $error_message);
        return;
    }
    
    write_log($order_id, 'Cancelling document via Billingo v3 API', 'Document ID', $document_id);
    
    $result = cancel_document_api($order_id, $api_key, $document_id, $reason);
    
    if (\is_wp_error($result)) {
        $error_message = 'Failed to cancel invoice: ' . $result->get_error_message();
        write_log($order_id, 'Invoice cancellation failed', 'Error', $error_message);
        log_activity($order_id, false, $error_message);
        return;
    }
    
    write_log($order_id, 'Document cancelled successfully', 'Document ID', $document_id);
    
    $message = 'Billingo invoice cancelled successfully';
    log_activity($order_id, true, $message);
}

function create_invoice($order, $main_order = null) {
    $order_id = $order->id;
    if ($main_order === null)
        $main_order = $order;
    
    write_log($order_id, 'Invoice creation triggered', 'Order ID', $order_id, 'Main order ID', $main_order->id);
    
    init_paths();
    
    $existing_invoice_number = get_invoice_number_by_order_id($order_id);
    if ($existing_invoice_number) {
        $message = sprintf('Invoice already exists: %s', $existing_invoice_number);
        write_log($order_id, 'Invoice already exists', $existing_invoice_number);
        log_activity($order_id, true, $message);
        return;
    }
    
    $result = generate_invoice($main_order, $order_id);
    
    if (\is_wp_error($result)) {
        $error_message = 'Failed to generate invoice: ' . $result->get_error_message();
        write_log($order_id, 'Invoice generation failed', 'Error', $error_message);
        log_activity($order_id, false, $error_message);
        return;
    }
    
    if (!empty($result['success']) && !empty($result['invoice_number']) && !empty($result['document_id'])) {
        $invoice_number = $result['invoice_number'];
        $document_id = $result['document_id'];
        
        write_log($order_id, 'Invoice generated successfully', 'Invoice number', $invoice_number, 'Document ID', $document_id);
        
        save_invoice($order_id, $invoice_number, $document_id);
        
        $message = sprintf('Billingo invoice created: %s', $invoice_number);
        log_activity($order_id, true, $message);
    } else {
        $error_message = 'Failed to generate invoice: Missing invoice number or document ID';
        write_log($order_id, 'Invoice generation failed', 'Error', $error_message);
        log_activity($order_id, false, $error_message);
    }
}