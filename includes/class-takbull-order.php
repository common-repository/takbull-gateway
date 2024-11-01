<?php
add_action('admin_notices', 'takbull_order_charge_admin_notice');
add_filter('bulk_actions-edit-shop_order', 'takbull_charge_order_bulk_actions', 20, 1);
add_filter('handle_bulk_actions-edit-shop_order', 'takbull_charge_bulk_order', 10, 3);
add_filter('woocommerce_admin_order_actions', 'takbull_add_custom_order_status_actions_button', 100, 2);
add_action('admin_head', 'takbull_add_custom_order_status_actions_button_css');
add_action('admin_enqueue_scripts', 'takbull_admin_order_script');
function takbull_admin_order_script($hook)
{
    // Only add to the edit.php admin page.
    // See WP docs.
    if ('edit.php' !== $hook) {
        return;
    }

    wp_enqueue_script('takbull_order_script', plugins_url('/includes/assets/js/takbull-admin-order-handle.js', dirname(__FILE__)));
}

function takbull_add_custom_order_status_actions_button($actions, $order)
{
    if ($order->has_status(array('on-hold'))) {
        // The key slug defined for your action button
        $action_slug = 'charge';
        $order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
        // Set the action button
        $actions[$action_slug] = array(
            'url'       => wp_nonce_url(admin_url('admin-ajax.php?action=wip_pdf_generator&status=invoice&order_id=' . $order_id), 'wip_pdf_generator'),
            'name'      => __('Charge', 'woocommerce'),
            'action'    => $action_slug,
        );
    }
    return $actions;
}

function takbull_add_custom_order_status_actions_button_css()
{
    $action_slug = "charge"; // The key slug defined for your action button    
    echo '<style>.wc-action-button-' . $action_slug . '::after { font-family: woocommerce !important; content: "\e01e" !important; }</style>';
}

// Adding to admin order list bulk dropdown a custom action 'custom_downloads'
function takbull_charge_order_bulk_actions($actions)
{
    $actions['charge_orders'] = __('Charge orders', 'woocommerce');
    return $actions;
}

