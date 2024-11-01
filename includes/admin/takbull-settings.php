<?php
if (!defined('ABSPATH')) {
	exit;
}

return apply_filters(
	'wc_takbull_settings',
	array(
		'enabled'                       => array(
			'title'       => __('Enable/Disable', 'takbull-gateway'),
			'label'       => __('Enable Takbull', 'takbull-gateway'),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title'                         => array(
			'title'       => __('Title', 'takbull-gateway'),
			'type'        => 'text',
			'description' => __('This controls the title which the user sees during checkout.', 'takbull-gateway'),
			'default'     => __('תשלום מאובטח בכרטיס אשראי ( תקבול)', 'takbull-gateway'),
			'desc_tip'    => true,
		),
		'description'                   => array(
			'title'       => __('Description', 'takbull-gateway'),
			'type'        => 'text',
			'description' => __('This controls the description which the user sees during checkout.', 'takbull-gateway'),
			'default'     => __('תשלום מאובטח בתקן PCI DSS.', 'takbull-gateway'),
			'desc_tip'    => true,
		),
		'api_key'               => array(
			'title'       => __('API Key', 'takbull-gateway'),
			'type'        => 'text',
			'description' => __('Get your API keys from your takbull account.', 'takbull-gateway'),
			'default'     => '95ed9b8d-8702-4c54-9dd8-b9242c7e3427',
			'desc_tip'    => true,
		),
		'api_secret'                    => array(
			'title'       => __('API Secret', 'takbull-gateway'),
			'type'        => 'password',
			'description' => __('Get your API keys from your takbull account.', 'takbull-gateway'),
			'default'     => '488447db-0503-4fc8-9ed0-4e1d62ee0114',
			'desc_tip'    => true,
		),
		'display_type'   => array(
			'title'       => __('Display Type', 'takbull-gateway'),
			'label'       => __('Display Type', 'takbull-gateway'),
			'type'        => 'select',
			'description' => __('Select the way you want Payment gateway will be displied.', 'takbull-gateway'),
			'default'     => 'iframe',
			'desc_tip'    => true,
			'options'     => array(
				'iframe' => __('Iframe', 'takbull-gateway'),
				'redirect'     => __('Redirect', 'takbull-gateway'),
				// 'checkout_page'  => __( 'In Checkout', 'takbull-gateway' ),
			),
		),
		'send_secure_sms'               => array(
			'title'       => __('Enable Secure SMS', 'takbull-gateway'),
			'label'       => sprintf(__('Enable Secure SMS.', 'takbull-gateway')),
			'type'        => 'checkbox',
			'description' => __('If enabled, code will be send to customer before payment process initiate.', 'takbull-gateway'),
			'default'     => 'no',
			'desc_tip'    => false,
		),
		'Payment_title' => [
			'title' => __('Payment Setup', 'takbull-gateway'),
			'type' => 'title',
		],

		'deal_type'   => array(
			'title'       => __('Deal Type', 'takbull-gateway'),
			'label'       => __('Deal Type', 'takbull-gateway'),
			'type'        => 'select',
			'description' => __('J4= regular payment, J2= Save token without charge, J7= Deal Suspended NO CHANGES ALLOWED', 'takbull-gateway'),
			'default'     => 'J4',
			'desc_tip'    => true,
			'options'     => array(
				'1' => __('J4 - Regular', 'takbull-gateway'),
				'6'     => __('J2 - Token', 'takbull-gateway'),
				'7'     => __('SUSPENDED * Valid only to merchants with Sapak Number', 'takbull-gateway'),
			),
		),
		'token_enabled'        => array(
			'title'       => __('Saved cards', 'everypay'),
			'label'       => __('Enable payments with saved cards', 'takbull'),
			'type'        => 'checkbox',
			'description' => __("When card token payments are enabled users get an option to store reference to credit card and can make future purchases without need to enter card details.", 'takbull'),
			'default'     => 'no',
			'desc_tip'    => false,
		),

		'number_of_payments'                    => array(
			'title'       => __('Number of payments', 'takbull-gateway'),
			'type'        => 'text',
			'description' => __('Set MAX number of payments allowed.', 'takbull-gateway'),
			'default'     => '1',
			'desc_tip'    => true,
		),
		'custom_payment_range' => array(
			'type'        => 'custom_payment_range',
		),
		'payment_fee'=> array(
			'type'        => 'payment_fee',			
		),
		'Invoice_title' => [
			'title' => __('Invoice Setup', 'takbull-gateway'),
			'type' => 'title',
		],
		'create_document'               => array(
			'title'       => __('Create invoice', 'takbull-gateway'),
			'label'       => sprintf(__('Enable Invoice creation.')),
			'type'        => 'checkbox',
			'description' => __('If enabled, invoice will create.', 'takbull-gateway'),
			'default'     => 'no',
			'desc_tip'    => false,
		),
		'document_type'   => array(
			'title'       => __('Document type', 'takbull-gateway'),
			'label'       => sprintf(__('Set document type.', 'takbull-gateway')),
			'type'        => 'select',
			'desc_tip'    => true,
			'options'     => array(
				0 => __('בחר', 'takbull-gateway'),
				100 => __('הזמנה', 'takbull-gateway'),
				320 => __('חשבונית מס קבלה', 'takbull-gateway'),
				300 => __('חשבונית עסקה', 'takbull-gateway'),
				400     => __('קבלה', 'takbull-gateway'),
				305     => __('חשבונית מס', 'takbull-gateway'),
				405     => __('קבלת תרומה', 'takbull-gateway'),
				// 'checkout_page'  => __( 'In Checkout', 'takbull-gateway' ),
			),
			'default'     => 320,
		),

		'send_sms'               => array(
			'title'       => __('Send Invoice SMS', 'takbull-gateway'),
			'label'       => sprintf(__('Enable SMS.', 'takbull-gateway')),
			'type'        => 'checkbox',
			'description' => __('If enabled and customer provide valid mobile number, invoice will be send.', 'takbull-gateway'),
			'default'     => 'no',
			'desc_tip'    => false,
		),

		'is_taxtable'   => array(
			'title'       => __('Tax included', 'takbull-gateway'),
			'label'       => sprintf(__('Extract tax from order total.')),
			'type'        => 'select',
			'description' =>  __('If enabled, tax will be extract from total price (17%)', 'takbull-gateway'),
			'default'     => 'yes',
			'desc_tip'    => true,
			'options'     => array(
				'yes' => __('Yes', 'takbull-gateway'),
				'no'     => __('No', 'takbull-gateway'),
				'onlyforisreal'     => __('Only For Isreal', 'takbull-gateway'),
				// 'checkout_page'  => __( 'In Checkout', 'takbull-gateway' ),
			),
		),

		'is_product_taxtable'               => array(
			'title'       => __('Activate Tax per product', 'takbull-gateway'),
			'label'       => sprintf(__('Get tax rate per product.')),
			'type'        => 'checkbox',
			'description' => __('If enabled, tax will be extract from product. Tax included option should be turned on', 'takbull-gateway'),
			'default'     => 'no',
			'desc_tip'    => false,
		),
		'third_party_gateway_create_invoice'               => array(
			'title'       => __('Create Invoice for other payment gateways', 'takbull-gateway'),
			'label'       => sprintf(__('Create invoice for paied order.')),
			'type'        => 'checkbox',
			'description' => __('If enabled, Invoice will be created as order marked processed (woocommerce default order state after payment)', 'takbull-gateway'),
			'default'     => 'no',
			'desc_tip'    => false,
		),

		'debug_title' => [
			'title' => __('Debug', 'takbull-gateway'),
			'type' => 'title',
		],
		'logging'                       => array(
			'title'       => __('Logging', 'takbull-gateway'),
			'label'       => __('Log debug messages', 'takbull-gateway'),
			'type'        => 'checkbox',
			'description' => __('Save debug messages to the WooCommerce System Status log.', 'takbull-gateway'),
			'default'     => 'no',
			'desc_tip'    => true,
		),


	)
);
