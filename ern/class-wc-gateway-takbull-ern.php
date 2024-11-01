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
class WC_Gateway_Takbull_Ern extends WC_Takbull_Payment_Gateway
{
	private $takbull_gateway;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->id             = 'takbull_ern';
		$this->method_title   = __('Bank wire transfer', 'takbull-gateway');
		/* translators: 1) link to Takbull register page 2) link to Takbull api keys page */
		$this->method_description = sprintf(__('Please pay attantion for this method fully functioning Takbull Credit card payment method should b.'));
		$this->has_fields         = false;
		$this->supports   = ['products'];
		// Load the form fields.
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();
		$this->title       = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->PaymentMethodType = 31;
		$this->takbull_gateway = new WC_Gateway_Takbull_Settings();
		$this->api_secret           = $this->get_option('api_secret');
		$this->api_key      = $this->get_option('api_key');
		$this->create_document      =  'yes' === $this->takbull_gateway->create_document;
		$this->is_taxtable      =  $this->takbull_gateway->is_taxtable;
		$this->is_product_taxtable      =  'yes' === $this->takbull_gateway->is_product_taxtable;
		$this->deal_type =  1;
		$this->display_type = $this->get_option('display_type');
		$this->document_type = $this->takbull_gateway->document_type;
		$this->api = new WC_Takbull_API($this->api_secret, $this->api_key);
		// Get setting values.	
		add_query_arg('wc-api', 'WC_Gateway_Takbull_Ern', home_url('/'));
		add_action('woocommerce_api_wc_gateway_takbull_ern', array($this, 'return_handler'));
		add_filter('woocommerce_takbull_order_args', array($this, 'modify_takbull_args'), 10, 2);
		// Hooks.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page_ern'));
	}


	function modify_takbull_args($args, $order)
	{
		if ($order->get_payment_method() == 'takbull_ern')
			$args['IPNAddress'] = WC()->api_request_url('WC_Gateway_Takbull_Ern');
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
		$resp = $this->get_request_url($order);
		if (!isset($resp->responseCode) || $resp->responseCode != 0) {
			wc_add_notice(__('Payment error:', 'woothemes') . $resp->description, 'error');
			return;
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
		$this->form_fields = apply_filters(
			'wc_takbull_ern_settings',
			array(
				'enabled'                       => array(
					'title'       => __('Enable/Disable', 'takbull-gateway'),
					'label'       => __('Enable Takbull', 'takbull-gateway'),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'                         => array(
					'title'       => __('Title', 'takbull-gateway'),
					'type'        => 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'takbull-gateway'),
					'default'     => __('Banl wire transfer', 'takbull-gateway'),
					'desc_tip'    => true,
				),
				'description'                   => array(
					'title'       => __('Description', 'takbull-gateway'),
					'type'        => 'text',
					'description' => __('This controls the description which the user sees during checkout.', 'takbull-gateway'),
					'default'     => __('Banl wire transfer.', 'takbull-gateway'),
					'desc_tip'    => true,
				),
				// 'display_type'   => array(
				// 	'title'       => __('Display Type', 'takbull-gateway'),
				// 	'label'       => __('Display Type', 'takbull-gateway'),
				// 	'type'        => 'select',
				// 	'description' => __('Select the way you want Payment gateway will be displied.', 'takbull-gateway'),
				// 	'default'     => 'iframe',
				// 	'desc_tip'    => true,
				// 	'options'     => array(
				// 		'iframe' => __('Iframe', 'takbull-gateway'),
				// 		'redirect'     => __('Redirect', 'takbull-gateway'),
				// 		// 'checkout_page'  => __( 'In Checkout', 'takbull-gateway' ),
				// 	),
				// ),
				'api_key'               => array(
					'title'       => __('API Key', 'takbull-gateway'),
					'type'        => 'text',
					'description' => __('Get your API keys from your takbull account.', 'takbull-gateway'),
					'default'     => '95ed9b8d-8702-4c54-9dd8-b9242c7e3427',
					'desc_tip'    => true,
				),
				'api_secret'                    => array(
					'title'       => __('API Secret', 'takbull-gateway'),
					'type'        => 'password',
					'description' => __('Get your API keys from your takbull account.', 'takbull-gateway'),
					'default'     => '488447db-0503-4fc8-9ed0-4e1d62ee0114',
					'desc_tip'    => true,
				),
			)
		);
	}

	public function get_icon()
	{
		$icon =  '<img src="' . WC_TAKBULL_PLUGIN_URL . '/includes/assets/images/logo_ern.png" class="takbull-ern-icon" style="max-height: 2.4em;" alt="takbull-ern" />';
		return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
	}



	/**
	 * Output redirect or iFrame form on receipt page
	 *
	 * @access public
	 *
	 * @param $order_id
	 */
	public function receipt_page_ern($order_id)
	{
		$order = wc_get_order($order_id);
		wp_register_script('wc-takbull-iframe', plugins_url('/includes/assets/js/takbull-iframe-handle.js', dirname(__FILE__)), array('jquery'), false, true);
		wp_enqueue_script('wc-takbull-iframe');
		echo $this->generate_iframe_form_html_ern($order);
	}

	public function is_available()
	{
		return $this->enabled === 'yes';
	}
}
