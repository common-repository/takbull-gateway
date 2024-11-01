<?php

/**
 * Class Transaction
 */
class WC_Takbull_Transaction extends \WC_Data
{

    protected $data = array(
        'order_id' => 0,
        'transactionInternalNumber' => '',
        'status'     => '',
        'statusDescription'     => '',
        'last4DigitsCardNumber'     => '',
        'invoiceId'     => 0,
        'numberOfPayments'     => 0,
        'cardtype'     => '',
        'cardCompanyTtype'     => '',
        'clearer'     => '',
        'dealType'     => 0,
        'transactionType'     => 0,
        'invoiceLink'     => '',
        'isDocumentCreated'     => false,
        'transactionDate'     => '',
        'amount' => 0
    );

    protected $api;

    /**
     * Transaction constructor.
     *
     * @param string $transaction_id
     */
    public function __construct($transactionInternalNumber = '', $order_id = 0)
    {
        parent::__construct();
        $order = wc_get_order($order_id);
        if ($order) {
            $gateway = $this->get_wc_gateway($order->get_payment_method()); //WC_Gateway_Takbull::getInstance();
            $this->api      = new WC_Takbull_API($gateway->api_secret, $gateway->api_key);
            $this->set_order_id($order_id);
            if (!empty($transactionInternalNumber)) {
                $exist = false;
                foreach ($order->get_meta(WC_TAKBULL_META_KEY . '_transaction', false) as $meta) {
                    $tran =          json_decode($meta->value);
                    if ($tran ==  $transactionInternalNumber) {
                        WC_Takbull_Logger::log('transactionNumber exist::: ' . $transactionInternalNumber);
                        $order->update_meta_data(WC_TAKBULL_META_KEY . '_transaction', (string) $this, $meta->id);
                        $exist = true;
                        $this->get_transaction();
                    }
                }
                if ($exist == false) {
                    $this->set_id($transactionInternalNumber);
                    $this->get_transaction();
                    $this->save();
                }
            }
        }
    }

    public function get_wc_gateway($getway_id)
    {
        global $woocommerce;
        $gateways = $woocommerce->payment_gateways->payment_gateways();
        return $gateways[$getway_id];
    }

    public function set_id($id)
    {
        $this->id =  $id;
    }

    public function get_transaction()
    {
        $response = $this->api->get_request("api/ExtranalAPI/GetTransaction?transactionInternalNumber=" . $this->get_id());
        if (is_wp_error($response) || empty($response['body'])) {
            WC_Takbull_Logger::log(print_r($response, true), __('There was a problem connecting to the Takbull API endpoint.', 'takbull-gateway'));
            //add order not that transaction get failed
        }
        $body = json_decode($response['body']);
        WC_Takbull_Logger::log('GetTransaction Response: ' . $response['body']);
        $this->set_tran_data($body);
    }

    public function save()
    {
        $order = $this->get_order();
        if (!$order) {
            return;
        }
        $transactionNumber = $this->get_id();
        $updated = false;
        foreach ($order->get_meta(WC_TAKBULL_META_KEY . '_transaction', false) as $meta) {
            if (json_decode($meta->value)->id ==  $transactionNumber) {
                WC_Takbull_Logger::log('transactionNumber update meta::: ' . $transactionNumber);
                $order->update_meta_data(WC_TAKBULL_META_KEY . '_transaction', (string) $this, $meta->id);
                $updated = true;
            }
        }
        if ($updated == false) {
            $order->update_meta_data(WC_TAKBULL_META_KEY . '_transaction', (string) $this);
        }

        $order->save();
    }

    public function order_process_complite($order)
    {
        if ($order) {
            $order->update_meta_data(WC_TAKBULL_META_KEY . '_order_status', OrderStatus::Complite);
            $order->save_meta_data();
            $order->add_order_note(__('Takbull payment Processed', 'woocommerce'));
            $order->payment_complete();
        }
    }

