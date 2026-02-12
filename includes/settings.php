<?php

namespace BillingoFluentCart;

use FluentCart\App\Models\TaxRate;

if ( ! defined( 'ABSPATH' ) ) exit;

const LANGUAGE_HU = 'hu';
const LANGUAGE_EN = 'en';
const LANGUAGE_DE = 'de';
const LANGUAGE_IT = 'it';
const LANGUAGE_RO = 'ro';
const LANGUAGE_SK = 'sk';
const LANGUAGE_HR = 'hr';
const LANGUAGE_FR = 'fr';
const LANGUAGE_ES = 'es';
const LANGUAGE_CZ = 'cz';
const LANGUAGE_PL = 'pl';

const INVOICE_TYPE_P_INVOICE = 1;
const INVOICE_TYPE_E_INVOICE = 2;

\add_action('admin_menu', function() {
    \add_options_page(
        \__('Billingo for FluentCart Settings', 'integration-for-billingo-fluentcart'),
        'Billingo',
        'manage_options',
        'integration-for-billingo-fluentcart',
        __NAMESPACE__ . '\\settings_page'
    );
});


\add_action('admin_init', function() {
    \register_setting('billingo_fluentcart_settings', 'billingo_fluentcart_agent_api_key', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return is_string($value) ? trim(wp_unslash($value)) : '';
        }
    ]);
    \register_setting('billingo_fluentcart_settings', 'billingo_fluentcart_shipping_vat', [
        'type' => 'integer',
        'default' => 27,
        'sanitize_callback' => function($value) {
            $allowed = [0, 5, 18, 27];
            return in_array((int)$value, $allowed) ? (int)$value : 27;
        }
    ]);
    \register_setting('billingo_fluentcart_settings', 'billingo_fluentcart_invoice_language', [
        'type' => 'string',
        'default' => LANGUAGE_HU,
        'sanitize_callback' => function($value) {
            $allowed = [LANGUAGE_HU, LANGUAGE_EN, LANGUAGE_DE, LANGUAGE_IT, LANGUAGE_RO, 
                        LANGUAGE_SK, LANGUAGE_HR, LANGUAGE_FR, LANGUAGE_ES, LANGUAGE_CZ, LANGUAGE_PL];
            return in_array($value, $allowed) ? $value : LANGUAGE_HU;
        }
    ]);
    \register_setting('billingo_fluentcart_settings', 'billingo_fluentcart_invoice_type', [
        'type' => 'integer',
        'default' => INVOICE_TYPE_P_INVOICE,
        'sanitize_callback' => function($value) {
            $allowed = [INVOICE_TYPE_P_INVOICE, INVOICE_TYPE_E_INVOICE];
            return in_array((int)$value, $allowed) ? (int)$value : INVOICE_TYPE_P_INVOICE;
        }
    ]);
    \register_setting('billingo_fluentcart_settings', 'billingo_fluentcart_quantity_unit', [
        'type' => 'string',
        'default' => 'db',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
    \register_setting('billingo_fluentcart_settings', 'billingo_fluentcart_shipping_title', [
        'type' => 'string',
        'default' => 'Szállítás',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
    \register_setting('billingo_fluentcart_settings', 'billingo_fluentcart_document_block_id', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => function($value) {
            return empty($value) ? '' : absint($value);
        }
    ]);
    \register_setting('billingo_fluentcart_settings', 'billingo_fluentcart_payment_method', [
        'type' => 'string',
        'default' => 'Átutalás',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
    \register_setting('billingo_fluentcart_settings', 'billingo_fluentcart_tax_exempt', [
        'type' => 'boolean',
        'default' => 0,
        'sanitize_callback' => function($value) {
            return $value ? 1 : 0;
        }
    ]);
    \register_setting('billingo_fluentcart_settings', 'billingo_fluentcart_zero_invoice', [
        'type' => 'boolean',
        'default' => 1,
        'sanitize_callback' => function($value) {
            return $value ? 1 : 0;
        }
    ]);
    
    if (isset($_POST['billingo_fluentcart_clear_cache']) && \check_admin_referer('billingo_fluentcart_clear_cache_action', 'billingo_fluentcart_clear_cache_nonce')) {
        clear_cache();
        \add_settings_error('billingo_fluentcart_messages', 'billingo_fluentcart_cache_cleared', \__('Cache cleared successfully', 'integration-for-billingo-fluentcart'), 'updated');
    }
    
    if (isset($_POST['billingo_fluentcart_apply_shipping_vat']) && \check_admin_referer('billingo_fluentcart_apply_shipping_vat_action', 'billingo_fluentcart_apply_shipping_vat_nonce')) {
        $shipping_vat = \get_option('billingo_fluentcart_shipping_vat', 27);
        setShippingTaxRate($shipping_vat);
        \add_settings_error('billingo_fluentcart_messages', 'billingo_fluentcart_vat_applied', \__('Shipping VAT rate applied to all tax rates successfully', 'integration-for-billingo-fluentcart'), 'updated');
    }

    add_settings_fields();

});

\add_filter('plugin_action_links_' . \plugin_basename(\dirname(__DIR__) . '/integration-for-billingo-fluentcart.php'), function($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        \admin_url('options-general.php?page=integration-for-billingo-fluentcart'),
        \__('Settings', 'integration-for-billingo-fluentcart')
    );
    \array_unshift($links, $settings_link);
    return $links;
});


function setShippingTaxRate($vatRate) {
    $taxRates = TaxRate::where('country', 'HU')->get();
    foreach ($taxRates as $rate) {
        $rate->for_shipping = $vatRate;
        $rate->save();
    }
}

function getShippingTaxRates(): array
{
    $taxRates = TaxRate::where('country', 'HU')->get();
    $rates = [];
    
    foreach ($taxRates as $taxRate) {
        $rate = $taxRate->for_shipping !== null ? $taxRate->for_shipping : $taxRate->rate;
        $rates[] = $rate;
    }
    
    return array_values(array_unique($rates));
}