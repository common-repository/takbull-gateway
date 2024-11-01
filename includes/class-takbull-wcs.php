<?php

/**
 * Takbull Woo Subscription Class
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Takbull_subscription
{
    protected static $takbull_settings;
    protected static $instance;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function init()
    {
        self::$takbull_settings = self::get_options();
        add_action('woocommerce_scheduled_subscription_payment_takbull', __CLASS__ . '::process_subscription_takbull', 10, 2);
        add_action('woocommerce_subscription_cancelled_takbull', __CLASS__ . '::cancel_subscription');
        add_action('woocommerce_subscription_pending-cancel_takbull', __CLASS__ . '::suspend_subscription');
        add_action('woocommerce_subscription_expired_takbull', __CLASS__ . '::suspend_subscription');
        add_action('woocommerce_subscription_on-hold_takbull', __CLASS__ . '::suspend_subscription');
        add_action('woocommerce_subscription_activated_takbull', __CLASS__ . '::reactivate_subscription');
        add_filter('wcs_gateway_status_payment_changed', __CLASS__ . '::suspend_subscription_on_payment_changed', 10, 2);
        add_filter('woocommerce_takbull_order_args', __CLASS__ . '::modify_takbull_arguments', 10, 2);
    }

    public static function modify_takbull_arguments($args, $order)
    {
        if (! self::has_subscription($order->get_id())) {
            return $args;
        }
        $sign_up_fee = WC_Subscriptions_Order::get_sign_up_fee($order);
        $subscription_trial_length = WC_Subscriptions_Order::get_subscription_trial_length($order);
        if ($order->get_total() == 0 || ($subscription_trial_length > 0 && $sign_up_fee == 0)) {
            $args['Dealtype'] = 6;
        }
        return $args;
    }

    public static function process_subscription_takbull($amount, $order)
    {
        //get token
        $post_id = $order->get_id();
        // Get the subscription(s) associated with this renewal order
        $subscriptions = wcs_get_subscriptions_for_renewal_order($post_id);

        if (! empty($subscriptions)) {
            $subscription = reset($subscriptions);  // Get the first subscription
        } else {
            // No subscription found for this renewal order
            WC_Takbull_Logger::log('No subscriptions found for renewal order ' . $post_id);
        }

        // WC_Takbull_Logger::log('post_id: ' . $post_id . ' process_subscription_takbull  empty($token_id)' . print_r($subscription, true));
        // Get the payment token from the subscription
        $token_ids = $subscription->get_payment_tokens('_payment_token');
        if (empty($token_ids)) {
            WC_Takbull_Logger::log('process_subscription_takbull  empty($token_id)' . print_r($token_ids, true));
            WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($order);
            return;
        }
        $token_id = reset($token_ids);


        WC_Takbull_Logger::log('token_id::' . $token_id);
        if ($token_id == false) {
            WC_Takbull_Logger::log('charge_order empty($token_id)');
            WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($order);
            return;
        }

        $token = WC_Payment_Tokens::get($token_id);
        $token_val = $token->get_token();

        $takbull = WC_Gateway_Takbull::getInstance();
        $data = $takbull->init_data_to_send($order);
        $data['DealType'] = 1;
        $data['IPNAddress'] = "";
        $data['SaveToken'] = false;
        $data['CreditCard']['CardExternalToken'] = $token_val;
        $data = apply_filters('woocommerce_takbull_order_args', $data, $order);
        $response = $takbull->api->request(json_encode($data), "api/ExtranalAPI/ChargeToken");
        if (!empty($response->error)) {
            throw new WC_Takbull_Exception(print_r($response, true), $response->error->message);
        }
        $body = wp_remote_retrieve_body($response);
        WC_Takbull_Logger::log('charge respons' . json_encode($body));
        if ($body->internalCode == 0) {
            $tranService = new WC_Takbull_Transaction($body->transactionInternalNumber, $post_id);
            $tranService->order_process_complite($order);
            $processed_ids[] = $post_id;
        } else {
            $order->add_order_note(__('Takbull payment Failed description: ' . $body->internalDescription, 'woocommerce'));
            $order->update_meta_data(WC_TAKBULL_META_KEY . '_order_status', OrderStatus::Error);
            $order->save_meta_data();
            $failed_ids[] = $post_id;
        }
    }


    /**
     * When a store manager or user cancels a subscription in the store, also cancel the subscription with PayPal.
     *
     * @since 2.0
     */
    public static function cancel_subscription($subscription) {}

    /**
     * When a store manager or user suspends a subscription in the store.
     *
     * @since 2.0
     */
    public static function suspend_subscription($subscription) {}


    /**
     * When a store manager or user reactivates a subscription in the store.
     * @since 2.0
     */
    public static function reactivate_subscription($subscription) {}


    /**
     * When changing the payment method on edit subscription screen from PayPal, only suspend the subscription rather
     * than cancelling it.
     *
     * @param string $status The subscription status sent to the current payment gateway before changing subscription payment method.
     * @return object $subscription
     * @since 2.0
     */
    public static function suspend_subscription_on_payment_changed($status, $subscription)
    {
        return ('takbull' == $subscription->get_payment_method()) ? 'on-hold' : $status;
    }


    public static function has_subscription($order_id)
    {
        return (function_exists('wcs_order_contains_subscription') && (wcs_order_contains_subscription($order_id) || wcs_is_subscription($order_id) || wcs_order_contains_renewal($order_id)));
    }
    protected static function get_options()
    {

        self::$takbull_settings = get_option('woocommerce_takbull_settings');
        return self::$takbull_settings;
    }
}

WC_Takbull_subscription::init();
