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
    
    // Log to debug.log
    \error_log(sprintf('[Számlázz.hu FluentCart] Order #%d: %s', $order_id, $formatted_message));
    
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
