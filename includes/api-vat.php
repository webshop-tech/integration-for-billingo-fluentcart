<?php

namespace BillingoFluentCart;

if ( ! defined( 'ABSPATH' ) ) exit;

function check_tax_number_api($order_id, $api_key, $tax_number) {
    if (!preg_match('/^([0-9]{8})-([12345])-([0-9]{2})$/', $tax_number)) {
        return create_error($order_id, 'invalid_format', 'Invalid tax number format');
    }
    
    $endpoint = '/utils/check-tax-number/' . urlencode($tax_number);
    return make_billingo_request($order_id, $api_key, $endpoint, 'GET');
}

function find_partner_by_tax_number($order_id, $api_key, $tax_number) {
    $search_term = str_replace('-', '', $tax_number);
    
    $endpoint = '/partners?query=' . urlencode($search_term);
    $result = make_billingo_request($order_id, $api_key, $endpoint, 'GET');
    
    if (\is_wp_error($result)) {
        return $result;
    }
    
    if (isset($result['data']) && is_array($result['data'])) {
        foreach ($result['data'] as $partner) {
            $partner_tax = str_replace('-', '', $partner['taxcode'] ?? '');
            if ($partner_tax === $search_term) {
                return $partner;
            }
        }
    }
    
    return null;
}