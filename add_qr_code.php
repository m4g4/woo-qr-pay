<?php

namespace WooQrPay;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Build PAY by square payload string.
 */
function build_pay_by_square_payload(array $payment_data) {
	$recipient = isset($payment_data['recipient']) ? trim((string) $payment_data['recipient']) : '';
	$iban = isset($payment_data['iban']) ? strtoupper(str_replace(' ', '', (string) $payment_data['iban'])) : '';
	$swift = isset($payment_data['swift']) ? strtoupper(trim((string) $payment_data['swift'])) : '';
	$note = isset($payment_data['note']) ? (string) $payment_data['note'] : '';
	$amount = isset($payment_data['amount']) ? (float) $payment_data['amount'] : 0.0;
	$vs = isset($payment_data['vs']) ? preg_replace('/\D+/', '', (string) $payment_data['vs']) : '';
	$ks = isset($payment_data['ks']) ? preg_replace('/\D+/', '', (string) $payment_data['ks']) : '';
	$ss = isset($payment_data['ss']) ? preg_replace('/\D+/', '', (string) $payment_data['ss']) : '';
	$date = isset($payment_data['date']) ? preg_replace('/\D+/', '', (string) $payment_data['date']) : gmdate('Ymd');

	if ($recipient === '') {
		return new \WP_Error('woo_qr_pay_missing_recipient', __('Missing payment recipient name.', 'woo-qr-pay'));
	}

	if ($iban === '' || !preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/', $iban)) {
		return new \WP_Error('woo_qr_pay_invalid_iban', __('Invalid IBAN for PAY by square.', 'woo-qr-pay'));
	}

	if ($swift !== '' && !preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/', $swift)) {
		return new \WP_Error('woo_qr_pay_invalid_swift', __('Invalid BIC/SWIFT for PAY by square.', 'woo-qr-pay'));
	}

	$amount_formatted = number_format(max(0, $amount), 2, '.', '');
	$vs = substr($vs, 0, 10);
	$ks = substr($ks, 0, 4);
	$ss = substr($ss, 0, 10);
	$date = strlen($date) === 8 ? $date : gmdate('Ymd');
	$note = strtolower(function_exists('remove_accents') ? remove_accents($note) : $note);
	$note = substr(trim($note), 0, 35);
	$recipient = substr($recipient, 0, 70);

	$data = implode(
		"\t",
		array(
			'',
			'1',
			implode(
				"\t",
				array(
					'1',
					$amount_formatted,
					'EUR',
					$date,
					$vs,
					$ks,
					$ss,
					'',
					$note,
					'1',
					$iban,
					$swift,
					'0',
					'0',
					$recipient,
				)
			),
		)
	);

	$with_crc = strrev(hash('crc32b', $data, true)) . $data;
	$compressed = pay_by_square_lzma_compress($with_crc);
	if (is_wp_error($compressed)) {
		return $compressed;
	}

	$hex = bin2hex("\x00\x00" . pack('v', strlen($with_crc)) . $compressed);
	$bits = '';
	$hex_len = strlen($hex);

	for ($i = 0; $i < $hex_len; $i++) {
		$bits .= str_pad(base_convert($hex[$i], 16, 2), 4, '0', STR_PAD_LEFT);
	}

	$rem = strlen($bits) % 5;
	if ($rem > 0) {
		$bits .= str_repeat('0', 5 - $rem);
	}

	$alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUV';
	$chunks = strlen($bits) / 5;
	$output = '';

	for ($i = 0; $i < $chunks; $i++) {
		$output .= $alphabet[bindec(substr($bits, $i * 5, 5))];
	}

	return $output;
}

/**
 * PAY by square requires raw LZMA compression.
 */
function pay_by_square_lzma_compress($payload) {
	if (!function_exists('proc_open')) {
		return new \WP_Error(
			'woo_qr_pay_missing_proc_open',
			__('PAY by square requires proc_open support on the server.', 'woo-qr-pay')
		);
	}

	$cmd = 'xz --format=raw --lzma1=lc=3,lp=0,pb=2,dict=128KiB -c -';
	$pipes = array();
	$process = proc_open(
		$cmd,
		array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		),
		$pipes
	);

	if (!is_resource($process)) {
		return new \WP_Error(
			'woo_qr_pay_lzma_failed',
			__('Unable to initialize PAY by square compressor (xz).', 'woo-qr-pay')
		);
	}

	fwrite($pipes[0], $payload);
	fclose($pipes[0]);

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	$code = proc_close($process);

	if ($code !== 0 || !is_string($stdout) || $stdout === '') {
		return new \WP_Error(
			'woo_qr_pay_lzma_failed',
			__('PAY by square compression failed. Ensure xz is available on server.', 'woo-qr-pay') . ($stderr ? ' ' . trim($stderr) : '')
		);
	}

	return $stdout;
}

