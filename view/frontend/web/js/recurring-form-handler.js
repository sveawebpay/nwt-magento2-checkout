define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        const recurringToggle = $(config.toggleRecurringSelector);
        const frequencyOptionSelector = $(config.frequencyOptionSelector);
        const action = element.action;
        recurringToggle.change(function () {
            $(element).submit();
        });

        frequencyOptionSelector.change(function () {
            const data = $(element).serialize() + '&frequency_only=1';
            $.post(action, data);
        });
    }
});
