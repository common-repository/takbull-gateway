// var pmpro_require_billing;
var eventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
var eventer = window[eventMethod];
var messageEvent = eventMethod == "attachEvent" ? "onmessage" : "message";
// Listen for a message from the iframe.
eventer(messageEvent, function (e) {
	if (isNaN(parseInt(e.data))) {
		form = jQuery('#add_payment_method');

		console.log(e.data + ' msg: ' + e.message);
		switch (event.data.message) {
			case 'paymentreplay':
				let resp = event.data.value
				if (resp && !isNaN(resp.InternalCode)) {
					if (resp.InternalCode != 0) {
						alert(resp.InternalDescription);
					} else {
						form.append('<input type="hidden" name="payment_intent_id" value="' + resp.transactionInternalId + '" />');
						form.append('<input type="hidden" name="setup_intent_id" value="' + resp.transactionInternalId + '" />');
						form.append('<input type="hidden" name="subscription_id" value="' + resp.uniqId + '" />');
						form.append('<input type="hidden" name="takbull_token" value="' + resp.CreditCard.CardExternalToken + '" />');
						form.append('<input type="hidden" name="last4Digits" value="' + resp.CreditCard.Last4Digits + '"/>');
						form.append('<input type="hidden" name="ExpirationMonth" value="' + ('0' + resp.CreditCard.CardTokenExpirationMonth).slice(-2) + '"/>');
						form.append('<input type="hidden" name="ExpirationYear" value="' + resp.CreditCard.CardTokenExpirationYear + '"/>');
						form.get(0).submit();
					}
				}
		}
		return;
	}
	document.getElementById('wc_takbull_iframe').style.height = e.data + 50 + 'px';
}, false);


jQuery(document).ready(function ($) {
	$('#takbull_payment_popup').on('hidden.bs.modal', function () {
		$('.blockUI').remove();
	});
	var readyToprocess = $('#readyToProcessByTakbull').val();

	$('#add_payment_method').submit(function (event) {
		processTakbull();
		event.preventDefault();
	});
	function processTakbull() {
		delatype = 6;
		var paymentRequest = {
			"DealType": delatype,
			"DisplayType": "iframe",
			"PostProcessMethod": 1,
		};
		jQuery.ajax({
			url: pmproTakbullVars.data.url,
			type: "post",
			data: {
				contentType: "application/json; charset=utf-8",
				dataType: "JSON",
				action: pmproTakbullVars.data.action,
				nonce: pmproTakbullVars.data.nonce,
				req: paymentRequest
			},
			success: function (apiresponse) {
				if (apiresponse.data.responseCode != 0) {
					// alert(apiresponse.description);
					$('#pmpro_message').text(apiresponse.data.description).addClass('pmpro_error').removeClass('pmpro_alert').removeClass('pmpro_success').show();
					$('.pmpro_btn-submit-checkout,.pmpro_btn-submit').removeAttr('disabled');
				}
				else {
					$('#wc_takbull_iframe').attr('src', pmproTakbullVars.data.redirect_url + apiresponse.data.uniqId)
					$('#takbull_payment_popup').modal('show');
				}
			},
			error: function (request, status, error) {
				$('#pmpro_message').text(request.responseText).addClass('pmpro_error').removeClass('pmpro_alert').removeClass('pmpro_success').show();
				$('.pmpro_btn-submit-checkout,.pmpro_btn-submit').removeAttr('disabled');
			}
		})
	}
});
