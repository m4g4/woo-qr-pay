<?php
/**
 * Plugin Name: Woo Pay With a QR Code
 * Description: Allows customers to pay for their orders using a QR code. Generates a QR code that can be scanned with a mobile device to complete the payment process.
 * Version:     0.0.1
 * Author:      m4g4
 * License:     GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * 
 * Requires at least: 5.6
 * Tested up to: 6.8
 * Requires PHP: 8.2
 */

require_once __DIR__ . '/add_qr_code.php';
require_once __DIR__ . '/settings.php';

if (!function_exists('woo_qr_pay_build_sepa_epc_payload')) {
	function woo_qr_pay_build_sepa_epc_payload(array $payment_data) {
		return \WooQrPay\build_sepa_epc_payload($payment_data);
	}
}

if (!function_exists('woo_qr_pay_get_sepa_qr_image_data')) {
	function woo_qr_pay_get_sepa_qr_image_data(array $payment_data, $size = null, $as_base64 = true) {
		return \WooQrPay\get_sepa_qr_image_data($payment_data, $size, $as_base64);
	}
}

if (!function_exists('woo_qr_pay_get_sepa_qr_image_url')) {
	function woo_qr_pay_get_sepa_qr_image_url(array $payment_data, $size = null) {
		return \WooQrPay\get_sepa_qr_image_url($payment_data, $size);
	}
}

if (!function_exists('woo_qr_pay_get_sepa_qr_image_tag')) {
	function woo_qr_pay_get_sepa_qr_image_tag(array $payment_data, $size = null, $alt = '') {
		return \WooQrPay\get_sepa_qr_image_tag($payment_data, $size, $alt);
	}
}

if (!function_exists('woo_qr_pay_get_configured_qr_size')) {
	function woo_qr_pay_get_configured_qr_size() {
		return \WooQrPay\get_configured_qr_size();
	}
}

if (!function_exists('woo_qr_pay_build_pay_by_square_payload')) {
	function woo_qr_pay_build_pay_by_square_payload(array $payment_data) {
		return \WooQrPay\build_pay_by_square_payload($payment_data);
	}
}

if (!function_exists('woo_qr_pay_get_pay_by_square_qr_image_data')) {
	function woo_qr_pay_get_pay_by_square_qr_image_data(array $payment_data, $size = null, $as_base64 = true) {
		return \WooQrPay\get_pay_by_square_qr_image_data($payment_data, $size, $as_base64);
	}
}

if (!function_exists('woo_qr_pay_get_pay_by_square_qr_image_url')) {
	function woo_qr_pay_get_pay_by_square_qr_image_url(array $payment_data, $size = null) {
		return \WooQrPay\get_pay_by_square_qr_image_url($payment_data, $size);
	}
}

if (!function_exists('woo_qr_pay_get_pay_by_square_qr_image_tag')) {
	function woo_qr_pay_get_pay_by_square_qr_image_tag(array $payment_data, $size = null, $alt = '') {
		return \WooQrPay\get_pay_by_square_qr_image_tag($payment_data, $size, $alt);
	}
}

?>
