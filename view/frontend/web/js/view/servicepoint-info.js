define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote'
], function ($, ko, Component, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'DHLParcel_Shipping/servicepoint-info'
        },

        initObservable: function () {

            this.DHLParcel_Shipping_SelectedMethod = ko.computed(function() {

                var method = quote.shippingMethod();
                if (method === null) {
                    return null;
                }

                return method.carrier_code + '_' + method.method_code;

            }, this);

            this.DHLParcel_Shipping_ServicePointName = ko.computed(function() {

                var method = quote.shippingMethod();
                if (method === null) {
                    return null;
                }

                return method.method_title;

            }, this);

            return this;
        }
    });
});
