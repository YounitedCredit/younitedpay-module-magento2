/**
 * Copyright since 2022 Younited Credit
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to tech@202-ecommerce.com so we can send you a copy immediately.
 *
 * @author     202 ecommerce <tech@202-ecommerce.com>
 * @copyright 2022 Younited Credit
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License (AFL 3.0)
 */
define([
    'ko',
    'jquery',
    'mage/translate',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/totals',
    'Magento_Checkout/js/action/get-totals',
    'Magento_Checkout/js/action/get-payment-information',
    'mage/url',
    'Magento_Checkout/js/model/full-screen-loader'
], function (ko, $, $t, Component, quote, totals, getTotalsAction, getPaymentInformationAction, url, fullScreenLoader) {
    'use strict';

    return Component.extend({
        redirectAfterPlaceOrder: false,
        maturities: null,
        currentTotal: null,
        phoneError: window.checkoutConfig.payment.younited.phoneError,
        defaults: {
            template: 'YounitedCredit_YounitedPay/payment/younited'
        },

        /**
         * Select maturity
         *
         * @param installment
         */
        selectMaturity: function (installment) {
            $('.yp-info').hide()
            $('#yp-info-' + installment).show()
            setTimeout(function () {
                $('#mat_' + installment).prop("checked", true);
            }, 50)

            var indicative = quote.shippingAddress().telephone
                .replace('.', '')
                .replace(' ', '')
                .slice(0, 3)

            if (indicative != window.checkoutConfig.payment.younited.phoneAreaCode) {
                $('.yp-error').show()
                $('#yp-checkout').prop("disabled", true);
            } else {
                $('.yp-error').hide()
                $('#yp-checkout').prop("disabled", false);
            }
        },

        /**
         * Get Phone Error Message
         */
        getPhoneError: function () {
            return this.phoneError;
        },

        /**
         * Get maturity list
         */
        getMaturities: function () {
            var _this = this;
            var feesMessage = $.mage.__('Pay in %1 times (for %2€/month) with');
            var feesWithoutMessage = $.mage.__('Pay in %1 times without fees (for %2€/month) with');

            if (this.currentTotal != totals.totals().grand_total) {
                this.currentTotal = totals.totals().grand_total

                var url = window.checkoutConfig.payment.younited.url
                    + 'amount/' + this.currentTotal + '/store/'
                    + window.checkoutConfig.payment.younited.store + '/'

                $.ajax({
                    'url': url,
                    'type': 'POST',
                    'success': function (data) {
                        window.checkoutConfig.payment.younited.maturities = {}
                        for (const installment in data) {
                            window.checkoutConfig.payment.younited.maturities[installment] = data[installment];
                        }

                        if (Object.keys(window.checkoutConfig.payment.younited.maturities).length > 0) {
                            $('#yp-method').show();
                        } else {
                            $('#yp-method').hide();
                        }

                        _this.maturities = [];
                        for (const installment in window.checkoutConfig.payment.younited.maturities) {
                            var maturity = window.checkoutConfig.payment.younited.maturities[installment];
                            var monthlyInstallmentAmount = maturity.monthlyInstallmentAmount.toFixed(2);
                            maturity.installment = parseInt(installment);

                            maturity.annualDebitRate = maturity.annualDebitRate;
                            maturity.annualPercentageRate = maturity.annualPercentageRate;

                            var feesTxt = maturity.annualDebitRate ? feesMessage : feesWithoutMessage;
                            maturity.subTitle = `<span>` +
                                feesTxt.replace('%1', installment).replace('%2', monthlyInstallmentAmount) +
                                `</span>` +
                                `<img src="${window.checkoutConfig.payment.younited.logo}" alt="Younited Pay">`;

                            _this.maturities.push(maturity)
                        }

                        // Magento 2.2 only
                        if (parseInt(window.checkoutConfig.payment.younited.magento2Version) < 3) {
                            var deferred = $.Deferred();
                            getTotalsAction([], deferred);
                            getPaymentInformationAction(deferred);
                        }

                        return _this.maturities;
                    }, 'error': function (request, error) {
                        console.log("Request error: " + JSON.stringify(request));
                        $('#yp-method').hide();
                    }
                });
            } else {
                return this.maturities;
            }
        },

        /**
         * After place order callback
         */
        afterPlaceOrder: function () {
            fullScreenLoader.startLoader();
            var placeOrderUrl = window.checkoutConfig.payment.younited.contractUrl
                + 'maturity/' + $('input[name=maturity]:checked').val() + '/';
            window.location.replace(url.build(placeOrderUrl));
        }
    });
});
