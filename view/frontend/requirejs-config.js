var config = {
    map: {
        '*': {
            'dhlparcel-shipping-servicepoint': 'DHLParcel_Shipping/js/view/servicepoint-loader'
        }
    },
    'config': {
        'mixins': {
            'Magento_Checkout/js/view/shipping': {
                'DHLParcel_Shipping/js/view/servicepoint-validate-mixin': true,
                'DHLParcel_Shipping/js/view/deliverytimes-validate-mixin': true
            }
        }
    }
};