function takbull_charge_bulk_order($redirect_to, $action, $post_ids)
{
    WC_Takbull_Logger::log('charge_bulk_order HIT');
    if ($action !== 'charge_orders')
        return $redirect_to; // Exit
    $processed_ids = array();
    $failed_ids = array();
    $takbull = WC_Gateway_Takbull::getInstance();
    foreach ($post_ids as $post_id) {
        WC_Takbull_Logger::log('charge_order id: ' . $post_id);
        $order = wc_get_order($post_id);
        if ($order->get_status() === 'on-hold') {
            $takbull_order = json_decode($order->get_meta(WC_TAKBULL_META_KEY . '_order'));
            if ($takbull_order->dealType == 7) {
                $takbull_transaction = json_decode($order->get_meta(WC_TAKBULL_META_KEY));
                $suspended_tran_meta = $order->get_meta(WC_TAKBULL_META_KEY . '_suspended_transaction');
                $suspended_transaction = json_decode(($suspended_tran_meta));
                if ($suspended_transaction->status == TransactionStatus::PENDING) {
                    $response = $takbull->api->get_request("api/ExtranalAPI/SubmitTransaction?transactionInternalNumber=" . $suspended_transaction->transactionInteranlNumber);
                    if (!empty($response->error)) {
                        throw new WC_Takbull_Exception(print_r($response, true), $response->error->message);
                    }
                    $body = wp_remote_retrieve_body($response);
                    if ($body->internalCode == 0) {
                        $tranService = new WC_Takbull_Transaction($body->transactionInternalNumber, $post_id);
                        $tranService->order_process_complite($order);
                        $takbull_transaction->status = TransactionStatus::Complite;
                        $suspended_transaction->status = TransactionStatus::Complite;
                        $order->update_meta_data(WC_TAKBULL_META_KEY . '_suspended_transaction', json_encode($suspended_transaction));
                        $order->update_meta_data(WC_TAKBULL_META_KEY, json_encode($takbull_transaction));
                        $order->save();
                        $processed_ids[] = $post_id;
                    } else {
                        $failed_ids[] = $post_id;
                    }
                }
            } else {
                if ($order->get_meta(WC_TAKBULL_META_KEY . '_order_status') == OrderStatus::Complite || $order->get_meta(WC_TAKBULL_META_KEY . '_order_status') == OrderStatus::Process) {
                    WC_Takbull_Logger::log('order in proccess or complited');
                    continue;
                } else {
                    $order->update_meta_data(WC_TAKBULL_META_KEY . '_order_status', OrderStatus::Process);
                    $order->save_meta_data();
                }
                $token_ids = $order->get_payment_tokens();
                if (empty($token_ids)) {
                    $failed_ids[] = $post_id;
                    WC_Takbull_Logger::log('charge_order empty($token_id)');
                    continue;
                }

                $token_id = array_pop($token_ids);
                WC_Takbull_Logger::log('token_id::' . $token_id);
                if (empty($token_id)) {
                    WC_Takbull_Logger::log('charge_order empty($token_id)');
                    $failed_ids[] = $post_id;
                    continue;
                }
                $token = WC_Payment_Tokens::get($token_id);
                $token_val = $token->get_token();
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
        }
    }
    $redirect_to = add_query_arg(array(
        'charge_orders' => '1',
        'processed_count' => count($processed_ids),
        'failed_count' => count($failed_ids),
        'processed_ids' => implode(',', $processed_ids),
        'failed_ids' => implode(',', $failed_ids),
    ), $redirect_to);
    WC_Takbull_Logger::log('charge_bulk_order redirect :: ' . print_r($redirect_to, true));
    return $redirect_to;
}

// The results notice from bulk action on orders

function takbull_order_charge_admin_notice()
{
    if (empty($_REQUEST['charge_orders'])) return; // Exit

    $count = intval($_REQUEST['processed_count']);
    $fail_count = intval($_REQUEST['processed_count']);

    printf('<div id="message" class="updated fade"><p>' .
        _n(
            'Success %s Orders charged.',
            'Failed %s Orders .',
            $count,
            $fail_count
        ) . '</p></div>', $count);
}

class TakbullOrderEx
{

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
     * Order constructor.
     */
    private function __construct()
    {
        add_action('admin_init', [$this, 'register_hooks']);
        add_action('woocommerce_order_item_add_action_buttons', array($this,  'render_charge_button'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'), 20);
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'), 10, 2);
    }



    public function add_meta_boxes($post_type, $post)
    {
        WC_Takbull_Logger::log('add_meta_boxes::');
        if ('shop_order' !== $post_type && 'woocommerce_page_wc-orders' !== $post_type) {
            WC_Takbull_Logger::log('add_meta_boxes not order page::' . $post_type);
            return;
        }
        $order = wc_get_order($post);
        $transactions = $this->get_transactions($order);
        WC_Takbull_Logger::log('add_meta_boxes  transactions::' . json_encode($transactions));
        if (empty($transactions)) {
            WC_Takbull_Logger::log('add_meta_boxes No Transactions');
            return;
        }
        add_meta_box(
            'takbull-transactions',
            __('Transactions', 'takbull-gateway'),
            [$this->getInstance(), 'render_transactions_metabox'],
            $post_type,
            'advanced',
            'default',
            ['transactions' => $transactions]
        );
    }




    /**
     * @param \WC_Order $order
     */
    public function render_charge_button(WC_Order $order)
    {
        // $takbull_order = json_decode($order->get_meta(WC_TAKBULL_META_KEY));
        $takbull_order = json_decode($order->get_meta(WC_TAKBULL_META_KEY . '_order'));
        // WC_Takbull_Logger::log('render_charge_button takbull_order' . print_r($takbull_order, true));
        $suspended_tran_meta = $order->get_meta(WC_TAKBULL_META_KEY . '_suspended_transaction');
        $suspended_transaction = json_decode(($suspended_tran_meta));

        if ($takbull_order->dealType == 7 && $suspended_transaction->status == TransactionStatus::PENDING) {
        } else {
            $token_ids = $order->get_payment_tokens();
            if (empty($token_ids)) {
                return false;
            }
            $token_id = array_pop($token_ids);
            if (
                $order->get_meta(WC_TAKBULL_META_KEY . '_order_status') == OrderStatus::Complite ||
                $order->get_meta(WC_TAKBULL_META_KEY . '_order_status') == OrderStatus::Process ||
                empty($token_id) || !current_user_can('edit_shop_orders')
            ) {
                WC_Takbull_Logger::log('render_charge_button prohibit');
                return;
            }
        }
        // WC_Takbull_Logger::log('render_charge_button');
        echo sprintf('<button type="button" class="button takbull_charge">%1$s</button>', esc_html__('Charge', 'takbull-gateway'));
    }

    public function enqueue_admin_scripts()
    {
        wp_register_script('wc-takbull-order', plugins_url('/includes/assets/js/takbull-order.js', dirname(__FILE__)), array('jquery'), false, true);
        wp_enqueue_script('wc-takbull-order');
    }

    public function register_hooks()
    {
        add_action('wp_ajax_takbull_submit_order', [WC_Gateway_Takbull::getInstance(), 'charge_order']);
    }

    public function get_transactions(WC_Order $order)
    {
        $transactions = [];

        /**
         * @var \WC_Meta_Data $meta
         */
        foreach ($order->get_meta(WC_TAKBULL_META_KEY . '_transaction', false) as $meta) {
            $transactions[] = (new WC_Takbull_Transaction())->set_json_data($meta->value);
        }
        return $transactions;
    }

    public function render_transactions_metabox($post_or_order_object,array $metabox)
    {
        $order = ( $post_or_order_object instanceof WP_Post )
		? wc_get_order( $post_or_order_object->ID )
		: $post_or_order_object;
        wc_get_template(
            'takbull-transactions.php',
            [
                'transactions' => $metabox['args']['transactions'],
            ],
            null,
            WC_TAKBULL_PLUGIN_PATH . '/templates/order/'
        );
    }
}

TakbullOrderEx::getInstance();
