jQuery(document).ready(function ($) {
    if ($('body').hasClass('woocommerce-checkout')) {
        $(document).on('click', '#place_order', function (e) {
            e.preventDefault();
            var phone = $('#billing_phone').val();
            if (phone) {
                $.ajax({
                    url: smsVerification.adminurl,
                    type: 'POST',
                    data: {
                        action: smsVerification.requestCodeAction,
                        billing_phone: phone
                    },
                    success: function (response) {
                        if (response.success) {
                            if (response.data.send_secure_sms != undefined && response.data.send_secure_sms == false) {
                                $('form.checkout').submit();
                            } else {
                                if (response.data.responseCode == 0 || response.data.responseCode == 120002) {
                                    // Show the popup
                                    $('body').append('<div id="verification-popup-overlay"></div>' +
                                        '<div id="verification-popup">' +
                                        '<span id="close-popup">&times;</span>' + // Close button
                                        '<h2>' + smsVerification.verificationTxt + '</h2>' +
                                        '<input type="hidden" id="uniqId" value="' + response.data.uniqId + '"/>' +
                                        '<input type="text" id="verification_code" />' +
                                        '<button id="verify_code">' + smsVerification.sbmitBtn + '</button>' +
                                        '<p id="timer">Resend code in <span id="countdown">03:00</span></p>' +
                                        '<button id="resend_code" style="display:none;">Resend Code</button>' +
                                        '</div>');
                                    // Disable scrolling on body
                                    $('body').css('overflow', 'hidden');
                                    startCountdown(180); // 3 minutes = 180 seconds
                                } else {
                                    alert(response.data.responseDescription);
                                }
                            }
                        }
                    }
                });
            }
            alert("Please enter phone number to continue the purchase");
        });

        // Close button handler
        $(document).on('click', '#close-popup', function () {
            // Remove the popup and overlay
            $('#verification-popup, #verification-popup-overlay').remove();
            // Re-enable scrolling
            $('body').css('overflow', 'auto');
        });

        $(document).on('click', '#verify_code', function () {
            var code = $('#verification_code').val();
            var uniqId = $('#uniqId').val();
            $.ajax({
                url: smsVerification.adminurl,
                type: 'POST',
                data: {
                    action: smsVerification.validateCodeAction,
                    code: code,
                    uniqId: uniqId
                },
                success: function (response) {
                    if (response.success) {
                        if (response.data.responseCode == 0) {
                            $('#verification-popup, #verification-popup-overlay').remove();
                            $('form.checkout').submit();
                        } else {
                            alert(response.data.responseDescription);
                        }
                    } else {
                        alert('Invalid code, please try again.');
                    }
                }
            });
        });

        // Resend button click handler
        $(document).on('click', '#resend_code', function () {
            var phone = $('#billing_phone').val();
            $.ajax({
                url: smsVerification.adminurl,
                type: 'POST',
                data: {
                    action: smsVerification.requestCodeAction,
                    billing_phone: phone
                },
                success: function (response) {
                    if (response.success) {
                        if (response.data.responseCode == 0) {
                            alert('A new verification code has been sent.');
                            // Restart the timer for another 3 minutes
                            $('#resend_code').hide();
                            startCountdown(180);
                        } else {

                            alert(response.data.responseDescription);
                        }
                    }
                }
            });
        });

        // Countdown timer function
        function startCountdown(seconds) {
            var timerInterval = setInterval(function () {
                var minutes = Math.floor(seconds / 60);
                var remainingSeconds = seconds % 60;
                if (remainingSeconds < 10) remainingSeconds = '0' + remainingSeconds;
                $('#countdown').text(minutes + ':' + remainingSeconds);

                if (seconds > 0) {
                    seconds--;
                } else {
                    clearInterval(timerInterval);
                    // Show the resend button after the countdown reaches 0
                    $('#resend_code').show();
                    $('#timer').hide();
                }
            }, 1000); // Update the timer every second
        }

    }
});