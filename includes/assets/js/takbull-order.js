jQuery( function( $ ) {

	var $container = jQuery( '#woocommerce-order-items' );

	$container.on( 'click', 'button.takbull_charge', function( e ) {
		e.preventDefault();

		if ( !window.confirm( 'Are You sure' ) ) {
			return false;
		}

		const $button = $( this );

		const data = {
			dataType: 'json',
			action: 'takbull_submit_order',
			order_id: woocommerce_admin_meta_boxes.post_id,
			security: woocommerce_admin_meta_boxes.order_item_nonce,
		};

		$.ajax( {
			type: 'POST',
			url: woocommerce_admin_meta_boxes.ajax_url,
			data: data,
			beforeSend: function() {
				$container.block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
				$button.prop( 'disabled', true );
				$( 'button.do-api-refund' ).prop( 'disabled', true );
			},
			success: function( response ) {
				if ( true === response.success ) {
					window.location.reload();
				} else {
					window.alert( response.data.internalDescription );
					$button.prop( 'disabled', false );
					$container.unblock();
				}
			},
			complete: function() {
				window.wcTracks.recordEvent( 'order_edit_charged', {
					order_id: data.order_id,
					status: $( '#order_status' ).val()
				} );
			}
		} );
	} );	
} );