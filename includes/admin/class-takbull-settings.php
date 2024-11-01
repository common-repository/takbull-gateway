<?php

namespace Takbull\Admin;

use WC_Takbull_Logger;

class WC_Gateway_Takbull_Settings
{
    public $api_secret;
    public $api_key;
    public $token_enabled;
    public $deal_type;
    public $document_type;
    public $create_document;
    public $send_sms;
    public $display_type;
    public $is_taxtable;
    public $is_product_taxtable;
    public function __construct()
    {
        $this->get_takbull_settings();
    }
    public function get_takbull_settings()
    {
        $settings = get_option('woocommerce_takbull_settings');
        // WC_Takbull_Logger::log('Takbull SETTINGS: ' . json_encode($settings));
        foreach (get_object_vars($this) as $prop_name => $prop_value) {
            $this->magic($prop_name, $settings[$prop_name]);
            // $this->prop_name = $settings[$prop_name];
            // WC_Takbull_Logger::log('Takbull ' . $prop_name . ' : ' . $this->prop_name . PHP_EOL . 'setting value:.' . $settings[$prop_name]);
        }

        // WC_Takbull_Logger::log('Takbull api_key' . $this->api_key);
    }

    public function magic($member, $value = NULL)
    {
        if ($value != NULL) {
            if (!property_exists($this, $member)) {
                trigger_error('Undefined property via magic(): ' .
                    $member, E_USER_ERROR);
                return NULL;
            }
            $this->$member = $value;
        } else {
            if (!property_exists($this, $member)) {
                trigger_error('Undefined property via magic(): ' .
                    $member, E_USER_ERROR);
                return NULL;
            }
            return $this->$member;
        }
    }
}
