<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Log all things!
 *
 * @since 4.0.0
 * @version 4.0.0
 */
class WC_Takbull_Token
{

	public static $takbull_token;
	private static $token_enabled = false;
	/**
	 * Set secret API Key.
	 * @param string $key
	 */
	public static function set_enable($token_enabled)
	{
		self::$token_enabled = $token_enabled;
	}


	public static function get_token_type_fullname($type)
	{

		$cc_types = array(
			'visa'        => "Visa",
			'master_card' => "MasterCard",
		);

		$fullname = isset($cc_types[$type]) ? $cc_types[$type] : '';

		return $fullname;
	}



	/**
	 * Maybe add new token to user - when processing callback
	 *
	 * @param WC_order $order
	 */
	public static function maybe_add_token($order, $validationData)
	{
		$token = get_post_meta($order->get_id(), '_wc_takbull_token', true);

		try {
			if (empty($token) || ($token === 'add_new')) {
				WC_Takbull_Logger::log('maybe_add_token :::: true');
				if (
					($validationData->saveToken == true || $validationData->dealType == 6) &&
					!empty($validationData->token) && !empty($validationData->last4Digits)
				) {
					$cardType = '';
					switch ($validationData->cardtype) {
						case 'VI':
							$cardType = 'visa';
							break;
						case 'MA':
							$cardType = 'mastercard';
							break;
						case '':
							$cardType = 'visa';
							break;
						default:
							$cardType = $validationData->cardtype;
							break;
					}
					if (0 != $order->get_user_id()) {
						$customer_id = $order->get_user_id();
					} else {
						$customer_id = get_current_user_id();
					}

					$token = new WC_Payment_Token_CC();
					$token->set_gateway_id('takbull');
					$token->set_token($validationData->token);
					$token->set_last4($validationData->last4Digits);
					$token->set_expiry_month(date('m'));
					$token->set_expiry_year(date('Y', strtotime('+20 year')));
					$token->set_card_type($cardType);
					$token->set_user_id($customer_id);
					if ($token->validate()) {
						$save_result = $token->save();
						if ($save_result) {
							$order->add_payment_token($token);
							if (class_exists('WC_Subscriptions') && function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) {
								$subscriptions = wcs_get_subscriptions_for_order($order->get_id());

								foreach ($subscriptions as $subscription) {
									// Attach the token to the subscription
									$subscription->add_payment_token($token);
								}
							}
						}
					} else {
						WC_Takbull_Logger::log('maybe_add_token ::::failed');
						$order->add_order_note('ERROR MESSAGE: ' . __('Invalid or missing payment token fields.', 'woocommerce'));
					}
				}
			}
		} catch (\Throwable $th) {
			WC_Takbull_Logger::log('maybe_add_token exception: ' . print_r($th, true));
		}
	}
}