/**
 * Build EPC (SEPA) payload string for QR code generation.
 *
 * Required keys in $payment_data:
 * - name: Beneficiary name
 * - iban: Beneficiary IBAN
 * Optional keys:
 * - bic: Beneficiary BIC/SWIFT
 * - amount: Amount in EUR (e.g. "123.45" or 123.45)
 * - purpose: Purpose code (4 chars, optional)
 * - reference: Structured creditor reference (RF...)
 * - message: Unstructured remittance info
 */
function build_sepa_epc_payload(array $payment_data) {
	$name = isset($payment_data['name']) ? trim((string) $payment_data['name']) : '';
	$iban = isset($payment_data['iban']) ? strtoupper(str_replace(' ', '', (string) $payment_data['iban'])) : '';
	$bic = isset($payment_data['bic']) ? strtoupper(trim((string) $payment_data['bic'])) : '';
	$purpose = isset($payment_data['purpose']) ? strtoupper(trim((string) $payment_data['purpose'])) : '';
	$reference = isset($payment_data['reference']) ? trim((string) $payment_data['reference']) : '';
	$message = isset($payment_data['message']) ? trim((string) $payment_data['message']) : '';
	$amount = isset($payment_data['amount']) ? (float) $payment_data['amount'] : 0.0;

	if ($name === '') {
		return new \WP_Error('woo_qr_pay_missing_name', __('Missing SEPA recipient name.', 'woo-qr-pay'));
	}

	if ($iban === '' || !preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/', $iban)) {
		return new \WP_Error('woo_qr_pay_invalid_iban', __('Invalid IBAN for SEPA payment.', 'woo-qr-pay'));
	}

	if ($bic !== '' && !preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/', $bic)) {
		return new \WP_Error('woo_qr_pay_invalid_bic', __('Invalid BIC/SWIFT code.', 'woo-qr-pay'));
	}

	if ($purpose !== '' && !preg_match('/^[A-Z]{4}$/', $purpose)) {
		return new \WP_Error('woo_qr_pay_invalid_purpose', __('Purpose code must be 4 letters.', 'woo-qr-pay'));
	}

	$amount_line = '';
	if ($amount > 0) {
		$amount_line = 'EUR' . number_format($amount, 2, '.', '');
	}

	// EPC line order for SCT QR.
	$lines = array(
		'BCD',
		'002',
		'1',
		'SCT',
		$bic,
		substr($name, 0, 70),
		$iban,
		$amount_line,
		$purpose,
		substr($reference, 0, 35),
		substr($message, 0, 140),
	);

	return implode("\n", $lines);
}

/**
 * Attempt to load local QR library autoloader.
 */
function load_local_qr_library() {
	if (class_exists('\chillerlan\QRCode\QRCode', false)) {
		return true;
	}

	$settings_shim = __DIR__ . '/php-settings-container-shim.php';
	if (file_exists($settings_shim)) {
		require_once $settings_shim;
	}

	$src_dir = '';
	$candidate_dirs = array(
		__DIR__ . '/php-qrcode/src/',
		__DIR__ . '/vendor/php-qrcode/src/',
	);

	foreach ($candidate_dirs as $candidate_dir) {
		if (is_dir($candidate_dir)) {
			$src_dir = $candidate_dir;
			break;
		}
	}

	if ($src_dir === '') {
		return false;
	}

	static $loader_registered = false;
	if (!$loader_registered) {
		spl_autoload_register(
			static function ($class) use ($src_dir) {
				$prefix = 'chillerlan\\QRCode\\';
				if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
					return;
				}

				$relative = substr($class, strlen($prefix));
				$file = $src_dir . str_replace('\\', '/', $relative) . '.php';

				if (file_exists($file)) {
					require_once $file;
				}
			}
		);
		$loader_registered = true;
	}

	return class_exists('\chillerlan\QRCode\QRCode', true);
}

function resolve_qr_size($size = null, array $payment_data = array()) {
	if (($size === null || $size === '') && isset($payment_data['size'])) {
		$size = $payment_data['size'];
	}

	if ($size === null || $size === '') {
		$size = function_exists(__NAMESPACE__ . '\\get_configured_qr_size')
			? get_configured_qr_size()
			: 320;
	}

	return max(64, min((int) $size, 1000));
}

