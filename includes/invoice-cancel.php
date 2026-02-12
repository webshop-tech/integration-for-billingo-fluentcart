<?php

namespace BillingoFluentCart;

if ( ! defined( 'ABSPATH' ) ) exit;
function cancel_invoice($order, $reason) {
    $order_id = $order->id;

    write_log($order_id, 'Invoice cancellation triggered', 'Order ID', $order_id);

    init_paths();

    $api_key = \get_option('billingo_fluentcart_agent_api_key', '');

    if (empty($api_key)) {
        $error_message = 'API Key not configured';
        write_log($order_id, 'Invoice cancellation failed', 'Error', $error_message);
        log_activity($order_id, false, $error_message);
        return;
    }

    $document_id = get_document_id_by_order_id($order_id);

    if (empty($document_id) || $document_id == -1) {
        $error_message = 'No invoice found for this order';
        write_log($order_id, 'Invoice cancellation failed', 'Error', $error_message);
        log_activity($order_id, false, $error_message);
        return;
    }

    write_log($order_id, 'Cancelling document via Billingo v3 API', 'Document ID', $document_id);

    $result = cancel_document_api($order_id, $api_key, $document_id, $reason);

    if (\is_wp_error($result)) {
        $error_message = 'Failed to cancel invoice: ' . $result->get_error_message();
        write_log($order_id, 'Invoice cancellation failed', 'Error', $error_message);
        log_activity($order_id, false, $error_message);
        return;
    }

    write_log($order_id, 'Document cancelled successfully', 'Document ID', $document_id);

    $message = 'Billingo invoice cancelled successfully';
    log_activity($order_id, true, $message);
}