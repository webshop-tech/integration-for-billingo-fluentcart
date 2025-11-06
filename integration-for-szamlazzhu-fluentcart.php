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

require __DIR__ . DIRECTORY_SEPARATOR .'autoload.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'utils.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'database.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'invoice-generator.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'settings.php';

use FluentCart\App\Models\Order;

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
    
    return $base_path;
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
        // Initialize WP_Filesystem
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;
        
        // Recursively delete all files and folders
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cache_path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $wp_filesystem->rmdir($file->getPathname());
            } else {
                $wp_filesystem->delete($file->getPathname());
            }
        }
        
        // Remove the main directory
        $wp_filesystem->rmdir($cache_path);
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
 * Fetch invoice PDF from Számlázz.hu API using WordPress HTTP API
 * 
 * @param string $api_key Számlázz.hu API key
 * @param string $invoice_number Invoice number
 * @return array|WP_Error Array with 'success' boolean and 'pdf_data' on success, or WP_Error on failure
 */
function fetch_invoice_pdf($api_key, $invoice_number) {
    // Build XML request for invoice PDF
    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmlszamlapdf></xmlszamlapdf>');
    
    // Add authentication
    $beallitasok = $xml->addChild('beallitasok');
    $beallitasok->addChild('szamlaagentkulcs', $api_key);
    $beallitasok->addChild('szamlaLetoltes', 'true');
    
    // Add invoice number
    $fejlec = $xml->addChild('fejlec');
    $fejlec->addChild('szamlaszam', $invoice_number);
    
    $xml_string = $xml->asXML();
    
    // Send request to Számlázz.hu API using WordPress HTTP API
    $response = \wp_remote_post('https://www.szamlazz.hu/szamla/', array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'text/xml; charset=UTF-8',
        ),
        'body' => $xml_string,
    ));
    
    // Check for HTTP errors
    if (\is_wp_error($response)) {
        return $response;
    }
    
    $response_code = \wp_remote_retrieve_response_code($response);
    $response_body = \wp_remote_retrieve_body($response);
    $response_headers = \wp_remote_retrieve_headers($response);
    
    // Check response code
    if ($response_code !== 200) {
        return new \WP_Error('api_error', 'API returned error code: ' . $response_code);
    }
    
    // Check if response is PDF or error message
    if (isset($response_headers['content-type']) && strpos($response_headers['content-type'], 'application/pdf') !== false) {
        // Success - got PDF
        return array(
            'success' => true,
            'pdf_data' => $response_body,
            'filename' => 'invoice_' . $invoice_number . '.pdf'
        );
    } else {
        // Error response (usually XML)
        return new \WP_Error('api_error', 'Failed to retrieve PDF: ' . substr($response_body, 0, 200));
    }
}

/**
 * Create database table on plugin activation
 */
\register_activation_hook(__FILE__, __NAMESPACE__ . '\\create_invoices_table');


\add_action('fluent_cart/order_paid_done', function($data) {
    $order = $data['order'];
    create_invoice($order);
}, 10, 1);

\add_action('fluent_cart/payment_status_changed_to_paid', function($data) {
    $order = $data['order'];
    create_invoice($order);
}, 10, 1);

\add_action('fluent_cart/subscription_renewed', function($data) {
    $order = $data['order'];
    $main_order = $data['main_order'];
    create_invoice($order, $main_order);
}, 10, 1);

\add_action('init', function() {
    if (isset($_GET['fluent-cart']) && $_GET['fluent-cart'] === 'receipt') {
        // Your custom logic here
        $order_hash = isset($_GET['order_hash']) ? \sanitize_text_field(\wp_unslash($_GET['order_hash'])) : '';
        $download = isset($_GET['download']) ? \sanitize_text_field(\wp_unslash($_GET['download'])) : '';
        if ($download !== '1')
            return;

        $order_id = Order::where('uuid', $order_hash)->value('id');
    
        try {
            // Initialize paths and ensure folders exist
            init_paths();
            
            // Get API key from settings
            $api_key = \get_option('szamlazz_hu_agent_api_key', '');
            
            if (empty($api_key)) {
                return;
            }
            
            // Check if invoice exists in database
            $invoice_record = get_invoice_by_order_id($order_id);
            
            if ($invoice_record) {
                // Check if PDF exists in cache
                $cached_pdf_path = get_pdf_path($invoice_record->invoice_number);
                
                if ($cached_pdf_path && \file_exists($cached_pdf_path)) {
                    // Initialize WP_Filesystem
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    WP_Filesystem();
                    global $wp_filesystem;
                    
                    // Serve from cache
                    \header('Content-Type: application/pdf');
                    \header('Content-Disposition: attachment; filename="' . \basename($cached_pdf_path) . '"');
                    \header('Content-Length: ' . \filesize($cached_pdf_path));
                    echo $wp_filesystem->get_contents($cached_pdf_path);
                    exit;
                }
                
                // PDF not in cache, fetch from API using WordPress HTTP API
                $result = fetch_invoice_pdf($api_key, $invoice_record->invoice_number);
                
                // Check if fetch was successful
                if (!is_wp_error($result) && isset($result['success']) && $result['success']) {
                    // Save PDF to cache
                    $cache_path = get_cache_path();
                    if ($cache_path) {
                        $pdf_dir = $cache_path . DIRECTORY_SEPARATOR . 'pdf';
                        $pdf_filename = $pdf_dir . DIRECTORY_SEPARATOR . $result['filename'];
                        
                        // Initialize WP_Filesystem
                        require_once(ABSPATH . 'wp-admin/includes/file.php');
                        WP_Filesystem();
                        global $wp_filesystem;
                        
                        // Save PDF file
                        $wp_filesystem->put_contents($pdf_filename, $result['pdf_data'], FS_CHMOD_FILE);
                    }
                    
                    // Serve PDF to user
                    \header('Content-Type: application/pdf');
                    \header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
                    \header('Content-Length: ' . strlen($result['pdf_data']));
                    echo $result['pdf_data'];
                    exit;
                }
            }
            
        } catch (\Exception $e) {
            log_activity($order_id, false, 'Download error: ' . $e->getMessage());
            return;
        }
    }
}, 1);
