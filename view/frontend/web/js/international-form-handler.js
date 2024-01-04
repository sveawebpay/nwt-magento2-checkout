define([
    'jquery',
    'Magento_Ui/js/modal/alert',
], function ($, magealert) {
    'use strict';

    return function (config, element) {
        const countrySelector = $(config.countrySelector);
        const action = element.action;
        countrySelector.change(function () {
            const data = $(element).serialize();
            if (window.scoApi) {
                window.scoApi.setCheckoutEnabled(false);
            }
            $.post(action, data).done(function (response) {
                if (response.reload) {
                    window.location.reload();
                    return;
                }

                if (response.message) {
                    magealert({
                        content: response.message
                    });
                    return;
                }

                if (window.scoApi) {
                    window.scoApi.setCheckoutEnabled(true);
                }
            });
        });
    }
});
