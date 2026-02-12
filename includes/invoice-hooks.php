<?php

namespace BillingoFluentCart;

if ( ! defined( 'ABSPATH' ) ) exit;

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
    write_log($order_id, 'fluent_cart/order_refunded', 'Order ID', $order_id);
    if ($data['type'] == 'full') {
        $reason = json_decode($data['transaction']->meta, true)['reason'];
        cancel_invoice($order, $reason);
    } else {
        log_activity($order_id, false, "Partial refund is not supported yet. Create invoice manually.");
    }
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