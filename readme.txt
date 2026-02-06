=== Integration for Billingo and FluentCart ===
Contributors: gaborangyal
Tags: billingo, fluentcart, invoice, magyar, szamlazo
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generates invoices on Billingo for FluentCart orders with VAT validation and multi-language support.

== Description ==

Integration for Billingo and FluentCart is a WordPress plugin that seamlessly connects your FluentCart store with Billingo, automatically generating professional invoices when orders are paid.

== External Services ==

This plugin relies on the Billingo invoice generation service to create and manage invoices for FluentCart orders.

= Service Information =

* Service Provider: Billingo (billingo.hu)
* Service Purpose: The plugin connects to Billingo's API to automatically generate invoices, fetch taxpayer information from the Hungarian Tax Authority (NAV), and retrieve generated invoice PDFs.

= Data Transmission =

The plugin sends the following data to https://api.billingo.hu/v3 when:

**Generating Invoices** (when an order is marked as paid):

* Your Billingo API Key
* Order information: order number, dates, amounts, currency
* Buyer information: name, email, address, postal code, city, country
* Buyer's VAT/tax number (if provided)
* Product details: names, quantities, prices, VAT rates
* Shipping information: title, amount, VAT rate
* Invoice settings: language, type (paper/electronic), payment method

**Fetching Taxpayer Data** (when a Hungarian VAT number is provided):

* Your Billingo API Key
* The taxpayer's tax identification number (in format 12345678-1-23)

**Downloading Invoice PDFs** (when users click to download invoices):

* Your Billingo API Key
* The document ID

= Legal Information =

* Billingo Terms of Service: https://www.billingo.hu/felhasznalasi-feltetelek
* Billingo Privacy Policy: https://www.billingo.hu/adatkezelesi-tajekoztato

**Important:** By using this plugin, you agree to transmit your store and customer data to Billingo. Ensure you have proper consent from your customers and comply with applicable data protection regulations (GDPR, etc.).

= Key Features =

* **Automatic Invoice Generation** - Invoices are automatically created when orders are marked as paid
* **Download Invoices** - Every receipt download button is overriden with Billingo invoice download.
* **Multi-language Support** - Generate invoices in 11 languages: Hungarian, English, German, Italian, Romanian, Slovak, Croatian, French, Spanish, Czech, Polish
* **Invoice Types** - Choose between Paper Invoice and E-Invoice formats
* **VAT Number Validation** - Automatically fetches company data from NAV (Hungarian Tax Authority) when VAT number is provided
* **Customizable Settings** - Configure invoice language, type, quantity units, and shipping details
* **Shipping VAT Management** - Easily set and apply VAT rates for shipping
* **Cache Management** - Built-in cache system with easy cleanup
* **Bilingual Admin Interface** - Full support for English and Hungarian languages
* **Subscription Support** - Automatically generates invoices for subscription renewals

= Requirements =

* WordPress 5.0 or higher
* FluentCart plugin installed and activated
* Active Billingo account with Agent API Key
* PHP 7.4 or higher

= Important Warnings =

**Before using this plugin in production:**

1. **Enable Test Mode** in both FluentCart and Billingo
2. **Generate test invoices** to verify everything works correctly
3. **Consult with your accountant** to ensure the plugin meets your accounting requirements
4. **Review all generated invoices** for accuracy
5. **Test all edge cases** relevant to your business

**This plugin generates official accounting documents. Incorrect invoices can have legal and tax implications.**

= API Usage Costs =

**Billingo charges for API usage.** This plugin uses the Billingo Agent API to generate invoices automatically, which is a paid service. Review the [Billingo pricing](https://www.billingo.hu/szolgaltatasok/api) before enabling automatic invoice generation.

= Limitations =

* **VAT Rates**: Only explicit rates supported (0%, 5%, 18%, 27%). Named VAT keys (AAM, TAM, TEHK) are not supported.
* **B2B Sales**: Buyers must have an EU VAT ID. Local VAT ID only is not yet supported.
* **Document Types**: Only Invoices can be generated. Receipts and Pro forma invoices are not supported.
* **IPN**: Instant Payment Notification is not yet supported.
* **Lag**: Customers might need to wait a few seconds on the order conrifmation page before being able to download the invoice.
* **Shipping VAT**: FluentCart shipping VAT may contain **minor rounding errors**. This is a known bug in FluentCart, which will be corrected in the following releases. The shipping VAT on the **invoice is calculated correctly** according to Hungarian legal regulations. This may cause a small difference (fraction of a Forint) between what the customer pays, and what appears on the invoice.

= Language Support =

The admin interface is available in:
* English (Default)
* Hungarian (Magyar)

The interface language follows your WordPress language settings.

= Configuration =

1. Navigate to **Settings > Billingo**
2. Enter your Billingo Agent API Key ([How to get API key](https://support.billingo.hu/content/951124273))
3. Configure invoice settings:
   * Invoice Language (default: Hungarian)
   * Invoice Type (Paper Invoice or E-Invoice)
   * Quantity Unit (default: "db")
   * Shipping Title (default: "Szállítás")
   * Shipping VAT Rate (default: 27%)
4. Save settings

== Screenshots ==

1. Plugin settings page

== Changelog ==

= 1.0.1 =
* Fix sanitation issue for API key

= 1.0.0 =
* Initial release
* Automatic invoice generation for FluentCart orders
* Multi-language support (11 languages)
* Paper Invoice and E-Invoice types
* VAT number validation with NAV integration
* Customizable quantity units and shipping titles
* Shipping VAT management with easy application to tax rates
* Cache management system
* Bilingual admin interface (English/Hungarian)
* Support for subscription renewals
* PDF caching and download functionality
* Debug logging when WP_DEBUG is enabled

== Additional Information ==

= Troubleshooting for advanced users =

Enable `WP_DEBUG` to see debug information on each orders activity log. 

= Support =

For general questions, please visit the [plugin website](https://webshop.tech/integration-for-billingo-fluentcart/).
Bug reports and feature requests can be submitted on the project's [GitHub](https://github.com/agabor/integration-for-billingo-fluentcart) page.

= Contributing =

This plugin is open source. Contributions are welcome!

= Credits =

* Author: Gábor Angyal
* Website: [webshop.tech](https://webshop.tech)
