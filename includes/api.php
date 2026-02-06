<?php

namespace BillingoFluentCart;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Make a generic request to the Billingo v3 API
 */
function make_billingo_request($order_id, $api_key, $endpoint, $method = 'GET', $body = null) {
    $url = 'https://api.billingo.hu/v3' . $endpoint;
    
    $args = array(
        'method' => $method,
        'timeout' => 30,
        'headers' => array(
            'X-API-KEY' => $api_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ),
    );
    
    if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $args['body'] = json_encode($body);
    }
    
    $response = \wp_remote_request($url, $args);
    
    if (\is_wp_error($response)) {
        return $response;
    }
    
    $response_code = \wp_remote_retrieve_response_code($response);
    $response_body = \wp_remote_retrieve_body($response);
    
    // Try to decode JSON response
    $data = json_decode($response_body, true);
    
    // Handle error responses
    if ($response_code >= 400) {
        $error_message = 'API error';
        
        if ($data && isset($data['message'])) {
            $error_message = $data['message'];
        } elseif ($data && isset($data['error']['message'])) {
            $error_message = $data['error']['message'];
        }
        
        return create_error($order_id, 'api_error', sprintf('API returned %d: %s', $response_code, $error_message));
    }
    
    return $data;
}

/**
 * Get list of document blocks (invoice pads)
 */
function get_document_blocks_api($order_id, $api_key, $type = 'invoice') {
    $endpoint = '/document-blocks?type=' . $type;
    return make_billingo_request($order_id, $api_key, $endpoint, 'GET');
}

/**
 * Search for a partner by tax number
 */
function find_partner_by_tax_number($order_id, $api_key, $tax_number) {
    // Remove dashes and format the tax number for search
    $search_term = str_replace('-', '', $tax_number);
    
    $endpoint = '/partners?query=' . urlencode($search_term);
    $result = make_billingo_request($order_id, $api_key, $endpoint, 'GET');
    
    if (\is_wp_error($result)) {
        return $result;
    }
    
    // Check if we have results
    if (isset($result['data']) && is_array($result['data'])) {
        foreach ($result['data'] as $partner) {
            // Check if tax code matches (with or without dashes)
            $partner_tax = str_replace('-', '', $partner['taxcode'] ?? '');
            if ($partner_tax === $search_term) {
                return $partner;
            }
        }
    }
    
    return null;
}

/**
 * Create a new partner in Billingo
 */
function create_partner_api($order_id, $api_key, $partner_data) {
    return make_billingo_request($order_id, $api_key, '/partners', 'POST', $partner_data);
}

/**
 * Check and validate a Hungarian tax number via NAV
 */
function check_tax_number_api($order_id, $api_key, $tax_number) {
    // Format: 12345678-1-23
    if (!preg_match('/^([0-9]{8})-([12345])-([0-9]{2})$/', $tax_number)) {
        return create_error($order_id, 'invalid_format', 'Invalid tax number format');
    }
    
    $endpoint = '/utils/check-tax-number/' . urlencode($tax_number);
    $result = make_billingo_request($order_id, $api_key, $endpoint, 'GET');
    
    if (\is_wp_error($result)) {
        return $result;
    }
    
    return $result;
}

/**
 * Create a document (invoice) in Billingo v3
 */
function create_billingo_document($order_id, $api_key, $document_data) {
    return make_billingo_request($order_id, $api_key, '/documents', 'POST', $document_data);
}

/**
 * Download a document PDF
 */
function download_document_pdf($order_id, $api_key, $document_id) {
    $url = 'https://api.billingo.hu/v3/documents/' . $document_id . '/download';
    
    $args = array(
        'method' => 'GET',
        'timeout' => 30,
        'headers' => array(
            'X-API-KEY' => $api_key,
            'Accept' => 'application/pdf',
        ),
    );
    
    $response = \wp_remote_request($url, $args);
    
    if (\is_wp_error($response)) {
        return $response;
    }
    
    $response_code = \wp_remote_retrieve_response_code($response);
    
    if ($response_code === 202) {
        // PDF not ready yet
        return create_error($order_id, 'pdf_not_ready', 'PDF is being generated, please try again later');
    }
    
    if ($response_code !== 200) {
        return create_error($order_id, 'api_error', 'Failed to download PDF', $response_code);
    }
    
    $pdf_data = \wp_remote_retrieve_body($response);
    
    return array(
        'success' => true,
        'pdf_data' => $pdf_data,
    );
}

