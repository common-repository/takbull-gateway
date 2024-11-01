<?php
if (!defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.Files.FileName

/**
 * Abstract class that will be inherited by all payment methods.
 *
 * @extends WC_Payment_Gateway_CC
 *
 * @since 4.0.0
 */
abstract class WC_Takbull_Payment_Gateway extends WC_Payment_Gateway
{

	public $PaymentMethodType = 3;
	public $api_secret;
	public $api_key;
	public $token_enabled;
	public $deal_type;
	public $document_type;
	public $create_document;
	public $display_type;
	public $is_taxtable;
	public $is_product_taxtable;
	public $send_secure_sms;
	public $send_sms;
	/**
	 * The subscription helper.
	 *
	 * @var WC_Takbull_API
	 */
	protected  $api;

	public function generate_custom_payment_range_html(string $field)
	{
		ob_start();

		$field_key = $this->get_field_key($field);
		$ranges = array_filter((array) $this->get_option($field, []));
?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php _e('Custom Payments', 'takbull-gateway'); ?>:</th>
			<td class="forminp" id="wpg_payment_range">
				<div class="wc_input_table_wrapper">
					<table class="widefat wc_input_table sortable" cellspacing="0">
						<thead>
							<tr>
								<th class="sort">&nbsp;</th>
								<th><?php _e('Min Cart', 'takbull-gateway'); ?></th>
								<th><?php _e('Max Cart', 'takbull-gateway'); ?></th>
								<th><?php _e('Min Payments', 'takbull-gateway'); ?></th>
								<th><?php _e('Max Payments', 'takbull-gateway'); ?></th>
							</tr>
						</thead>
						<tbody class="ranges">
							<?php foreach ($ranges as $i => $range) : ?>
								<tr class="range">
									<td class="sort"></td>
									<td>
										<input type="number" value="<?= esc_attr($range['min_cart']); ?>" name="<?= $field_key . '[' . $i . '][min_cart]'; ?>" step="0.1" min="1" required />
									</td>
									<td>
										<input type="number" value="<?= esc_attr($range['max_cart']); ?>" name="<?= $field_key . '[' . $i . '][max_cart]'; ?>" step="0.1" min="1" required />
									</td>
									<td>
										<input type="number" value="<?= esc_attr($range['min_payments']); ?>" name="<?= $field_key . '[' . $i . '][min_payments]'; ?>" step="1" min="1" required />
									</td>
									<td>
										<input type="number" value="<?= esc_attr($range['max_payments']); ?>" name="<?= $field_key . '[' . $i . '][max_payments]'; ?>" step="1" min="1" required />
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="7">
									<a href="#" class="add button"><?php _e('+ Add row', 'takbull-gateway'); ?></a>
									<a href="#" class="remove_rows button"><?php _e('Remove selected row(s)', 'takbull-gateway'); ?></a>
								</th>
							</tr>
						</tfoot>
					</table>
				</div>
				<script type="text/javascript">
					jQuery(function() {
						var $container = jQuery('#wpg_payment_range');

						$container.on('click', 'a.add', function() {
							var size = $container.find('tbody .range').length;
							var field = '<?= $field_key; ?>[' + size + ']';

							jQuery('<tr class="range">\
									<td class="sort"></td>\
									<td><input type="number" name="' + field + '[min_cart]" step="0.1" min="1" required /></td>\
									<td><input type="number" name="' + field + '[max_cart]" step="0.1" min="1" required /></td>\
									<td><input type="number" name="' + field + '[min_payments]" step="1" min="1" required /></td>\
									<td><input type="number" name="' + field + '[max_payments]" step="1" min="1" required /></td>\
								</tr>').appendTo($container.find('tbody'));

							return false;
						});
					});
				</script>
			</td>
		</tr>
	<?php

		return ob_get_clean();
	}

	public function validate_custom_payment_range_field($key, $value)
	{
		$value = (array) $value;

		foreach ($value as $row => $range) {
			if (array_filter($range) !== $range) {
				unset($value[$row]);

				continue;
			}

			if ($range['max_cart'] < $range['min_cart'] || $range['max_payments'] < $range['min_payments']) {
				unset($value[$row]);
			}
		}

		return $value;
	}


	public function generate_payment_fee_html(string $field)
	{
		ob_start();

		$field_key = $this->get_field_key($field);
		// error_log(print_r($field_key,true));
		$ranges = array_filter((array) $this->get_option($field, []));
		// error_log(print_r($ranges, true));
		usort($ranges,function($first,$second){
			// error_log("1".print_r($first, true));
			// error_log("2".print_r($second, true));
			return $first['number_of_payments'] > $second['number_of_payments'];
		});
		// error_log(print_r($ranges, true));
	?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php _e('Payments Fees', 'takbull-gateway'); ?>:</th>
			<td class="forminp" id="takbull_payment_fee">
				<div class="wc_input_table_wrapper">
					<table class="widefat wc_input_table sortable" cellspacing="0">
						<thead>
							<tr>
								<th class="sort">&nbsp;</th>
								<th><?php _e('Number of payments', 'takbull-gateway'); ?></th>
								<th><?php _e('Fee', 'takbull-gateway'); ?></th>
							</tr>
						</thead>
						<tbody class="ranges">
							<?php foreach ($ranges as $i => $range) : ?>
								<tr class="range">
									<td class="sort"></td>
									<td>
										<input type="number" value="<?= esc_attr($range['number_of_payments']); ?>" name="<?= $field_key . '[' . $i . '][number_of_payments]'; ?>" step="1" min="1" required />
									</td>
									<td>
										<input type="number" value="<?= esc_attr($range['fee']); ?>" name="<?= $field_key . '[' . $i . '][fee]'; ?>" step="0.1" min="0" required />
									</td>

								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="7">
									<a href="#" class="add_fee button"><?php _e('+ Add row', 'takbull-gateway'); ?></a>
									<a href="#" class="remove_rows button"><?php _e('Remove selected row(s)', 'takbull-gateway'); ?></a>
								</th>
							</tr>
						</tfoot>
					</table>
					<p><?php _e('Insert fee percentage, for example if customer choose 5 payments apply 2% you should set payments number to 5 and fee to 2.', 'takbull-gateway') ?></p>
				</div>
				<script type="text/javascript">
					jQuery(function() {
						var $container = jQuery('#takbull_payment_fee');

						$container.on('click', 'a.add_fee', function() {
							var size = $container.find('tbody .range').length;
							var field = '<?= $field_key; ?>[' + size + ']';

							jQuery('<tr class="range">\
									<td class="sort"></td>\
									<td><input type="number" name="' + field + '[number_of_payments]" step="1" min="1" required /></td>\
									<td><input type="number" name="' + field + '[fee]" step="0.1" min="0" required /></td>\
								</tr>').appendTo($container.find('tbody'));

							return false;
						});
					});
				</script>
			</td>
		</tr>
<?php

		return ob_get_clean();
	}

	public function validate_payment_fee_field($key, $value)
	{
		$value = (array) $value;

		foreach ($value as $row => $fee) {
			if (array_filter($fee) !== $fee) {
				unset($value[$row]);
				continue;
			}

			if ($fee['number_of_payments'] < 1 || $fee['fee'] < 0) {
				unset($value[$row]);
			}
		}

		return $value;
	}
	/**
	 * Check if we need to make gateways available.
	 *
	 * @since 4.1.3
	 */
	public function is_available()
	{
		if ('yes' === $this->enabled) {
			if (!$this->api_secret || !$this->api_key) {
				return false;
			}
			return true;
		}

		return parent::is_available();
	}

	/**
	 * Gets the locale with normalization that only Takbull accepts.
	 *
	 * @since 4.0.6
	 * @return string $locale
	 */
	public function get_locale()
	{
		$locale = get_locale();

		/*
		 * Takbull expects Norwegian to only be passed NO.
		 * But WP has different dialects.
		 */
		if ('NO' === substr($locale, 3, 2)) {
			$locale = 'no';
		} else {
			$locale = substr(get_locale(), 0, 2);
		}

		return $locale;
	}

	/**
	 * Create the level 3 data array to send to Takbull when making a purchase.
	 *
	 * @param WC_Order $order The order that is being paid for.
	 * @return array          The level 3 data to send to Takbull.
	 */
	public function get_data_from_order($order)
	{
		if (WC_Takbull_Helper::is_wc_lt('3.0')) {
			return array();
		}
		try {
			$order_items = array_values($order->get_items(array('line_item', 'fee')));
			$currency    = $order->get_currency();
			$takbull_line_items = array_map(function ($item) use ($currency, $order) {
				if ('fee' === $item['type']) {
					$unit_cost           = WC_Takbull_Helper::get_takbull_amount($item['line_total'], $currency);
					$quantity            = 1;
				} else {
					$unit_cost           = WC_Takbull_Helper::get_takbull_amount($order->get_item_subtotal($item, true), $currency);
					$quantity            = $item->get_quantity();
					$product = $item->get_product();
					$isTaxtable = (bool)$product->is_taxable();
					$product_id          = $item->get_variation_id()
						? $item->get_variation_id()
						: $item->get_product_id();
					$tax_amount          = WC_Takbull_Helper::get_takbull_amount($item->get_total_tax(), $currency);
					$discount_amount     = WC_Takbull_Helper::get_takbull_amount($item->get_subtotal() - $item->get_total(), $currency);
				}
				$product_description = substr(strip_tags(preg_replace("/&#\d*;/", " ", $item->get_name())), 0, 200);
				$product_description = $this->string_sanitize($product_description);
				// $product_description =	filter_var($item->get_name(), FILTER_SANITIZE_ENCODED);
				$regex = '/( [\x00-\x7F] | [\xC0-\xDF][\x80-\xBF] | [\xE0-\xEF][\x80-\xBF]{2} | [\xF0-\xF7][\x80-\xBF]{3} ) | ./x';
				$product_description = substr(strip_tags(preg_replace($regex, '$1', $product_description)), 0, 250);
				$order_producr = array(
					'ProductSourceId' => (string) $product_id,
					// 'ProductSource' => get_bloginfo(),
					'ProductName' => $item->get_name(), // Up to 26 characters long describing the product.
					'Description' => $product_description, // Up to 26 characters long describing the product.
					'Price'           => $unit_cost, // Cost of the product, in cents, as a non-negative integer.
					'Quantity'            => $quantity, // The number of items of this type sold, as a non-negative integer.
					'TaxAmount'          => $tax_amount, // The amount of tax this item had added to it, in cents, as a non-negative integer.
					'IsTaxteble' => $isTaxtable,
					// 'Discount'     => $discount_amount, // The amount an item was discounted—if there was a sale,for example, as a non-negative integer.
				);
				if ($product) {
					$order_producr['SKU'] = $product->get_sku();
				}
				return $order_producr;
			}, $order_items);
			$customer_name = substr(strip_tags(preg_replace("/&#\d*;/", " ", $order->get_billing_first_name() . " " . $order->get_billing_last_name())), 0, 200);
			$company_name = substr(strip_tags(preg_replace("/&#\d*;/", " ", $order->get_billing_company())), 0, 200);
			if ($this->IsNullOrEmptyString($company_name) == false) {
				$customer_name  =  $company_name;
			}
			if ($this->IsNullOrEmptyString($customer_name)) {
				$name = $this->limit_length($order->get_shipping_first_name(), 32);
				$last =	$this->limit_length($order->get_shipping_last_name(), 64);
				$customer_name = $name . ' ' . $last;
			}
			$email = $order->get_billing_email();
			if ($this->IsNullOrEmptyString($email)) {
				$phone_meta = '_shipping_phone';
				$email_meta = '_shipping_email';
				$email = get_post_meta($order->get_id(), $email_meta, true);
				$phone = get_post_meta($order->get_id(), $phone_meta, true);
			}
			$customer = array(
				'CustomerFullName' => $customer_name,
				'FirstName' => substr(strip_tags(preg_replace("/&#\d*;/", " ", $order->get_billing_first_name())), 0, 200),
				'LastName' => substr(strip_tags(preg_replace("/&#\d*;/", " ", $order->get_billing_last_name())), 0, 200),
				'Email' => $email,
				'PhoneNumber' => substr(strip_tags(preg_replace("/[^A-Za-z0-9]/", '', $order->get_billing_phone())), 0, 15),
				'Address' => array(
					'Address1' => $order->get_billing_address_1(),
					'Address2' => $order->get_billing_address_2(),
					'City' => $order->get_billing_city(),
					'Country' => $order->get_billing_country(),
					'Zip' => $order->get_billing_postcode(),
				)
			);
			$version = 'Woocommerce';
			$total = $this->get_checkout_total($order);
			$level3_data = array(
				'order_reference'   => $order->get_id(), // An alphanumeric string of up to  characters in length. This unique value is assigned by the merchant to identify the order. Also known as an “Order ID”.
				// 'OrderSourceDescription' => $this->string_sanitize(get_bloginfo()),
				'OrderSourceVersion' => $version,
				'IPNAddress' => WC()->api_request_url('wc_gateway_takbull'),
				'RedirectAddress'        => esc_url_raw(add_query_arg('utm_nooverride', '1', $this->get_return_url($order))),
				'CancelReturnAddress' => esc_url_raw($order->get_cancel_order_url_raw()),
				'ShippingAmount'      => WC_Takbull_Helper::get_takbull_amount($order->get_shipping_total() + $order->get_shipping_tax(), $currency), // The shipping cost, in cents, as a non-negative integer.
				'ShippingTaxAmount'	=>	WC_Takbull_Helper::get_takbull_amount($order->get_shipping_tax(), $currency),
				'Products'           => $takbull_line_items,
				'Currency'	=>	$currency,
				'CustomerFullName'	=>	$customer_name,
				'CustomerEmail' => $email,
				'Customer'	=>	$customer,
				'CustomerPhoneNumber'	=>	$order->get_billing_phone(),
				'OrderTotalSum' => WC_Takbull_Helper::get_takbull_amount($order->get_total(), $currency),
				'TaxAmount' => WC_Takbull_Helper::get_takbull_amount($order->get_total_tax(), $currency),
				'Discount' => WC_Takbull_Helper::get_takbull_amount($order->get_total_discount(), $currency),
				'Language' => $this->get_locale(),
				'MaxNumberOfPayments' => $this->get_maximum_payments($total),
				'MinNumberOfPayments' => $this->get_minimum_payments($total),
				'IsMobile' => wp_is_mobile()
			);
			$payments_fees = $this->get_option('payment_fee', []);
			if (!empty($payments_fees)) {
				$level3_data['MaxNumberOfPayments'] = 0;
				$level3_data['MinNumberOfPayments'] = 0;
			}
			if ($order->needs_shipping_address()) {
				$level3_data['ShippingAddress1']   = $this->limit_length($order->get_shipping_address_1(), 100);
				$level3_data['ShippingAddress2']   = $this->limit_length($order->get_shipping_address_2(), 100);
				$level3_data['ShippingCity']       = $this->limit_length($order->get_shipping_city(), 40);
				$level3_data['ShippingCountry']    = $this->limit_length($order->get_shipping_country(), 2);
				$level3_data['ShippingZipCode']        = $this->limit_length(wc_format_postcode($order->get_shipping_postcode(), $order->get_shipping_country()), 32);
			}
			return $level3_data;
		} catch (\Throwable $th) {
			WC_Takbull_Logger::log('order: ' . print_r($level3_data, true));
			WC_Takbull_Logger::log('error: ' . print_r($th, true));
			throw $th;
		}
	}

	public function get_checkout_total($order)
	{
		if ($order) {
			return $order->get_total() + $order->get_total_tax();
		}
		if (WC()->cart && function_exists('get_cart_contents_total'))
			$total = WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax();
		return $total ? (float) $total : 0.0;
	}

	public function get_maximum_payments($total)
	{
		$max_payments = $this->get_option('number_of_payments');
		if ($total > 0) {
			$custom_payment = $this->get_custom_payment($total);
			if ($custom_payment) {
				$max_payments = $custom_payment['max_payments'];
			}
		}
		return absint($max_payments);
	}

	public function get_minimum_payments($total)
	{
		$min_payments = $this->get_option('min_payments', 1);
		if ($total > 0) {
			$custom_payment = $this->get_custom_payment($total);
			if ($custom_payment) {
				$min_payments = $custom_payment['min_payments'];
			}
		}

		return absint($min_payments);
	}

	public function get_custom_payment($total)
	{
		$custom_payments = $this->get_option('custom_payment_range', []);

		return array_reduce($custom_payments, function ($carry, $custom_payment) use ($total) {
			return $custom_payment['min_cart'] <= $total && $total <= $custom_payment['max_cart']
				? $custom_payment
				: $carry;
		});
	}

	function IsNullOrEmptyString($str)
	{
		return (!isset($str) || trim($str) === '');
	}

	protected function limit_length($string, $limit = 127)
	{
		$str_limit = $limit - 3;
		if (function_exists('mb_strimwidth')) {
			if (mb_strlen($string) > $limit) {
				$string = mb_strimwidth($string, 0, $str_limit) . '...';
			}
		} else {
			if (strlen($string) > $limit) {
				$string = substr($string, 0, $str_limit) . '...';
			}
		}
		return $string;
	}
	/**
	 * Verifies whether a certain ZIP code is valid for the US, incl. 4-digit extensions.
	 *
	 * @param string $zip The ZIP code to verify.
	 * @return boolean
	 */
	public function is_valid_us_zip_code($zip)
	{
		return !empty($zip) && preg_match('/^\d{5,5}(-\d{4,4})?$/', $zip);
	}

	/**
	 * Get the return url (thank you page).
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_return_url($order = null)
	{
		if ($order) {
			$return_url = $order->get_checkout_order_received_url();
		} else {
			$return_url = wc_get_endpoint_url('order-received', '', wc_get_checkout_url());
		}

		return apply_filters('woocommerce_get_return_url', $return_url, $order);
	}


	public function init_data_to_send($order)
	{
		$taxtable = true;
		if ($this->is_taxtable == 'onlyforisreal') {
			// Get an instance of the WC_Geolocation object class
			$geo_instance  = new WC_Geolocation();
			// Get geolocated user geo data.
			$user_geodata = $geo_instance->geolocate_ip();
			WC_Takbull_Logger::log("user_geodata: " . json_encode($user_geodata));
			// Get current user GeoIP Country
			$country = $user_geodata['country'];
			// WC_Takbull_Logger::log("Customer counrty: " . $country);
			$shipping_address = $order->get_shipping_country();
			$ordercountry = $order->get_billing_country();
			if ($shipping_address == 'IL') {
				$taxtable = true;
			} else {
				if (empty($shipping_address) && $ordercountry == 'IL') {
					$taxtable = true;
				} else {
					if ($country != 'IL') {
						$taxtable = false;
					} else {
						$taxtable = true;
					}
				}
			}
		} else {
			$taxtable = $this->is_taxtable == 'yes';
		}
		$data = $this->get_data_from_order($order);
		$data['CreateDocument'] = $this->create_document;
		$data['SendSmsInvioce'] = $this->send_sms == 'yes';
		$data['DocumentType'] = $this->document_type;
		$data['DealType'] = $this->deal_type;
		$payments_fees = $this->get_option('payment_fee', []);
		if ($this->deal_type == 6 || $this->deal_type == 7 || !empty($payments_fees)) {
			$totalPayments =	$order->get_meta('_wc_takbull_total_payment') ?? 1;
			if (!$totalPayments) {
			} else
				$data['NumberOfPayments'] = $totalPayments;
		}
		$data['Taxtable'] = $taxtable;
		$data['SaveToken'] = (bool) get_post_meta($order->get_id(), '_wc_takbull_tokenize_payment', true);
		$data['DisplayType'] = $this->display_type;
		$data['IsProductTaxtable'] = $this->is_product_taxtable;
		$data['PaymentMethodType'] = $this->PaymentMethodType;
		return $data;
	}
	/**
	 * Get the PayPal request URL for an order.
	 *
	 * @param  WC_Order $order Order object.
	 * @param  bool     $sandbox Whether to use sandbox mode or not.
	 * @return string
	 */
	public function get_request_url($order)
	{
		$data = $this->init_data_to_send($order);
		$data = apply_filters('woocommerce_takbull_order_args', $data, $order);
		WC_Takbull_Logger::log("get_request_url:: " . json_encode($data, JSON_NUMERIC_CHECK));
		$response = $this->api->request(json_encode($data, JSON_NUMERIC_CHECK), "api/ExtranalAPI/GetTakbullPaymentPageRedirectUrl");
		if (!empty($response->error)) {
			throw new WC_Takbull_Exception(print_r($response, true), $response->error->message);
		}
		$body = wp_remote_retrieve_body($response);
		return $body;
	}

	function string_sanitize($s)
	{
		$result = htmlentities($s);
		$result = preg_replace('/^(&quot;)(.*)(&quot;)$/', "$2", $result);
		$result = preg_replace('/^(&laquo;)(.*)(&raquo;)$/', "$2", $result);
		$result = preg_replace('/^(&#8220;)(.*)(&#8221;)$/', "$2", $result);
		$result = preg_replace('/^(&#39;)(.*)(&#39;)$/', "$2", $result);
		$result = html_entity_decode($result);
		// WC_Takbull_Logger::log("senitized string:: " . $result);
		return $result;
	}
}