    public function submit_postone_transaction($order)
    {
        $response = $this->api->get_request("api/ExtranalAPI/GetTransaction?transactionInternalNumber=" . $this->get_id());
    }
    /**
     * ProcessValidation.
     *    Validate order purches and set order status 
     */
    public function processValidation()
    {
        WC_Takbull_Logger::log('processValidation: ' . print_r($_GET, true));
        $order = wc_get_order($this->get_order_id());
        if ($_GET['statusCode'] != 0) {
            if ($order) {
                $order->add_order_note(__('Takbull payment submit Failed ' . $_GET['statusDescription'], 'woocommerce'));
            }
            // WC_Takbull_Logger::log('processValidation: status code !=0');
            return false;
        }
        $takbull_order = json_decode($order->get_meta(WC_TAKBULL_META_KEY . '_order'));
        // in case of Order submit at Takbull update panding order
        if ($takbull_order && $takbull_order->orderStatus == 0) {
            $status = intval($this->get_prop('status', 'view'));
            if ($status == 1 && $this->get_transactionType() == 10) {
                $this->order_process_complite($order);
                $order->update_meta_data(
                    WC_TAKBULL_META_KEY . '_suspended_transaction',
                    json_encode(
                        array(
                            'transactionInteranlNumber' => $this->id,
                            'status' => TransactionStatus::Complite
                        )
                    )
                );
                $order->save_meta_data();
                // WC_Takbull_Logger::log('processValidation: takbull_order && $takbull_order->orderStatus == 0');
                return;
            }
        }

        $validateReq = array(
            'uniqId' => $_GET['uniqId']
        );

        $response = $this->api->request(json_encode($validateReq), "api/ExtranalAPI/ValidateNotification");
        // WC_Takbull_Logger::log('processValidation: ExtranalAPI/ValidateNotification response');
        if (!empty($response->error)) {
            // WC_Takbull_Logger::log('processValidation: ExtranalAPI/ValidateNotification response' . print_r($response, true));
            throw new Exception(print_r($response, true), $response
                ->error
                ->message);
        }
        $body = wp_remote_retrieve_body($response);
        // WC_Takbull_Logger::log('processValidation: ExtranalAPI/ValidateNotification response' . print_r($body, true));
        $order->update_meta_data(WC_TAKBULL_META_KEY . '_order', json_encode($body));

        if ($body->internalCode == 0) {
            WC_Takbull_Logger::log('processValidation: ExtranalAPI/InternalCode == 0');
            if ($body->dealType == 6  && $this->get_transactionType() == 60) {
                WC_Takbull_Logger::log('processValidation: dealType == 6  && $this->get_transactionType() == 60');

                if (class_exists('WC_Subscriptions_Order') && class_exists('WC_Takbull_subscription')) {
                    if (WC_Takbull_subscription::has_subscription($this->get_order_id())) {
                        $this->order_process_complite($order);
                    }
                } else {
                    $order->update_meta_data(WC_TAKBULL_META_KEY . '_order_status', OrderStatus::Pending);
                    $order->update_status('on-hold', __('Payment J2(token) successfull', 'woocommerce'), true);
                }
            }
            if ($body->dealType == 7) {
                $status = intval($this->get_prop('status', 'view'));
                if ($status == 1) {
                    $this->order_process_complite($order);
                    $order->update_meta_data(
                        WC_TAKBULL_META_KEY . '_suspended_transaction',
                        json_encode(
                            array(
                                'transactionInteranlNumber' => $this->id,
                                'status' => TransactionStatus::Complite
                            )
                        )
                    );
                } else {
                    $order->update_meta_data(
                        WC_TAKBULL_META_KEY . '_suspended_transaction',
                        json_encode(
                            array(
                                'transactionInteranlNumber' => $this->id,
                                'status' => TransactionStatus::PENDING
                            )
                        )
                    );
                    $order->update_status('on-hold', __('Suspent Payment successfull', 'woocommerce'), true);
                }
            }
            if ($body->dealType != 6 && $body->dealType != 7  && $this->get_transactionType() == 10) {
                $this->order_process_complite($order);
            }
            $order->save_meta_data();
            WC_Takbull_Token::maybe_add_token($order, $body);
            return true;
        }
        return false;
    }

