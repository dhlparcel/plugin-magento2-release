define([
        'jquery',
        'ko',
        'Magento_Checkout/js/model/quote'
    ], function ($, ko, quote) {
        'use strict';

        return function (shippingAction) {
            return shippingAction.extend({
                errorDeliveryValidationMessage: ko.observable(false),
                validateShippingInformation: function () {
                    var method = quote.shippingMethod();
                    if (method !== null) {
                        if (method.carrier_code === 'dhlparcel') {
                            if (method.method_code === 'door') {
                                if (window.dhlparcel_shipping_deliveryservices_enabled === true) {
                                    if (window.dhlparcel_shipping_services_current_request_sequence_validation !== window.dhlparcel_shipping_services_current_request_sequence) {
                                        $('#dhlparcel-shipping-deliveryservices-info-error').show();
                                        return false;
                                    }
                                }
                            }
                        }
                    }

                    return this._super();
                }
            });
        }
    }
);
