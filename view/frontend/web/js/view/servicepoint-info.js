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
            this._super();

            this.DHLParcel_Shipping_SelectedMethod = ko.computed(function() {

                var method = quote.shippingMethod();
                if (typeof method === 'undefined' || method === null || typeof method.carrier_code === 'undefined' || typeof method.method_code === 'undefined') {
                    return null;
                }

                return method.carrier_code + '_' + method.method_code;

            }, this);

            this.DHLParcel_Shipping_ServicePointName = ko.computed(function() {

                var method = quote.shippingMethod();
                if (typeof method === 'undefined' || method === null || typeof method.method_title === 'undefined') {
                    return null;
                }

                return method.method_title;

            }, this);

            return this;
        }
    });
});
