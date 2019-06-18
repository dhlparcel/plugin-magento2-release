define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/url',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-shipping-method'
], function ($, ko, Component, urlBuilder, quote, selectShippingMethodAction) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'DHLParcel_Shipping/deliverytimes-info'
        },

        allTimes: ko.observable(null),
        dayTimes:  ko.observable(null),
        nightTimes: ko.observable(null),

        isEnabled: ko.observable(false),

        selectedTime: ko.observable(),

        initObservable: function () {

            var self = this;
            var postcode_memory = null;
            var country_memory = null;

            quote.shippingAddress.subscribe(function () {

                if (postcode_memory === quote.shippingAddress().postcode) {
                    if (country_memory === quote.shippingAddress().countryId) {
                        return;
                    }
                }

                postcode_memory = quote.shippingAddress().postcode;
                country_memory = quote.shippingAddress().countryId;

                var data = {
                    'postcode': quote.shippingAddress().postcode,
                    'country': quote.shippingAddress().countryId
                };

                $.post(urlBuilder.build('dhlparcel_shipping/deliverytimes/times'), data, function (response) {
                    try {
                        var data = response.data;
                    } catch (error) {
                        console.log(error);
                        return;
                    }

                    self.allTimes([]);
                    self.allTimes(data.allTimes);

                    self.dayTimes([]);
                    self.dayTimes(data.dayTimes);

                    self.nightTimes([]);
                    self.nightTimes(data.nightTimes);

                    if (
                        self.allTimes() === null ||
                        self.allTimes().length === 0 ||
                        self.dayTimes() === null ||
                        self.dayTimes().length === 0 ||
                        self.nightTimes() === null ||
                        self.nightTimes().length === 0
                    ) {
                        // Skip extra validation if there are missing times, to prevent blocking orders
                        window.dhlparcel_shipping_deliverytimes_validated = true;
                    }

                }, 'json');
            });

            $.post(urlBuilder.build('dhlparcel_shipping/deliverytimes/enabled'), {}, function (response) {
                try {
                    var data = response.data;
                } catch (error) {
                    console.log(error);
                    return;
                }

                self.isEnabled(data);
                window.dhlparcel_shipping_deliverytimes_enabled = data;
            }, 'json');

            this.showDeliveryTimes = ko.computed(function() {

                var method = quote.shippingMethod();
                if (method === null) {
                    return false;
                }

                if (method.carrier_code !== 'dhlparcel') {
                    return false;
                }

                if (quote.shippingAddress().countryId !== 'NL') {
                    return false;
                }

                if (self.allTimes() === null || self.allTimes().length === 0) {
                    return false;
                }

                return (
                    method.method_code === 'door' ||
                    method.method_code === 'evening' ||
                    method.method_code === 'no_neighbour' ||
                    method.method_code === 'no_neighbour_evening'
                );

            }, this);

            this.showDayTimes = ko.computed(function() {

                var method = quote.shippingMethod();
                if (method === null) {
                    return false;
                }

                if (method.carrier_code !== 'dhlparcel') {
                    return false;
                }

                return (
                    method.method_code === 'door' ||
                    method.method_code === 'no_neighbour'
                );

            }, this);

            this.showNightTimes = ko.computed(function() {

                var method = quote.shippingMethod();
                if (method === null) {
                    return false;
                }

                if (method.carrier_code !== 'dhlparcel') {
                    return false;
                }

                return (
                    method.method_code === 'evening' ||
                    method.method_code === 'no_neighbour_evening'
                );

            }, this);

            this.selectedTime.subscribe(function(latest) {
                if (typeof latest !== 'undefined') {
                    var data = {
                        'identifier': latest.identifier,
                        'date': latest.source.deliveryDate,
                        'startTime': latest.source.startTime,
                        'endTime': latest.source.endTime
                    };
                } else {
                    var data = {
                        'identifier': null,
                        'date': null,
                        'startTime': null,
                        'endTime': null
                    };
                }

                $.post(urlBuilder.build('dhlparcel_shipping/deliverytimes/sync'), data, function (response) {
                    try {
                        var data = response.data;
                    } catch (error) {
                        console.log(error);
                    }

                    window.dhlparcel_shipping_deliverytimes_validated = data;
                    $('#dhlparcel-shipping-deliverytimes-info-error').hide();
                }, 'json');
            }, this);

            return this;
        }
    });
});
