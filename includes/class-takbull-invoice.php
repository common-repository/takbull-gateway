<?php



class Takbull_Invoice extends \WC_Data
{

    /**
     *
     * @var string
     */
    private $is_taxtable = '';
    /**
     *
     * @var bool
     */
    private $is_product_taxtable = false;

    private $docType;

    /**
     * The subscription helper.
     *
     * @var WC_Takbull_API
     */
    protected  $api;

    public function __construct(WC_Takbull_API $api, $is_tax, $is_product_tax, $docType)
    {
        parent::__construct();
        $this->is_taxtable = $is_tax;
        $this->is_product_taxtable = $is_product_tax;
        $this->api = $api;
        $this->docType = $docType;
    }


    public function ProcessInvoice($order)
    {
        WC_Takbull_Logger::log("invoicehit");
        // $order = new WC_Order($order_id);
        if ($order->get_total() == "0") {
            WC_Takbull_Logger::log("order total sum 0");
            return;
        }
        $order_id = $order->get_id();
        $takbull_invoice_lock = get_post_meta((int)$order_id, 'takbull_invoice_lock', true);
        if ($takbull_invoice_lock) {
            return;
        }
        update_post_meta((int)$order_id, 'takbull_invoice_lock', true);
        $key_1_value = get_post_meta((int)$order_id, 'document_number', true);
        $key_2_value = get_post_meta((int)$order_id, 'document_type', true);

        if (!empty($key_1_value) && !empty($key_2_value)) {
            WC_Takbull_Logger::log("Order has document: " . $key_1_value);
            return;
        }
        // Get the order items. Don't need their keys, only their values.
        // Order item IDs are used as keys in the original order items array.
        $order_items = array_values($order->get_items(array('line_item', 'fee')));
        $currency    = $order->get_currency();
        $takbull_line_items = array_map(function ($item) use ($currency, $order) {
            if ('fee' === $item['type']) {
                $unit_cost           = WC_Takbull_Helper::get_takbull_amount($item['line_total'], $currency);
                $quantity            = 1;
            } else {
                $unit_cost           = WC_Takbull_Helper::get_takbull_amount($order->get_item_subtotal($item, true), $currency);
                $quantity            = $item->get_quantity();
                $product = $item->get_product();
                if(!empty($product)){
                    $isTaxtable = (bool)$product->is_taxable();
                }
                $product_id          = $item->get_variation_id()
                ? $item->get_variation_id()
                : $item->get_product_id();
                $discount_amount     = WC_Takbull_Helper::get_takbull_amount($item->get_subtotal() - $item->get_total(), $currency);
                $tax_amount          = WC_Takbull_Helper::get_takbull_amount($item->get_total_tax(), $currency);
            }
            $product_description = substr(strip_tags(preg_replace("/&#\d*;/", " ", $item->get_name())), 0, 200);
            WC_Takbull_Logger::log('Product: ' . $product_description  . '::: Price: ' . $unit_cost);
            return array(
                'SKU'        => (string) $product_id, // Up to 12 characters that uniquely identify the product.
                'Description' => $product_description, // Up to 26 characters long describing the product.
                'Price'           => $unit_cost, // Cost of the product, in cents, as a non-negative integer.
                'Quantity'            => $quantity, // The number of items of this type sold, as a non-negative integer.
                'TaxAmount'          => $tax_amount, // The amount of tax this item had added to it, in cents, as a non-negative integer.
                'IsTaxteble' => $isTaxtable,
                'Discount'     => $discount_amount, // The amount an item was discounted—if there was a sale,for example, as a non-negative integer.
            );
        }, $order_items);

        // }
        $customer_name = substr(strip_tags(preg_replace("/&#\d*;/", " ", $order->get_billing_first_name() . " " . $order->get_billing_last_name())), 0, 200);
        $company_name = substr(strip_tags(preg_replace("/&#\d*;/", " ", $order->get_billing_company())), 0, 200);
        if ($company_name != '') {
            $customer_name  =  $company_name;
        }
        $customer = array(
            'CustomerFullName' => $customer_name,
            'FirstName' => substr(strip_tags(preg_replace("/&#\d*;/", " ", $order->get_billing_first_name())), 0, 200),
            'LastName' => substr(strip_tags(preg_replace("/&#\d*;/", " ", $order->get_billing_last_name())), 0, 200),
            'Email' => $order->get_billing_email(),
            'PhoneNumber' => substr(strip_tags(preg_replace("/[^A-Za-z0-9]/", '', $order->get_billing_phone())), 0, 10),
            'Address' => array(
                'Address1' => $order->get_billing_address_1(),
                'Address2' => $order->get_billing_address_2(),
                'City' => $order->get_billing_city(),
                'Country' => $order->get_billing_country(),
                'Zip' => $order->get_billing_postcode(),
            )
        );

        $paymentmethod = $order->get_payment_method();
        $custom_payment = array(
            'MethodName' => $order->get_payment_method_title(),
            'Amount' => WC_Takbull_Helper::get_takbull_amount($order->get_total(), $currency),
            'CreationDate' => date('c'),
            'Currancy' => $currency,
            // 'PayerAccountNumber'=>,
            'ReferanceId' => $order->get_transaction_id(),
        );
        $taxtable = true;
        if ($this->is_taxtable == 'onlyforisreal') {

            // Get an instance of the WC_Geolocation object class
            $geo_instance  = new WC_Geolocation();
            // Get geolocated user geo data.
            $user_geodata = $geo_instance->geolocate_ip();

            // Get current user GeoIP Country
            $country = $user_geodata['country'];
            // WC_Takbull_Logger::log("Customer counrty: " . $country);
            if (strtolower($country) == 'IL') {
                $taxtable = true;
            } else {
                $taxtable = false;
            }
        } else {
            $taxtable = $this->is_taxtable == 'yes';
        }

        $level3_data = array(
            'order_reference'   => $order->get_id(), // An alphanumeric string of up to  characters in length. This unique value is assigned by the merchant to identify the order. Also known as an “Order ID”.
            'ShippingAmount'      => WC_Takbull_Helper::get_takbull_amount($order->get_shipping_total() + $order->get_shipping_tax(), $currency), // The shipping cost, in cents, as a non-negative integer.
            'ShippingTaxAmount'    =>    WC_Takbull_Helper::get_takbull_amount($order->get_shipping_tax(), $currency),
            'Products'           => $takbull_line_items,
            'Currency'    =>    $currency,
            'CustomerFullName'    =>    $customer_name,
            'Customer'    =>    $customer,
            'CustomerPhoneNumber'    =>    $order->get_billing_phone(),
            'OrderTotalSum' => WC_Takbull_Helper::get_takbull_amount($order->get_total(), $currency),
            'TaxAmount' => WC_Takbull_Helper::get_takbull_amount($order->get_total_tax(), $currency),
            'Taxtable' => $taxtable,
            'IsProductTaxtable' => $this->is_product_taxtable,
            'Discount' => WC_Takbull_Helper::get_takbull_amount($order->get_total_discount(), $currency),
            'Language' => get_locale(),
            'CustomPayments' => [$custom_payment],
            'DocumentType' => $this->docType
        );

        // The customer’s U.S. shipping ZIP code.
        $shipping_address_zip = $order->get_shipping_postcode();

        $level3_data['ShippingZipCode'] = $shipping_address_zip;

        if (!empty($key_1_value) && !empty($key_2_value)) {
            WC_Takbull_Logger::log("Order has document: " . $key_1_value);
            return;
        }

        update_post_meta((int)$order_id, 'document_number', 0);
        update_post_meta((int)$order_id, 'document_type', 0);
        WC_Takbull_Logger::log(print_r(json_encode($level3_data), true));
        $level3_data = apply_filters('woocommerce_takbull_order_args', $level3_data, $order);
        $response = $this->api->request(json_encode($level3_data), "api/ExtranalAPI/CreateDocument");
        if (!empty($response->error)) {
            throw new WC_Takbull_Exception(print_r($response, true), $response->error->message);
            update_post_meta((int)$order_id, 'takbull_invoice_lock', false);
        }
        $body = wp_remote_retrieve_body($response);
        WC_Takbull_Logger::log('TAkbull RESPONE ' . $order->get_order_number() . ': ' . wc_print_r(wp_remote_retrieve_body($response), true));
        if ($body->responseCode != 0) {
            throw new WC_Takbull_Exception(print_r($response, true), __(print_r($body->errorList, true), 'takbull-gateway'));
        } else {
            update_post_meta((int)$order_id, 'document_number', $body->invoiceId);
            update_post_meta((int)$order_id, 'document_type', $body);
        }
    }

    public function get_locale()
    {
        $locale = get_locale();

        /*
		 * Takbull expects Norwegian to only be passed NO.
		 * But WP has different dialects.
		 */
        if ('NO' === substr($locale, 3, 2)) {
            $locale = 'no';
        } else {
            $locale = substr(get_locale(), 0, 2);
        }

        return $locale;
    }
}
