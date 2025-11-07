=== Integration for Számlázz.hu and FluentCart ===
Contributors: gaborangyal
Tags: szamlazz.hu, fluentcart, invoice, magyar, szamlazo
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generates invoices on Számlázz.hu for FluentCart orders with VAT validation and multi-language support.

== Description ==

Integration for Számlázz.hu and FluentCart is a WordPress plugin that seamlessly connects your FluentCart store with Számlázz.hu, automatically generating professional invoices when orders are paid.

= Key Features =

* **Automatic Invoice Generation** - Invoices are automatically created when orders are marked as paid
* **Download Invoices** - Every receipt download button is overriden with Számlázz.hu invoice download.
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
* Active Számlázz.hu account with Agent API Key
* PHP 7.4 or higher

= Important Warnings =

**Before using this plugin in production:**

1. **Enable Test Mode** in both FluentCart and Számlázz.hu
2. **Generate test invoices** to verify everything works correctly
3. **Consult with your accountant** to ensure the plugin meets your accounting requirements
4. **Review all generated invoices** for accuracy
5. **Test all edge cases** relevant to your business

**This plugin generates official accounting documents. Incorrect invoices can have legal and tax implications.**

= API Usage Costs =

**Számlázz.hu charges for API usage.** This plugin uses the Számlázz.hu Agent API to generate invoices automatically, which is a paid service. Review the [Számlázz.hu pricing](https://www.szamlazz.hu/egyedi-megoldasok/tomeges-szamlageneralas/) before enabling automatic invoice generation.

= Limitations =

* **VAT Rates**: Only explicit rates supported (0%, 5%, 18%, 27%). Named VAT keys (AAM, TAM, TEHK) are not supported.
* **B2B Sales**: Buyers must have an EU VAT ID. Local VAT ID only is not yet supported.
* **Document Types**: Only Invoices can be generated. Receipts and Pro forma invoices are not supported.
* **IPN**: Instant Payment Notification is not yet supported.
* **Lag**: Customers might need to wait a few seconds on the order conrifmation page before being able to download the invoice.

= Language Support =

The admin interface is available in:
* English (Default)
* Hungarian (Magyar)

The interface language follows your WordPress language settings.

= Configuration =

1. Navigate to **Settings > Számlázz.hu**
2. Enter your Számlázz.hu Agent API Key ([How to get API key](https://tudastar.szamlazz.hu/gyik/kulcs))
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

= Support =

For general questions, please visit the [plugin website](https://webshop.tech/integration-for-szamlazzhu-fluentcart/).
Bug reports can be submitted here on the project's [GitHub](https://github.com/agabor/integration-for-szamlazzhu-fluentcart) page.

= Contributing =

This plugin is open source. Contributions are welcome!

= Credits =

* Author: Gábor Angyal
* Website: [webshop.tech](https://webshop.tech)