/**
 * Generate local SEPA QR image output.
 *
 * @param bool $as_base64 True => data URI (base64), false => raw PNG binary.
 */
function get_sepa_qr_image_data(array $payment_data, $size = null, $as_base64 = true) {
	$payload = build_sepa_epc_payload($payment_data);

	if (is_wp_error($payload)) {
		return $payload;
	}

	if (!load_local_qr_library()) {
		return new \WP_Error(
			'woo_qr_pay_missing_library',
			__('Missing local QR library bootstrap. Ensure php-qrcode exists in vendor/php-qrcode.', 'woo-qr-pay')
		);
	}

	if (!extension_loaded('gd')) {
		return new \WP_Error(
			'woo_qr_pay_missing_gd',
			__('GD extension is required for PNG QR output.', 'woo-qr-pay')
		);
	}

	$size = resolve_qr_size($size, $payment_data);
	$scale = max(4, min(12, (int) floor($size / 34)));

	$options = new \chillerlan\QRCode\QROptions(
		array(
			'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
			'eccLevel'        => 'M',
			'addQuietzone'    => false,
			'outputBase64'    => (bool) $as_base64,
			'scale'           => $scale,
		)
	);

	$qrcode = new \chillerlan\QRCode\QRCode($options);

	return $qrcode->render($payload);
}

/**
 * Backward-compatible helper: return base64 image data URI.
 */
function get_sepa_qr_image_url(array $payment_data, $size = null) {
	return get_sepa_qr_image_data($payment_data, $size, true);
}

/**
 * Generate <img> element for SEPA QR payment using base64 data URI.
 */
function get_sepa_qr_image_tag(array $payment_data, $size = null, $alt = '') {
	$url = get_sepa_qr_image_data($payment_data, $size, true);

	if (is_wp_error($url)) {
		return $url;
	}

	$alt_text = $alt !== '' ? $alt : __('SEPA payment QR code', 'woo-qr-pay');
	$size = resolve_qr_size($size, $payment_data);

	$src = (is_string($url) && strpos($url, 'data:image/') === 0)
		? esc_attr($url)
		: esc_url($url);

	return sprintf(
		'<img src="%1$s" width="%2$d" height="%2$d" alt="%3$s" loading="lazy" style="display:block;margin:0 auto;" />',
		$src,
		$size,
		esc_attr($alt_text)
	);
}

/**
 * Generate PAY by square QR image output.
 */
function get_pay_by_square_qr_image_data(array $payment_data, $size = null, $as_base64 = true) {
	$payload = build_pay_by_square_payload($payment_data);

	if (is_wp_error($payload)) {
		return $payload;
	}

	$size = resolve_qr_size($size, $payment_data);

	return render_qr_payload_as_image($payload, $size, $as_base64);
}

/**
 * Internal QR renderer used by multiple payload formats.
 */
function render_qr_payload_as_image($payload, $size = null, $as_base64 = true) {
	if (!load_local_qr_library()) {
		return new \WP_Error(
			'woo_qr_pay_missing_library',
			__('Missing local QR library bootstrap. Ensure php-qrcode exists in vendor/php-qrcode.', 'woo-qr-pay')
		);
	}

	if (!extension_loaded('gd')) {
		return new \WP_Error(
			'woo_qr_pay_missing_gd',
			__('GD extension is required for PNG QR output.', 'woo-qr-pay')
		);
	}

	$size = resolve_qr_size($size);
	$scale = max(4, min(12, (int) floor($size / 34)));

	$options = new \chillerlan\QRCode\QROptions(
		array(
			'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
			'eccLevel'        => 'M',
			'addQuietzone'    => false,
			'outputBase64'    => (bool) $as_base64,
			'scale'           => $scale,
		)
	);

	$qrcode = new \chillerlan\QRCode\QRCode($options);

	return $qrcode->render($payload);
}

function get_pay_by_square_qr_image_url(array $payment_data, $size = null) {
	return get_pay_by_square_qr_image_data($payment_data, $size, true);
}

function get_pay_by_square_qr_image_tag(array $payment_data, $size = null, $alt = '') {
	$url = get_pay_by_square_qr_image_data($payment_data, $size, true);

	if (is_wp_error($url)) {
		return $url;
	}

	$alt_text = $alt !== '' ? $alt : __('PAY by square QR code', 'woo-qr-pay');
	$size = resolve_qr_size($size, $payment_data);

	$src = (is_string($url) && strpos($url, 'data:image/') === 0)
		? esc_attr($url)
		: esc_url($url);

	return sprintf(
		'<img src="%1$s" width="%2$d" height="%2$d" alt="%3$s" loading="lazy" style="display:block;margin:0 auto;" />',
		$src,
		$size,
		esc_attr($alt_text)
	);
}

