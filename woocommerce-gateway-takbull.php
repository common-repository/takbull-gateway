<?php

/**
 * Plugin Name: Takbull Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-takbull/
 * Description: Take credit card payments on your store using Takbull.
 * Author: S.P Takbull
 * Author URI: https://takbull.co.il/
 * Version: 4.3.0.9
 * Requires at least: 4.4
 * Tested up to: 6.6
 * WC requires at least: 2.6
 * WC tested up to: 9.3
 * Text Domain: takbull-gateway
 * Domain Path: /languages
 *
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_footer', 'wpf_wc_add_cart_fees_by_payment_gateway_script');
if (!function_exists('wpf_wc_add_cart_fees_by_payment_gateway_script')) {
    /**
     * wpf_wc_add_cart_fees_by_payment_gateway_script.
     */
    function wpf_wc_add_cart_fees_by_payment_gateway_script()
    {
?>
        <script>
            jQuery(function() {
                jQuery('body').on('change', 'input[name="takbull-total-payments"]', function() {
                    jQuery('body').trigger('update_checkout');
                });
            });
        </script>
<?php
    }
}

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', 'init_takbull_gateway_class');

function init_takbull_gateway_class()
{
    if (!class_exists('WC_Takbull')) :
        /**
         * Required minimums and constants
         */
        define('WC_TAKBULL_META_KEY', '_takbull');
        define('WC_TAKBULL_VERSION', '3.0.0.1');
        define('WC_TAKBULL_MIN_PHP_VER', '5.6.0');
        define('WC_TAKBULL_MIN_WC_VER', '2.6.0');
        define('WC_TAKBULL_MAIN_FILE', __FILE__);
        define('WC_TAKBULL_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
        define('WC_TAKBULL_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

        class WC_Takbull
        {
            /**
             * @var Singleton The reference the *Singleton* instance of this class
             */
            private static $instance;

            /**
             * Returns the *Singleton* instance of this class.
             *
             * @return Singleton The *Singleton* instance.
             */
            public static function get_instance()
            {
                if (null === self::$instance) {
                    self::$instance = new self();
                }
                return self::$instance;
            }
            /**
             * Private clone method to prevent cloning of the instance of the
             * *Singleton* instance.
             *
             * @return void
             */
            private function __clone() {}
            /**
             * Private unserialize method to prevent unserializing of the *Singleton*
             * instance.
             *
             * @return void
             */
            public function __wakeup() {}


            private function __construct()
            {
                add_action('admin_init', array($this, 'install'));
                $this->init();
            }

            public function init()
            {
                if (class_exists('WooCommerce')) {
                    require_once dirname(__FILE__) . '/includes/class-wc-takbull-exception.php';
                    require_once dirname(__FILE__) . '/includes/class-wc-takbull-logger.php';
                    require_once dirname(__FILE__) . '/includes/class-wc-takbull-token.php';
                    require_once dirname(__FILE__) . '/includes/class-wc-takbull-helper.php';
                    include_once dirname(__FILE__) . '/includes/class-wc-takbull-api.php';
                    require_once dirname(__FILE__) . '/includes/abstracts/abstract-wc-takbull-payment-gateway.php';
                    require_once dirname(__FILE__) . '/includes/class-wc-gateway-takbull.php';
                    require_once dirname(__FILE__) . '/bit/class-wc-gateway-takbull-bit.php';
                    require_once dirname(__FILE__) . '/ern/class-wc-gateway-takbull-ern.php';
                    require_once dirname(__FILE__) . '/includes/admin/class-takbull-settings.php';
                    require_once dirname(__FILE__) . '/includes/class-takbull-order.php';
                    require_once dirname(__FILE__) . '/models/class-takbull-transaction.php';
                    require_once dirname(__FILE__) . '/models/order-status-enum.php';
                    require_once dirname(__FILE__) . '/includes/class-takbull-invoice.php';
                    require_once dirname(__FILE__) . '/includes/class-takbull-sms.php';

                    add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
                    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
                    add_action('woocommerce_blocks_loaded', [$this, 'woocommerce_takbull_woocommerce_block_support']);
                    $domain = 'takbull-gateway';
                    $locale = apply_filters('plugin_locale', get_locale(), $domain);
                    if (class_exists('WC_Subscriptions_Order')) {
                        require_once dirname(__FILE__) . '/includes/class-takbull-wcs.php';
                    }

                    load_textdomain($domain, WC_TAKBULL_PLUGIN_PATH . "/languages/$domain-$locale.mo");

                    load_plugin_textdomain($domain, false, WC_TAKBULL_PLUGIN_PATH . '/languages');
                } else {
                    // you don't appear to have WooCommerce activated
                }
            }



            public function update_plugin_version()
            {
                delete_option('wc_takbull_version');
                update_option('wc_takbull_version', WC_TAKBULL_VERSION);
            }

            /**
             * Handles upgrade routines.
             *
             * @since 3.1.0
             * @version 3.1.0
             */
            public function install()
            {
                if (!is_plugin_active(plugin_basename(__FILE__))) {
                    return;
                }

                if (!defined('IFRAME_REQUEST') && (WC_TAKBULL_VERSION !== get_option('wc_takbull_version'))) {
                    do_action('woocommerce_takbull_updated');

                    if (!defined('WC_TAKBULL_INSTALLING')) {
                        define('WC_TAKBULL_INSTALLING', true);
                    }

                    $this->update_plugin_version();
                }
            }

            /**
             * Adds plugin action links.
             *
             * @since 1.0.0
             * @version 4.0.0
             */
            public function plugin_action_links($links)
            {
                $plugin_links = array(
                    '<a href="admin.php?page=wc-settings&tab=checkout&section=takbull">' . esc_html__('Settings', 'takbull-gateway') . '</a>',
                );
                return array_merge($plugin_links, $links);
            }

            /**
             * Add the gateways to WooCommerce.
             *
             * @since 1.0.0
             * @version 4.0.0
             */
            public function add_gateways($methods)
            {
                $methods[] = 'WC_Gateway_Takbull';
                $methods[] = 'WC_Gateway_Takbull_Bit';
                $methods[] = 'WC_Gateway_Takbull_Ern';
                return $methods;
            }

            /**
             * Modifies the order of the gateways displayed in admin.
             *
             * @since 4.0.0
             * @version 4.0.0
             */
            public function filter_gateway_order_admin($sections)
            {
                unset($sections['takbull']);
                $sections['takbull']            = 'Takbull';
                return $sections;
            }

            public function woocommerce_takbull_woocommerce_block_support()
            {
                if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                    require_once 'blocks/wc_takbull_blocks_payment.php';
                    add_action(
                        'woocommerce_blocks_payment_method_type_registration',
                        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                            $payment_method_registry->register(new WC_Takbull_CC_Block());
                            $payment_method_registry->register(new WC_Takbull_Bit_Block());
                        }
                    );
                }
            }
        }
        WC_Takbull::get_instance();
    endif;
}