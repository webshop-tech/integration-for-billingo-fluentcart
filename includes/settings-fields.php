<?php

namespace BillingoFluentCart;

if ( ! defined( 'ABSPATH' ) ) exit;
function add_settings_fields()
{

    \add_settings_section(
        'billingo_fluentcart_api_section',
        \__('API Settings', 'integration-for-billingo-fluentcart'),
        function() {
            echo '<p>' . \esc_html__('Enter your Billingo API credentials below.', 'integration-for-billingo-fluentcart') . '</p>';
        },
        'integration-for-billingo-fluentcart'
    );

    \add_settings_field(
        'billingo_fluentcart_agent_api_key',
        \__('Agent API Key', 'integration-for-billingo-fluentcart'),
        function() {
            $value = \get_option('billingo_fluentcart_agent_api_key', '');
            echo '<input type="password" name="billingo_fluentcart_agent_api_key" value="' . \esc_attr($value) . '" class="regular-text" autocomplete="off" />';
            echo '<p class="description"><a href="https://support.billingo.hu/content/951124273" target="_blank" rel="noopener noreferrer">' . \esc_html__('What is this?', 'integration-for-billingo-fluentcart') . '</a></p>';
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_api_section'
    );

    \add_settings_section(
        'billingo_fluentcart_invoice_section',
        \__('Invoice Settings', 'integration-for-billingo-fluentcart'),
        null,
        'integration-for-billingo-fluentcart'
    );

    \add_settings_field(
        'billingo_fluentcart_invoice_language',
        \__('Invoice Language', 'integration-for-billingo-fluentcart'),
        function () {
            $value = \get_option('billingo_fluentcart_invoice_language', LANGUAGE_HU);
            $languages = [
                LANGUAGE_HU => \__('Magyar (Hungarian)', 'integration-for-billingo-fluentcart'),
                LANGUAGE_EN => \__('English', 'integration-for-billingo-fluentcart'),
                LANGUAGE_DE => \__('Deutsch (German)', 'integration-for-billingo-fluentcart'),
                LANGUAGE_IT => \__('Italiano (Italian)', 'integration-for-billingo-fluentcart'),
                LANGUAGE_RO => \__('Română (Romanian)', 'integration-for-billingo-fluentcart'),
                LANGUAGE_SK => \__('Slovenčina (Slovak)', 'integration-for-billingo-fluentcart'),
                LANGUAGE_HR => \__('Hrvatski (Croatian)', 'integration-for-billingo-fluentcart'),
                LANGUAGE_FR => \__('Français (French)', 'integration-for-billingo-fluentcart'),
                LANGUAGE_ES => \__('Español (Spanish)', 'integration-for-billingo-fluentcart'),
                LANGUAGE_CZ => \__('Čeština (Czech)', 'integration-for-billingo-fluentcart'),
                LANGUAGE_PL => \__('Polski (Polish)', 'integration-for-billingo-fluentcart')
            ];
            echo '<select name="billingo_fluentcart_invoice_language">';
            foreach ($languages as $code => $name) {
                echo '<option value="' . \esc_attr($code) . '" ' . ($code == $value ? 'selected>' : '>') . \esc_html($name) . '</option>';
            }
            echo '</select>';
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_invoice_section'
    );

    \add_settings_field(
        'billingo_fluentcart_invoice_type',
        \__('Invoice Type', 'integration-for-billingo-fluentcart'),
        function () {
            $value = \get_option('billingo_fluentcart_invoice_type', INVOICE_TYPE_P_INVOICE);
            $types = [
                INVOICE_TYPE_P_INVOICE => \__('Paper Invoice', 'integration-for-billingo-fluentcart'),
                INVOICE_TYPE_E_INVOICE => \__('E-Invoice', 'integration-for-billingo-fluentcart')
            ];
            echo '<select name="billingo_fluentcart_invoice_type">';
            foreach ($types as $type_value => $type_name) {
                echo '<option value="' . \esc_attr($type_value) . '" ' . ($type_value == $value ? 'selected>' : '>') . \esc_html($type_name) . '</option>';
            }
            echo '</select>';
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_invoice_section'
    );

    \add_settings_field(
        'billingo_fluentcart_quantity_unit',
        \__('Quantity Unit', 'integration-for-billingo-fluentcart'),
        function () {
            $value = \get_option('billingo_fluentcart_quantity_unit', 'db');
            echo '<input type="text" name="billingo_fluentcart_quantity_unit" value="' . \esc_attr($value) . '" class="regular-text" />';
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_invoice_section'
    );

    \add_settings_field(
        'billingo_fluentcart_shipping_title',
        \__('Shipping Title', 'integration-for-billingo-fluentcart'),
        function () {
            $value = \get_option('billingo_fluentcart_shipping_title', 'Szállítás');
            echo '<input type="text" name="billingo_fluentcart_shipping_title" value="' . \esc_attr($value) . '" class="regular-text" />';
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_invoice_section'
    );

    \add_settings_field(
        'billingo_fluentcart_document_block_id',
        \__('Document Block ID', 'integration-for-billingo-fluentcart'),
        function () {
            $value = \get_option('billingo_fluentcart_document_block_id', '');
            echo '<input type="number" name="billingo_fluentcart_document_block_id" value="' . \esc_attr($value) . '" class="regular-text" min="1" />';
            echo '<p class="description">' . \esc_html__('Optional: Enter your Billingo document block (invoice pad) ID. If left empty, the first available invoice block will be used automatically.', 'integration-for-billingo-fluentcart') . '</p>';
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_invoice_section'
    );

    \add_settings_field(
        'billingo_fluentcart_payment_method',
        \__('Payment Method', 'integration-for-billingo-fluentcart'),
        function () {
            $value = \get_option('billingo_fluentcart_payment_method', 'Átutalás');
            $payment_methods = [
                'Átutalás' => \__('Wire Transfer', 'integration-for-billingo-fluentcart'),
                'Készpénz' => \__('Cash', 'integration-for-billingo-fluentcart'),
                'Bankkártya' => \__('Bank Card', 'integration-for-billingo-fluentcart'),
                'Csekk' => \__('Check', 'integration-for-billingo-fluentcart'),
                'Utánvét' => \__('Cash on Delivery', 'integration-for-billingo-fluentcart'),
                'PayPal' => 'PayPal',
                'Barion' => 'Barion',
                'Egyéb' => \__('Other', 'integration-for-billingo-fluentcart'),
            ];
            echo '<select name="billingo_fluentcart_payment_method">';
            foreach ($payment_methods as $method_value => $method_name) {
                echo '<option value="' . \esc_attr($method_value) . '" ' . ($method_value == $value ? 'selected>' : '>') . \esc_html($method_name) . '</option>';
            }
            echo '</select>';
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_invoice_section'
    );

    \add_settings_field(
        'billingo_fluentcart_shipping_vat',
        \__('Shipping VAT Rate', 'integration-for-billingo-fluentcart'),
        function () {
            $value = \get_option('billingo_fluentcart_shipping_vat', 27);
            $options = [0, 5, 18, 27];
            echo '<select name="billingo_fluentcart_shipping_vat">';
            foreach ($options as $option) {
                echo '<option value="' . \esc_attr($option) . '" ' . ($option == $value ? 'selected>' : '>') . \esc_html($option) . '%</option>';
            }
            echo '</select>';
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_invoice_section'
    );

    \add_settings_field(
        'billingo_fluentcart_apply_shipping_vat_field',
        \__('Apply to Tax Rates', 'integration-for-billingo-fluentcart'),
        function () {
            $current_rates = getShippingTaxRates();
            $selected_vat = \get_option('billingo_fluentcart_shipping_vat', 27);

            if (empty($current_rates)) {
                echo '<p class="description" style="color: #dc3232;"><strong>' . \esc_html__('Warning:', 'integration-for-billingo-fluentcart') . '</strong> ' . \esc_html__('No tax rates found. Please configure tax rates in FluentCart first.', 'integration-for-billingo-fluentcart') . '</p>';
            } elseif (count($current_rates) === 1 && $current_rates[0] == $selected_vat) {
                echo '<p class="description" style="color: #46b450;">' . \esc_html__('All tax rates are already set to', 'integration-for-billingo-fluentcart') . ' ' . \esc_html($selected_vat) . '%</p>';
            } else {
                echo '<p class="description">' . \esc_html__('Current shipping VAT rates in use:', 'integration-for-billingo-fluentcart') . ' ' . \esc_html(\implode(', ', $current_rates)) . '%</p>';
            }
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_invoice_section'
    );

    \add_settings_field(
        'billingo_fluentcart_tax_exempt',
        \__('Vat Exempt', 'integration-for-billingo-fluentcart'),
        function () {
            $value = \get_option('billingo_fluentcart_tax_exempt', 0);
            echo '<input type="checkbox" name="billingo_fluentcart_tax_exempt" value="1" ' . ($value ? 'checked' : '') . ' />';
            echo '<label for="billingo_fluentcart_tax_exempt">' . \esc_html__('I am exempt from Hungarian VAT.', 'integration-for-billingo-fluentcart') . '</label>';
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_invoice_section'
    );

    \add_settings_field(
        'billingo_fluentcart_zero_invoice',
        \__('Zero-Value Invoice', 'integration-for-billingo-fluentcart'),
        function () {
            $value = \get_option('billingo_fluentcart_zero_invoice', 1);
            echo '<input type="checkbox" name="billingo_fluentcart_zero_invoice" value="1" ' . ($value ? 'checked' : '') . ' />';
            echo '<label for="billingo_fluentcart_zero_invoice">' . \esc_html__('Create invoice when cart total is zero.', 'integration-for-billingo-fluentcart') . '</label>';
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_invoice_section'
    );
}