function get_bacs_account_details() {
	$accounts = get_option('woocommerce_bacs_accounts', array());

	if (!is_array($accounts)) {
		$accounts = array();
	}

	$primary = isset($accounts[0]) && is_array($accounts[0]) ? $accounts[0] : array();

	$recipient = isset($primary['account_name']) ? trim((string) $primary['account_name']) : '';
	$iban = isset($primary['iban']) ? trim((string) $primary['iban']) : '';
	$bic = isset($primary['bic']) ? trim((string) $primary['bic']) : '';

	if ($recipient === '' || $iban === '') {
		$bacs_settings = get_option('woocommerce_bacs_settings', array());

		if (is_array($bacs_settings)) {
			if ($recipient === '' && isset($bacs_settings['title'])) {
				$recipient = trim((string) $bacs_settings['title']);
			}

			if ($iban === '' && isset($bacs_settings['iban'])) {
				$iban = trim((string) $bacs_settings['iban']);
			}

			if ($bic === '' && isset($bacs_settings['bic'])) {
				$bic = trim((string) $bacs_settings['bic']);
			}
		}
	}

	if ($recipient === '' || $iban === '') {
		error_log('[woo-qr-pay] Missing BACS account_name or IBAN in WooCommerce settings.');
		return null;
	}

	return array(
		'recipient' => $recipient,
		'iban'      => $iban,
		'bic'       => $bic,
	);
}

function render_order_qr_on_thankyou($order_id) {
	if (!function_exists('wc_get_order')) {
		error_log('[woo-qr-pay] WooCommerce is not available, cannot render QR on thank-you page.');
		return;
	}

	$order = wc_get_order($order_id);
	if (!$order) {
		error_log('[woo-qr-pay] Order not found, cannot render QR on thank-you page. order_id=' . (string) $order_id);
		return;
	}

	if ($order->get_payment_method() !== 'bacs') {
		error_log('[woo-qr-pay] Skipping QR on thank-you page because payment method is not bacs. order_id=' . (string) $order_id . ', method=' . (string) $order->get_payment_method());
		return;
	}

	$account = get_bacs_account_details();
	if (!$account) {
		error_log('[woo-qr-pay] Missing bank account data, cannot render QR on thank-you page. order_id=' . (string) $order_id);
		return;
	}

	$billing_country = strtoupper((string) $order->get_billing_country());
	$invoice_ref = method_exists($order, 'get_order_number') ? $order->get_order_number() : (string) $order->get_id();
	$amount = (float) $order->get_total();
	$description = '';
	$qr = null;

	if ($billing_country === 'SK') {
		$qr = get_pay_by_square_qr_image_tag(
			array(
				'recipient' => $account['recipient'],
				'iban'      => $account['iban'],
				'swift'     => $account['bic'],
				'amount'    => $amount,
				'vs'        => preg_replace('/\D+/', '', $invoice_ref),
				'note'      => sprintf('Objednavka %s', $invoice_ref),
			),
			null,
			'PAY by square'
		);
		$description = __('Pay by square', 'woo-qr-pay');
	}

	if (is_wp_error($qr) || empty($qr)) {
		$qr = get_sepa_qr_image_tag(
			array(
				'name'      => $account['recipient'],
				'iban'      => $account['iban'],
				'bic'       => $account['bic'],
				'amount'    => $amount,
				'reference' => $invoice_ref,
				'message'   => sprintf('Objednavka %s', $invoice_ref),
			),
			null,
			'SEPA QR code'
		);
		$description = __('SEPA QR payment', 'woo-qr-pay');
	}

	if (is_wp_error($qr) || empty($qr)) {
		error_log('[woo-qr-pay] QR code generation failed on thank-you page. order_id=' . (string) $order_id . ', reason=' . (is_wp_error($qr) ? $qr->get_error_message() : 'No QR code generated'));
		return;
	}

	?>

	<div style="text-align:center;">
		<?php echo $qr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ($description !== '') : ?>
			<p style="margin:8px 0 0;"><?php echo esc_html($description); ?></p>
		<?php endif; ?>
	</div>
		
	<?php
}

