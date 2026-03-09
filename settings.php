<?php

namespace WooQrPay;

if (!defined('ABSPATH')) {
	exit;
}

const OPTION_QR_SIZE = 'woo_qr_pay_qr_size';
const DEFAULT_QR_SIZE = 220;
const MIN_QR_SIZE = 64;
const MAX_QR_SIZE = 1000;

function sanitize_qr_size($size) {
	return max(MIN_QR_SIZE, min((int) $size, MAX_QR_SIZE));
}

function get_configured_qr_size() {
	return sanitize_qr_size(get_option(OPTION_QR_SIZE, DEFAULT_QR_SIZE));
}

function register_plugin_settings() {
	register_setting(
		'woo_qr_pay_settings',
		OPTION_QR_SIZE,
		array(
			'type'              => 'integer',
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_qr_size',
			'default'           => DEFAULT_QR_SIZE,
		)
	);

	add_settings_section(
		'woo_qr_pay_main',
		__('QR Settings', 'woo-qr-pay'),
		'__return_false',
		'woo_qr_pay_settings'
	);

	add_settings_field(
		OPTION_QR_SIZE,
		__('QR code size (px)', 'woo-qr-pay'),
		__NAMESPACE__ . '\\render_qr_size_field',
		'woo_qr_pay_settings',
		'woo_qr_pay_main'
	);
}

function render_qr_size_field() {
	printf(
		'<input type="number" min="%3$d" max="%4$d" step="1" name="%1$s" value="%2$d" class="small-text" />',
		esc_attr(OPTION_QR_SIZE),
		(int) get_configured_qr_size(),
		(int) MIN_QR_SIZE,
		(int) MAX_QR_SIZE
	);
	echo '<p class="description">' . esc_html__('Used as the default QR image size when no explicit size is provided.', 'woo-qr-pay') . '</p>';
}

function add_plugin_settings_page() {
	add_options_page(
		__('Woo QR Pay Settings', 'woo-qr-pay'),
		__('Woo QR Pay', 'woo-qr-pay'),
		'manage_options',
		'woo-qr-pay',
		__NAMESPACE__ . '\\render_plugin_settings_page'
	);
}

function render_plugin_settings_page() {
	?>
	<div class="wrap">
		<h1><?php echo esc_html__('Woo QR Pay Settings', 'woo-qr-pay'); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields('woo_qr_pay_settings');
			do_settings_sections('woo_qr_pay_settings');
			submit_button();
			?>
		</form>
	</div>
	<?php
}

function add_plugin_action_links($links) {
	$settings_url = admin_url('options-general.php?page=woo-qr-pay');
	array_unshift(
		$links,
		sprintf('<a href="%s">%s</a>', esc_url($settings_url), esc_html__('Settings', 'woo-qr-pay'))
	);

	return $links;
}

function add_wc_settings($settings) {
	$settings[] = array(
		'title' => __('Woo QR Pay', 'woo-qr-pay'),
		'type'  => 'title',
		'id'    => 'woo_qr_pay_section',
		'desc'  => __('Configure default QR rendering for bank transfer payments.', 'woo-qr-pay'),
	);

	$settings[] = array(
		'title'             => __('QR code size (px)', 'woo-qr-pay'),
		'id'                => OPTION_QR_SIZE,
		'type'              => 'number',
		'default'           => (string) DEFAULT_QR_SIZE,
		'desc'              => __('Used as the default QR image size.', 'woo-qr-pay'),
		'desc_tip'          => true,
		'custom_attributes' => array(
			'min'  => MIN_QR_SIZE,
			'max'  => MAX_QR_SIZE,
			'step' => 1,
		),
	);

	$settings[] = array(
		'type' => 'sectionend',
		'id'   => 'woo_qr_pay_section',
	);

	return $settings;
}

function sanitize_option_on_update($value) {
	return sanitize_qr_size($value);
}

if (is_admin()) {
	add_action('admin_init', __NAMESPACE__ . '\\register_plugin_settings');
	add_action('admin_menu', __NAMESPACE__ . '\\add_plugin_settings_page');
	add_filter('woocommerce_get_settings_advanced', __NAMESPACE__ . '\\add_wc_settings', 20);
	add_filter('pre_update_option_' . OPTION_QR_SIZE, __NAMESPACE__ . '\\sanitize_option_on_update', 10, 1);
	add_filter('plugin_action_links_' . plugin_basename(__DIR__ . '/woo-qr-pay.php'), __NAMESPACE__ . '\\add_plugin_action_links');
}
