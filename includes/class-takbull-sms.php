<?php
if (!defined('ABSPATH')) {
    exit;
}

class TakbullSms
{
    protected static $id;
    protected static $takbull_settings;
    protected static $send_secure_sms;
    public function __construct()
    {
        self::$id             = 'takbull';
        self::get_options();
        // error_log(print_r(self::$takbull_settings,true));
        self::$send_secure_sms = self::$takbull_settings['send_secure_sms'] === 'yes';

        if (self::$send_secure_sms) {
            add_filter('woocommerce_checkout_fields', __CLASS__ . '::add_phone_field_checkout');
        }
        add_action('wp_ajax_send_sms_verification', __CLASS__ . '::send_sms_verification');
        add_action('wp_ajax_nopriv_send_sms_verification', __CLASS__ . '::send_sms_verification');
        add_action('wp_ajax_verify_sms_code', __CLASS__ . '::verify_sms_code');
        add_action('wp_ajax_nopriv_verify_sms_code', __CLASS__ . '::verify_sms_code');
        add_action('wp_enqueue_scripts', __CLASS__.'::custom_checkout_script');
    }

    private static $instance = [];
    public static function getInstance()
    {
        $cls = static::class;
        if (!isset(self::$instance[$cls])) {
            self::$instance[$cls] = new static();
        }

        return self::$instance[$cls];
    }

    protected static function get_options()
    {
        self::$takbull_settings = get_option('woocommerce_takbull_settings');
        return self::$takbull_settings;
    }

    public static function add_phone_field_checkout($fields)
    {
        $fields['billing']['billing_phone'] = array(
            'type'        => 'text',
            'label'       => __('Phone Number'),
            'required'    => true,
            'priority'    => 25,
        );
        return $fields;
    }

    public static function send_sms_verification()
    {
        if (self::$send_secure_sms) {
            if (isset($_POST['billing_phone'])) {
                $phone_number = sanitize_text_field($_POST['billing_phone']);
                $takbull = WC_Gateway_Takbull::getInstance();
                $req = array(
                    'PhoneNumber' => $phone_number,
                    'Ip' => self::get_the_user_ip(),
                    'Source' => 4,

                );
                $response = $takbull->api->request(json_encode($req), "api/Security/SmsProcess");
                if (!empty($response->error)) {
                    wp_send_json_error($response->error);
                    throw new WC_Takbull_Exception(print_r($response, true), $response->error->message);
                }
                $body = wp_remote_retrieve_body($response);
                $verification_code = $body;
                error_log(print_r($body, true));             // Store the verification code in session or database
                // Return response
                wp_send_json_success($body);
            }
            wp_send_json_error();
        }
        $data = array(
            'send_secure_sms' => self::$send_secure_sms,
            'status' => 'success',

        );
        wp_send_json_success($data);
    }

    private static function get_the_user_ip()
    {

        if (! empty($_SERVER['HTTP_CLIENT_IP'])) {

            //check ip from share internet

            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {

            //to check ip is pass from proxy

            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {

            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return apply_filters('wpb_get_ip', $ip);
    }

    public static function verify_sms_code()
    {
        if (isset($_POST['code'])) {
            $code = sanitize_text_field($_POST['code']);
            $uniqId = sanitize_text_field($_POST['uniqId']);
            $takbull = WC_Gateway_Takbull::getInstance();
            $req = array(
                'SourceUniqId' => $uniqId,
                'Code' => $code
            );
            $response = $takbull->api->request(json_encode($req), "api/Security/SmsSecurityValidate");
            if (!empty($response->error)) {
                wp_send_json_error($response->error);
                throw new WC_Takbull_Exception(print_r($response, true), $response->error->message);
            }
            $body = wp_remote_retrieve_body($response);
            error_log(print_r($body, true));             // Store the verification code in session or database
            // Return response
            wp_send_json_success($body);
        }
        wp_send_json_error();
    }

    public static function custom_checkout_script()
    {
        if (is_checkout() && !is_wc_endpoint_url() && self::$send_secure_sms) {
            wp_register_style('takbull_sms_styles', WC_TAKBULL_PLUGIN_URL . '/includes/assets/css/sms.css', array(), WC_TAKBULL_VERSION);
            wp_enqueue_style('takbull_sms_styles');
            wp_enqueue_script(
                'secure-sms-takbull',
                plugins_url('/includes/assets/js/secure-sms.js', __FILE__),
                array('jquery'),
                WC_TAKBULL_VERSION,
                true,
            );
            $smsVerification = array(
                'verificationTxt' =>  __('Enter Verification Code', 'takbull-gateway'),
                'sbmitBtn' => __('Verify', 'takbull-gateway'),
                'adminurl' => admin_url("admin-ajax.php"),
                'requestCodeAction' => 'send_sms_verification',
                'validateCodeAction' =>  'verify_sms_code',
            );
            wp_localize_script('secure-sms-takbull', 'smsVerification', $smsVerification);
        }
    }
}



TakbullSms::getInstance();
