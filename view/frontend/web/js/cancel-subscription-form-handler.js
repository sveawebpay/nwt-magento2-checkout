define([
    'jquery',
    'Magento_Ui/js/modal/confirm',
    'mage/translate',
], function ($, modalconfirm) {
    'use strict';

    return function (config, element) {
        const button = $(config.buttonSelector);
        const confirmButtons = [{
            text: $.mage.__('No, do not cancel'),
            class: 'action-secondary action-dismiss',

            /**
             * Click handler.
             */
            click: function (event) {
                this.closeModal(event);
            }
        }, {
            text: $.mage.__('Yes, cancel the subscription'),
            class: 'action-primary action-accept',

            /**
             * Click handler.
             */
            click: function (event) {
                this.closeModal(event, true);
            }
        }]
        const confirmConfig = {
            content: $.mage.__('Are you sure you wish to cancel this subscription? This cannot be undone!'),
            buttons: confirmButtons,
            actions: {
                confirm: function () {
                    element.submit();
                },
                cancel: function () {
                    return false;
                },
            },
        };

        button.click(function (e) {
            modalconfirm(confirmConfig);
            return false;
        });
    };
});
