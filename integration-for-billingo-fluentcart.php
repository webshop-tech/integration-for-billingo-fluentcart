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
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'cache.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'database.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'api.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'api-vat.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'settings.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'settings-fields.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'settings-page.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'vat.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'invoice-hooks.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'invoice-download.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'invoice-payload.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'invoice-create.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'invoice-cancel.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'invoice-partner.php';

\register_activation_hook(__FILE__, __NAMESPACE__ . '\\create_invoices_table');