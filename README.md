# Woo QR Pay

Woo QR Pay is a WordPress + WooCommerce plugin for generating payment QR codes.
It supports:

- `PAY by square` payloads (Slovakia)
- `SEPA EPC` payloads

The plugin can return QR data as:

- a base64 data URI
- raw PNG binary
- a ready-to-use `<img>` tag

## Features

- Generates QR codes locally (no external API required)
- Configurable default QR size (`64` to `1000` px)
- Admin settings page in WordPress
- WooCommerce settings field under **Advanced**
- Global helper functions for use in themes and plugins
- Automatically shows QR on the order-received (thank-you) page for bank transfer orders

## Requirements

- WordPress `>= 5.6`
- WooCommerce
- PHP `>= 8.2` (from plugin header)
- PHP extension: `gd`
- `proc_open` enabled (required for PAY by square)
- `xz` binary available on server (required for PAY by square compression)

## Installation

1. Place plugin in:
   - `wp-content/plugins/woo-qr-pay`
2. Ensure QR library source is available for the loader.
3. Activate **Woo Pay With a QR Code** in WordPress admin.

## Settings

You can configure the default QR size in either place:

- **Settings > Woo QR Pay**
- **WooCommerce > Settings > Advanced**

Option key used internally:

- `woo_qr_pay_qr_size`

## Checkout / Thank-You Page Behavior

After a customer places an order with payment method `Direct bank transfer (bacs)`, the plugin injects a QR payment field into each BACS account block on the thank-you page.

- If billing country is `SK`, plugin tries `PAY by square` first
- If PAY by square is unavailable/fails, plugin falls back to `SEPA`
- For non-SK billing countries, plugin uses `SEPA`
- If multiple bank accounts are configured, each account block gets its own QR code

For BACS thank-you rendering, the plugin uses each configured WooCommerce BACS account (`woocommerce_bacs_accounts`) and generates QR per account block.

## Public Helper Functions

These wrappers are available globally after plugin load:

- `woo_qr_pay_build_sepa_epc_payload(array $payment_data)`
- `woo_qr_pay_get_sepa_qr_image_data(array $payment_data, $size = null, $as_base64 = true)`
- `woo_qr_pay_get_sepa_qr_image_url(array $payment_data, $size = null)`
- `woo_qr_pay_get_sepa_qr_image_tag(array $payment_data, $size = null, $alt = '')`
- `woo_qr_pay_get_configured_qr_size()`
- `woo_qr_pay_build_pay_by_square_payload(array $payment_data)`
- `woo_qr_pay_get_pay_by_square_qr_image_data(array $payment_data, $size = null, $as_base64 = true)`
- `woo_qr_pay_get_pay_by_square_qr_image_url(array $payment_data, $size = null)`
- `woo_qr_pay_get_pay_by_square_qr_image_tag(array $payment_data, $size = null, $alt = '')`

## Payment Data Format

### PAY by square (`woo_qr_pay_get_pay_by_square_qr_image_*`)

Required keys:

- `recipient`
- `iban`

Optional keys:

- `swift`
- `amount`
- `size` (QR size in px; if omitted, plugin setting is used)
- `vs`
- `ks`
- `ss`
- `note`
- `date` (`YYYYMMDD`)

### SEPA (`woo_qr_pay_get_sepa_qr_image_*`)

Required keys:

- `name`
- `iban`

Optional keys:

- `bic`
- `amount`
- `size` (QR size in px; if omitted, plugin setting is used)
- `purpose` (4 letters)
- `reference`
- `message`

## Error Handling

Functions return `WP_Error` when generation fails.
Typical error causes:

- missing/invalid IBAN or recipient/name
- missing `gd` extension
- missing QR library path
- unavailable `proc_open`
- missing `xz` command (PAY by square only)

Always guard output:

```php
if (is_wp_error($qr)) {
	// log or fallback
}
```

## License

GPLv3 or later. See [`LICENSE`](./LICENSE).
