<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Provides static methods as helpers.
 *
 * @since 4.0.0
 */
class WC_Takbull_Helper
{
	const LEGACY_META_NAME_FEE      = 'Takbull Fee';
	const LEGACY_META_NAME_NET      = 'Net Revenue From Takbull';
	const META_NAME_FEE             = '_takbull_fee';
	const META_NAME_NET             = '_takbull_net';
	const META_NAME_TAKBULL_CURRENCY = '_takbull_currency';

	/**
	 * Gets the Takbull currency for order.
	 *
	 * @since 4.1.0
	 * @param object $order
	 * @return string $currency
	 */
	public static function get_takbull_currency($order = null)
	{
		if (is_null($order)) {
			return false;
		}

		$order_id = WC_Takbull_Helper::is_wc_lt('3.0') ? $order->id : $order->get_id();

		return WC_Takbull_Helper::is_wc_lt('3.0') ? get_post_meta($order_id, self::META_NAME_TAKBULL_CURRENCY, true) : $order->get_meta(self::META_NAME_TAKBULL_CURRENCY, true);
	}

	public static function get_takbull_amount($total, $currency = '')
	{
		if (!$currency) {
			$currency = get_woocommerce_currency();
		}
		return number_format((float) $total, 2, '.', '');
		// return absint(wc_format_decimal(((float) $total), wc_get_price_decimals())); // In cents.
	}
	

	/**
	 * Localize Takbull messages based on code
	 *
	 * @since 3.0.6
	 * @version 3.0.6
	 * @return array
	 */
	public static function get_localized_messages()
	{
		return apply_filters(
			'wc_takbull_localized_messages',
			array(
				'invalid_number'           => __('The card number is not a valid credit card number.', 'takbull-gateway'),
				'invalid_expiry_month'     => __('The card\'s expiration month is invalid.', 'takbull-gateway'),
				'invalid_expiry_year'      => __('The card\'s expiration year is invalid.', 'takbull-gateway'),
				'invalid_cvc'              => __('The card\'s security code is invalid.', 'takbull-gateway'),
				'incorrect_number'         => __('The card number is incorrect.', 'takbull-gateway'),
				'incomplete_number'        => __('The card number is incomplete.', 'takbull-gateway'),
				'incomplete_cvc'           => __('The card\'s security code is incomplete.', 'takbull-gateway'),
				'incomplete_expiry'        => __('The card\'s expiration date is incomplete.', 'takbull-gateway'),
				'expired_card'             => __('The card has expired.', 'takbull-gateway'),
				'incorrect_cvc'            => __('The card\'s security code is incorrect.', 'takbull-gateway'),
				'incorrect_zip'            => __('The card\'s zip code failed validation.', 'takbull-gateway'),
				'invalid_expiry_year_past' => __('The card\'s expiration year is in the past', 'takbull-gateway'),
				'card_declined'            => __('The card was declined.', 'takbull-gateway'),
				'missing'                  => __('There is no card on a customer that is being charged.', 'takbull-gateway'),
				'processing_error'         => __('An error occurred while processing the card.', 'takbull-gateway'),
				'invalid_request_error'    => __('Unable to process this payment, please try again or use alternative method.', 'takbull-gateway'),
				'invalid_sofort_country'   => __('The billing country is not accepted by SOFORT. Please try another country.', 'takbull-gateway'),
				'email_invalid'            => __('Invalid email address, please correct and try again.', 'takbull-gateway'),
			)
		);
	}

	/**
	 * List of currencies supported by Takbull that has no decimals
	 * https://takbull.com/docs/currencies#zero-decimal from https://takbull.com/docs/currencies#presentment-currencies
	 *
	 * @return array $currencies
	 */
	public static function no_decimal_currencies()
	{
		return array(
			'bif', // Burundian Franc
			'clp', // Chilean Peso
			'djf', // Djiboutian Franc
			'gnf', // Guinean Franc
			'jpy', // Japanese Yen
			'kmf', // Comorian Franc
			'krw', // South Korean Won
			'mga', // Malagasy Ariary
			'pyg', // Paraguayan Guaraní
			'rwf', // Rwandan Franc
			'ugx', // Ugandan Shilling
			'vnd', // Vietnamese Đồng
			'vuv', // Vanuatu Vatu
			'xaf', // Central African Cfa Franc
			'xof', // West African Cfa Franc
			'xpf', // Cfp Franc
		);
	}

