(function ($) {
    function bpointFormHandler() {
        var $form = $('form.checkout, form#order_review');

        if ($('#payment_method_bpoint').is(':checked')) {
            if (0 === $('#result_key').length) {
                $form.block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });
                var bpointCardName = $.trim($("#" + bpoint_checkout_language.id + "-card-holder-name").val());
                var bpointCardNumber = standardizeCreditCardNumber($("#" + bpoint_checkout_language.id + "-card-number").val());
                var bpointCardCvc = standardizeCVN($("#" + bpoint_checkout_language.id + "-card-cvc").val());
                var bpointExMonth = $.trim($("#" + bpoint_checkout_language.id + "-card-month-expiry").val());
                var bpointExYear = $.trim($("#" + bpoint_checkout_language.id + "-card-year-expiry").val());

                $('.woocommerce-error, .woocommerce-message').remove();

                var bpointCheckNull = false;
                // Validate 
                var bpointErrorMessages = {"messages": "<ul class=\"woocommerce-error\">"};
                if (bpointCardName == '') {
                    bpointErrorMessages.messages += "<li>" + bpoint_checkout_language.name_require + "<\/li>";
                    bpointCheckNull = true;
                    bpointValidateField(bpoint_checkout_language.id + "-card-holder-name", false);
                } else {
                    bpointValidateField(bpoint_checkout_language.id + "-card-holder-name", true);
                }
                if (bpointCardNumber == '') {
                    bpointErrorMessages.messages += "<li>" + bpoint_checkout_language.card_require + "<\/li>";
                    bpointCheckNull = true;
                    bpointValidateField(bpoint_checkout_language.id + "-card-number", false);
                } else {
                    if (!validateCreditCardNumber(bpointCardNumber)) {
                        bpointErrorMessages.messages += "<li>" + bpoint_checkout_language.card_valid + "<\/li>";
                        bpointValidateField(bpoint_checkout_language.id + "-card-number", false);
                        bpointCheckNull = true;
                    } else {
                        bpointValidateField(bpoint_checkout_language.id + "-card-number", true);
                    }
                }
                var bpointExpiryDateSelected = true;
                if (bpointExMonth == '') {
                    bpointErrorMessages.messages += "<li>" + bpoint_checkout_language.month_require + "<\/li>";
                    bpointCheckNull = true;
                    bpointValidateField(bpoint_checkout_language.id + "-card-month-expiry", false);
                    bpointExpiryDateSelected = false;
                } else {
                    bpointValidateField(bpoint_checkout_language.id + "-card-month-expiry", true);
                }
                if (bpointExYear == '') {
                    bpointErrorMessages.messages += "<li>" + bpoint_checkout_language.year_require + "<\/li>";
                    bpointCheckNull = true;
                    bpointValidateField(bpoint_checkout_language.id + "-card-year-expiry", false);
                    bpointExpiryDateSelected = false;
                } else {
                    bpointValidateField(bpoint_checkout_language.id + "-card-year-expiry", false);
                }

                if (bpointExpiryDateSelected) {
                    if (!validateExpiryDate(bpointExMonth, bpointExYear)) {
                        bpointErrorMessages.messages += "<li>" + bpoint_checkout_language.date_valid + "<\/li>";
                        bpointCheckNull = true;
                        bpointValidateField(bpoint_checkout_language.id + "-card-month-expiry", false);
                        bpointValidateField(bpoint_checkout_language.id + "-card-year-expiry", false);
                    } else {
                        bpointValidateField(bpoint_checkout_language.id + "-card-month-expiry", true);
                        bpointValidateField(bpoint_checkout_language.id + "-card-year-expiry", true);
                    }
                }
                document.getElementById(bpoint_checkout_language.id + "-card-month-expiry").style.setProperty("font-family", "inherit", "important");
                document.getElementById(bpoint_checkout_language.id + "-card-year-expiry").style.setProperty("font-family", "inherit", "important");
                var nameElement = document.getElementById(bpoint_checkout_language.id + "-card-holder-name"),
                    nameStyle = window.getComputedStyle(nameElement),
                    nameLineHeight = parseInt(nameStyle.getPropertyValue("line-height")),
                    namePadding = parseInt(nameStyle.getPropertyValue("padding-top"));

                var expiryElement = document.getElementById(bpoint_checkout_language.id + "-card-month-expiry"),
                    expiryStyle = window.getComputedStyle(expiryElement),
                    expiryLineHeight = parseInt(expiryStyle.getPropertyValue("line-height"));
                $("#" + bpoint_checkout_language.id + "-card-month-expiry").css("padding", Math.round(namePadding - expiryLineHeight + nameLineHeight + 1) + "px");
                $("#" + bpoint_checkout_language.id + "-card-year-expiry").css("padding", Math.round(namePadding - expiryLineHeight + nameLineHeight + 1) + "px");
                var isValidCvc = false;
                if (bpointCardCvc == '') {
                    bpointErrorMessages.messages += "<li>" + bpoint_checkout_language.cvn_require + "<\/li>";
                    bpointCheckNull = true;
                    bpointValidateField(bpoint_checkout_language.id + "-card-cvc", false);
                } else {
                    isValidCvc = validateCVN(bpointCardCvc, bpointCardNumber);
                    if (!isValidCvc) {
                        bpointErrorMessages.messages += "<li>" + bpoint_checkout_language.cvn_valid + "<\/li>";
                        bpointValidateField(bpoint_checkout_language.id + "-card-cvc", false);
                        bpointCheckNull = true;
                    } else {
                        bpointValidateField(bpoint_checkout_language.id + "-card-cvc", true);
                    }
                }
                bpointErrorMessages.messages += "<\/ul>";

                if ($('#submit_disable').prop('checked')) {
                    $('.woocommerce-error, .woocommerce-message').remove();
                    bpointErrorMessages.messages = "<ul class=\"woocommerce-error\">\n\t\t\t<li>" + bpoint_checkout_language.gateway_disabled + "<\/li>\n\t<\/ul>";
                }

                if (bpointCheckNull) {
                    showError(bpointErrorMessages);
                    return false;
                }

                // Disable card information. 
                $("#" + bpoint_checkout_language.id + "-card-holder-name").prop("disabled", true);
                $("#" + bpoint_checkout_language.id + "-card-number").prop("disabled", true);
                $("#" + bpoint_checkout_language.id + "-card-cvc").prop("disabled", true);
                $("#" + bpoint_checkout_language.id + "-card-expiry").prop("disabled", true);
                if (0 === $("#" + bpoint_checkout_language.id + "-key").length) {
                    $.ajax({
                        type: 'POST',
                        url: bpoint_checkout_language.checkout_url,
                        data: $form.serialize(),
                        success: function (code) {
                            $("#" + bpoint_checkout_language.id + "-card-holder-name").prop("disabled", false);
                            $("#" + bpoint_checkout_language.id + "-card-number").prop("disabled", false);
                            $("#" + bpoint_checkout_language.id + "-card-cvc").prop("disabled", false);
                            $("#" + bpoint_checkout_language.id + "-card-expiry").prop("disabled", false);
                            var result = '';
                            try {
                                if (typeof code != 'object') {
                                    // Get the valid JSON only from the returned string
                                    if (code.indexOf('<!--WC_START-->') >= 0)
                                        code = code.split('<!--WC_START-->')[1]; // Strip off before after WC_START
                                    if (code.indexOf('<!--WC_END-->') >= 0)
                                        code = code.split('<!--WC_END-->')[0]; // Strip off anything after WC_END
                                    // Parse
                                    result = $.parseJSON(code);
                                } else {
                                    result = code;
                                }
                                if (result.result === 'success') {
                                    //Ajax process payment by BPOINT
                                    if (bpointExYear.length > 2) {
                                        bpointExYear = bpointExYear.substring(2);
                                    }
                                    var redirect_url = result.redirect_url;
                                    BPOINT.txn.authkey.attachPaymentMethod(result.auth_key,
                                        {
                                            card: {
                                                number: bpointCardNumber,
                                                expiry: {
                                                    month: bpointExMonth,
                                                    year: bpointExYear
                                                },
                                                name: bpointCardName,
                                                cvn: bpointCardCvc
                                            }        
                                        },
                                        function (code, data) {
                                            if (code === 'success') {
                                                window.location = redirect_url;
                                            } else {
                                                text = '<ul class=\"woocommerce-error\"><li>' + data.message + '<\/li><\/ul>';
                                                var result = {"messages": text};
                                                showError(result);
                                            }
                                        }
                                    );
                                } else if (result.result === 'failure') {
                                    throw bpoint_checkout_language.result_fail;
                                } else {
                                    throw  bpoint_checkout_language.invalid_res;
                                }
                            }
                            catch (err) {
                                console.error(err.stack);
                                showError(result);
                            }
                        }
                    })
                }
                else {
                    var key = $.trim($("#" + bpoint_checkout_language.id + "-key").val());
                    $.ajax({
                        type: 'POST',
                        url: bpoint_checkout_language.checkout_url,
                        data: {action: "bpoint_order_review", key: key},
                        success: function (code) {
                            $("#" + bpoint_checkout_language.id + "-card-holder-name").prop("disabled", false);
                            $("#" + bpoint_checkout_language.id + "-card-number").prop("disabled", false);
                            $("#" + bpoint_checkout_language.id + "-card-cvc").prop("disabled", false);
                            $("#" + bpoint_checkout_language.id + "-card-expiry").prop("disabled", false);
                            var result = '';
                            try {
                                if (typeof code != 'object') {
                                    // Get the valid JSON only from the returned string
                                    if (code.indexOf('<!--WC_START-->') >= 0)
                                        code = code.split('<!--WC_START-->')[1]; // Strip off before after WC_START
                                    if (code.indexOf('<!--WC_END-->') >= 0)
                                        code = code.split('<!--WC_END-->')[0]; // Strip off anything after WC_END
                                    // Parse
                                    result = $.parseJSON(code);
                                } else {
                                    result = code;
                                }
                                if (result.result === 'success') {
                                    //Ajax process payment by BPOINT
                                    if (bpointExYear.length > 2) {
                                        bpointExYear = bpointExYear.substring(2);
                                    }
                                    var redirect_url = result.redirect_url;
                                    BPOINT.txn.authkey.attachPaymentMethod(result.auth_key,
                                        {
                                            card: {
                                                number: bpointCardNumber,
                                                expiry: {
                                                    month: bpointExMonth,
                                                    year: bpointExYear
                                                },
                                                name: bpointCardName,
                                                cvn: bpointCardCvc
                                            }        
                                        },
                                        function (code, data) {
                                            if (code === 'success') {
                                                window.location = redirect_url;
                                            } else {
                                                text = '<ul class=\"woocommerce-error\"><li>' + data.message + '<\/li><\/ul>';
                                                var result = {"messages": text};
                                                showError(result);
                                            }
                                        }
                                    );
                                } else if (result.result === 'failure') {
                                    throw bpoint_checkout_language.result_fail;
                                } else {
                                    throw  bpoint_checkout_language.invalid_res;
                                }
                            }
                            catch (err) {
                                console.error(err.stack);
                                showError(result);
                            }
                        }
                    })
                }
                // Prevent the form from submitting
                return false;
            }
        }
        return true;
    }

    function showError(result) {
        var $form = $('form.checkout, form#order_review');
        // Remove old errors
        $('.woocommerce-error, .woocommerce-message').remove();
        // Add new errors
        if (result && result.messages) {
            $form.prepend(result.messages);
        } else {

            $form.prepend('<ul class=\"woocommerce-error\">\n\t\t\t<li>Error.<\/li>\n\t<\/ul>\n');
        }
        // Cancel processing
        $form.removeClass('processing').unblock();
        // Lose focus for all fields
        $form.find('.input-text, select').blur();
        // Scroll to top
        $('html, body').animate({
            scrollTop: ($form.offset().top - 100)
        }, 1000);
        // Trigger update in case we need a fresh nonce
        $('body').trigger('checkout_error');
    }

    function validateCreditCardNumber(CardNumber) {
        var a = standardizeCreditCardNumber(CardNumber);
        var b;
        if (b = a.length >= 10) {
            if (b = a.length <= 16) {
                a = a;
                if (/[^0-9-\s]+/.test(a))
                    b = false;
                else {
                    b = 0;
                    var d, e = false;
                    a = a.replace(/\D/g, "");
                    for (var h = a.length - 1; h >= 0; h--) {
                        d = a.charAt(h);
                        d = parseInt(d, 10);
                        if (e)
                            if ((d *= 2) > 9)
                                d -= 9;
                        b += d;
                        e = !e
                    }
                    b = b % 10 == 0
                }
            }
            b = b
        }
        //return result (true/false)
        return b;
    }

    function validateCVN(CVN, CardNumber) {
        var cardType = getCreditCardType(CardNumber);
        var a = standardizeCVN(CVN);
        //return result (true/false)
        if (cardType == '') {
            return /^\d+$/.test(a) && a.length >= 3 && a.length <= 4;
        }
        if (cardType == 'AE') {
            return /^\d+$/.test(a) && a.length == 4;
        } else {
            return /^\d+$/.test(a) && a.length == 3;
        }

    }

    function getCreditCardType(CardNumber) {
        /* 
         * Return Credit Card Type
         * MC: Master Card
         * VC: Visa
         * AE: American Express
         * DC Diners Club
         * JC: JCB
         * empty string for others
         */
        var a = standardizeCreditCardNumber(CardNumber);
        if (new RegExp('^5[1-5][0-9]{14}$').test(a)) {
            return 'MC';
        }
        if (new RegExp('^4[0-9]{12}([0-9]{3})?$').test(a)) {
            return 'VC';
        }
        if (new RegExp('^3[47][0-9]{13}$').test(a)) {
            return 'AE';
        }
        if (new RegExp('^(30[0-5,9][0-9]{11}|3[689][0-9]{12}|5[45][0-9]{14})$').test(a)) {
            return 'DC';
        }
        if (new RegExp('^35(2[89][0-9]{12}$|[3-8][0-9]{13}$)').test(a)) {
            return 'JC';
        }
        return '';
    }

    function validateExpiryDate(ExpiryMonth, ExpiryYear) {
        var d, e;
        var a = ExpiryMonth;
        var b = ExpiryYear;
        if (/^\d+$/.test(a) == false)
            return false;
        if (/^\d+$/.test(b) == false)
            return false;
        if (parseInt(a, 10) <= 12 == false)
            return false;
        e = new Date(b, a);
        d = new Date;
        e.setMonth(e.getMonth() - 1);
        e.setMonth(e.getMonth() + 1, 1);
        return e > d
    }

    function standardizeCreditCardNumber(CardNumber) {
        //remove space and "-"
        return (CardNumber + "").replace(/\s+|-/g, "");
    }

    function standardizeCVN(CVN) {
        //remove space of start and end
        return (CVN + "").replace(/^\s+|\s+$/g, "");
    }

    $(function () {
        $('body').on('checkout_error', function () {
            $('#result_key').remove();
        });

        /* Checkout Form */
        $('form.checkout').on('checkout_place_order_bpoint', function () {
            return bpointFormHandler();
        });

        /* Pay Page Form */
        $('form#order_review').on('submit', function () {
            return bpointFormHandler();
        });

        /* Both Forms */
        $('form.checkout, form#order_review').on('change', '#bpoint-cc-form input', function () {
            $('#result_key').remove();
        });
    });
    function bpointValidateField(field, validated) {
        if (validated) {
            $('#' + field).copyCSS("#bpoint-validated-field", null, ['display']);
        } else {
            $('#' + field).copyCSS("#bpoint-invalid-field", null, ['display']);
        }
    }

    !function (e) {
        e.fn.getStyles = function (e, t) {
            var r, n, i = {};
            if (e && e instanceof Array)for (var f = 0, o = e.length; o > f; f++)n = e[f], i[n] = this.css(n); else if (this.length) {
                var l = this.get(0);
                if (window.getComputedStyle) {
                    var s = /\-([a-z])/g, a = function (e, t) {
                        return t.toUpperCase()
                    }, u = function (e) {
                        return e.replace(s, a)
                    };
                    if (r = window.getComputedStyle(l, null)) {
                        var c, g;
                        if (r.length)for (var f = 0, o = r.length; o > f; f++)n = r[f], c = u(n), g = r.getPropertyValue(n), i[c] = g; else for (n in r)c = u(n), g = r.getPropertyValue(n) || r[n], i[c] = g
                    }
                } else if (r = l.currentStyle)for (n in r)i[n] = r[n]; else if (r = l.style)for (n in r)"function" != typeof r[n] && (i[n] = r[n])
            }
            if (t && t instanceof Array)for (var f = 0, o = t.length; o > f; f++)n = t[f], delete i[n];
            return i
        }, e.fn.copyCSS = function (t, r, n) {
            var i = e(t).getStyles(r, n);
            return this.css(i), this
        }
    }(jQuery);
}(jQuery));
