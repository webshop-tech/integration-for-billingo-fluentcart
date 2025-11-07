<?php

namespace SzamlazzHuFluentCart;

if (!\defined('ABSPATH')) {
    exit;
}

use FluentCart\App\Models\Activity;
use FluentCart\App\Models\Cart;
use FluentCart\App\Models\OrderItem;


function get_vat_number($order_id) {
    $checkout_data = Cart::where('order_id', $order_id)->first()['checkout_data'];
    return $checkout_data['tax_data']['vat_number'] ?? null;
}

function get_taxpayer_data($order_id, $api_key, $vat_number) {
    try {
        write_log($order_id, 'Fetching taxpayer data from NAV', 'VAT number', $vat_number);
        
        $taxpayer_data = get_taxpayer_api($order_id, $api_key, $vat_number);
        
        if (\is_wp_error($taxpayer_data)) {
            write_log($order_id, 'Failed to fetch taxpayer data', 'Error', $taxpayer_data->get_error_message());
            return null;
        }
        
        if (!empty($taxpayer_data['valid'])) {
            write_log($order_id, 'Taxpayer is valid');
            
            if (isset($taxpayer_data['name'])) {
                write_log($order_id, 'Taxpayer name extracted', 'Name', $taxpayer_data['name']);
            }
            if (isset($taxpayer_data['vat_id'])) {
                write_log($order_id, 'VAT ID formatted', $taxpayer_data['vat_id']);
            }
            if (isset($taxpayer_data['postcode']) && isset($taxpayer_data['city']) && isset($taxpayer_data['address'])) {
                write_log($order_id, 'Taxpayer address extracted', $taxpayer_data['postcode'], $taxpayer_data['city'], $taxpayer_data['address']);
            }
            
            write_log($order_id, 'Taxpayer data successfully parsed from NAV');
            
            return $taxpayer_data;
        }
        
        write_log($order_id, 'Taxpayer is not valid');
        return null;
        
    } catch (\Exception $e) {
        write_log($order_id, 'Failed to fetch taxpayer data', 'Error', $e->getMessage());
        return null;
    }
}

