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

        servicePointSelected: ko.observable(false),

        initObservable: function () {
            this._super();

            var self = this;

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

            this.DHLParcel_Shipping_HasServicePoint = ko.computed(function() {
                return self.servicePointSelected()
            })

            $(document).on('dhlparcel_shipping:servicepoint_selection', function(e, servicepoint_id, servicepoint_country, servicepoint_name) {
                if (servicepoint_id == null) {
                    self.servicePointSelected(false)
                }

                self.servicePointSelected(true)
            })

            $(document.body).on('dhlparcel_shipping:servicepoint_validated', function(event, selected) {
                self.servicePointSelected(selected === true)
            })

            return this;
        }
    });
});
