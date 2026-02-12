<?php

namespace BillingoFluentCart;

if ( ! defined( 'ABSPATH' ) ) exit;

use FluentCart\App\Models\OrderItem;

function get_payload($order, $current_order_id, $partner_id, $block_id)
{
    $items_data = build_order_items_data($order, $current_order_id);
    if (\is_wp_error($items_data)) {
        return $items_data;
    }

    $billingo_items = build_document_items($items_data);

    $invoice_type = \get_option('billingo_fluentcart_invoice_type', 1);
    $invoice_language = \get_option('billingo_fluentcart_invoice_language', 'hu');
    $payment_method_raw = \get_option('billingo_fluentcart_payment_method', 'Átutalás');

    $today = gmdate('Y-m-d');
    $due_date = gmdate('Y-m-d', strtotime('+8 days'));

    $payment_method = map_payment_method($payment_method_raw);

    write_log($current_order_id, 'Invoice settings', 'Type', ($invoice_type == 2) ? 'Electronic' : 'Paper', 'Language', $invoice_language, 'Payment method', $payment_method);

    $document_params = array(
        'partner_id' => $partner_id,
        'block_id' => $block_id,
        'type' => 'invoice',
        'fulfillment_date' => $today,
        'due_date' => $due_date,
        'payment_method' => $payment_method,
        'language' => $invoice_language,
        'currency' => $order->currency,
        'electronic' => ($invoice_type == 2),
        'paid' => false,
        'items' => $billingo_items,
    );

    if (!empty($current_order_id)) {
        $document_params['vendor_id'] = strval($current_order_id);
    }

    return  build_document_payload($document_params);
}

function map_vat_rate($rate): string
{
    $rate_numeric = floatval($rate);

    if ($rate_numeric == 0) {
        return '0%';
    }

    return strval(intval($rate_numeric)) . '%';
}
function build_document_items($items_data): array
{
    $billingo_items = array();

    foreach ($items_data as $item) {
        $billingo_item = array(
            'name' => $item['name'],
            'unit_price' => floatval($item['unit_price']),
            'unit_price_type' => 'net',
            'quantity' => floatval($item['quantity']),
            'unit' => $item['unit'],
            'vat' => map_vat_rate($item['vat_rate']),
        );

        if (!empty($item['comment'])) {
            $billingo_item['comment'] = $item['comment'];
        }

        $billingo_items[] = $billingo_item;
    }

    return $billingo_items;
}

function build_order_items_data($order, $current_order_id) {
    $order_id = $order->id;
    $items = OrderItem::where('order_id', $order_id)->get();

    if ($items->isEmpty()) {
        return create_error($current_order_id, 'no_items', "No items found for order " . absint($order_id));
    }

    $quantity_unit = \get_option('billingo_fluentcart_quantity_unit', 'db');

    write_log($current_order_id, 'Building order items', 'Item count', $items->count());

    $items_data = array();

    foreach ($items as $order_item) {
        $taxRate = "0";
        $tax_amount = 0;

        if ($order->tax_behavior != 0) {
            if (isset($order_item->line_meta['tax_config']['rates'][0]['rate'])) {
                $taxRate = $order_item->line_meta['tax_config']['rates'][0]['rate'];
            }
            $tax_amount = $order_item->tax_amount / 100;
        }

        $net_price = $order_item->line_total / 100;
        $unit_price = $net_price / $order_item->quantity;
        $gross_amount = $net_price + $tax_amount;

        $items_data[] = array(
            'name' => $order_item->title,
            'quantity' => $order_item->quantity,
            'unit' => $quantity_unit,
            'unit_price' => $unit_price,
            'vat_rate' => $taxRate,
            'net_price' => $net_price,
            'vat_amount' => $tax_amount,
            'gross_amount' => $gross_amount,
        );

        write_log(
            $current_order_id,
            'Item',
            $order_item->title,
            'Qty',
            $order_item->quantity,
            'Unit price',
            $unit_price,
            'Tax rate',
            $taxRate . '%',
            'Net',
            $net_price,
            'VAT',
            $tax_amount,
            'Gross',
            $gross_amount
        );
    }

    if ($order->shipping_total != 0) {
        $shipping_title = \get_option('billingo_fluentcart_shipping_title', 'Szállítás');
        $shipping_net = $order->shipping_total / 100;
        $shipping_vat_amount = 0;
        $shipping_vat_rate = "0";

        if ($order->tax_behavior != 0) {
            $shipping_vat = \get_option('billingo_fluentcart_shipping_vat', 27);
            $shipping_vat_rate = strval($shipping_vat);
            $shipping_vat_amount = $shipping_net * ($shipping_vat / 100);
        }

        $shipping_gross = $shipping_net + $shipping_vat_amount;

        $items_data[] = array(
            'name' => $shipping_title,
            'quantity' => 1,
            'unit' => 'db',
            'unit_price' => $shipping_net,
            'vat_rate' => $shipping_vat_rate,
            'net_price' => $shipping_net,
            'vat_amount' => $shipping_vat_amount,
            'gross_amount' => $shipping_gross,
        );
    }

    return $items_data;
}


function build_document_payload($params): array
{
    $payload = array(
        'partner_id' => $params['partner_id'],
        'block_id' => $params['block_id'],
        'type' => $params['type'] ?? 'invoice',
        'fulfillment_date' => $params['fulfillment_date'],
        'due_date' => $params['due_date'],
        'payment_method' => $params['payment_method'],
        'language' => $params['language'],
        'currency' => $params['currency'],
        'items' => $params['items'],
    );

    if (isset($params['vendor_id'])) {
        $payload['vendor_id'] = $params['vendor_id'];
    }

    if (isset($params['bank_account_id'])) {
        $payload['bank_account_id'] = $params['bank_account_id'];
    }

    if (isset($params['conversion_rate'])) {
        $payload['conversion_rate'] = floatval($params['conversion_rate']);
    }

    if (isset($params['electronic'])) {
        $payload['electronic'] = (bool)$params['electronic'];
    }

    if (isset($params['paid'])) {
        $payload['paid'] = (bool)$params['paid'];
    }

    if (isset($params['comment'])) {
        $payload['comment'] = $params['comment'];
    }

    if (isset($params['settings'])) {
        $payload['settings'] = $params['settings'];
    }

    return $payload;
}


function map_payment_method($old_method): string
{
    $mapping = array(
        'Átutalás' => 'wire_transfer',
        'Készpénz' => 'cash',
        'Bankkártya' => 'bankcard',
        'Csekk' => 'postai_csekk',
        'Utánvét' => 'cash_on_delivery',
        'PayPal' => 'paypal',
        'Barion' => 'barion',
        'Egyéb' => 'other',
    );

    return $mapping[$old_method] ?? 'wire_transfer';
}