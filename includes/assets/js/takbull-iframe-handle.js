
var shrinked_iframe_data;

var iframe = jQuery('#wc_takbull_iframe');

// Create browser compatible event handler.
var eventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
var eventer = window[eventMethod];
var messageEvent = eventMethod == "attachEvent" ? "onmessage" : "message";
// Listen for a message from the iframe.
eventer(messageEvent, function (e) {
    if (isNaN(parseInt(e.data))) return;
    document.getElementById('wc_takbull_iframe').style.height = e.data + 50 + 'px';

}, false);

jQuery(document).ready(function () {
    jQuery('.loading').hide();
    jQuery('html, body').animate({
        scrollTop: jQuery('#wc_takbull_iframe').offset().top
    }, 'slow');
});