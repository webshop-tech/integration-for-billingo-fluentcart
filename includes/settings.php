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
    
    if (isset($_POST['billingo_fluentcart_clear_cache']) && \check_admin_referer('billingo_fluentcart_clear_cache_action', 'billingo_fluentcart_clear_cache_nonce')) {
        clear_cache();
        \add_settings_error('billingo_fluentcart_messages', 'billingo_fluentcart_cache_cleared', \__('Cache cleared successfully', 'integration-for-billingo-fluentcart'), 'updated');
    }
    
    if (isset($_POST['billingo_fluentcart_apply_shipping_vat']) && \check_admin_referer('billingo_fluentcart_apply_shipping_vat_action', 'billingo_fluentcart_apply_shipping_vat_nonce')) {
        $shipping_vat = \get_option('billingo_fluentcart_shipping_vat', 27);
        setShippingTaxRate($shipping_vat);
        \add_settings_error('billingo_fluentcart_messages', 'billingo_fluentcart_vat_applied', \__('Shipping VAT rate applied to all tax rates successfully', 'integration-for-billingo-fluentcart'), 'updated');
    }
    
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
        function() {
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
        function() {
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
        function() {
            $value = \get_option('billingo_fluentcart_quantity_unit', 'db');
            echo '<input type="text" name="billingo_fluentcart_quantity_unit" value="' . \esc_attr($value) . '" class="regular-text" />';
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_invoice_section'
    );
    
    \add_settings_field(
        'billingo_fluentcart_shipping_title',
        \__('Shipping Title', 'integration-for-billingo-fluentcart'),
        function() {
            $value = \get_option('billingo_fluentcart_shipping_title', 'Szállítás');
            echo '<input type="text" name="billingo_fluentcart_shipping_title" value="' . \esc_attr($value) . '" class="regular-text" />';
        },
        'integration-for-billingo-fluentcart',
        'billingo_fluentcart_invoice_section'
    );
    
    \add_settings_field(
        'billingo_fluentcart_document_block_id',
        \__('Document Block ID', 'integration-for-billingo-fluentcart'),
        function() {
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
        function() {
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
        function() {
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
        function() {
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

function settings_page() {
    if (!\current_user_can('manage_options')) {
        return;
    }
    
    \settings_errors('billingo_fluentcart_messages');
    ?>
    <div class="wrap">
        <h1><?php echo \esc_html(\get_admin_page_title()); ?></h1>
        
        <form action="options.php" method="post">
            <?php
            \settings_fields('billingo_fluentcart_settings');
            \do_settings_sections('integration-for-billingo-fluentcart');
            \submit_button(\__('Save Settings', 'integration-for-billingo-fluentcart'));
            ?>
        </form>
        <h1><?php echo \esc_html__('Actions', 'integration-for-billingo-fluentcart'); ?></h1>
        
        <h2><?php echo \esc_html__('Shipping VAT Settings', 'integration-for-billingo-fluentcart'); ?></h2>
        
        <?php
        $current_rates = getShippingTaxRates();
        $selected_vat = \get_option('billingo_fluentcart_shipping_vat', 27);
        $is_button_disabled = empty($current_rates) || (count($current_rates) === 1 && $current_rates[0] == strval($selected_vat));
        ?>
        <form action="<?php echo \esc_url(\admin_url('options-general.php?page=integration-for-billingo-fluentcart')); ?>" method="post" style="margin-top: 20px;">
            <?php \wp_nonce_field('billingo_fluentcart_apply_shipping_vat_action', 'billingo_fluentcart_apply_shipping_vat_nonce'); ?>
            <input type="hidden" name="billingo_fluentcart_apply_shipping_vat" value="1" />
            <?php \submit_button(\__('Apply Shipping VAT to All Tax Rates', 'integration-for-billingo-fluentcart'), 'primary', 'submit', false, $is_button_disabled ? ['disabled' => true] : []); ?>
        </form>
        
        <h2><?php echo \esc_html__('Cache Management', 'integration-for-billingo-fluentcart'); ?></h2>
        <?php
        $cache_size = get_cache_size();
        $formatted_size = format_bytes($cache_size);
        ?>
        <p><?php echo \esc_html__('Current cache size:', 'integration-for-billingo-fluentcart'); ?> <strong><?php echo \esc_html($formatted_size); ?></strong></p>
        <p class="description"><?php echo \esc_html__('Clearing the cache will delete all cached PDFs, XMLs, and logs.', 'integration-for-billingo-fluentcart'); ?></p>
        
        <form action="<?php echo \esc_url(\admin_url('options-general.php?page=integration-for-billingo-fluentcart')); ?>" method="post" style="margin-top: 20px;">
            <?php \wp_nonce_field('billingo_fluentcart_clear_cache_action', 'billingo_fluentcart_clear_cache_nonce'); ?>
            <input type="hidden" name="billingo_fluentcart_clear_cache" value="1" />
            <?php \submit_button(\__('Clear Cache', 'integration-for-billingo-fluentcart'), 'secondary', 'submit', false); ?>
        </form>
    </div>
    <?php
}

function setShippingTaxRate($vatRate) {
    $taxRates = TaxRate::where('country', 'HU')->get();
    foreach ($taxRates as $rate) {
        $rate->for_shipping = $vatRate;
        $rate->save();
    }
}

function getShippingTaxRates() {
    $taxRates = TaxRate::where('country', 'HU')->get();
    $rates = [];
    
    foreach ($taxRates as $taxRate) {
        $rate = $taxRate->for_shipping !== null ? $taxRate->for_shipping : $taxRate->rate;
        $rates[] = $rate;
    }
    
    return array_values(array_unique($rates));
}
