<?php
if (!defined('ABSPATH')) {
	exit;
}

return apply_filters(
	'wc_takbull_ern_settings',
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
			'default'     => __('ERN (Takbull)', 'takbull-gateway'),
			'desc_tip'    => true,
		),
		'description'                   => array(
			'title'       => __('Description', 'takbull-gateway'),
			'type'        => 'text',
			'description' => __('This controls the description which the user sees during checkout.', 'takbull-gateway'),
			'default'     => __('ERN via Takbull.', 'takbull-gateway'),
			'desc_tip'    => true,
		),
		// 'display_type'   => array(
		// 	'title'       => __('Display Type', 'takbull-gateway'),
		// 	'label'       => __('Display Type', 'takbull-gateway'),
		// 	'type'        => 'select',
		// 	'description' => __('Select the way you want Payment gateway will be displied.', 'takbull-gateway'),
		// 	'default'     => 'iframe',
		// 	'desc_tip'    => true,
		// 	'options'     => array(
		// 		'iframe' => __('Iframe', 'takbull-gateway'),
		// 		'redirect'     => __('Redirect', 'takbull-gateway'),
		// 		// 'checkout_page'  => __( 'In Checkout', 'takbull-gateway' ),
		// 	),
		// ),
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
	)
);
