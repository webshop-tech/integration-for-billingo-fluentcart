<?php

namespace BillingoFluentCart;

if ( ! defined( 'ABSPATH' ) ) exit;

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
    
    $data = json_decode($response_body, true);
    
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

function get_document_blocks_api($order_id, $api_key, $type = 'invoice') {
    $endpoint = '/document-blocks?type=' . $type;
    return make_billingo_request($order_id, $api_key, $endpoint, 'GET');
}

function create_partner_api($order_id, $api_key, $partner_data) {
    return make_billingo_request($order_id, $api_key, '/partners', 'POST', $partner_data);
}

function create_billingo_document($order_id, $api_key, $document_data) {
    return make_billingo_request($order_id, $api_key, '/documents', 'POST', $document_data);
}

function cancel_document_api($order_id, $api_key, $document_id, $reason = 'refund') {
    $endpoint = '/documents/' . intval($document_id) . '/cancel';
    $body = array(
        'cancellation_reason' => $reason
    );
    return make_billingo_request($order_id, $api_key, $endpoint, 'POST', $body);
}

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
    $endpoint = '/documents/vendor/' . urlencode((string)$order_id);
    $document = make_billingo_request($order_id, $api_key, $endpoint, 'GET');
    
    if (\is_wp_error($document)) {
        return $document;
    }
    
    if (!isset($document['id'])) {
        return create_error($order_id, 'document_not_found', 'Document not found for order ID: ' . $order_id);
    }
    
    $document_id = $document['id'];
    
    $pdf_result = download_document_pdf($order_id, $api_key, $document_id);
    
    if (\is_wp_error($pdf_result)) {
        return $pdf_result;
    }
    
    $safe_invoice_number = preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoice_number);
    $filename = 'invoice_' . $safe_invoice_number . '.pdf';
    
    return array(
        'success' => true,
        'pdf_data' => $pdf_result['pdf_data'],
        'filename' => $filename,
    );
}

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

function map_vat_rate($rate) {
    $rate_numeric = floatval($rate);
    
    if ($rate_numeric == 0) {
        return '0%';
    }
    
    return strval(intval($rate_numeric)) . '%';
}

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

function build_document_items($items_data) {
    $billingo_items = array();
    
    foreach ($items_data as $item) {
        $billingo_item = array(
            'name' => $item['name'],
            'unit_price' => floatval($item['unit_price']),
            'unit_price_type' => 'net',
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