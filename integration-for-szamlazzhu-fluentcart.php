<?php
/**
 * Plugin Name: Integration for Számlázz.hu and FluentCart
 * Plugin URI: https://webshop.tech/integration-for-szamlazzhu-fluentcart/
 * Description: Generates invoices on Számlázz.hu for FluentCart orders
 * Version: 1.0.0
 * Author: Gábor Angyal
 * Author URI: https://webshop.tech
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: integration-for-szamlazzhu-fluentcart
 */

namespace SzamlazzHuFluentCart;

// Exit if accessed directly
if (!\defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
\add_action('plugins_loaded', function() {
    \load_plugin_textdomain('integration-for-szamlazzhu-fluentcart', false, dirname(\plugin_basename(__FILE__)) . '/languages/');
});

require __DIR__ . DIRECTORY_SEPARATOR .'autoload.php';

use \SzamlaAgent\SzamlaAgentAPI;
use \SzamlaAgent\SzamlaAgentUtil;
use \SzamlaAgent\Buyer;
use \SzamlaAgent\Seller;
use \SzamlaAgent\Language;
use \SzamlaAgent\Document\Invoice\Invoice;
use \SzamlaAgent\Item\InvoiceItem;

use FluentCart\App\Models\Activity;
use FluentCart\App\Models\Cart;
use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderItem;
use FluentCart\App\Models\TaxRate;

/**
 * Initialize Szamlazz.hu base path and ensure required folders exist
 */
function init_paths() {
    // Get or generate a random 8-character suffix
    $suffix = get_option('szamlazz_hu_folder_suffix', '');
    if (empty($suffix)) {
        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        update_option('szamlazz_hu_folder_suffix', $suffix);
    }
    
    // Use WordPress cache directory
    $cache_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache';
    $base_path = $cache_dir . DIRECTORY_SEPARATOR . 'integration-for-szamlazzhu-fluentcart-' . $suffix;
    
    // Set the base path for SzamlaAgent
    SzamlaAgentUtil::setBasePath($base_path);
    
    // Define required folders
    $required_folders = [
        'logs',
        'pdf',
        'xmls'
    ];
    
    // Create cache directory if it doesn't exist
    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }
    
    // Create base directory if it doesn't exist
    if (!file_exists($base_path)) {
        wp_mkdir_p($base_path);
    }
    
    // Create required subdirectories if they don't exist
    foreach ($required_folders as $folder) {
        $folder_path = $base_path . DIRECTORY_SEPARATOR . $folder;
        if (!file_exists($folder_path)) {
            wp_mkdir_p($folder_path);
        }
    }
}

/**
 * Get the cache directory path
 */
function get_cache_path() {
    $suffix = get_option('szamlazz_hu_folder_suffix', '');
    if (empty($suffix)) {
        return null;
    }
    
    $cache_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache';
    return $cache_dir . DIRECTORY_SEPARATOR . 'integration-for-szamlazzhu-fluentcart-' . $suffix;
}

/**
 * Get the cache directory size in bytes
 */
function get_cache_size() {
    $cache_path = get_cache_path();
    if (!$cache_path || !file_exists($cache_path)) {
        return 0;
    }
    
    $size = 0;
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($cache_path, \RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    
    return $size;
}

/**
 * Format bytes to human-readable size
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Clear the cache directory
 */
function clear_cache() {
    $cache_path = get_cache_path();
    
    if ($cache_path && file_exists($cache_path)) {
        // Recursively delete all files and folders
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cache_path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        
        // Remove the main directory
        rmdir($cache_path);
    }
    
    // Delete the suffix option to regenerate a new one
    delete_option('szamlazz_hu_folder_suffix');
}

/**
 * Get PDF file path for invoice number
 */
function get_pdf_path($invoice_number) {
    $cache_path = get_cache_path();
    if (!$cache_path) {
        return null;
    }
    
    $pdf_dir = $cache_path . DIRECTORY_SEPARATOR . 'pdf';
    
    // Search for PDF files matching the invoice number
    if (file_exists($pdf_dir)) {
        $files = glob($pdf_dir . DIRECTORY_SEPARATOR . '*' . $invoice_number . '*.pdf');
        if (!empty($files)) {
            return $files[0]; // Return the first matching file
        }
    }
    
    return null;
}

/**
 * Create database table on plugin activation
 */
\register_activation_hook(__FILE__, function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'szamlazz_invoices';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        invoice_number varchar(255) NOT NULL,
        invoice_id varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY order_id (order_id),
        KEY invoice_number (invoice_number)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});

/**
 * Register admin menu
 */
