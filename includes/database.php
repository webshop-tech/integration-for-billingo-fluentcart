<?php

namespace SzamlazzHuFluentCart;

if (!\defined('ABSPATH')) {
    exit;
}

function create_invoices_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'szamlazz_invoices';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        invoice_number varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY order_id (order_id),
        KEY invoice_number (invoice_number)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function save_invoice($order_id, $invoice_number) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'szamlazz_invoices';
    
    // Direct database insert is necessary for custom table.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    return $wpdb->insert(
        $table_name,
        [
            'order_id' => $order_id,
            'invoice_number' => $invoice_number
        ],
        ['%d', '%s']
    );
}

/**
 * Get invoice number by order ID
 * 
 * @param int $order_id The order ID
 * @return string|null The invoice number or null if not found
 */
function get_invoice_number_by_order_id($order_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'szamlazz_invoices';
    
    // Direct database query is necessary for custom table.
    // Response is not cached because data is volatile
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return $wpdb->get_var($wpdb->prepare("SELECT invoice_number FROM %i WHERE order_id = %d", $table_name, $order_id));
}