    public function set_tran_data($data)
    {
        $this->set_props([
            'status'     => $data->status,
            'statusCode'     => $data->statusCode,
            'statusDescription'     => $data->statusCode == 0 ? '' : $data->statusDescription,
            'last4DigitsCardNumber'     => $data->last4DigitsCardNumber,
            'numberOfPayments'     => $data->numberOfPayments,
            'cardtype'     => $data->cardtype,
            'clearer'     => $data->clearer,
            'cardCompanyTtype'     => $data->cardCompanyTtype,
            'dealType'     => $data->dealType,
            'transactionType'     => $data->transactionType,
            'amount'     => $data->amount,
            'transactionDate' => $data->transactionDate,
            'invoiceLink' => !empty($data->invoiceUniqId) ? $data->invoiceUniqId : ''
        ]);
    }


    /*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	*/
    public function get_isDocumentCreated($context = 'view')
    {
        return $this->get_prop('isDocumentCreated', $context);
    }
    public function get_invoiceLink($context = 'view')
    {
        return $this->get_prop('invoiceLink', $context);
    }
    public function get_last4DigitsCardNumber($context = 'view')
    {
        return $this->get_prop('last4DigitsCardNumber', $context);
    }

    public function get_order_id($context = 'view')
    {
        return $this->get_prop('order_id', $context);
    }

    public function get_transactionType($context = 'view')
    {
        return $this->get_prop('transactionType', $context);
    }

    public function get_status($context = 'view')
    {
        $status = intval($this->get_prop('status', $context));
        switch ($status) {
            case 0:
                return "לא ידוע";
            case 1:
                return "מאושר";
            case 2:
                return "סירוב";
            case 3:
                return "זיכוי חלקי";
            case 4:
                return "ממתין";
            case 5:
                return "זיכוי";
            case 6:
                return "נכשל";
            default:
                return '????';
        }
    }

    public function get_statusCode($context = 'view')
    {
        return $this->get_prop('statusCode', $context);
    }

    public function get_description($context = 'view')
    {
        return $this->get_prop('description', $context);
    }
    public function get_transactionDate($context = 'view')
    {
        return $this->get_prop('transactionDate', $context);
    }

    public function get_order()
    {
        $order_id = $this->get_order_id();

        return wc_get_order($order_id);
    }

    /*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	*/
    public function set_invoiceLink($invoiceLink)
    {
        if (!empty($invoiceLink))
            $this->set_prop('invoiceLink', $invoiceLink);
        return $this;
    }

    public function set_order_id($order_id)
    {
        return $this->set_prop('order_id', $order_id);
    }

    public function set_transactionDate($transactionDate)
    {
        return $this->set_prop('transactionDate', $transactionDate);
    }

    public function set_status($status)
    {
        return $this->set_prop('status', $status);
    }
    public function set_statusCode($status)
    {
        return $this->set_prop('statusCode', $status);
    }

    public function set_transactionType($transactionType)
    {
        return $this->set_prop('transactionType', $transactionType);
    }

    public function set_statusDescription($statusDescription)
    {
        return $this->set_prop('statusDescription', $statusDescription);
    }

    public function set_last4DigitsCardNumber($last4DigitsCardNumber)
    {
        return $this->set_prop('last4DigitsCardNumber', $last4DigitsCardNumber);
    }

    public function set_numberOfPayments($numberOfPayments)
    {
        return $this->set_prop('numberOfPayments', $numberOfPayments);
    }

    public function set_cardtype($cardtype)
    {
        return $this->set_prop('cardtype', $cardtype);
    }


    public function set_cardCompanyTtype($cardCompanyTtype)
    {
        return $this->set_prop('cardCompanyTtype', $cardCompanyTtype);
    }


    public function set_clearer($clearer)
    {
        return $this->set_prop('clearer', $clearer);
    }


    public function set_dealType($dealType)
    {
        return $this->set_prop('dealType', $dealType);
    }


    public function set_isDocumentCreated($isDocumentCreated)
    {
        return $this->set_prop('isDocumentCreated', $isDocumentCreated);
    }
    public function set_amount($amount)
    {
        return $this->set_prop('amount', $amount);
    }

    public function set_json_data($data)
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        $this->set_props($data);
        return $this;
    }
}
