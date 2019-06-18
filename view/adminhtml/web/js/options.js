require([
    "jquery",
    "jquery/ui"
], function ($) {

    $.widget('dhlparcel.optionsform', {
        options: {
            container: null,
            enableCheckbox: null,
            baseUrl: '',
            audienceState: '',
            capabilitiesData: {},
            initialized: false
        },

        _create: function () {
            this.options.container = this.element
            this.options.enableCheckbox = $('#create_dhlparcel_shipping_label')
            this.options.baseUrl = this.options.container.attr('data-url-base')
            this._updateState($('.dhlparcel-audience-selector:checked', container))
            this._bind()
        },

        _bind: function () {
            let self = this
            $('.dhlparcel-audience-selector', this.options.container).change(function () {
                self._updateState(this)
            })
            $('.dhlparcel-service-option input,.dhlparcel-delivery-option input', this.options.container).change(function () {
                self._updateExceptions()
            })
            $('#test_button').click(function () {
                self._debug()
            })
            $(this.options.enableCheckbox).change(function () {
                if ($(this).prop('checked')) {
                    $('#dhlparcel-options-container').show();
                    $('#dhlparcel-options-container .dhlparcel-package-selection').prop('required',true)
                } else {
                    $('#dhlparcel-options-container').hide();
                    $('#dhlparcel-options-container .dhlparcel-package-selection').prop('required',false)
                }
            })

            $('.dhlparcel-remove-package').click(function() {
                $(this).closest('.dhlparcel-package').remove()
            })

            $('.dhlparcel-add-package').click(function() {
                $(this).closest('.dhlparcel-package').clone(true, true).appendTo('.dhlparcel-packages')
            })
        },

        _capabilities: function (attribute = null) {
            if (this.options.capabilitiesData[this.options.audienceState]) {
                let capabilities = this.options.capabilitiesData[this.options.audienceState];
                switch (attribute) {
                    case 'options':
                        return capabilities.options
                    case 'products':
                        return capabilities.products
                    default:
                        return capabilities
                }
            } else {
                return false;
            }
        },

        _updateState: function (element) {
            this.options.audienceState = $(element).val()
            if (!this._capabilities()) {
                let self = this
                $.get(this.options.baseUrl + this.options.audienceState, function (data) {
                    self.options.capabilitiesData[self.options.audienceState] = data
                    self._updateElements()
                    if(!self.options.initialized){
                        self.options.initialized = true;
                        $(self.options.enableCheckbox).trigger('change')
                    }
                })
            } else {
                this._updateElements()
            }
        },

        _updateElements: function () {
            let capabilities = this._capabilities('options');
            $('.dhlparcel-delivery-option, .dhlparcel-service-option', this.options.container).each(function () {
                if (capabilities[$(this).attr('data-option')]) {
                    $(this).removeClass('unavailable-option')
                } else {
                    $(this).addClass('unavailable-option')
                    $('input:checked', this).prop('checked', false)
                }
            })
            this._updateExceptions()
        },

        _updateExceptions: function () {
            if (this._capabilities()) {
                let capabilities = this._capabilities('options')
                let exclusions = []
                let packages = this._capabilities('products')
                let self = this
                this._updatePackages()
                $('.dhlparcel-service-option input:checked,.dhlparcel-delivery-option input:checked', this.options.container).each(function () {
                    let option = $(this).prop('value')
                    $.each(capabilities[option].exclusions, function (key, value) {
                        if (!exclusions.includes(value)) {
                            exclusions.push(value)
                        }
                    })
                    $.each(packages, function (key) {
                        if (!capabilities[option].type.includes(key)) {
                            $('.dhlparcel-package-selection option[value="' + key + '"]', self.options.container).remove()
                        }
                    })
                })
                $('.dhlparcel-service-option').each(function () {
                    if (exclusions.includes($(this).attr('data-option'))) {
                        $('input.dhlparcel-service-option', this).prop('checked', false).prop('disabled', true)
                    } else {
                        $('input.dhlparcel-service-option', this).prop('disabled', false)
                    }
                })
                $('input, select','.dhlparcel-step-container .dhlparcel-delivery-options-data').hide();
                $.each($('.dhlparcel-step-container .dhlparcel-delivery-options input[type="radio"]:checked'),function (key,element) {
                    $('.dhlparcel-step-container .dhlparcel-delivery-options-data [data-method="'+$(element).val()+'"]').show();
                });
            }
        },

        _updatePackages: function () {
            let options = '';
            $.each(this._capabilities('products'), function (key, product) {
                options += '<option value="' + product.key + '">' +
                    product.key + ' ' + product.minWeightKg + 'KG - ' + product.maxWeightKg + 'KG (max L' +
                    product.dimensions.maxLengthCm + ' W' + product.dimensions.maxWidthCm + ' H' + product.dimensions.maxHeightCm +
                    ' cm)</option>'
            })

            $('.dhlparcel-package-selection', this.options.container).each(function(){
                let selectedValue = $('option:selected', $(this)).val()

                $(this).html(options)

                if (typeof selectedValue !== 'undefined') {
                    $('option[value="' + selectedValue + '"]', this).attr('selected',' selected')
                }
            })
        },

        _debug: function () {
            console.log(this.options)
        },
    });

    $('#dhlparcel-options-container').optionsform();
});
