<?php

namespace BillingoFluentCart;

if ( ! defined( 'ABSPATH' ) ) exit;

use FluentCart\App\Models\Activity;

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
        'title' => 'Billingo debug info',
        'content' => $formatted_message
    ]);
}

function create_error($order_id, $code, $message, ...$args) {
    if (!empty($args)) {
        $formatted_message = $message . ': ' . \implode(', ', $args);
    } else {
        $formatted_message = $message;
    }
    
    write_log($order_id, 'Error', $code, $formatted_message);
    
    return new \WP_Error($code, $formatted_message);
}

function log_activity($order_id, $success, $message) {
    Activity::create([
        'status' => $success ? 'success' : 'failed',
        'log_type' => 'activity',
        'module_type' => 'FluentCart\App\Models\Order',
        'module_id' => $order_id,
        'module_name' => 'order',
        'title' => $success ? 'Billingo invoice successfully generated' : 'Billingo invoice generation failed',
        'content' => $message
    ]);
}

function serve_pdf_download($file_path = null, $pdf_data = null, $filename = 'invoice.pdf') {
    if ($file_path && \file_exists($file_path)) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;
        
        $content = $wp_filesystem->get_contents($file_path);
        $content_length = \filesize($file_path);
        $filename = \basename($file_path);
    } elseif ($pdf_data !== null) {
        $content = $pdf_data;
        $content_length = strlen($pdf_data);
    } else {
        return;
    }
    
    \header('Content-Type: application/pdf');
    \header('Content-Disposition: attachment; filename="' . $filename . '"');
    \header('Content-Length: ' . $content_length);

    // Raw file output for download
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
    echo $content;
    exit;
}

function format_bytes($bytes, $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}