	/**
	 * Takbull uses smallest denomination in currencies such as cents.
	 * We need to format the returned currency from Takbull into human readable form.
	 * The amount is not used in any calculations so returning string is sufficient.
	 *
	 * @param object $balance_transaction
	 * @param string $type Type of number to format
	 * @return string
	 */
	public static function format_balance_fee($balance_transaction, $type = 'fee')
	{
		if (!is_object($balance_transaction)) {
			return;
		}

		if (in_array(strtolower($balance_transaction->currency), self::no_decimal_currencies())) {
			if ('fee' === $type) {
				return $balance_transaction->fee;
			}

			return $balance_transaction->net;
		}

		if ('fee' === $type) {
			return number_format($balance_transaction->fee / 100, 2, '.', '');
		}

		return number_format($balance_transaction->net / 100, 2, '.', '');
	}


	/**
	 * Gets all the saved setting options from a specific method.
	 * If specific setting is passed, only return that.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $method The payment method to get the settings from.
	 * @param string $setting The name of the setting to get.
	 */
	public static function get_settings($method = null, $setting = null)
	{
		$all_settings = null === $method ? get_option('woocommerce_takbull_settings', array()) : get_option('woocommerce_takbull_' . $method . '_settings', array());

		if (null === $setting) {
			return $all_settings;
		}

		return isset($all_settings[$setting]) ? $all_settings[$setting] : '';
	}


	/**
	 * Checks if WC version is less than passed in version.
	 *
	 * @since 4.1.11
	 * @param string $version Version to check against.
	 * @return bool
	 */
	public static function is_wc_lt($version)
	{
		return version_compare(WC_VERSION, $version, '<');
	}

	/**
	 * Gets the webhook URL for Takbull triggers. Used mainly for
	 * asyncronous redirect payment methods in which statuses are
	 * not immediately chargeable.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public static function get_webhook_url()
	{
		return add_query_arg('wc-api', 'wc_takbull', trailingslashit(get_home_url()));
	}

	/**
	 * Gets the order by Takbull source ID.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $source_id
	 */
	public static function get_order_by_source_id($source_id)
	{
		global $wpdb;

		$order_id = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $source_id, '_takbull_source_id'));

		if (!empty($order_id)) {
			return wc_get_order($order_id);
		}

		return false;
	}

	/**
	 * Gets the order by Takbull charge ID.
	 *
	 * @since 4.0.0
	 * @since 4.1.16 Return false if charge_id is empty.
	 * @param string $charge_id
	 */
	public static function get_order_by_charge_id($charge_id)
	{
		global $wpdb;

		if (empty($charge_id)) {
			return false;
		}

		$order_id = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $charge_id, '_transaction_id'));

		if (!empty($order_id)) {
			return wc_get_order($order_id);
		}

		return false;
	}



	/**
	 * Sanitize statement descriptor text.
	 *
	 * Takbull requires max of 22 characters and no
	 * special characters with ><"'.
	 *
	 * @since 4.0.0
	 * @param string $statement_descriptor
	 * @return string $statement_descriptor Sanitized statement descriptor
	 */
	public static function clean_statement_descriptor($statement_descriptor = '')
	{
		$disallowed_characters = array('<', '>', '"', "'");

		// Remove special characters.
		$statement_descriptor = str_replace($disallowed_characters, '', $statement_descriptor);

		$statement_descriptor = substr(trim($statement_descriptor), 0, 22);

		return $statement_descriptor;
	}


	public static function GetClientIP()
	{
		if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
		{
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
		{
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}
}
