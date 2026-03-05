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
- Configurable default QR size (`120` to `1000` px)
- Admin settings page in WordPress
- WooCommerce settings field under **Advanced**
- Global helper functions for use in themes and plugins

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
   - The plugin loader expects: `wp-content/plugins/woo-qr-pay/vendor/php-qrcode/src/`
3. Activate **Woo Pay With a QR Code** in WordPress admin.

## Settings

You can configure the default QR size in either place:

- **Settings > Woo QR Pay**
- **WooCommerce > Settings > Advanced**

Option key used internally:

- `woo_qr_pay_qr_size`

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
- `purpose` (4 letters)
- `reference`
- `message`

## Example (Template Usage)

```php
<?php
$qr = woo_qr_pay_get_pay_by_square_qr_image_tag(
	array(
		'recipient' => 'AR MUSIC s.r.o.',
		'iban'      => 'SK3683300000002200433678',
		'swift'     => 'FIOZSKBAXXX',
		'amount'    => 123.45,
		'vs'        => '20260001',
		'note'      => 'Faktura 20260001',
	),
	null,
	'PAY by square'
);

if (!is_wp_error($qr) && !empty($qr)) {
	echo $qr; // <img ... />
}
```

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
