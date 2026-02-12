<?php

namespace BillingoFluentCart;

if ( ! defined( 'ABSPATH' ) ) exit;

function build_partner_data($buyer_info): array
{
    $countryCode = $buyer_info['country'] ?: 'HU';
    $partner = array(
        'name' => $buyer_info['name'],
        'address' => array(
            'country_code' => $countryCode,
            'post_code' => $buyer_info['postcode'],
            'city' => $buyer_info['city'],
            'address' => $buyer_info['address'],
        ),
    );

    if (!empty($buyer_info['email'])) {
        $partner['emails'] = array($buyer_info['email']);
    }

    if (!empty($buyer_info['tax_number'])) {
        if ($countryCode == 'HU') {
            $partner['taxcode'] = $buyer_info['tax_number'];
        } else {
            $partner['taxcode'] = $countryCode . $buyer_info['tax_number'];
        }

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

function get_or_create_partner($order_id, $api_key, $buyer_data) {
    write_log($order_id, 'Getting or creating partner');

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