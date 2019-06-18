define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-rate-registry',
    'Magento_Ui/js/modal/modal',
    'mage/url',
    'Magento_Checkout/js/model/shipping-rate-processor/new-address',
    'Magento_Checkout/js/model/shipping-rate-processor/customer-address',
], function($, quote, rateRegistry, modal, urlBuilder, defaultProcessor, customerAddressProcessor) {
    return function(config, element) {
        var dhlparcel_shipping_servicepoint_confirm_button_loaded = false;
        var dhlparcel_shipping_servicepoint_confirm_button = null;
        var dhlparcel_shipping_servicepoint_modal_loading_busy = false;
        var dhlparcel_shipping_servicepoint_modal_loaded = false;
        var dhlparcel_shipping_servicepoint_selected = false;
        var dhlparcel_shipping_servicepoint_postcode_memory = null;
        var dhlparcel_shipping_servicepoint_country_memory = null;

        $(document.body).on('dhlparcel_shipping:load_servicepoint_modal', function(e) {
            if (dhlparcel_shipping_servicepoint_modal_loaded === true) {
                return;
            }

            /* Prevent loading additional times while loading by checking if it's busy loading */
            if (dhlparcel_shipping_servicepoint_modal_loading_busy === true) {
                return;
            }

            dhlparcel_shipping_servicepoint_modal_loading_busy = true;

            /* Preload the confirm button html */
            $(document.body).trigger("dhlparcel_shipping:load_servicepoint_component_confirm_button");

            $.post(urlBuilder.build('dhlparcel_shipping/servicepoint/content'), {}, function (response) {
                try {
                    var view = response.data.view;
                } catch (error) {
                    console.log(error);
                    return;
                }

                $(document.body).append(view);

                /* Init modal */
                $('#dhlparcel-shipping-modal-content').modal({
                    modalClass: 'dhlparcel-shipping-modal',
                    title:  'ServicePoint',
                    buttons: []
                });

                // Create selection function
                window.dhlparcel_shipping_select_servicepoint = function(event)
                {
                    $(document.body).trigger("dhlparcel_shipping:add_servicepoint_component_confirm_button");
                    $(document.body).trigger("dhlparcel_shipping:servicepoint_selection", [event.id, event.address.countryCode, event.name]);
                };

                // Disable getScript from adding a custom timestamp
                // $.ajaxSetup({cache: true});
                $.getScript("https://servicepoint-locator.dhlparcel.nl/servicepoint-locator.js").done(function() {
                    dhlparcel_shipping_servicepoint_modal_loaded = true;
                    dhlparcel_shipping_servicepoint_modal_loading_busy = false;
                });

            }, 'json');

        }).on('dhlparcel_shipping:load_servicepoint_component_confirm_button', function(e) {
            $.post(urlBuilder.build('dhlparcel_shipping/servicepoint/confirmbutton'), function (response) {
                try {
                    var view = response.data.view;
                } catch (error) {
                    console.log(error);
                    return;
                }

                dhlparcel_shipping_servicepoint_confirm_button = view;
                dhlparcel_shipping_servicepoint_confirm_button_loaded = true;
            });

        }).on('dhlparcel_shipping:add_servicepoint_component_confirm_button', function(e) {
            if (dhlparcel_shipping_servicepoint_confirm_button_loaded == false) {
                return;
            }

            if ($('.dhl-parcelshop-locator .dhl-parcelshop-locator-desktop ul .dhlparcel-shipping-servicepoint-component-confirm-button').length === 0) {
                $('.dhl-parcelshop-locator .dhl-parcelshop-locator-desktop ul').prepend(dhlparcel_shipping_servicepoint_confirm_button);
            }

        }).on('click', '.dhlparcel-shipping-servicepoint-component-confirm-button', function(e) {
            e.preventDefault();
            $('#dhlparcel-shipping-modal-content').modal('closeModal');

        }).on('dhlparcel_shipping:show_servicepoint_modal', function(e) {
            // Do nothing if the base modal hasn't been loaded yet.
            if (dhlparcel_shipping_servicepoint_modal_loaded !== true) {
                console.log('An unexpected error occured. ServicePoint component is not loaded yet.');
                return;
            }

            if (typeof  window.dhlparcel_shipping_reset_servicepoint === "function") {
                var countryId = quote.shippingAddress().countryId;
                var postcode = quote.shippingAddress().postcode;

                var options = {
                    limit: 7,
                    countryCode: countryId,
                    query: postcode
                };

                // Use the generated function provided by the component to load the ServicePoints
                window.dhlparcel_shipping_reset_servicepoint(options);

                $('#dhlparcel-shipping-modal-content').modal('openModal').on('modalclosed', function () {
                    $(document.body).trigger('dhlparcel_shipping:check_servicepoint_selection');
                });
            } else {
                console.log('An unexpected error occured. ServicePoint functions were not loaded.');
            }

        }).on('dhlparcel_shipping:check_servicepoint_selection', function(e) {
            if (dhlparcel_shipping_servicepoint_selected != true) {
                // No servicepoint is selected, deselect shipping method
                quote.shippingMethod(null);
            }

        }).on('dhlparcel_shipping:servicepoint_selection', function(e, servicepoint_id, servicepoint_country, servicepoint_name) {
            if (servicepoint_id == null) {
                dhlparcel_shipping_servicepoint_selected = false;
                return;
            }

            dhlparcel_shipping_servicepoint_selected = true;

            var data = {
                'servicepoint_id': servicepoint_id,
                'servicepoint_country': servicepoint_country,
                'servicepoint_name': servicepoint_name,
                'servicepoint_postcode': quote.shippingAddress().postcode
            };

            $.post(urlBuilder.build('dhlparcel_shipping/servicepoint/sync'), data, function (response) {
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

                try {
                    var data = response.data;
                } catch (error) {
                    console.log(error);
                    return;
                }
                window.dhlparcel_shipping_servicepoint_validate = data;
                $('#dhlparcel-shipping-servicepoint-info-error').hide();
            });

        }).on('click', '#dhlparcel-shipping-servicepoint-button', function(e) {
            e.preventDefault();
            $(document.body).trigger('dhlparcel_shipping:show_servicepoint_modal');

        }).on('dhlparcel_shipping:servicepoint_validate', function(e) {
            $.post(urlBuilder.build('dhlparcel_shipping/servicepoint/validate'), function (response) {
                try {
                    var data = response.data;
                } catch (error) {
                    console.log(error);
                    return;
                }
                window.dhlparcel_shipping_servicepoint_validate = data;
                $('#dhlparcel-shipping-servicepoint-info-error').hide();
            });

        });

        // Preload modal, since it's loaded dynamically (hidden DOM defaults)
        $(document.body).trigger('dhlparcel_shipping:load_servicepoint_modal');

        // Save shipping method to global
        quote.shippingMethod.subscribe(function(method) {
            if (method === null) {
                return;
            }

            var method_name_check = method.carrier_code + '_' + method.method_code;
            var method_full_name_check =  method_name_check + '_' + quote.shippingAddress().postcode + '_' + quote.shippingAddress().countryId;

            // Added a memory check, due to a double firing bug of Magento2 of this event
            if (window.dhlparcel_shipping_selected_shipping_method === method_full_name_check) {
                return;
            }

            if (method_name_check === 'dhlparcel_servicepoint') {
                $(document.body).trigger('dhlparcel_shipping:servicepoint_validate');
            }

            window.dhlparcel_shipping_selected_shipping_method = method_full_name_check;

        }, null, 'change');

        // Always validate just to be sure the servicepoint validation is not out of sync
        $(document.body).trigger('dhlparcel_shipping:servicepoint_validate');

    };
});