function add_qr_to_bacs_account_fields($fields, $order_id) {
	if (!is_array($fields) || !function_exists('wc_get_order')) {
		return $fields;
	}

	$order = wc_get_order($order_id);
	if (!$order) {
		return $fields;
	}

	$iban = isset($fields['iban']['value']) ? trim((string) $fields['iban']['value']) : '';
	if ($iban === '') {
		return $fields;
	}

	$bic = isset($fields['bic']['value']) ? trim((string) $fields['bic']['value']) : '';
	$recipient = '';

	$accounts = get_option('woocommerce_bacs_accounts', array());
	if (is_array($accounts)) {
		$iban_normalized = strtoupper(str_replace(' ', '', $iban));
		foreach ($accounts as $account) {
			if (!is_array($account)) {
				continue;
			}

			$account_iban = isset($account['iban']) ? strtoupper(str_replace(' ', '', (string) $account['iban'])) : '';
			if ($account_iban === $iban_normalized) {
				$recipient = isset($account['account_name']) ? trim((string) $account['account_name']) : '';
				break;
			}
		}
	}

	if ($recipient === '') {
		$recipient = isset($fields['bank_name']['value']) ? trim((string) $fields['bank_name']['value']) : '';
	}
	if ($recipient === '') {
		$recipient = (string) get_bloginfo('name');
	}

	$billing_country = strtoupper((string) $order->get_billing_country());
	$invoice_ref = method_exists($order, 'get_order_number') ? $order->get_order_number() : (string) $order->get_id();
	$amount = (float) $order->get_total();
	$qr_size = function_exists(__NAMESPACE__ . '\\get_configured_qr_size')
		? get_configured_qr_size()
		: 220;
	$qr = null;
	$description = '';

	if ($billing_country === 'SK') {
		$qr = get_pay_by_square_qr_image_tag(
			array(
				'recipient' => $recipient,
				'iban'      => $iban,
				'swift'     => $bic,
				'amount'    => $amount,
				'vs'        => preg_replace('/\D+/', '', $invoice_ref),
				'note'      => sprintf('Objednavka %s', $invoice_ref),
			),
			$qr_size,
			'PAY by square'
		);
		$description = __('Pay by square', 'woo-qr-pay');
	}

	if (is_wp_error($qr) || empty($qr)) {
		$qr = get_sepa_qr_image_tag(
			array(
				'name'      => $recipient,
				'iban'      => $iban,
				'bic'       => $bic,
				'amount'    => $amount,
				'reference' => $invoice_ref,
				'message'   => sprintf('Objednavka %s', $invoice_ref),
			),
			$qr_size,
			'SEPA QR code'
		);
		$description = __('SEPA QR payment', 'woo-qr-pay');
	}

	if (is_wp_error($qr) || empty($qr)) {
		error_log('[woo-qr-pay] QR generation failed in woocommerce_bacs_account_fields. order_id=' . (string) $order_id . ', reason=' . (is_wp_error($qr) ? $qr->get_error_message() : 'No QR code generated'));
		return $fields;
	}

	$fields['qr_code'] = array(
		'label' => __('QR payment', 'woo-qr-pay'),
		'value' => '<span class="woo-qr-pay-bacs-field">' . $qr . '<small class="woo-qr-pay-bacs-caption">' . esc_html($description) . '</small></span>',
	);

	return $fields;
}

function allow_data_protocol_for_kses($protocols) {
	if (!is_array($protocols)) {
		return $protocols;
	}

	if (!in_array('data', $protocols, true)) {
		$protocols[] = 'data';
	}

	return $protocols;
}

function render_bacs_qr_styles() {
	if (!function_exists('is_order_received_page') || !is_order_received_page()) {
		return;
	}
	?>
	<style id="woo-qr-pay-bacs-styles">
		.woocommerce-bacs-bank-details .bacs_details li.qr_code {
			text-align: left;
		}
		.woocommerce-bacs-bank-details .bacs_details li.qr_code strong {
			font-weight: 400;
			text-align: left;
			vertical-align: top;
		}
		.woocommerce-bacs-bank-details .bacs_details li.qr_code .woo-qr-pay-bacs-field {
			display: inline-block;
			text-align: left;
			padding: 8px 0;
		}
		.woocommerce-bacs-bank-details .bacs_details li.qr_code .woo-qr-pay-bacs-field img {
			display: block;
			margin: 0;
		}
		.woocommerce-bacs-bank-details .bacs_details li.qr_code .woo-qr-pay-bacs-caption {
			display: block;
			margin-top: 4px;
			text-align: center;
		}
	</style>
	<?php
}
