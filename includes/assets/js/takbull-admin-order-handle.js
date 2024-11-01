jQuery(document).ready(function () {
    jQuery('#wpbody').on('click', '#doaction, #doaction2', function () {
        var action = jQuery(this).is('#doaction') ? jQuery('#bulk-action-selector-top').val() : jQuery('#bulk-action-selector-bottom').val();
        form = jQuery('#posts-filter');
        if ('charge_orders' === action) {
            jQuery(":submit").attr("disabled", true);
            if (window.confirm("Selected orders will be charged")) {
                form.get(0).submit();
            } else {
                jQuery(":submit").removeAttr("disabled");
                return false;
            }
        }
    });
});