function fetch_invoice_pdf($order_id, $api_key, $invoice_number) {
    // First, get the document by vendor_id (which is the order_id)
    $endpoint = '/documents/vendor/' . urlencode((string)$order_id);
    $document = make_billingo_request($order_id, $api_key, $endpoint, 'GET');
    
    if (\is_wp_error($document)) {
        return $document;
    }
    
    if (!isset($document['id'])) {
        return create_error($order_id, 'document_not_found', 'Document not found for order ID: ' . $order_id);
    }
    
    $document_id = $document['id'];
    
    // Now download the PDF using the document ID
    $pdf_result = download_document_pdf($order_id, $api_key, $document_id);
    
    if (\is_wp_error($pdf_result)) {
        return $pdf_result;
    }
    
    // Generate a safe filename
    $safe_invoice_number = preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoice_number);
    $filename = 'invoice_' . $safe_invoice_number . '.pdf';
    
    return array(
        'success' => true,
        'pdf_data' => $pdf_result['pdf_data'],
        'filename' => $filename,
    );
}

/**
 * Map old payment method to new Billingo v3 enum
 */
function map_payment_method($old_method) {
    $mapping = array(
        'Átutalás' => 'wire_transfer',
        'Készpénz' => 'cash',
        'Bankkártya' => 'bankcard',
        'Csekk' => 'postai_csekk',
        'Utánvét' => 'cash_on_delivery',
        'PayPal' => 'paypal',
        'Barion' => 'barion',
        'Egyéb' => 'other',
    );
    
    return $mapping[$old_method] ?? 'wire_transfer';
}

/**
 * Map VAT rate to Billingo v3 format
 */
function map_vat_rate($rate) {
    // Rate should be a string like "27", "18", "5"
    $rate_numeric = floatval($rate);
    
    // Special cases
    if ($rate_numeric == 0) {
        return '0%';
    }
    
    // Return as percentage string
    return strval(intval($rate_numeric)) . '%';
}

/**
 * Build partner data structure for Billingo v3
 */
function build_partner_data($buyer_info) {
    $partner = array(
        'name' => $buyer_info['name'],
        'address' => array(
            'country_code' => $buyer_info['country'] ?: 'HU',
            'post_code' => $buyer_info['postcode'],
            'city' => $buyer_info['city'],
            'address' => $buyer_info['address'],
        ),
    );
    
    if (!empty($buyer_info['email'])) {
        $partner['emails'] = array($buyer_info['email']);
    }
    
    if (!empty($buyer_info['tax_number'])) {
        $partner['taxcode'] = $buyer_info['tax_number'];
        
        // Determine tax type based on tax number format
        if (preg_match('/^([0-9]{8})-([12345])-([0-9]{2})$/', $buyer_info['tax_number'])) {
            $partner['tax_type'] = 'HAS_TAX_NUMBER';
        } else {
            $partner['tax_type'] = 'FOREIGN';
        }
    } else {
        $partner['tax_type'] = 'NO_TAX_NUMBER';
    }
    
    if (!empty($buyer_info['phone'])) {
        $partner['phone'] = $buyer_info['phone'];
    }
    
    return $partner;
}

/**
 * Build document items for Billingo v3
 */
function build_document_items($items_data) {
    $billingo_items = array();
    
    foreach ($items_data as $item) {
        $billingo_item = array(
            'name' => $item['name'],
            'unit_price' => floatval($item['unit_price']),
            'unit_price_type' => 'net', // We work with net prices
            'quantity' => floatval($item['quantity']),
            'unit' => $item['unit'],
            'vat' => map_vat_rate($item['vat_rate']),
        );
        
        if (!empty($item['comment'])) {
            $billingo_item['comment'] = $item['comment'];
        }
        
        $billingo_items[] = $billingo_item;
    }
    
    return $billingo_items;
}

/**
 * Build complete document payload for Billingo v3
 */
function build_document_payload($params) {
    $payload = array(
        'partner_id' => $params['partner_id'],
        'block_id' => $params['block_id'],
        'type' => $params['type'] ?? 'invoice',
        'fulfillment_date' => $params['fulfillment_date'],
        'due_date' => $params['due_date'],
        'payment_method' => $params['payment_method'],
        'language' => $params['language'],
        'currency' => $params['currency'],
        'items' => $params['items'],
    );
    
    if (isset($params['vendor_id'])) {
        $payload['vendor_id'] = $params['vendor_id'];
    }
    
    if (isset($params['bank_account_id'])) {
        $payload['bank_account_id'] = $params['bank_account_id'];
    }
    
    if (isset($params['conversion_rate'])) {
        $payload['conversion_rate'] = floatval($params['conversion_rate']);
    }
    
    if (isset($params['electronic'])) {
        $payload['electronic'] = (bool)$params['electronic'];
    }
    
    if (isset($params['paid'])) {
        $payload['paid'] = (bool)$params['paid'];
    }
    
    if (isset($params['comment'])) {
        $payload['comment'] = $params['comment'];
    }
    
    if (isset($params['settings'])) {
        $payload['settings'] = $params['settings'];
    }
    
    return $payload;
}
