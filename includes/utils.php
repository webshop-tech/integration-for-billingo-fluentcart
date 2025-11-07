<?php
/**
 * Utility functions for Számlázz.hu plugin
 * 
 * @package SzamlazzHuFluentCart
 */

namespace SzamlazzHuFluentCart;

// Exit if accessed directly
if (!\defined('ABSPATH')) {
    exit;
}

use FluentCart\App\Models\Activity;

/**
 * Logging function - only works when WP_DEBUG is enabled
 * 
 * @param int $order_id The order ID
 * @param string $message The message to log
 * @param mixed ...$args Variable-length argument list to be concatenated with commas
 */
function write_log($order_id, $message, ...$args) {
    if (!\defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    if (!empty($args)) {
        $formatted_message = $message . ', ' . \implode(', ', $args);
    } else {
        $formatted_message = $message;
    }
    
    Activity::create([
        'status' => 'info',
        'log_type' => 'activity',
        'module_type' => 'FluentCart\App\Models\Order',
        'module_id' => $order_id,
        'module_name' => 'order',
        'title' => 'Számlázz.hu debug info',
        'content' => $formatted_message
    ]);
}

/**
 * Create a WP_Error and log it
 * 
 * @param int $order_id The order ID
 * @param string $code Error code
 * @param string $message Error message
 * @param mixed ...$args Additional arguments to be concatenated with the message
 * @return \WP_Error The created WP_Error object
 */
function create_error($order_id, $code, $message, ...$args) {
    // Concatenate message with additional arguments if provided
    if (!empty($args)) {
        $formatted_message = $message . ': ' . \implode(', ', $args);
    } else {
        $formatted_message = $message;
    }
    
    // Log the error
    write_log($order_id, 'Error', $code, $formatted_message);
    
    // Create and return WP_Error
    return new \WP_Error($code, $formatted_message);
}

/**
 * Log an existing WP_Error object
 * 
 * @param int $order_id The order ID
 * @param \WP_Error $error The WP_Error object to log
 */
function write_error_to_log($order_id, $error) {
    $error_code = $error->get_error_code();
    $error_message = $error->get_error_message();
    
    // Log the error
    write_log($order_id, 'Error', $error_code, $error_message);
}

/**
 * Serve PDF file to browser
 * Supports both file path and PDF data in memory
 * 
 * @param string|null $file_path Path to PDF file on disk (optional)
 * @param string|null $pdf_data PDF data in memory (optional)
 * @param string $filename Filename for download
 * @return void Exits after serving the file
 */
function serve_pdf_download($file_path = null, $pdf_data = null, $filename = 'invoice.pdf') {
    // Determine source and get content
    if ($file_path && \file_exists($file_path)) {
        // Read from file on disk
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;
        
        $content = $wp_filesystem->get_contents($file_path);
        $content_length = \filesize($file_path);
        $filename = \basename($file_path);
    } elseif ($pdf_data !== null) {
        // Use data from memory
        $content = $pdf_data;
        $content_length = strlen($pdf_data);
    } else {
        // No valid source provided
        return;
    }
    
    // Set headers and serve PDF
    \header('Content-Type: application/pdf');
    \header('Content-Disposition: attachment; filename="' . $filename . '"');
    \header('Content-Length: ' . $content_length);

    // Raw file output for download
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
    echo $content;
    exit;
}