\add_action('admin_menu', function() {
    \add_options_page(
        \__('Számlázz.hu for FluentCart Settings', 'integration-for-szamlazzhu-fluentcart'),
        'Számlázz.hu',
        'manage_options',
        'integration-for-szamlazzhu-fluentcart',
        __NAMESPACE__ . '\\settings_page'
    );
});

/**
 * Register settings
 */
\add_action('admin_init', function() {
    \register_setting('szamlazz_hu_fluentcart_settings', 'szamlazz_hu_agent_api_key');
    \register_setting('szamlazz_hu_fluentcart_settings', 'szamlazz_hu_shipping_vat', [
        'type' => 'integer',
        'default' => 27,
        'sanitize_callback' => function($value) {
            $allowed = [0, 5, 18, 27];
            return in_array((int)$value, $allowed) ? (int)$value : 27;
        }
    ]);
    \register_setting('szamlazz_hu_fluentcart_settings', 'szamlazz_hu_invoice_language', [
        'type' => 'string',
        'default' => Language::LANGUAGE_HU,
        'sanitize_callback' => function($value) {
            try {
                $allowed = Language::getAll();
                return in_array($value, $allowed) ? $value : Language::LANGUAGE_HU;
            } catch (\Exception $e) {
                return Language::LANGUAGE_HU;
            }
        }
    ]);
    \register_setting('szamlazz_hu_fluentcart_settings', 'szamlazz_hu_invoice_type', [
        'type' => 'integer',
        'default' => Invoice::INVOICE_TYPE_P_INVOICE,
        'sanitize_callback' => function($value) {
            $allowed = [Invoice::INVOICE_TYPE_P_INVOICE, Invoice::INVOICE_TYPE_E_INVOICE];
            return in_array((int)$value, $allowed) ? (int)$value : Invoice::INVOICE_TYPE_P_INVOICE;
        }
    ]);
    \register_setting('szamlazz_hu_fluentcart_settings', 'szamlazz_hu_quantity_unit', [
        'type' => 'string',
        'default' => 'db',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
    \register_setting('szamlazz_hu_fluentcart_settings', 'szamlazz_hu_shipping_title', [
        'type' => 'string',
        'default' => 'Szállítás',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
    
    // Handle clear cache action
    if (isset($_POST['szamlazz_hu_clear_cache']) && \check_admin_referer('szamlazz_hu_clear_cache_action', 'szamlazz_hu_clear_cache_nonce')) {
        clear_cache();
        \add_settings_error('szamlazz_hu_messages', 'szamlazz_hu_cache_cleared', \__('Cache cleared successfully', 'integration-for-szamlazzhu-fluentcart'), 'updated');
    }
    
    // Handle apply shipping VAT action
    if (isset($_POST['szamlazz_hu_apply_shipping_vat']) && \check_admin_referer('szamlazz_hu_apply_shipping_vat_action', 'szamlazz_hu_apply_shipping_vat_nonce')) {
        $shipping_vat = \get_option('szamlazz_hu_shipping_vat', 27);
        setShippingTaxRate($shipping_vat);
        \add_settings_error('szamlazz_hu_messages', 'szamlazz_hu_vat_applied', \__('Shipping VAT rate applied to all tax rates successfully', 'integration-for-szamlazzhu-fluentcart'), 'updated');
    }
    
    \add_settings_section(
        'szamlazz_hu_api_section',
        \__('API Settings', 'integration-for-szamlazzhu-fluentcart'),
        function() {
            echo '<p>' . \esc_html__('Enter your Számlázz.hu API credentials below.', 'integration-for-szamlazzhu-fluentcart') . '</p>';
        },
        'integration-for-szamlazzhu-fluentcart'
    );
    
    \add_settings_field(
        'szamlazz_hu_agent_api_key',
        \__('Agent API Key', 'integration-for-szamlazzhu-fluentcart'),
        function() {
            $value = \get_option('szamlazz_hu_agent_api_key', '');
            echo '<input type="password" name="szamlazz_hu_agent_api_key" value="' . \esc_attr($value) . '" class="regular-text" autocomplete="off" />';
            echo '<p class="description"><a href="https://tudastar.szamlazz.hu/gyik/kulcs" target="_blank" rel="noopener noreferrer">' . \esc_html__('What is this?', 'integration-for-szamlazzhu-fluentcart') . '</a></p>';
        },
        'integration-for-szamlazzhu-fluentcart',
        'szamlazz_hu_api_section'
    );
    
    \add_settings_section(
        'szamlazz_hu_invoice_section',
        \__('Invoice Settings', 'integration-for-szamlazzhu-fluentcart'),
        null,
        'integration-for-szamlazzhu-fluentcart'
    );
    
    \add_settings_field(
        'szamlazz_hu_invoice_language',
        \__('Invoice Language', 'integration-for-szamlazzhu-fluentcart'),
        function() {
            $value = \get_option('szamlazz_hu_invoice_language', Language::LANGUAGE_HU);
            $languages = [
                Language::LANGUAGE_HU => \__('Magyar (Hungarian)', 'integration-for-szamlazzhu-fluentcart'),
                Language::LANGUAGE_EN => \__('English', 'integration-for-szamlazzhu-fluentcart'),
                Language::LANGUAGE_DE => \__('Deutsch (German)', 'integration-for-szamlazzhu-fluentcart'),
                Language::LANGUAGE_IT => \__('Italiano (Italian)', 'integration-for-szamlazzhu-fluentcart'),
                Language::LANGUAGE_RO => \__('Română (Romanian)', 'integration-for-szamlazzhu-fluentcart'),
                Language::LANGUAGE_SK => \__('Slovenčina (Slovak)', 'integration-for-szamlazzhu-fluentcart'),
                Language::LANGUAGE_HR => \__('Hrvatski (Croatian)', 'integration-for-szamlazzhu-fluentcart'),
                Language::LANGUAGE_FR => \__('Français (French)', 'integration-for-szamlazzhu-fluentcart'),
                Language::LANGUAGE_ES => \__('Español (Spanish)', 'integration-for-szamlazzhu-fluentcart'),
                Language::LANGUAGE_CZ => \__('Čeština (Czech)', 'integration-for-szamlazzhu-fluentcart'),
                Language::LANGUAGE_PL => \__('Polski (Polish)', 'integration-for-szamlazzhu-fluentcart')
            ];
            echo '<select name="szamlazz_hu_invoice_language">';
            foreach ($languages as $code => $name) {
                $selected = ($code == $value) ? 'selected' : '';
                echo '<option value="' . \esc_attr($code) . '" ' . $selected . '>' . \esc_html($name) . '</option>';
            }
            echo '</select>';
        },
        'integration-for-szamlazzhu-fluentcart',
        'szamlazz_hu_invoice_section'
    );
    
    \add_settings_field(
        'szamlazz_hu_invoice_type',
        \__('Invoice Type', 'integration-for-szamlazzhu-fluentcart'),
        function() {
            $value = \get_option('szamlazz_hu_invoice_type', Invoice::INVOICE_TYPE_P_INVOICE);
            $types = [
                Invoice::INVOICE_TYPE_P_INVOICE => \__('Paper Invoice', 'integration-for-szamlazzhu-fluentcart'),
                Invoice::INVOICE_TYPE_E_INVOICE => \__('E-Invoice', 'integration-for-szamlazzhu-fluentcart')
            ];
            echo '<select name="szamlazz_hu_invoice_type">';
            foreach ($types as $type_value => $type_name) {
                $selected = ($type_value == $value) ? 'selected' : '';
                echo '<option value="' . \esc_attr($type_value) . '" ' . $selected . '>' . \esc_html($type_name) . '</option>';
            }
            echo '</select>';
        },
        'integration-for-szamlazzhu-fluentcart',
        'szamlazz_hu_invoice_section'
    );
    
    \add_settings_field(
        'szamlazz_hu_quantity_unit',
        \__('Quantity Unit', 'integration-for-szamlazzhu-fluentcart'),
        function() {
            $value = \get_option('szamlazz_hu_quantity_unit', 'db');
            echo '<input type="text" name="szamlazz_hu_quantity_unit" value="' . \esc_attr($value) . '" class="regular-text" />';
        },
        'integration-for-szamlazzhu-fluentcart',
        'szamlazz_hu_invoice_section'
    );
    
    \add_settings_field(
        'szamlazz_hu_shipping_title',
        \__('Shipping Title', 'integration-for-szamlazzhu-fluentcart'),
        function() {
            $value = \get_option('szamlazz_hu_shipping_title', 'Szállítás');
            echo '<input type="text" name="szamlazz_hu_shipping_title" value="' . \esc_attr($value) . '" class="regular-text" />';
        },
        'integration-for-szamlazzhu-fluentcart',
        'szamlazz_hu_invoice_section'
    );
    
    \add_settings_field(
        'szamlazz_hu_shipping_vat',
        \__('Shipping VAT Rate', 'integration-for-szamlazzhu-fluentcart'),
        function() {
            $value = \get_option('szamlazz_hu_shipping_vat', 27);
            $options = [0, 5, 18, 27];
            echo '<select name="szamlazz_hu_shipping_vat">';
            foreach ($options as $option) {
                $selected = ($option == $value) ? 'selected' : '';
                echo '<option value="' . \esc_attr($option) . '" ' . $selected . '>' . \esc_html($option) . '%</option>';
            }
            echo '</select>';
        },
        'integration-for-szamlazzhu-fluentcart',
        'szamlazz_hu_invoice_section'
    );
    
    \add_settings_field(
        'szamlazz_hu_apply_shipping_vat_field',
        \__('Apply to Tax Rates', 'integration-for-szamlazzhu-fluentcart'),
        function() {
            $current_rates = getShippingTaxRates();
            $selected_vat = \get_option('szamlazz_hu_shipping_vat', 27);
            
            if (empty($current_rates)) {
                echo '<p class="description" style="color: #dc3232;"><strong>' . \esc_html__('Warning:', 'integration-for-szamlazzhu-fluentcart') . '</strong> ' . \esc_html__('No tax rates found. Please configure tax rates in FluentCart first.', 'integration-for-szamlazzhu-fluentcart') . '</p>';
            } elseif (count($current_rates) === 1 && $current_rates[0] == $selected_vat) {
                echo '<p class="description" style="color: #46b450;">' . \esc_html__('All tax rates are already set to', 'integration-for-szamlazzhu-fluentcart') . ' ' . \esc_html($selected_vat) . '%</p>';
            } else {
                echo '<p class="description">' . \esc_html__('Current shipping VAT rates in use:', 'integration-for-szamlazzhu-fluentcart') . ' ' . \esc_html(\implode(', ', $current_rates)) . '%</p>';
            }
        },
        'integration-for-szamlazzhu-fluentcart',
        'szamlazz_hu_invoice_section'
    );
    
});

/**
 * Add settings link to plugins page
 */
\add_filter('plugin_action_links_' . \plugin_basename(__FILE__), function($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        \admin_url('options-general.php?page=integration-for-szamlazzhu-fluentcart'),
        \__('Settings', 'integration-for-szamlazzhu-fluentcart')
    );
    \array_unshift($links, $settings_link);
    return $links;
});

/**
 * Settings page callback
 */
function settings_page() {
    if (!\current_user_can('manage_options')) {
        return;
    }
    
    if (isset($_GET['settings-updated'])) {
        \add_settings_error('szamlazz_hu_messages', 'szamlazz_hu_message', \__('Settings Saved', 'integration-for-szamlazzhu-fluentcart'), 'updated');
    }
    
    \settings_errors('szamlazz_hu_messages');
    ?>
    <div class="wrap">
        <h1><?php echo \esc_html(\get_admin_page_title()); ?></h1>
        
        <!-- API Settings Form -->
        <form action="options.php" method="post">
            <?php
            \settings_fields('szamlazz_hu_fluentcart_settings');
            \do_settings_sections('integration-for-szamlazzhu-fluentcart');
            \submit_button(\__('Save Settings', 'integration-for-szamlazzhu-fluentcart'));
            ?>
        </form>
        <h1><?php echo \esc_html__('Actions', 'integration-for-szamlazzhu-fluentcart'); ?></h1>
        
        <h2><?php echo \esc_html__('Shipping VAT Settings', 'integration-for-szamlazzhu-fluentcart'); ?></h2>
        
        <!-- Apply Shipping VAT Form -->
        <?php
        $current_rates = getShippingTaxRates();
        $selected_vat = \get_option('szamlazz_hu_shipping_vat', 27);
        $is_button_disabled = empty($current_rates) || (count($current_rates) === 1 && $current_rates[0] == strval($selected_vat));
        ?>
        <form action="<?php echo \esc_url(\admin_url('options-general.php?page=integration-for-szamlazzhu-fluentcart')); ?>" method="post" style="margin-top: 20px;">
            <?php \wp_nonce_field('szamlazz_hu_apply_shipping_vat_action', 'szamlazz_hu_apply_shipping_vat_nonce'); ?>
            <input type="hidden" name="szamlazz_hu_apply_shipping_vat" value="1" />
            <?php \submit_button(\__('Apply Shipping VAT to All Tax Rates', 'integration-for-szamlazzhu-fluentcart'), 'primary', 'submit', false, $is_button_disabled ? ['disabled' => true] : []); ?>
        </form>
        
        <!-- Cache Management Section -->
        <h2><?php echo \esc_html__('Cache Management', 'integration-for-szamlazzhu-fluentcart'); ?></h2>
        <?php
        $cache_size = get_cache_size();
        $formatted_size = format_bytes($cache_size);
        ?>
        <p><?php echo \esc_html__('Current cache size:', 'integration-for-szamlazzhu-fluentcart'); ?> <strong><?php echo \esc_html($formatted_size); ?></strong></p>
        <p class="description"><?php echo \esc_html__('Clearing the cache will delete all cached PDFs, XMLs, and logs.', 'integration-for-szamlazzhu-fluentcart'); ?></p>
        
        <!-- Clear Cache Form -->
        <form action="<?php echo \esc_url(\admin_url('options-general.php?page=integration-for-szamlazzhu-fluentcart')); ?>" method="post" style="margin-top: 20px;">
            <?php \wp_nonce_field('szamlazz_hu_clear_cache_action', 'szamlazz_hu_clear_cache_nonce'); ?>
            <input type="hidden" name="szamlazz_hu_clear_cache" value="1" />
            <?php \submit_button(\__('Clear Cache', 'integration-for-szamlazzhu-fluentcart'), 'secondary', 'submit', false); ?>
        </form>
    </div>
    <?php
}


\add_action('fluent_cart/order_paid_done', function($data) {
    $order = $data['order'];
    create_invoice($order);
}, 10, 1);

\add_action('fluent_cart/payment_status_changed_to_paid', function($data) {
    $order = $data['order'];
    create_invoice($order);
}, 10, 1);

/**
 * Check if an invoice already exists for the given order
 */
function check_existing_invoice($order_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'szamlazz_invoices';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE order_id = %d",
        $order_id
    ));
}

/**
 * Get and validate API key from settings
 */
function get_api_key() {
    $api_key = \get_option('szamlazz_hu_agent_api_key', '');
    
    if (empty($api_key)) {
        throw new \Exception('Agent API Key is not configured. Please configure it in Settings > Számlázz.hu');
    }
    
    return $api_key;
}

/**
 * Get VAT number from checkout data
 */
function get_vat_number($order_id) {
    $checkout_data = Cart::where('order_id', $order_id)->first()['checkout_data'];
    return $checkout_data['tax_data']['vat_number'] ?? null;
}

/**
 * Fetch and parse taxpayer data from NAV
 */
function get_taxpayer_data($order_id, $agent, $vat_number) {
    try {
        debug_log($order_id, 'Fetching taxpayer data from NAV', 'VAT number', $vat_number);
        
        $taxpayer_response = $agent->getTaxPayer($vat_number);
        $taxpayer_xml = $taxpayer_response->getTaxPayerData();
        
        if (!$taxpayer_xml) {
            debug_log($order_id, 'No taxpayer data returned from NAV');
            return null;
        }
        
        debug_log($order_id, 'Taxpayer XML received, parsing data');
        
        $xml = new \SimpleXMLElement($taxpayer_xml);
        
        // Register namespaces
        $xml->registerXPathNamespace('ns2', 'http://schemas.nav.gov.hu/OSA/3.0/api');
        $xml->registerXPathNamespace('ns3', 'http://schemas.nav.gov.hu/OSA/3.0/base');

        $taxpayerValidity = $xml->xpath('//ns2:taxpayerValidity');
        if ("true" === (string)$taxpayerValidity[0]) {
            debug_log($order_id, 'Taxpayer is valid');
        } else {
            debug_log($order_id, 'Taxpayer is not valid');
            return;
        }

        $data = [];
        
        // Extract taxpayer name
        $taxpayer_short_name = $xml->xpath('//ns2:taxpayerShortName');
        $taxpayer_name = $xml->xpath('//ns2:taxpayerName');
        
        if (!empty($taxpayer_short_name)) {
            $data['name'] = (string)$taxpayer_short_name[0];
            debug_log($order_id, 'Taxpayer name extracted', 'Name', $data['name']);
        } elseif (!empty($taxpayer_name)) {
            $data['name'] = (string)$taxpayer_name[0];
            debug_log($order_id, 'Taxpayer name extracted', 'Name', $data['name']);
        }
        
        // Extract VAT ID components
        $taxpayer_id = $xml->xpath('//ns3:taxpayerId');
        $vat_code = $xml->xpath('//ns3:vatCode');
        $county_code = $xml->xpath('//ns3:countyCode');
        
        if (!empty($taxpayer_id) && !empty($vat_code) && !empty($county_code)) {
            $data['vat_id'] = sprintf(
                '%s-%s-%s',
                (string)$taxpayer_id[0],
                (string)$vat_code[0],
                (string)$county_code[0]
            );
            debug_log($order_id, 'VAT ID formatted', $data['vat_id']);
        }
        
        // Extract address
        $postal_code = $xml->xpath('//ns3:postalCode');
        $city = $xml->xpath('//ns3:city');
        $street_name = $xml->xpath('//ns3:streetName');
        $public_place = $xml->xpath('//ns3:publicPlaceCategory');
        $number = $xml->xpath('//ns3:number');
        $door = $xml->xpath('//ns3:door');
        
        if (!empty($postal_code)) {
            $data['postcode'] = (string)$postal_code[0];
        }
        
        if (!empty($city)) {
            $data['city'] = (string)$city[0];
        }
        
        if (!empty($street_name)) {
            $address_parts = [(string)$street_name[0]];
            
            if (!empty($public_place)) {
                $address_parts[] = (string)$public_place[0];
            }
            
            if (!empty($number)) {
                $address_parts[] = (string)$number[0];
            }
            
            if (!empty($door)) {
                $address_parts[] = (string)$door[0];
            }
            
            $data['address'] = implode(' ', $address_parts);
        }
        
        if (isset($data['postcode']) && isset($data['city']) && isset($data['address'])) {
            debug_log($order_id, 'Taxpayer address extracted', $data['postcode'], $data['city'], $data['address']);
        }
        
        debug_log($order_id, 'Taxpayer data successfully parsed from NAV');
        
        return $data;
        
    } catch (\Exception $e) {
        debug_log($order_id, 'Failed to fetch taxpayer data', 'Error', $e->getMessage());
        \error_log("Failed to fetch taxpayer data for VAT number $vat_number: " . $e->getMessage());
        return null;
    }
}

/**
 * Create buyer object from order data
 */
function create_buyer($order, $agent, $vat_number = null) {
    $order_id = $order->id;
    
    // Get billing address
    $billing = $order->billing_address;
    if (!$billing) {
        throw new \Exception("No billing address found for order $order_id");
    }
    
    // Initialize with billing address defaults
    $buyer_name = $billing->name;
    $buyer_postcode = $billing->postcode;
    $buyer_city = $billing->city;
    $buyer_address = $billing->address_1 . ($billing->address_2 ? ' ' . $billing->address_2 : '');
    $buyer_vat_id = $billing->country . $vat_number;
    
    // If VAT number is provided, try to get taxpayer data from NAV
    if (!empty($vat_number)) {
        $taxpayer_data = get_taxpayer_data($order_id, $agent, $vat_number);
        
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
        }
    }
    
    // Create buyer
    $buyer = new Buyer(
        $buyer_name,
        $buyer_postcode,
        $buyer_city,
        $buyer_address
    );
    
    // Set VAT ID if available
    if (!empty($buyer_vat_id)) {
        $buyer->setTaxNumber($buyer_vat_id);
    }
    
    // Set buyer email if available
    $meta = $billing->meta;
    if (isset($meta['other_data']['email'])) {
        $buyer->setEmail($meta['other_data']['email']);
    }
    
    return $buyer;
}

