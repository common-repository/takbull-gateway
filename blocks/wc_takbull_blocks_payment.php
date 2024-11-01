<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Takbull_Block extends AbstractPaymentMethodType
{
    private $gateway;
    protected $name = 'takbull'; // your payment gateway name

    public function __construct($payment_request_configuration = null)
    {
        // add_action('woocommerce_rest_checkout_process_payment_with_context', [$this, 'add_payment_request_order_meta'], 8, 2);        
    }
    public function initialize()
    {
        $this->settings = get_option("woocommerce_{$this->name}_settings", []);
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = $gateways[$this->name];
        // error_log("initialize WC_Takbull_Block: ".print_r($this->settings   ,true));
    }
    public function is_active()
    {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            "wc-{$this->name}-blocks-integration",
            plugin_dir_url(__FILE__) . "checkout-{$this->name}.js",
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        return ["wc-{$this->name}-blocks-integration"];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->settings['title'],
            'showSaveOption' => $this->settings['token_enabled'] == 'yes' ? true : false,
            'description' => $this->settings['description'],
        ];
    }
}

final class WC_Takbull_CC_Block extends WC_Takbull_Block
{
    protected $name = 'takbull';
}
final class WC_Takbull_Bit_Block extends WC_Takbull_Block
{
    protected $name = 'takbull_bit';
}