function create_buyer_data($order, $current_order_id, $api_key, $vat_number = null) {
    $order_id = $order->id;
    
    $billing = $order->billing_address;
    if (!$billing) {
        return create_error($current_order_id, 'no_billing_address', "No billing address found for order " . absint($order_id));
    }
    
    $buyer_name = $billing->name;
    $buyer_postcode = $billing->postcode;
    $buyer_city = $billing->city;
    $buyer_address = $billing->address_1 . ($billing->address_2 ? ' ' . $billing->address_2 : '');
    $buyer_country = $billing->country ?? '';
    $buyer_vat_id = null;
    
    if (!empty($vat_number)) {
        $taxpayer_data = get_taxpayer_data($order_id, $api_key, $vat_number);
        
        if ($taxpayer_data) {
            if (isset($taxpayer_data['name'])) {
                $buyer_name = $taxpayer_data['name'];
            }
            if (isset($taxpayer_data['vat_id'])) {
                $buyer_vat_id = $taxpayer_data['vat_id'];
            }
            if (isset($taxpayer_data['postcode'])) {
                $buyer_postcode = $taxpayer_data['postcode'];
            }
            if (isset($taxpayer_data['city'])) {
                $buyer_city = $taxpayer_data['city'];
            }
            if (isset($taxpayer_data['address'])) {
                $buyer_address = $taxpayer_data['address'];
            }
        } else {
            $buyer_vat_id = $buyer_country . $vat_number;
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
        $buyer_data['send_email'] = false; // Don't send email by default
    }
    
    write_log($current_order_id, 'Buyer data created', 'Name', $buyer_name, 'City', $buyer_city);
    
    return $buyer_data;
}

function create_seller_data($current_order_id) {
    return array(
        'email_reply_to' => \get_option('admin_email'),
        'email_subject' => 'Invoice for order #' . $current_order_id,
        'email_content' => 'Thank you for your order. Please find your invoice attached.',
    );
}

function build_order_items_data($order, $current_order_id) {
    $order_id = $order->id;
    $items = OrderItem::where('order_id', $order_id)->get();
    
    if ($items->isEmpty()) {
        return create_error($current_order_id, 'no_items', "No items found for order " . absint($order_id));
    }
    
    $quantity_unit = \get_option('szamlazz_hu_quantity_unit', 'db');
    
    write_log($current_order_id, 'Building order items', 'Item count', $items->count());
    
    $items_data = array();
    
    foreach ($items as $order_item) {
        $taxRate = "0";
        $tax_amount = 0;
        
        if ($order->tax_behavior != 0) {
            if (isset($order_item->line_meta['tax_config']['rates'][0]['rate'])) {
                $taxRate = $order_item->line_meta['tax_config']['rates'][0]['rate'];
            }
            $tax_amount = $order_item->tax_amount / 100;
        }
        
        $net_price = $order_item->line_total / 100;
        $gross_amount = $net_price + $tax_amount;
        
        $items_data[] = array(
            'name' => $order_item->title,
            'quantity' => $order_item->quantity,
            'unit' => $quantity_unit,
            'unit_price' => $order_item->unit_price / 100,
            'vat_rate' => $taxRate,
            'net_price' => $net_price,
            'vat_amount' => $tax_amount,
            'gross_amount' => $gross_amount,
        );
        
        write_log(
            $current_order_id, 
            'Item', 
            $order_item->title, 
            'Qty', 
            $order_item->quantity, 
            'Unit price', 
            $order_item->unit_price / 100,
            'Tax rate', 
            $taxRate . '%',
            'Net', 
            $net_price,
            'VAT', 
            $tax_amount,
            'Gross', 
            $gross_amount
        );
    }
    
    if ($order->shipping_total != 0) {
        $shipping_title = \get_option('szamlazz_hu_shipping_title', 'Szállítás');
        $shipping_net = $order->shipping_total / 100;
        $shipping_vat_amount = 0;
        $shipping_vat_rate = "0";
        
        if ($order->tax_behavior != 0) {
            $shipping_vat = \get_option('szamlazz_hu_shipping_vat', 27);
            $shipping_vat_rate = strval($shipping_vat);
            $shipping_vat_amount = $shipping_net * ($shipping_vat / 100);
        }
        
        $shipping_gross = $shipping_net + $shipping_vat_amount;
        
        $items_data[] = array(
            'name' => $shipping_title,
            'quantity' => 1,
            'unit' => 'db',
            'unit_price' => $shipping_net,
            'vat_rate' => $shipping_vat_rate,
            'net_price' => $shipping_net,
            'vat_amount' => $shipping_vat_amount,
            'gross_amount' => $shipping_gross,
        );
    }
    
    return $items_data;
}

function log_activity($order_id, $success, $message) {
    Activity::create([
        'status' => $success ? 'success' : 'failed',
        'log_type' => 'activity',
        'module_type' => 'FluentCart\App\Models\Order',
        'module_id' => $order_id,
        'module_name' => 'order',
        'title' => $success ? 'Számlázz.hu invoice successfully generated' : 'Számlázz.hu invoice generation failed',
        'content' => $message
    ]);
}

function generate_invoice($order, $current_order_id) {
    $order_id = $order->id;
    
    write_log($current_order_id, 'Starting invoice generation', 'Currency', $order->currency);
    
    $api_key = \get_option('szamlazz_hu_agent_api_key', '');
    
    if (empty($api_key)) {
        return create_error($current_order_id, 'api_error', 'API Key not configured.');
    }
    
    $vat_number = get_vat_number($order_id);
    if ($vat_number) {
        write_log($current_order_id, 'VAT number found', $vat_number);
    } else {
        write_log($current_order_id, 'No VAT number provided');
    }
    
    $buyer_data = create_buyer_data($order, $current_order_id, $api_key, $vat_number);
    if (\is_wp_error($buyer_data)) {
        return $buyer_data;
    }
    
    $seller_data = create_seller_data($current_order_id);
    
    $invoice_type = \get_option('szamlazz_hu_invoice_type', 1);
    
    $invoice_language = \get_option('szamlazz_hu_invoice_language', 'hu');
    
    $invoice_type_name = ($invoice_type == 2) ? 'E-Invoice' : 'Paper Invoice';
    write_log($order_id, 'Invoice type set to', $invoice_type_name);
    write_log($order_id, 'Invoice language set to', $invoice_language);
    
    $items_data = build_order_items_data($order, $current_order_id);
    if (\is_wp_error($items_data)) {
        return $items_data;
    }
    
    $today = gmdate('Y-m-d');
    $due_date = gmdate('Y-m-d', strtotime('+8 days'));
    
    $header_data = array(
        'issue_date' => $today,
        'fulfillment_date' => $today,
        'due_date' => $due_date,
        'payment_method' => 'Átutalás',
        'currency' => $order->currency,
        'language' => $invoice_language,
        'comment' => '',
        'order_number' => strval($current_order_id),
        'proforma_number' => '',
        'prepayment_invoice' => 'false',
        'final_invoice' => 'false',
        'corrective_invoice' => 'false',
        'corrective_invoice_number' => '',
        'proforma' => 'false',
        'invoice_prefix' => '',
        'paid' => 'false',
    );
    
    $params = array(
        'invoice_type' => $invoice_type,
        'download_pdf' => true,
        'header' => $header_data,
        'seller' => $seller_data,
        'buyer' => $buyer_data,
        'items' => $items_data,
    );
    
    write_log($current_order_id, 'Generating invoice via API');
    
    return generate_invoice_api($current_order_id, $api_key, $params);
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
    
    $result = generate_invoice($main_order);
    
    if (\is_wp_error($result)) {
        $error_message = 'Failed to generate invoice: ' . $result->get_error_message();
        write_log($order_id, 'Invoice generation failed', 'Error', $error_message);
        log_activity($order_id, false, $error_message);
        return;
    }
    
    if (!empty($result['success']) && !empty($result['invoice_number'])) {
        $invoice_number = $result['invoice_number'];
        
        write_log($order_id, 'Invoice generated successfully', 'Invoice number', $invoice_number);
        
        save_invoice($order_id, $invoice_number);
        
        $message = sprintf('Számlázz.hu invoice created: %s', $invoice_number);
        log_activity($order_id, true, $message);
    } else {
        $error_message = 'Failed to generate invoice: Unknown error';
        write_log($order_id, 'Invoice generation failed', 'Error', $error_message);
        log_activity($order_id, false, $error_message);
    }
}
