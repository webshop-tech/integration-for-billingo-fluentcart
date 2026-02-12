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
