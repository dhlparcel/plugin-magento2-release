define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/url',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/model/shipping-rate-registry',
    'Magento_Checkout/js/model/shipping-rate-processor/new-address',
    'Magento_Checkout/js/model/shipping-rate-processor/customer-address'
], function ($, ko, Component, urlBuilder, quote, selectShippingMethodAction, rateRegistry, defaultProcessor, customerAddressProcessor) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'DHLParcel_Shipping/deliveryoptions-info'
        },

        /** DeliveryTimes **/
        timesEnabled: ko.observable(false),

        allTimes: ko.observable(null),
        dayTimes:  ko.observable(null),
        nightTimes: ko.observable(null),

        selectedTime: ko.observable(),

        /** DeliveryServices **/
        serviceData: ko.observable([]),
        selectedServices: ko.observableArray([]),
        excludedServices: ko.observableArray([]),
        excludedServiceData: ko.observableArray([]),

        initObservable: function () {
            this._super();

            var self = this;
            var postcode_memory = null;
            var country_memory = null;
            var services_memory = null;

            window.dhlparcel_shipping_services_current_request_sequence = 0;
            window.dhlparcel_shipping_services_current_request_sequence_validation = 0;
            window.dhlparcel_shipping_services_current_request_timeout = 0;

            quote.shippingAddress.subscribe(function () {
                if (typeof quote.shippingAddress() === 'undefined' || quote.shippingAddress() === null) {
                    return;
                }

                if (country_memory !== quote.shippingAddress().countryId) {
                    var data = {
                        'postcode': quote.shippingAddress().postcode,
                        'country': quote.shippingAddress().countryId
                    };
                    $.post(urlBuilder.build('dhlparcel_shipping/deliveryservices/available'), data, function (response) {
                        try {
                            var data = response.data;
                        } catch (error) {
                            console.log(error);
                            return;
                        }

                        // Add computed value for 'enable'
                        data.serviceData.forEach(function(part, index) {
                            data.serviceData[index].enable = ko.computed(function() {
                                return !self.excludedServices().includes(data.serviceData[index].value);
                            });
                        });

                        window.dhlparcel_shipping_deliveryservices_enabled = data.enabled;
                        self.serviceData(data.serviceData);
                        self.excludedServiceData(data.excludedServiceData);
                        self.selectedServices(data.selectedServices);
                    }, 'json');
                }

                if (postcode_memory === quote.shippingAddress().postcode) {
                    if (country_memory === quote.shippingAddress().countryId) {
                        return;
                    }
                }

                postcode_memory = quote.shippingAddress().postcode;
                country_memory = quote.shippingAddress().countryId;

                // Don't do a delivery times API call without a postcode
                if (quote.shippingAddress().postcode === null || quote.shippingAddress().postcode === '') {
                    return;
                }

                var times_data = {
                    'postcode': quote.shippingAddress().postcode,
                    'country': quote.shippingAddress().countryId
                };

                $.post(urlBuilder.build('dhlparcel_shipping/deliverytimes/times'), times_data, function (response) {
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

            /**
             * Delivery Times
             */
            $.post(urlBuilder.build('dhlparcel_shipping/deliverytimes/enabled'), {}, function (response) {
                try {
                    var data = response.data;
                } catch (error) {
                    console.log(error);
                    return;
                }

                self.timesEnabled(data);
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

                if (typeof quote.shippingAddress() === 'undefined' || quote.shippingAddress() === null) {
                    return;
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

                // Service logic
                if (self.showDeliveryServices()) {
                    return !self.selectedServices().includes('EVE');
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

                // Service logic
                if (self.showDeliveryServices()) {
                    return self.selectedServices().includes('EVE');
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

            /**
             * Delivery Services
             */
            this.showDeliveryServices = ko.computed(function() {

                var method = quote.shippingMethod();
                if (method === null) {
                    return false;
                }

                if (method.carrier_code !== 'dhlparcel') {
                    return false;
                }

                if (typeof quote.shippingAddress() === 'undefined' || quote.shippingAddress() === null) {
                    return;
                }

                if (method.method_code !== 'door') {
                    return false;
                }

                return (self.serviceData().length > 0);
            }, this);

            this.excludedServices = ko.computed(function() {
                var excludedList = [];
                var selected = [];
                var excluded = [];
                self.selectedServices().forEach(function(item, index) {
                    if (typeof self.excludedServiceData()[item] !== 'undefined') {
                        selected.push(item);
                        excludedList = excludedList.concat(self.excludedServiceData()[item]);
                    }
                });
                // Check if service is in the excluded list, if so, do not use it's excludedData
                selected.forEach(function(item, index) {
                    if (!excludedList.includes(item)) {
                        excluded = excluded.concat(self.excludedServiceData()[item]);
                    }
                });
                return excluded;
            });

            this.selectedServices.subscribe(function(latest) {
                var selected = self.selectedServices();
                var excluded = self.excludedServices();
                var services = [];

                selected.forEach(function(item, index) {
                    if (!excluded.includes(item)) {
                        services.push(item);
                    }
                });

                var data = {
                    'services': services
                };

                var sequence = ++window.dhlparcel_shipping_services_current_request_sequence;

                window.dhlparcel_shipping_services_current_request_timeout = setTimeout(function() {
                    $(document.body).trigger('dhlparcel_shipping:delivery_services_sync', [data, sequence]);
                }, 2000);
            });

            $(document.body).on('dhlparcel_shipping:delivery_services_sync', function(e, data, sequence) {
                if (sequence !== window.dhlparcel_shipping_services_current_request_sequence) {
                    // Request has been replaced, ignore this input
                    return;
                }

                if (sequence === 1 && $('.iosc-place-order-button').length === 0) {
                    // Ignore the first sequence as it only double refreshes. The first one is always called due to the subscription
                    // But make an exception for Onestepcheckout

                    // Update sequence for this skip
                    window.dhlparcel_shipping_services_current_request_sequence_validation = sequence;
                    return;
                }

                data.sequence = sequence;

                $.post(urlBuilder.build('dhlparcel_shipping/deliveryservices/sync'), data, function (response) {
                    try {
                        var data = response.data;
                    } catch (error) {
                        console.log(error);
                    }
                    if (sequence !== window.dhlparcel_shipping_services_current_request_sequence) {
                        // Request has been replaced, ignore this output
                        return;
                    }

                    window.dhlparcel_shipping_services_current_request_sequence_validation = data.sequence;
                    $('#dhlparcel-shipping-deliveryservices-info-error').hide();

                    /* Update methods */
                    var processors = [];
                    rateRegistry.set(quote.shippingAddress().getCacheKey(), null);
                    processors.default =  defaultProcessor;
                    processors['customer-address'] = customerAddressProcessor;
                    var type = quote.shippingAddress().getType();
                    if (processors[type]) {
                        processors[type].getRates(quote.shippingAddress());
                    } else {
                        processors.default.getRates(quote.shippingAddress());
                    }
                }, 'json');
            });

            return this;
        }
    });
});
