define([
    "jquery",
    "Svea_Checkout/js/action/reload-shipping-methods"
], function($, reloadShippingMethods){
    "use strict";

    function main(config, element) {
        $(document).on('change', '.qty input', function() {
            reloadShippingMethods('#shipping-method-form', 2000);
        });
    };
    return main;
});
