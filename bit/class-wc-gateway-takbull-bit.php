<?php
if (!defined('ABSPATH')) {
	exit;
}

use Takbull\Admin\WC_Gateway_Takbull_Settings;

/**
 * Gateway Takbull  class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Takbull_Bit extends WC_Takbull_Payment_Gateway
{
	private $takbull_gateway;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->id             = 'takbull_bit';
		$this->method_title   = __('Takbull Bit', 'takbull-gateway');
		/* translators: 1) link to Takbull register page 2) link to Takbull api keys page */
		$this->method_description = sprintf(__('Please pay attantion for this method fully functioning Takbull Credit card payment method should.'));
		$this->has_fields         = false;
		$this->supports   = ['products'];
		// Load the form fields.
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();
		$this->title       = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->PaymentMethodType = 21;
		$this->takbull_gateway = new WC_Gateway_Takbull_Settings();
		$this->api_secret           = $this->get_option('api_secret');
		$this->api_key      = $this->get_option('api_key');
		$this->create_document      =  'yes' === $this->takbull_gateway->create_document;
		$this->send_sms      =  'yes' === $this->takbull_gateway->send_sms;
		$this->is_taxtable      =  $this->takbull_gateway->is_taxtable;
		$this->is_product_taxtable      =  'yes' === $this->takbull_gateway->is_product_taxtable;
		$this->deal_type =  1;
		$this->display_type = $this->get_option('display_type');
		$this->document_type = $this->takbull_gateway->document_type;
		$this->api = new WC_Takbull_API($this->api_secret, $this->api_key);
		// Get setting values.	
		add_query_arg('wc-api', 'WC_Gateway_Takbull_Bit', home_url('/'));
		add_action('woocommerce_api_wc_gateway_takbull_bit', array($this, 'return_handler'));
		add_filter('woocommerce_takbull_order_args', array($this, 'modify_takbull_args'), 10, 2);
		// Hooks.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page_bit'));
	}


	function modify_takbull_args($args, $order)
	{
		if ($order->get_payment_method() == 'takbull_bit')
			$args['IPNAddress'] = WC()->api_request_url('WC_Gateway_Takbull_Bit');
		return $args;
	}

	/**
	 * Process payment request via gateway.
	 *
	 * @param int $order_id Current order id.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);
		if ($this->display_type == 'iframe' && !wp_is_mobile()) {
			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url(true),
			);
		}
		$resp = $this->get_request_url($order);
		if (!isset($resp->responseCode) || $resp->responseCode != 0) {
			wc_add_notice(__('Payment error:', 'woothemes') . $resp->description, 'error');
			return;
		}
		WC_Takbull_Logger::log('Process BIT :: '.json_encode($resp));
		if (wp_is_mobile()) {
			return array(
				'result'   => 'success',
				'redirect' =>  $resp->url,
			);
		}
		$this->endpoint =   $this->api->get_redirecr_order_api() . "?orderUniqId=" . $resp->uniqId;
		return array(
			'result'   => 'success',
			'redirect' => $this->endpoint,
		);
	}

	function return_handler()
	{
		if (!empty($_GET)) {

			$orderId = wp_unslash($_GET['order_reference']); // WPCS: CSRF ok, input var ok.
			$tranService = new WC_Takbull_Transaction(wp_unslash($_GET['transactionInternalNumber']), $orderId);
			$tranService->processValidation();
			exit;
		}
		wp_die('Takbull IPN Request Failure', 'TAKBULL IPN', array('response' => 500));
	}
	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields()
	{
		$this->form_fields = require(dirname(__FILE__) . '/admin/takbull-bit-settings.php');
	}

	public function get_icon()
	{
		$icon =  '<img src="' . WC_TAKBULL_PLUGIN_URL . '/includes/assets/images/logo_bit.png" class="takbull-bit-icon" style="max-height: 2.4em;" alt="takbull-bit" />';
		return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
	}



	/**
	 * Output redirect or iFrame form on receipt page
	 *
	 * @access public
	 *
	 * @param $order_id
	 */
	public function receipt_page_bit($order_id)
	{
		$order = wc_get_order($order_id);
		wp_register_script('wc-takbull-iframe', plugins_url('/includes/assets/js/takbull-iframe-handle.js', dirname(__FILE__)), array('jquery'), false, true);
		wp_enqueue_script('wc-takbull-iframe');
		echo $this->generate_iframe_form_html_bit($order);
	}

	function generate_iframe_form_html_bit($order)
	{
		$html         = '';
		$resp = $this->get_request_url($order);
		if (!isset($resp->responseCode) || $resp->responseCode != 0) {
			wc_add_notice(__('Payment error:', 'woothemes') . $resp->description, 'error');
			$html .= '<div class="wc_takbull_iframe_error" id="error">' . PHP_EOL;
			$html .= '<h3>' . $resp->description . '</h3>' . PHP_EOL;
			$html .= '</div>' . PHP_EOL;
			return $html;
		}
		$url =   $this->api->get_redirecr_order_api() . "?orderUniqId=" . $resp->uniqId;
		$html .= '<div class="wc_takbull_iframe_form_detail takbull-iframe-loader" id="wc_takbull_iframe_payment_container" style="border: 0; max-width:540px; max-height: 1050px;">' . PHP_EOL;
		$html .= '<iframe id="wc_takbull_iframe" name="wc_takbull_iframe" width="540px" height="978px" style="border: 0;" src="' . $url . '"></iframe>' . PHP_EOL;
		$html .= '</div>' . PHP_EOL;

		$html .= '<div id="wc_takbull_iframe_buttons">' . PHP_EOL;

		// cancel is hidden for token payments, retry for all payments - enabling happens on payment fail
		$html .= '<a href="' . esc_url($order->get_cancel_order_url()) . '" id="wc_takbull_iframe_cancel" class="cancel">'
			. apply_filters('wc_takbull_iframe_cancel', __('Cancel order', 'takbull-gateway')) . '</a> ';
		$html .= '<a href="' . esc_url(wc_get_checkout_url()) . '" id="wc_takbull_iframe_retry" class="button alt" style="display: none;">'
			. apply_filters('wc_takbull_iframe_retry', __('Try paying again', 'takbull-gateway')) . '</a>' . PHP_EOL;
		$html .= '</div>' . PHP_EOL;
		// $formstring = '<iframe width="100%" height="1000" frameborder="0" src="'.$url.'" ></iframe>';
		return $html;
	}

	public function is_available()
	{
		return $this->enabled === 'yes';
	}
}