/**
 * Create seller object with email settings
 */
function create_seller($order_id) {
    $seller = new Seller();
    
    // Configure email settings
    $seller->setEmailReplyTo(\get_option('admin_email'));
    $seller->setEmailSubject('Invoice for order #' . $order_id);
    $seller->setEmailContent('Thank you for your order. Please find your invoice attached.');
    
    return $seller;
}

/**
 * Add order items to invoice
 */
function add_order_items($invoice, $order) {
    $order_id = $order->id;
    $items = OrderItem::where('order_id', $order_id)->get();
    
    if ($items->isEmpty()) {
        throw new \Exception("No items found for order $order_id");
    }
    
    // Get quantity unit from settings
    $quantity_unit = \get_option('szamlazz_hu_quantity_unit', 'db');
    
    debug_log($order_id, 'Adding order items', 'Item count', $items->count());
    
    foreach ($items as $order_item) {
        $taxRate = "0";
        if ($order->tax_behavior != 0) {
            if (isset($order_item->line_meta['tax_config']['rates'][0]['rate'])) {
                $taxRate = $order_item->line_meta['tax_config']['rates'][0]['rate'];
            }
        }
        
        $item = new InvoiceItem(
            $order_item->title,
            $order_item->unit_price / 100,
            $order_item->quantity,
            $quantity_unit,
            strval($taxRate)
        );
        
        $item->setNetPrice($order_item->line_total / 100);
        $tax_amount = 0;
        if ($order->tax_behavior != 0) {
            $tax_amount = $order_item->tax_amount / 100;
        }
        $item->setVatAmount($tax_amount);
        $item->setGrossAmount($order_item->line_total / 100 + $tax_amount);
        
        debug_log(
            $order_id, 
            'Item', 
            $order_item->title, 
            'Qty', 
            $order_item->quantity, 
            'Unit price', 
            $order_item->unit_price / 100,
            'Tax rate', 
            $taxRate . '%',
            'Net', 
            $order_item->line_total / 100,
            'VAT', 
            $order_item->tax_amount / 100,
            'Gross', 
            ($order_item->line_total + $order_item->tax_amount) / 100
        );
        
        $invoice->addItem($item);
    }
    if ($order->shipping_total != 0) {
        // Get shipping title from settings
        $shipping_title = \get_option('szamlazz_hu_shipping_title', 'Szállítás');
        
        $item = new InvoiceItem($shipping_title, $order->shipping_total / 100);
        $item->setNetPrice($order->shipping_total / 100);
        if ($order->tax_behavior != 0) {
            // Get shipping VAT rate from settings
            $shipping_vat = \get_option('szamlazz_hu_shipping_vat', 27);
            $vat_multiplier = 1 + ($shipping_vat / 100);
            
            $item->setVatAmount($order->shipping_total * ($shipping_vat / 100) / 100);
            $item->setVat(strval($shipping_vat));
            $item->setGrossAmount($order->shipping_total * $vat_multiplier / 100);
        } else {
            $item->setVatAmount(0);
            $item->setVat("0");
            $item->setGrossAmount($order->shipping_total / 100);
        }
        $invoice->addItem($item);
    }
}

