<?php

namespace BillingoFluentCart;

if ( ! defined( 'ABSPATH' ) ) exit;

use FluentCart\App\Models\Order;

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