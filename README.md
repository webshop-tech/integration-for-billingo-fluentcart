# Integration for Billingo and FluentCart

A WordPress plugin that automatically generates invoices on Billingo for FluentCart orders.

## Features

- **Automatic Invoice Generation**: Automatically creates invoices when orders are marked as paid
- **Multi-language Support**: Generate invoices in 11 languages (Hungarian, English, German, Italian, Romanian, Slovak, Croatian, French, Spanish, Czech, Polish)
- **Invoice Types**: Choose between Paper Invoice and E-Invoice
- **VAT Number Validation**: Automatically fetches company data from NAV (Hungarian Tax Authority) if VAT number is provided
- **Customizable Settings**: Configure invoice language, type, quantity units, and shipping details
- **Shipping VAT Management**: Easily set and apply VAT rates for shipping
- **Cache Management**: Built-in cache system with easy cleanup
- **Bilingual Admin Interface**: Full support for English and Hungarian languages

## Requirements

- WordPress 5.0 or higher
- FluentCart plugin installed and activated
- Active Billingo account with Agent API Key
- PHP 7.4 or higher

## ⚠️ Important Warning

**Before using this plugin in production:**

1. **Enable Test Mode** in both FluentCart and Billingo
2. **Generate test invoices** to verify everything works correctly
3. **Consult with your accountant** to ensure the plugin meets your accounting requirements
4. **Review all generated invoices** for accuracy (amounts, VAT calculations, company data)
5. **Test all edge cases** relevant to your business (B2B sales, different VAT rates, etc.)
6. **Read about limitations** [bellow](#limitations)

**This plugin generates official accounting documents. Incorrect invoices can have legal and tax implications. Always test thoroughly and get professional accounting advice before going live.**

### 💰 API Usage Costs

**Billingo charges for API usage.** This plugin uses the Billingo Agent API to generate invoices automatically, which is a paid service.

- Review the [Billingo pricing for bulk invoice generation](https://www.billingo.hu/szolgaltatasok/api)
- Understand the costs before enabling automatic invoice generation
- Each invoice generated through the API will be charged according to Billingo's pricing
- Consider your order volume and calculate expected monthly costs

## Installation

1. Download the plugin files
2. Upload to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Settings > Billingo** to configure the plugin

## Configuration

### 1. API Settings

Navigate to **Settings > Billingo** in your WordPress admin panel.

#### Agent API Key
- Enter your Billingo Agent API Key
- Click "[What is this?](https://support.billingo.hu/content/951124273)" for instructions on how to obtain your API key
- The key is stored securely and displayed as a password field

### 2. Invoice Settings

#### Invoice Language
Choose the language for your generated invoices:
- Magyar (Hungarian) - Default
- English
- Deutsch (German)
- Italiano (Italian)
- Română (Romanian)
- Slovenčina (Slovak)
- Hrvatski (Croatian)
- Français (French)
- Español (Spanish)
- Čeština (Czech)
- Polski (Polish)

#### Invoice Type
Select the type of invoice to generate:
- **Paper Invoice** (Default) - Traditional paper invoice format
- **E-Invoice** - Electronic invoice format

#### Quantity Unit
Set the default unit of measurement for invoice items (e.g., "db", "pcs", "kg")
- Default: "db" (darab/piece in Hungarian)

#### Shipping Title
Customize the title for the shipping line item on invoices
- Default: "Szállítás" (Shipping in Hungarian)

#### Shipping VAT Rate
Select the VAT rate to apply to shipping:
- 0%
- 5%
- 18%
- 27% (Default)

The current shipping VAT rates in use are displayed below the dropdown.

## Usage

### Automatic Invoice Generation

Once configured, the plugin automatically generates invoices when:
1. An order is marked as paid in FluentCart
2. A payment status changes to paid

The invoice will be:
- Generated on Billingo with your configured settings
- Stored in the database with order reference
- Available for download

### VAT Number Processing

If a customer provides a VAT number during checkout:
1. The plugin queries the Hungarian NAV database
2. Company information is automatically populated (name, address, VAT ID)
3. The invoice is generated with validated company data

### Manual Actions

#### Apply Shipping VAT to All Tax Rates
Use this button to apply the selected shipping VAT rate to all tax rates in FluentCart. This is useful when:
- Setting up the plugin for the first time
- Changing the shipping VAT rate
- Ensuring consistency across all tax configurations

**Note**: The button is disabled if:
- No tax rates are configured in FluentCart
- All tax rates already match the selected VAT rate

### Cache Management

The plugin caches generated PDFs, XMLs, and logs in a secure directory.

#### Current Cache Size
View the current size of cached files in the admin panel.

#### Clear Cache
Click "Clear Cache" to delete all cached files. This will:
- Remove all cached PDFs
- Remove all cached XMLs
- Remove all log files
- Free up server disk space

The cache directory will be automatically recreated when needed.

## Language Support

The admin interface is available in:
- **English** (Default)
- **Hungarian** (Magyar)

The interface language follows your WordPress language settings (Settings > General > Site Language).

## Limitations

Please be aware of the following limitations:

### VAT Rates
- Only explicit VAT rates are supported: **0%, 5%, 18%, 27%**
- Named VAT keys like AAM, TAM, TEHK are **not supported**
- Use **0%** as a substitute for special VAT categories (AAM, TAM, TEHK)

### B2B Sales
- For B2B (business-to-business) sales, the buyer **must have an EU VAT ID**
- Buyers with **local VAT ID only** are not yet supported
- The plugin validates and fetches company data from the Hungarian NAV database for EU VAT IDs

### Document Types
- Only **Invoices** can be generated
- **Receipts** (nyugta) are not supported
- **Pro forma invoices** (díjbekérő) are not supported

### Shipping VAT Calculation
- FluentCart shipping VAT may contain **minor rounding errors**. This is a known bug in FluentCart, which will be corrected in the following releases.
- The shipping VAT on the **invoice is calculated correctly** according to Hungarian legal regulations.
- This may cause a small difference between what the customer pays, and what appears on the invoice.

### Instant Payment Notification
- Instant Payment Notification (IPN) is not yet supported.

## Troubleshooting

### Invoice Not Generated

1. **Check API Key**: Ensure your Agent API Key is correctly entered
2. **Verify Order Status**: Invoices are only generated for paid orders
3. **Check Logs**: Enable `WP_DEBUG` in your `wp-config.php` to see detailed logs
4. **Tax Configuration**: Ensure tax rates are properly configured in FluentCart

### VAT Number Issues

- VAT numbers must be valid Hungarian tax numbers
- The NAV API must be accessible
- Company data will fall back to billing address if NAV query fails

### Cache Issues

If you experience issues with cached files:
1. Go to **Settings > Billingo**
2. Scroll to "Cache Management"
3. Click "Clear Cache"
4. Test invoice generation again

## Support

For support and bug reports, please contact: [webshop.tech](https://webshop.tech)

## License

GPL v2 or later

## Credits

- **Author**: Gábor Angyal
- **Plugin URI**: [https://webshop.tech/integration-for-billingo-fluentcart](https://webshop.tech/integration-for-billingo-fluentcart)
- **Version**: 0.0.1

## Changelog

### 0.0.1
- Initial release
- Automatic invoice generation for FluentCart orders
- Multi-language support (11 languages)
- Paper Invoice and E-Invoice types
- VAT number validation with NAV integration
- Customizable quantity units and shipping titles
- Shipping VAT management
- Cache management system
- Bilingual admin interface (English/Hungarian)
