<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Gateway Takbull  class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Takbull extends WC_Takbull_Payment_Gateway
{
	/**
	 * create invoice for third party gateways
	 *
	 * @var string
	 */
	public $third_party_gateway_create_invoice;

	/**
	 * The subscription helper.
	 *
	 * @var WC_Takbull_API
	 */
	public  $api;


	private static $instance = [];
	public static function getInstance()
	{
		$cls = static::class;
		if (!isset(self::$instance[$cls])) {
			self::$instance[$cls] = new static();
		}

		return self::$instance[$cls];
	}
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->id             = 'takbull';
		$this->method_title   = __('Takbull', 'takbull-gateway');
		/* translators: 1) link to Takbull register page 2) link to Takbull api keys page */
		$this->method_description = sprintf(__('Takbull works by adding payment fields on the checkout and then sending the details to Takbull for verification. <a href="%1$s" target="_blank">Sign up</a> for a Takbull account, and <a href="%2$s" target="_blank">get your Takbull account keys</a>.', 'takbull-gateway'), 'https://auth.takbull.co.il/register', 'https://app.takbull.co.il');
		$this->has_fields         = false;
		$this->supports           = array(
			'products',
			'refunds',
			'tokenization',
			'add_payment_method',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                = $this->get_option('title');
		$this->description          = $this->get_option('description');
		$this->enabled              = $this->get_option('enabled');
		$this->api_secret           = $this->get_option('api_secret');
		$this->api_key      		= $this->get_option('api_key');
		$this->send_sms      		= $this->get_option('send_sms');
		$this->token_enabled      	= 'yes' === $this->get_option('token_enabled', 'yes');
		$this->third_party_gateway_create_invoice      = 'yes' === $this->get_option('third_party_gateway_create_invoice');
		$this->create_document      =  'yes' === $this->get_option('create_document', 'yes');
		$this->is_taxtable      	=  $this->get_option('is_taxtable', 'yes');
		$this->is_product_taxtable      =  'yes' === $this->get_option('is_product_taxtable', 'yes');
		$this->deal_type =  $this->get_option('deal_type');
		$this->display_type = $this->get_option('display_type');
		$this->document_type = $this->get_option('document_type');
		$this->send_secure_sms      =  'yes' === $this->get_option('send_secure_sms', 'yes');
		$this->api = new WC_Takbull_API($this->api_secret, $this->api_key);
		WC_Takbull_Token::set_enable($this->token_enabled);
		// Hooks.
		add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page_takbull'));
		add_action('woocommerce_api_wc_gateway_takbull', array($this, 'return_handler'));
		add_action('woocommerce_api_cartflows_takbull', array($this, 'maybe_handle_takbull_api_call'));
		add_action('wp_ajax_nopriv_takbull_get_redirect_url', array(
			$this,
			'takbull_get_redirect_url'
		));
		add_action('wp_ajax_takbull_get_redirect_url', array(
			$this,
			'takbull_get_redirect_url'
		));

		add_action('woocommerce_thankyou_takbull', [$this, 'thankyou_page_takbull']);
		if ($this->third_party_gateway_create_invoice) {
			// add_action('woocommerce_update_order', array($this, 'action_woocommerce_order_status_processing'), 10, 1);
			add_action('woocommerce_order_status_completed', array($this, 'action_woocommerce_order_status_processing'), 10, 1);
			add_action('valid-paypal-standard-ipn-request', array($this, 'process_invoice_for_paypal'), 10, 1);
			add_action('woocommerce_api_wc_gateway_paypal', array($this, 'process_invoice_for_paypal'), 10, 1);
			add_action('woocommerce_thankyou_paypal', array($this, 'check_response'));
			add_action('woocommerce_paypal_express_checkout_valid_ipn_request', array($this, 'process_invoice_for_paypal'), 10, 1);
		}
	}

	function action_woocommerce_order_status_processing($order)
	{
		$order = wc_get_order($order);
		WC_Takbull_Logger::log('takbull invoice hook kicked order status: ' . $order->data['status']);
		if ($order->has_status('processing') || $order->has_status('completed')) {
			//update user when status is changed to processing
			if (strpos($order->get_payment_method(), 'takbull') === false) {
				$invoice = new Takbull_Invoice(
					$this->api,
					$this->is_taxtable,
					$this->is_product_taxtable,
					$this->document_type
				);
				$invoice->ProcessInvoice($order);
			}
		}
	}

	function process_invoice_for_paypal($post)
	{
		WC_Takbull_Logger::log('takbull-paypal', '$_POST: ' . json_encode($post));
		$takbull_class = new WC_Gateway_Takbull();

		/* Filter out other PayPal Payments */
		$json_custom_fields = json_decode($post['custom']);

		if (!isset($json_custom_fields->order_key) || (strpos($json_custom_fields->order_key, 'wc_order') === false)) {
			WC_Takbull_Logger::log('paypal', 'EXIT: NO order_key');
			return false; # This order is not from WooCommerce
		}


		$order_id = $json_custom_fields->order_id;
		$order_key = $json_custom_fields->order_key;
		$received_values = stripslashes_deep($post);
		$order = wc_get_order($order_id);
		if ($order->get_payment_method() == 'ppec_paypal') {
			$post['payment_status'] = strtolower($post['payment_status']);
			// Sandbox fix.
			if ((empty($post['pending_reason']) || 'authorization' !== $post['pending_reason']) && isset($post['test_ipn']) && 1 == $post['test_ipn'] && 	'pending	' == $post['payment_status']) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				$post['payment_status'] = 'completed';
			}
			if ($post['payment_status'] != 'completed') {
				return;
			}
		}
		if (!$order) {
			// We have an invalid $order_id, probably because invoice_prefix has changed.
			$order_id = wc_get_order_id_by_order_key($order_key);
			$order = wc_get_order($order_id);
		}
		$invoice = new Takbull_Invoice(
			$this->api,
			$takbull_class->is_taxtable,
			$takbull_class->is_product_taxtable,
			$this->document_type
		);
		$invoice->ProcessInvoice($order);
	}

	public function check_response()
	{
		if (empty($_REQUEST['cm']) || empty($_REQUEST['tx']) || empty($_REQUEST['st'])) { // WPCS: Input var ok, CSRF ok, sanitization ok.
			return;
		}
		$takbull_class = new WC_Gateway_Takbull();
		$order_id    = wc_clean(wp_unslash($_REQUEST['cm'])); // WPCS: input var ok, CSRF ok, sanitization ok.
		$status      = wc_clean(strtolower(wp_unslash($_REQUEST['st']))); // WPCS: input var ok, CSRF ok, sanitization ok.
		$order = wc_get_order($order_id);
		if (!$order) {
			return false;
		}
		WC_Takbull_Logger::log('Takbull PDT Transaction Status: ' . wc_print_r($status, true));
		if ('completed' === $status) {
			$invoice = new Takbull_Invoice(
				$this->api,
				$takbull_class->is_taxtable,
				$takbull_class->is_product_taxtable,
				$this->document_type
			);
			$invoice->ProcessInvoice($order);
		}
	}

	function thankyou_page_takbull($order_id)
	{
		try {
			WC_Takbull_Logger::log('thankyou_page_takbull HIT ::: ' . print_r($_GET, true));
			$order = wc_get_order($order_id);
			$order_status = $order->get_meta(WC_TAKBULL_META_KEY . '_order_status');
			if ($order_status == OrderStatus::Complite) {
				WC_Takbull_Logger::log('thankyou_page_takbull order_status == OrderStatus::Complite ::: ');
				exit;
			} else {
				if (!empty($_GET)) {
					$tranService = new WC_Takbull_Transaction(wp_unslash($_GET['transactionInternalNumber']), $order_id);
					$tranService->processValidation();
					exit;
				}
			}
		} catch (Exception $e) {
			WC_Takbull_Logger::log('thankyou_page_takbull HIT ERROR::: ' .  $e->getMessage());
			error_log('Takbull :: thankyou_page_takbull' . print_r($_GET, true));
		}
	}

	function return_handler()
	{
		error_log('Takbull :: return_handler' . print_r($_GET, true));
		try {
			WC_Takbull_Logger::log('return_handler HIT ::: ' . print_r($_GET, true));
			if (!empty($_GET)) {

				$orderId = wp_unslash($_GET['order_reference']); // WPCS: CSRF ok, input var ok.
				$tranService = new WC_Takbull_Transaction(wp_unslash($_GET['transactionInternalNumber']), $orderId);
				$tranService->processValidation();
				exit;
			}
		} catch (Exception $e) {
			WC_Takbull_Logger::log('return_handler HIT ERROR::: ' .  $e->getMessage());
			error_log('Takbull :: return_handler' . print_r($_GET, true));
		}
		wp_die('Takbull IPN Request Failure', 'TAKBULL IPN', array('response' => 500));
	}

	public function maybe_handle_takbull_api_call()
	{
		$orderId = wp_unslash($_GET['order_reference']);
		$order = wc_get_order($orderId);
		if ($_GET['statusCode'] != 0) {
			if ($order) {
				$order->add_order_note(__('Takbull payment submit Failed ' . $_GET['statusDescription'], 'woocommerce'));
			}
			return false;
		}

		//1) store token on order 
		$order->set_payment_method('takbull');
		$token    = esc_attr(sanitize_text_field(wp_unslash($_GET['token'])));
		if (!empty($token)) {
			$order->update_meta_data('_takbull_token', $token);
			$order->update_meta_data('takbull_order_status', 'token_saved');
			$order->save();
		}
		$step_id      = isset($_GET['step_id']) ? intval($_GET['step_id']) : 0;
		$upsell_url    = $_GET['upsell_url'];
		// redirect customer to order received page.
		wp_safe_redirect(esc_url_raw($upsell_url));
		//3) process upsell
	}


	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields()
	{
		$this->form_fields = require(dirname(__FILE__) . '/admin/takbull-settings.php');
	}

	public function payment_icons()
	{
		return apply_filters(
			'wc_takbull_payment_icons',
			array(
				'mastercard_visa'       => '<img src="' . WC_TAKBULL_PLUGIN_URL . '/includes/assets/images/cc_icons.png" class="stripe-visa-icon stripe-icon" alt="mastercard_visa" />',
			)
		);
	}
	public function get_icon()
	{
		$icons = $this->payment_icons();
		$icons_str = '';
		$icons_str .= isset($icons['mastercard_visa']) ? $icons['mastercard_visa'] : '';
		return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
	}

	public function process_payment($order_id)
	{
		try {
			$order = wc_get_order($order_id);
			$tokenize = false;
			$totalPayments =	$_POST['wc-' . $this->id . '-total-payments'] ?? 1;
			if (!$totalPayments) {
			} else {
				$payments_fees = $this->get_option('payment_fee', []);
				if (!empty($payments_fees)) {
					$item_fee = new WC_Order_Item_Fee();
					$fee = 0;
					foreach ($payments_fees as $row => $innerArray) {
						if ($innerArray['number_of_payments'] == $totalPayments) {
							$fee = $innerArray['fee'];
						}
					}
					if ($fee) {

						$item_fee->set_name(sprintf(__('%1$s Payments fee', "takbull-gateway"), $totalPayments)); // Generic fee name
						$item_fee->set_amount($order->get_total() * $fee / 100); // Fee amount

						$item_fee->set_total($order->get_total() * $fee / 100); // Fee amount

						// Add Fee item to the order
						$order->add_item($item_fee);

						## ----------------------------------------------- ##
						$order->calculate_totals();
						$order->save();
					}
				}
				$order->update_meta_data('_wc_takbull_total_payment', $totalPayments);
				$order->save_meta_data();
			}
			if ($this->token_enabled === true) {
				//charge token 
				if (isset($_POST['wc-takbull-payment-token']) && trim($_POST['wc-takbull-payment-token']) !== 'new') {

					$token_id = wc_clean($_POST['wc-takbull-payment-token']);
					$token = WC_Payment_Tokens::get($token_id);
					if ($token->get_user_id() !== get_current_user_id()) {
						return;
					}
					$data = $this->init_data_to_send($order);

					$data['DealType'] = $this->deal_type;
					$data['CreditCard']['CardExternalToken'] = $token->get_token();
					$data = apply_filters('woocommerce_takbull_order_args', $data, $order);
					// WC_Takbull_Logger::log('Woo_takbull_Charge_Token Request data: ' . json_encode($data));
					$response = $this->api->request(json_encode($data), "api/ExtranalAPI/ChargeToken");
					// WC_Takbull_Logger::log('Woo_takbull_Charge_Token Response data: ' . json_encode($response));
					if (!empty($response->error)) {
						throw new WC_Takbull_Exception(print_r($response, true), $response->error->message);
					}

					$body = wp_remote_retrieve_body($response);
					if ($this->deal_type == 6) {
						$order->update_meta_data(WC_TAKBULL_META_KEY . '_order_status', OrderStatus::Pending);
						$order->update_status('on-hold', __('Payment J2(token) successfull', 'woocommerce'), true);
						$order->save_meta_data();
					} else {
						$tranService = new WC_Takbull_Transaction($body->transactionInternalNumber, $order_id);
						$tranService->order_process_complite($order);
					}
					if ($body->internalCode == 0) {
						$order->add_payment_token($token);
						return array(
							'result' => 'success',
							'redirect' => $this->get_return_url($order)
						);
					} else {
						WC_Takbull_Logger::log(print_r($response, true) . ' desc: ' . __($body->internalDescription, 'takbull-gateway'));
						echo $body->internalDescription;
						wc_add_notice($body->internalDescription);
						$order->add_order_note(__('Failed to charge! Error:' .  $body->internalDescription, 'takbull-gateway'));
						echo " - Positive response\n";
						return array(
							'result' => 'fail',
							'redirect' =>  $this->get_return_url($order),
							'messages' => $body->internalDescription
						);
					}
				} else {
					delete_post_meta($order_id, '_wc_takbull_token');
				}

				if (
					isset($_POST['wc-takbull-new-payment-method']) && $_POST['wc-takbull-new-payment-method'] ===  'true'
				) {
					WC_Takbull_Logger::log('save token true');
					$tokenize = true;
					$order->update_meta_data('_wc_takbull_tokenize_payment', $tokenize);
					// update_post_meta($order_id, '_wc_takbull_tokenize_payment', $tokenize);
				} else {
					WC_Takbull_Logger::log('save token false');
					// update_post_meta($order_id, '_wc_takbull_tokenize_payment', false);
					$order->update_meta_data('_wc_takbull_tokenize_payment', false);
				}
				$order->save_meta_data();
			}


			if ($this->display_type == 'iframe') {
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

			$this->endpoint =   $this->api->get_redirecr_order_api() . "?orderUniqId=" . $resp->uniqId;
			return array(
				'result'   => 'success',
				'redirect' => $this->endpoint,
			);
		} catch (WC_Takbull_Exception $e) {
			wc_add_notice($e->getLocalizedMessage(), 'error');
			WC_Takbull_Logger::log('Error: ' . $e->getMessage());

			do_action('wc_gateway_takbull_process_payment_error', $e, $order);

			/* translators: error message */
			$order->update_status('failed');

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}



	/**
	 * Output redirect or iFrame form on receipt page
	 *
	 * @access public
	 *
	 * @param $order_id
	 */
	public function receipt_page_takbull($order_id)
	{
		// WC_Takbull_Logger::log('receipt_page' . PHP_EOL . __FILE__);
		$order = wc_get_order($order_id);
		$this->script_manager();
		echo $this->generate_iframe_form_html($order);
	}

	function generate_iframe_form_html($order)
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
		$html .= '<div class="loading">Loading&#8230;</div>';
		$html .= '<div class="wc_takbull_iframe_form_detail " id="wc_takbull_iframe_payment_container"   style="border: 0; width: 100%; height: 100%;">' . PHP_EOL;
		$html .= '<iframe id="wc_takbull_iframe" name="wc_takbull_iframe" seamless width="100%" height="720px" style="min-height:700px; overflow: hidden; border: 0;" src="' . $url . '"></iframe>' . PHP_EOL;
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


	/**
	 * Checks whether new keys are being entered when saving options.
	 */
	public function process_admin_options()
	{
		// Load all old values before the new settings get saved.
		$old_api_key      = $this->get_option('api_key');
		$old_api_secret           = $this->get_option('api_secret');


		parent::process_admin_options();

		// Load all old values after the new settings have been saved.
		$new_api_key      = $this->get_option('api_key');
		$new_api_secret           = $this->get_option('api_secret');

		// Checks whether a value has transitioned from a non-empty value to a new one.
		$has_changed = function ($old_value, $new_value) {
			return !empty($old_value) && ($old_value !== $new_value);
		};

		// Look for updates.
		if (
			$has_changed($old_api_key, $new_api_key)
			|| $has_changed($old_api_secret, $new_api_secret)
		) {
			update_option('wc_takbull_show_changed_keys_notice', 'yes');
		}
	}

	public function payment_fields()
	{
		global $woocommerce;
		$order_price = $woocommerce->cart->total;
		if (!empty($this->description)) {
			echo wpautop(wptexturize($this->description));
		}

		if (!is_user_logged_in() && $this->token_enabled) {
			echo wpautop(__('To save your card securely for easier future payments, sign up for an account or log in to your existing account.', 'takbull-gateway'));
		}

		if ($this->token_enabled) {
			$this->credit_card_form();
			if (!is_add_payment_method_page()) {
				$this->saved_payment_methods();
				$this->save_payment_method_checkbox();
			} else {
				wp_register_script('takbull_bootstrap', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js');
				wp_enqueue_script('takbull_bootstrap');

				// CSS
				wp_register_style('takbull_bootstrap', WC_TAKBULL_PLUGIN_URL . '/includes/assets/css/modal.css', array(), WC_TAKBULL_VERSION);
				wp_enqueue_style('takbull_bootstrap');

				// wp_register_script template ( $handle, $src, $deps, $ver, $in_footer );

				if (!function_exists('pmpro_takbull_javascript')) {

					$localize_vars = array(
						'data' => array(
							'url' => admin_url('admin-ajax.php'),
							'nonce' => wp_create_nonce('ajax-nonce' . 'takbull'),
							'action' => 'takbull_get_redirect_url',
							'redirect_url' => $this->api->get_redirecr_order_api() . "?orderUniqId="
							// 'pmpro_require_billing' => $pmpro_requirebilling,
							// 'order_reference' => wp_create_nonce('takbull_order_ref' . $pmpro_level->id
						)
					);

					wp_register_script(
						'takbull_popup',
						plugins_url('/includes/assets/js/takbull-popup.js', dirname(__FILE__)),
						array('jquery'),
						TAKBULL_API_VERSION
					);
					wp_localize_script('takbull_popup', 'pmproTakbullVars', $localize_vars);
					wp_enqueue_script('takbull_popup');
				}
			}
		}
		$min_payments = $this->get_minimum_payments($order_price);
		$max_payments = $this->get_maximum_payments($order_price);
		$payments_fees = $this->get_option('payment_fee', []);
		// WC_Takbull_Logger::log("$this->deal_type::: " . $this->deal_type . "max payments::: " . $max_payments);
		$is_upsell = false;
		if (class_exists('Cartflows_Pro_Gateway_Takbull')) {
			$checkout_id = wcf()->utils->get_checkout_id_from_post_data();
			$wcf_step_obj      = wcf_pro_get_step($checkout_id);
			$next_step_id      = $wcf_step_obj->get_next_step_id();
			$wcf_next_step_obj = wcf_pro_get_step($next_step_id);
			if ($next_step_id && $wcf_next_step_obj->is_offer_page()) {
				WC_Takbull_Logger::log('Offer page found. Step is - ' . $next_step_id);
				$is_upsell = true;
			}
		}
		$is_subscription = false;
		if (class_exists('WC_Subscriptions_Product')) {
			foreach (WC()->cart->get_cart() as $cart_item) {
				$product_id = $cart_item['product_id'];
				$is_subscription =  WC_Subscriptions_Product::is_subscription($product_id);
				if ($is_subscription) {
					break;
				}
			}
		}
		if (!$is_subscription) {
			if (!is_add_payment_method_page() && (!empty($payments_fees) || ($this->deal_type == 6 || $this->deal_type == 7  || $is_upsell) && ($max_payments > $min_payments || 1 > $min_payments))) {
				$payments_html =  wc_get_template_html(
					'number-of-payments.php',
					[
						'payments' => range($min_payments, $max_payments),
						'gateway' => $this,
						'fees' => $payments_fees,
						'fee_enabled' => !empty($payments_fees),
						'total' => $order_price
					],
					null,
					WC_TAKBULL_PLUGIN_PATH . '/templates/checkout/'
				);
				echo $payments_html;
			}
		}
	}


	public function credit_card_form($args = array(), $fields = array())
	{

?>
		<fieldset id="<?php echo $this->id; ?>-cc-form">
			<?php
			do_action('woocommerce_credit_card_form_start', $this->id);
			do_action('woocommerce_credit_card_form_end', $this->id);
			?>
			<div class="clear"></div>
		</fieldset>
		<?php
		if (is_add_payment_method_page()) {
		?>
			<div class="modal fade" id="takbull_payment_popup" tabindex="-1" role="dialog" data-backdrop="false">
				<div class="modal-dialog modal-lg" role="document">
					<div class="modal-content">
						<div class="modal-body">
							<iframe id="wc_takbull_iframe" name="wc_takbull_iframe" width="100%" height="620px" style="border: 0;"></iframe>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-default" data-dismiss="modal"><?php _e('Close', 'takbull-gateway') ?></button>
						</div>
					</div>
				</div>
			</div>
		<?php
		}
		?>
		<script type="text/javascript">
			jQuery('input#createaccount').change(function() {
				var tokenize = jQuery('#wc_takbull_tokenize_payment').closest('p.form-row');

				if (jQuery(this).is(':checked')) {
					tokenize.show("slow");
				} else {
					tokenize.hide("slow");
				}
			}).change();
			jQuery('input[name=wc_takbull_token]').change(function() {
				var tokenize = jQuery('#wc_takbull_tokenize_payment').closest('p.form-row');
				if (jQuery(this).val() == 'add_new') {
					tokenize.show("slow");
				} else {
					tokenize.hide("slow");
				}
			});
		</script>
<?php
	}

	public function script_manager()
	{
		wp_register_style('takbull_styles', WC_TAKBULL_PLUGIN_URL . '/includes/assets/css/takbull-styles.css', array(), WC_TAKBULL_VERSION);
		wp_enqueue_style('takbull_styles');

		// wp_register_script template ( $handle, $src, $deps, $ver, $in_footer );
		wp_register_script('wc-takbull-iframe', plugins_url('/includes/assets/js/takbull-iframe-handle.js', dirname(__FILE__)), array('jquery'), false, true);
		wp_enqueue_script('wc-takbull-iframe');
	}

	/**
	 * Payment_scripts function.
	 *
	 * Outputs scripts used for stripe payment
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function payment_scripts()
	{
		if (
			!is_product()
			&& !is_cart()
			&& !is_checkout()
			&& !isset($_GET['pay_for_order']) // wpcs: csrf ok.
			&& !is_add_payment_method_page()
			&& !isset($_GET['change_payment_method']) // wpcs: csrf ok.
			// && !(!empty(get_query_var('view-subscription')) && class_exists('WCS_Early_Renewal_Manager') && WCS_Early_Renewal_Manager::is_early_renewal_via_modal_enabled())
			|| (is_order_received_page())
		) {
			return;
		}

		// If Stripe is not enabled bail.
		if ('no' === $this->enabled) {
			return;
		}
	}

	public function add_payment_method()
	{
		$error     = false;
		$error_msg = __('There was a problem adding the payment method.', 'woocommerce-gateway-takbull');
		$source_id = '';
		WC_Takbull_Logger::log(print_r($_POST, true));
		try {
			if (!is_user_logged_in()) {
				$error = true;
			}
		} catch (WC_Takbull_Exception $e) {
			wc_add_notice($e->getLocalizedMessage(), 'error');
			WC_Takbull_Logger::log('Error: ' . $e->getMessage());
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	public function takbull_get_redirect_url()
	{
		if (!wp_verify_nonce($_POST['nonce'], 'ajax-nonce' . "takbull")) {
			die('Busted!');
		}

		$request_data = $_POST['req'];
		$request_data['Language'] = get_locale();
		$response = $this->api->request(json_encode($request_data), "api/ExtranalAPI/GetTakbullPaymentPageRedirectUrl");
		if (!empty($response->error)) {
			throw new Exception(print_r($response, true), $response
				->error
				->message);
		}
		$body = wp_remote_retrieve_body($response);
		wp_send_json_success($body);
	}


	public function charge_order()
	{
		try {
			check_ajax_referer('order-item', 'security');
			if (!current_user_can('edit_shop_orders')) {
				WC_Takbull_Logger::log('charge_order edit_shop_orders');
				wp_die(-1);
			}

			$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
			$order = wc_get_order($order_id);

			$takbull_order = json_decode($order->get_meta(WC_TAKBULL_META_KEY . '_order'));
			$takbull_transaction = json_decode($order->get_meta(WC_TAKBULL_META_KEY));
			if ($takbull_order->dealType == 7) {
				$suspended_tran_meta = $order->get_meta(WC_TAKBULL_META_KEY . '_suspended_transaction');
				$suspended_transaction = json_decode(($suspended_tran_meta));
				if ($suspended_transaction->status == TransactionStatus::PENDING) {
					$response = $this->api->get_request("api/ExtranalAPI/SubmitTransaction?transactionInternalNumber=" . $suspended_transaction->transactionInteranlNumber);
					if (!empty($response->error)) {
						throw new WC_Takbull_Exception(print_r($response, true), $response->error->message);
						wp_send_json_error($response->error->message);
					}
					$body = wp_remote_retrieve_body($response);
					if ($body->internalCode == 0) {
						$tranService = new WC_Takbull_Transaction($body->transactionInternalNumber, $order_id);
						$tranService->order_process_complite($order);
						$takbull_transaction->status = TransactionStatus::Complite;
						$suspended_transaction->status = TransactionStatus::Complite;
						$order->update_meta_data(WC_TAKBULL_META_KEY . '_suspended_transaction', json_encode($suspended_transaction));
						$order->update_meta_data(WC_TAKBULL_META_KEY, json_encode($takbull_transaction));
						$order->save();
						wp_send_json_success($body);
					} else {
						wp_send_json_error($body);
					}
				}
			} else {
				$token_id = $order->get_payment_tokens()[0];
				if (empty($token_id)) {
					WC_Takbull_Logger::log('charge_order empty($token_id)');
					wp_die(-1);
				}

				$token = WC_Payment_Tokens::get($token_id);
				$token_val = $token->get_token();
				$data = $this->init_data_to_send($order);
				$data['DealType'] = 1;
				$data['SaveToken'] = false;
				$data['CreditCard']['CardExternalToken'] = $token_val;
				$data = apply_filters('woocommerce_takbull_order_args', $data, $order);
				$response = $this->api->request(json_encode($data), "api/ExtranalAPI/ChargeToken");
				if (!empty($response->error)) {
					wp_send_json_error($response->error);
					throw new WC_Takbull_Exception(print_r($response, true), $response->error->message);
				}
				$body = wp_remote_retrieve_body($response);
				WC_Takbull_Logger::log('charge_order response ::' . json_encode($body));
				if ($body->internalCode == 0) {
					$tranService = new WC_Takbull_Transaction($body->transactionInternalNumber, $order_id);
					$tranService->order_process_complite($order);
					wp_send_json_success($body);
				} else {
					wp_send_json_error($body);
				}
			}
		} catch (Exception $e) {
			wp_send_json_error($body);
		}
	}
}
