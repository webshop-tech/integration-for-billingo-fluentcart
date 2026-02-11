<?php
/**
 * Plugin Name: Integration for Billingo and FluentCart
 * Plugin URI: https://webshop.tech/integration-for-billingo-fluentcart/
 * Description: Generates invoices on Billingo for FluentCart orders
 * Version: 1.2.0
 * Author: Gábor Angyal
 * Author URI: https://webshop.tech
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: integration-for-billingo-fluentcart
 * Requires Plugins: fluent-cart
 */

namespace BillingoFluentCart;

if ( ! defined( 'ABSPATH' ) ) exit;

require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'utils.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'database.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'api.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'invoice-generator.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'settings.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'vat.php';

use FluentCart\App\Models\Order;

function init_paths() {
    $suffix = get_option('billingo_fluentcart_folder_suffix', '');
    if (empty($suffix)) {
        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        update_option('billingo_fluentcart_folder_suffix', $suffix);
    }
    
    $cache_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache';
    $base_path = $cache_dir . DIRECTORY_SEPARATOR . 'integration-for-billingo-fluentcart-' . $suffix;
    
    $required_folders = [
        'logs',
        'pdf',
        'xmls'
    ];
    
    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }
    
    if (!file_exists($base_path)) {
        wp_mkdir_p($base_path);
    }
    
    foreach ($required_folders as $folder) {
        $folder_path = $base_path . DIRECTORY_SEPARATOR . $folder;
        if (!file_exists($folder_path)) {
            wp_mkdir_p($folder_path);
        }
    }
    
    return $base_path;
}

function get_cache_path() {
    $suffix = get_option('billingo_fluentcart_folder_suffix', '');
    if (empty($suffix)) {
        return null;
    }
    
    $cache_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache';
    return $cache_dir . DIRECTORY_SEPARATOR . 'integration-for-billingo-fluentcart-' . $suffix;
}

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

function clear_cache() {
    $cache_path = get_cache_path();
    
    if ($cache_path && file_exists($cache_path)) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;
        
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
        
        $wp_filesystem->rmdir($cache_path);
    }
    
    delete_option('billingo_fluentcart_folder_suffix');
}

function get_pdf_path($invoice_number) {
    $cache_path = get_cache_path();
    if (!$cache_path) {
        return null;
    }
    
    $pdf_dir = $cache_path . DIRECTORY_SEPARATOR . 'pdf';
    
    if (file_exists($pdf_dir)) {
        $files = glob($pdf_dir . DIRECTORY_SEPARATOR . '*' . $invoice_number . '*.pdf');
        if (!empty($files)) {
            return $files[0]; // Return the first matching file
        }
    }
    
    return null;
}

\register_activation_hook(__FILE__, __NAMESPACE__ . '\\create_invoices_table');

\add_action('fluent_cart/order_paid_done', function($data) {
    $order = $data['order'];
    $order_id = $order->id;
    write_log($order_id, 'fluent_cart/order_paid_done', 'Order ID', $order_id);
    create_invoice($order);
}, 10, 1);

\add_action('fluent_cart/order_status_changed', function($data) {
    $order = $data['order'];
    $order_id = $order->id;
    write_log($order_id, 'fluent_cart/order_status_changed', 'new status', $data['new_status']);
    if ($data['new_status'] =='completed') {
        create_invoice($order);
    }
}, 10, 1);

\add_action('fluent_cart/order_refunded', function($data) {
    $order = $data['order'];
    $order_id = $order->id;
    write_log($order_id, 'fluent_cart/order_refunded', print_r( $data, true ));
    cancel_invoice($order);
}, 10, 1);

\add_action('fluent_cart/payment_status_changed_to_paid', function($data) {
    $order = $data['order'];
    $order_id = $order->id;
    write_log($order_id, 'fluent_cart/payment_status_changed_to_paid', 'Order ID', $order_id);
    create_invoice($order);
}, 10, 1);

\add_action('fluent_cart/subscription_renewed', function($data) {
    $order = $data['order'];
    $main_order = $data['main_order'];
    create_invoice($order, $main_order);
}, 10, 1);

\add_action('fluent_cart/before_render_redirect_page', function($data) {
    if (!isset($data['is_receipt']) || !$data['is_receipt']) {
        return;
    }
    
    
    if (!isset($data['order_hash'])) {
        return;
    }

    $order_hash = $data['order_hash'];
    
    $order_id = Order::where('uuid', $order_hash)->value('id');

    if(!is_numeric($order_id)) {
        return;
    }
    
    try {
        init_paths();
        
        $api_key = \get_option('billingo_fluentcart_agent_api_key', '');
        
        if (empty($api_key)) {
            return;
        }
        
        $invoice_number = get_invoice_number_by_order_id($order_id);
        
        if ($invoice_number) {
            $cached_pdf_path = get_pdf_path($invoice_number);
            
            if ($cached_pdf_path && \file_exists($cached_pdf_path)) {
                serve_pdf_download($cached_pdf_path);
            }
            
            $result = fetch_invoice_pdf($order_id, $api_key, $invoice_number);
            
            if (!is_wp_error($result) && isset($result['success']) && $result['success']) {
                $cache_path = get_cache_path();
                if ($cache_path) {
                    $pdf_dir = $cache_path . DIRECTORY_SEPARATOR . 'pdf';
                    $pdf_filename = $pdf_dir . DIRECTORY_SEPARATOR . $result['filename'];
                    
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    WP_Filesystem();
                    global $wp_filesystem;
                    
                    $wp_filesystem->put_contents($pdf_filename, $result['pdf_data'], FS_CHMOD_FILE);
                }
                
                serve_pdf_download(null, $result['pdf_data'], $result['filename']);
            }
        }
        
    } catch (\Exception $e) {
        log_activity($order_id, false, 'Download error: ' . $e->getMessage());
        return;
    }
}, 1);
