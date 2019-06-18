define([
        'uiComponent',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/shipping-rates-validation-rules',
        'DHLParcel_Shipping/js/model/dhlparcel-validator',
        'DHLParcel_Shipping/js/model/dhlparcel-rules'
    ], function (
        Component,
        defaultShippingRatesValidator,
        defaultShippingRatesValidationRules,
        shippingRatesValidator,
        shippingRatesValidationRules
    ) {
        'use strict';
        defaultShippingRatesValidator.registerValidator('dhlparcel', shippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('dhlparcel', shippingRatesValidationRules);
        return Component;
    }
);
