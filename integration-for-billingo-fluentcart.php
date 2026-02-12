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
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'invoice-hooks.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'invoice-download.php';

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
            return $files[0];
        }
    }
    
    return null;
}

\register_activation_hook(__FILE__, __NAMESPACE__ . '\\create_invoices_table');