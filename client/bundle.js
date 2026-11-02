/*global jQuery, ss, tinymce */
jQuery(function () {
    Autotranslate(jQuery).init();
});

function Autotranslate($) {
    return {
        init: function () {
            var body = $('body');

            body.on('click', '.js-autotranslate', function (event) {
                setTimeout(function () {
                    $(event.target).find('ul').toggle();
                }, 50);
            });

            body.on('click', function (event) {
                if (false === $(event.target).hasClass('js-autotranslate')) {
                    $('.js-autotranslate').find('ul').hide();
                }
            });

            body.on('click', '.js-autotranslate-translate', function (event) {
                event.preventDefault();
                var elements = this.elements(event);
                elements.holder.append('<div class="translate-in-progress js-translate-in-progress"><span>Translating...</span></div>');
                elements.label.find('ul').hide();
                $.ajax({
                    url: 'autotranslate/translate',
                    method: 'POST',
                    dataType: 'text',
                    data: {
                        source: elements.label.data('source-lang'),
                        target: elements.label.data('target-lang'),
                        query: elements.textarea.length > 0 ? elements.textarea.val() : elements.input.val(),
                        // the CMS edit form's security token (the endpoint rejects requests without it)
                        SecurityID: elements.label.closest('form').find('input[name=SecurityID]').val()
                            || $('input[name=SecurityID]').first().val()
                    }
                }).done(function (response) {
                    this.setValue(elements.input, elements.textarea, response);
                }.bind(this)).fail(function (xhr) {
                    // shown as text next to the field (no browser dialog)
                    $('<div class="translate-error"></div>')
                        .text('Autotranslate: ' + (xhr.responseText || 'request failed'))
                        .appendTo(elements.holder).delay(6000).fadeOut(400, function () { $(this).remove(); });
                }).always(function () {
                    elements.holder.find('.js-translate-in-progress').fadeOut(200, function () { $(this).remove(); });
                });
            }.bind(this));

            body.on('click', '.js-autotranslate-reset', function (event) {
                var elements = this.elements(event);
                this.setValue(elements.input, elements.textarea, elements.label.data('value'));
                elements.label.find('ul').hide();
            }.bind(this));

            body.on('click', '.js-autotranslate-revert', function (event) {
                var elements = this.elements(event);
                this.setValue(elements.input, elements.textarea, elements.label.data('source-value'));
                elements.label.find('ul').hide();
            }.bind(this));
        },

        elements: function (event) {
            var $label = $(event.target).closest('.autotranslate');
            var $holder = $label.closest('.form-group').find('.form__field-holder');
            return {
                label: $label,
                holder: $holder,
                input: $holder.find('input'),
                textarea: $holder.find('textarea')
            };
        },

        setValue: function (input, textarea, value) {
            if (textarea.length > 0) {
                textarea.val(value);
                if ('tinyMCE' === textarea.data('editor')) {
                    tinymce.get(textarea.attr('id')).load();
                }
            } else {
                input.val(value);
            }
        }
    };
}