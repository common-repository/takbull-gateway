<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * WC_Takbull_API class.
 *
 * Communicates with Takbull API.
 */
const TAKBULL_API_VERSION = '1.0.0.1';
const ENDPOINT           = 'https://api.takbull.co.il/';
class WC_Takbull_API
{

	public function __construct($api_secret, $api_key)
	{
		$this->api_secret = $api_secret;
		$this->api_key = $api_key;
	}
	/**
	 * Takbull API Endpoint
	 */
	// const ENDPOINT           = 'http://192.168.1.16:8001/';

	/**
	 * Secret API Key.
	 * @var string
	 */
	private $api_secret = '';

	/**
	 * Set secret API Key.
	 * @param string $key
	 */


	/**
	 * Secret API Key.
	 * @var string
	 */
	private $api_key = '';



	public function	get_redirecr_order_api()
	{
		return ENDPOINT . "PaymentGateway";
	}

	/**
	 * Generates the user agent we use to pass to API request so
	 * Takbull can identify our application.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function get_user_agent()
	{
		$app_info = array(
			'name'    => 'WooCommerce Takbull Gateway',
			'version' => WC_TAKBULL_VERSION,
			'url'     => 'https://woocommerce.com/products/takbull/',
		);

		return array(
			'lang'         => 'php',
			'lang_version' => phpversion(),
			'publisher'    => 'woocommerce',
			'uname'        => php_uname(),
			'application'  => $app_info,
		);
	}

	/**
	 * Generates the headers to pass to API request.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function get_headers()
	{
		$user_agent = $this->get_user_agent();
		$app_info   = $user_agent['application'];

		return apply_filters(
			'woocommerce_takbull_request_headers',
			array(
				'API_Secret'              => $this->api_secret,
				'API_Key'              => $this->api_key,
				'Takbull-Version'             => TAKBULL_API_VERSION,
				'User-Agent'                 => $app_info['name'] . '/' . $app_info['version'] . ' (' . $app_info['url'] . ')',
				'X-Takbull-Client-User-Agent' => json_encode($user_agent),
				'Content-Type' => 'application/json'
			)
		);
	}

	/**
	 * Send the request to Takbull's API
	 *
	 * @since 3.1.0
	 * @version 4.0.6
	 * @param array $request
	 * @param string $api
	 * @param string $method
	 * @param bool $with_headers To get the response with headers.
	 * @return stdClass|array
	 * @throws WC_Takbull_Exception
	 */
	public function request($request, $api = 'charges', $method = 'POST', $with_headers = false)
	{
		$headers         = $this->get_headers();

		$response = wp_remote_post(
			ENDPOINT . $api,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => apply_filters('woocommerce_takbull_request_body', $request, $api),
				'timeout' => 70,
			)
		);

		if (is_wp_error($response) || empty($response['body'])) {
			throw new WC_Takbull_Exception(print_r($response, true), __('There was a problem connecting to the Takbull API endpoint.', 'takbull-gateway'));
		}
		if ($response['response']['code'] != 200)
			throw new WC_Takbull_Exception(print_r($response['response']['message'], true), __('There was a problem connecting to the Takbull API endpoint please validate api keis.', 'takbull-gateway'));
		return array(
			'headers' => wp_remote_retrieve_headers($response),
			'body'    => json_decode($response['body']),
		);
	}

	public function get_request($request)
	{
		// WC_Takbull_Logger::log('get_request ' . ENDPOINT . $request);
		$headers         = $this->get_headers();
		$response = wp_remote_get(
			ENDPOINT . $request,
			array(
				'headers' => $headers,
				'timeout' => 70,
			)
		);

		// WC_Takbull_Logger::log('get_request response ' . json_encode($response));		
		return $response;
	}
}
