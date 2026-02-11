<?php

namespace BillingoFluentCart;

if (!defined('ABSPATH')) {
    exit;
}

use FluentCart\App\Helpers\CartHelper;
use FluentCart\App\Services\Renderer\EUVatRenderer;
use FluentCart\App\Services\Renderer\CartSummaryRender;
function replace_eu_vat_header($content) {
    return preg_replace(
        '/<h4[^>]*id="eu-vat-heading"[^>]*>(.*?)<\/h4>/s',
        '<h4 id="eu-vat-heading" class="fct_form_section_header_label">' . \esc_html(__('Hungarian VAT ID', 'integration-for-billingo-fluentcart')) . '</h4>',
        $content
    );
}

function validateVatNumberWithApi($vatNumber): array
{
    $api_key = \get_option('billingo_fluentcart_agent_api_key', '');
    
    if (empty($api_key)) {
        return array(
            'valid' => false,
            'message' => __('API key not configured', 'integration-for-billingo-fluentcart'),
        );
    }

    $result = check_tax_number_api(0, $api_key, $vatNumber);
    
    if (\is_wp_error($result)) {
        return array(
            'valid' => false,
            'message' => $result->get_error_message(),
        );
    }
    
    if (isset($result['error'])) {
        return array(
            'valid' => false,
            'message' => $result['error']['message'] ?? __('Tax number validation failed', 'integration-for-billingo-fluentcart'),
        );
    }
    
    if (isset($result['tax_number'])) {
        return array(
            'valid' => true,
            'tax_number' => $result['tax_number'],
            'message' => $result['result'] ?? __('Valid tax number', 'integration-for-billingo-fluentcart'),
        );
    }
    
    return array(
        'valid' => false,
        'message' => __('Invalid tax number', 'integration-for-billingo-fluentcart'),
    );
}

function handleVatValidation() {
    $cart = CartHelper::getCart();

    $checkoutData = $cart->checkout_data ?? [];

    if ($checkoutData['form_data']['billing_country'] !== 'HU')
        return;

    \nocache_headers();
    if (!isset($_REQUEST['_wpnonce']) || !\wp_verify_nonce(\sanitize_text_field(\wp_unslash($_REQUEST['_wpnonce'])), 'fluentcart')) {
        \wp_send_json(['message' => __('Security check failed', 'fluent-cart')], 403);
    }

    $vatNumber = isset($_REQUEST['vat_number']) ? \sanitize_text_field(\wp_unslash($_REQUEST['vat_number'])) : '';

    if (empty($vatNumber)) {
        \wp_send_json(['message' => __('VAT number is required', 'fluent-cart')], 422);
    }

    $validation = validateVatNumberWithApi($vatNumber);
    
    if (!$validation['message'] != 'validation_ok') {
        
        // TODO: possible messages are:
        // external_nav_service_unreachable
        // invalid_tax_number
        // no_online_szamla_settings
        // non_exist_tax_number
        // validation_ok
        // return a human readable error message for each
        \wp_send_json(['message' => $validation['message']], 422);
    }

    $taxData = $checkoutData['tax_data'];
    $taxData['valid'] = true;
    $taxData['country'] = 'HU';
    $taxData['vat_number'] = $validation['tax_number'];

    $checkoutData['tax_data'] = $taxData;
    $cart->checkout_data = $checkoutData;
    $cart->save();

    \ob_start();
    (new CartSummaryRender($cart))->render(false);
    $cartSummaryInner = \ob_get_clean();


    \ob_start();
    (new EUVatRenderer(true))->render($cart);
    $euVatView = \ob_get_clean();
    $euVatView = replace_eu_vat_header($euVatView);

    wp_send_json([
        'success'   => true,
        'message'   => __('VAT has been applied successfully', 'fluent-cart'),
        'tax_data'  => $taxData,
        'fragments' => [
            [
                'selector' => '[data-fluent-cart-checkout-page-cart-items-wrapper]',
                'content'  => $cartSummaryInner,
                'type'     => 'replace'
            ],
            [
                'selector' => '[data-fluent-cart-checkout-page-tax-wrapper]',
                'content'  => $euVatView,
                'type'     => 'replace'
            ]
        ],
    ], 200);
}

function renameEuVatHeader($fragments, $args)
{
    $cart = CartHelper::getCart();
    $checkoutData = $cart->checkout_data ?? [];

    if ($checkoutData['form_data']['billing_country'] !== 'HU')
        return $fragments;

    if (empty($fragments) || !is_array($fragments)) {
        return $fragments;
    }

    foreach ($fragments as $key => $fragment) {
        if (isset($fragment['selector']) && $fragment['selector'] === '[data-fluent-cart-checkout-page-tax-wrapper]') {
            if (isset($fragment['content'])) {
                $fragments[$key]['content'] = replace_eu_vat_header($fragment['content']);
            }
        }
    }

    return $fragments;
}

\add_filter('fluent_cart/checkout/after_patch_checkout_data_fragments',  __NAMESPACE__ . '\\renameEuVatHeader', 20, 2);
\add_action('wp_ajax_fluent_cart_validate_vat', __NAMESPACE__ . '\\handleVatValidation', 1);
\add_action('wp_ajax_nopriv_fluent_cart_validate_vat', __NAMESPACE__ . '\\handleVatValidation', 1);