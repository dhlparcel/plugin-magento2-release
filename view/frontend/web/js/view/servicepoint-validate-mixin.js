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
                    if (typeof method !== 'undefined' && method !== null && typeof method.carrier_code !== 'undefined' && typeof method.method_code !== 'undefined') {
                        if (method.carrier_code === 'dhlparcel' && method.method_code === 'servicepoint') {
                            if (window.dhlparcel_shipping_servicepoint_validate !== true) {
                                $('#dhlparcel-shipping-servicepoint-info-error').show();
                                return false;
                            }
                        }
                    }

                    return this._super();
                }
            });
        }
    }
);