/**
 * Save invoice data to database
 */
function save_invoice($order_id, $result) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'szamlazz_invoices';
    
    $wpdb->insert(
        $table_name,
        [
            'order_id' => $order_id,
            'invoice_number' => $result->getDocumentNumber(),
            'invoice_id' => $result->getDataObj()->invoiceId ?? null
        ],
        ['%d', '%s', '%s']
    );
}

/**
 * Log invoice activity
 */
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

/**
 * Debug logging function - only works when WP_DEBUG is enabled
 * 
 * @param int $order_id The order ID
 * @param string $message The message to log
 * @param mixed ...$args Variable-length argument list to be concatenated with commas
 */
function debug_log($order_id, $message, ...$args) {
    // Only log if WP_DEBUG is enabled
    if (!\defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    // Concatenate message with additional arguments using commas
    if (!empty($args)) {
        $formatted_message = $message . ', ' . \implode(', ', $args);
    } else {
        $formatted_message = $message;
    }
    
    // Create order Activity with info status
    Activity::create([
        'status' => 'info',
        'log_type' => 'activity',
        'module_type' => 'FluentCart\App\Models\Order',
        'module_id' => $order_id,
        'module_name' => 'order',
        'title' => 'Számlázz.hu debug info',
        'content' => $formatted_message
    ]);
    
    // Write to debug.log file
    \error_log('[Számlázz.hu for FluentCart - Order #' . $order_id . '] ' . $formatted_message);
}

function generate_invoice($order) {
    $order_id = $order->id;
    
    debug_log($order_id, 'Starting invoice generation', 'Order ID', $order_id, 'Currency', $order->currency);
    
    // Get and validate API key
    $api_key = get_api_key();
    // Create Számla Agent
    $agent = SzamlaAgentAPI::create($api_key);
    $agent->setPdfFileSave(false);
    
    // Get VAT number from checkout data
    $vat_number = get_vat_number($order_id);
    if ($vat_number) {
        debug_log($order_id, 'VAT number found', $vat_number);
    } else {
        debug_log($order_id, 'No VAT number provided');
    }
    
    // Create buyer with taxpayer data if available
    $buyer = create_buyer($order, $agent, $vat_number);
    debug_log($order_id, 'Buyer created', 'Name', $buyer->getName(), 'City', $buyer->getCity());
    
    // Create seller with email settings
    $seller = create_seller($order_id);
    
    // Get invoice type from settings
    $invoice_type = \get_option('szamlazz_hu_invoice_type', Invoice::INVOICE_TYPE_P_INVOICE);
    
    // Create invoice
    $invoice = new Invoice($invoice_type);
    $invoice->setBuyer($buyer);
    $invoice->setSeller($seller);
    $invoice->getHeader()->setCurrency($order->currency);
    
    // Get invoice language from settings
    $invoice_language = \get_option('szamlazz_hu_invoice_language', Language::LANGUAGE_HU);
    $invoice->getHeader()->setLanguage($invoice_language);
    
    $invoice_type_name = ($invoice_type == Invoice::INVOICE_TYPE_E_INVOICE) ? 'E-Invoice' : 'Paper Invoice';
    debug_log($order_id, 'Invoice type set to', $invoice_type_name);
    debug_log($order_id, 'Invoice language set to', $invoice_language);
    
    // Add order items to invoice
    add_order_items($invoice, $order);
    
    debug_log($order_id, 'Generating invoice via API');
    
    // Generate invoice
    return $agent->generateInvoice($invoice);
}

/**
 * Main invoice creation function
 */
function create_invoice($order, $main_order = null) {
    $order_id = $order->id;
    if ($main_order === null)
        $main_order = $order;
    $main_order_id = $main_order->id;
    
    try {
        debug_log($order_id, 'Invoice creation triggered', 'Order ID', $order_id, 'Main order ID', $main_order_id);
        
        // Initialize paths and ensure folders exist
        init_paths();
        
        // Check if invoice already exists
        $existing = check_existing_invoice($order_id);
        if ($existing) {
            $message = sprintf('Invoice already exists: %s', $existing->invoice_number);
            debug_log($order_id, 'Invoice already exists', $existing->invoice_number);
            log_activity($order_id, true, $message);
            return;
        }
        
        $result = generate_invoice($main_order);
        // Check if invoice was created successfully
        if ($result->isSuccess()) {
            $invoice_number = $result->getDocumentNumber();
            
            debug_log($order_id, 'Invoice generated successfully', 'Invoice number', $invoice_number);
            
            // Save to database
            save_invoice($order_id, $result);
            
            // Log success
            $message = sprintf('Számlázz.hu invoice created: %s', $invoice_number);
            log_activity($order_id, true, $message);
        } else {
            throw new \Exception('Failed to generate invoice: ' . $result->getMessage());
        }
        
    } catch (\Exception $e) {
        debug_log($order_id, 'Invoice generation failed', 'Error', $e->getMessage());
        \file_put_contents('/var/www/error.txt', \var_export($e, true));
        log_activity($order_id, false, $e->getMessage());
    }
}

\add_action('fluent_cart/subscription_renewed', function($data) {
    $order = $data['order'];
    $main_order = $data['main_order'];
    create_invoice($order, $main_order);
}, 10, 1);

\add_action('init', function() {
    if (isset($_GET['fluent-cart']) && $_GET['fluent-cart'] === 'receipt') {
        // Your custom logic here
        $order_hash = isset($_GET['order_hash']) ? \sanitize_text_field($_GET['order_hash']) : '';
        $download = isset($_GET['download']) ? \sanitize_text_field($_GET['download']) : '';
        if ($download !== '1')
            return;

        $order_id = Order::where('uuid', $order_hash)->value('id');
        global $wpdb;
    
        try {
            // Initialize paths and ensure folders exist
            init_paths();
            
            // Get API key from settings
            $api_key = \get_option('szamlazz_hu_agent_api_key', '');
            
            if (empty($api_key)) {
                return;
            }
            
            // Check if invoice exists in database
            $table_name = $wpdb->prefix . 'szamlazz_invoices';
            $invoice_record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE order_id = %d",
                $order_id
            ));
            
            if ($invoice_record) {
                // Check if PDF exists in cache
                $cached_pdf_path = get_pdf_path($invoice_record->invoice_number);
                
                if ($cached_pdf_path && \file_exists($cached_pdf_path)) {
                    // Serve from cache
                    \header('Content-Type: application/pdf');
                    \header('Content-Disposition: attachment; filename="' . \basename($cached_pdf_path) . '"');
                    \header('Content-Length: ' . \filesize($cached_pdf_path));
                    \readfile($cached_pdf_path);
                    exit;
                }
                
                // PDF not in cache, fetch from API
                $agent = SzamlaAgentAPI::create($api_key);
                $agent->setPdfFileSave(true); // Enable saving to cache
                
                // Get invoice PDF
                $result = $agent->getInvoicePdf($invoice_record->invoice_number);
                
                // Check if PDF was retrieved successfully
                if ($result->isSuccess()) {
                    $result->downloadPdf();
                    exit;
                }
            }
            
        } catch (\Exception $e) {
            log_activity($order_id, false, 'Download error: ' . $e->getMessage());
            return;
        }
    }
}, 1);

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
        // Use for_shipping if not null, otherwise use rate
        $rate = $taxRate->for_shipping !== null ? $taxRate->for_shipping : $taxRate->rate;
        $rates[] = $rate;
    }
    
    // Return only distinct values
    return array_values(array_unique($rates));
}
