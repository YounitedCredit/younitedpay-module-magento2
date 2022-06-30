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
    'Magento_Checkout/js/checkout-data'
], function (ko, $, $t, Component, quote, checkoutData) {
    'use strict';

    return Component.extend({
        maturities: null,
        defaults: {
            template: 'YounitedCredit_YounitedPay/payment/younited'
        },

        getMailingAddress: function () {
            return window.checkoutConfig.payment.checkmo.mailingAddress;
        },

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

            if (indicative != '+33') {
                $('.yp-error').show()
                $('#yp-checkout').prop("disabled", true);
            } else {
                $('.yp-error').hide()
                $('#yp-checkout').prop("disabled", false);
            }
        },

        getMaturities: function () {
            if (this.maturities === null) {
                this.maturities = [];
                for (const installment in window.checkoutConfig.payment.younited.maturities) {
                    var maturity = window.checkoutConfig.payment.younited.maturities[installment];
                    var monthlyInstallmentAmount = maturity.monthlyInstallmentAmount.toFixed(2);
                    maturity.installment = parseInt(installment);
                    maturity.annualDebitRate = parseFloat(maturity.annualDebitRate) * 100;
                    maturity.annualPercentageRate = parseFloat(maturity.annualPercentageRate) * 100;
                    maturity.subTitle = `<span>` +
                        $.mage.__('Pay in %1 times without fees (for %2€/month) with')
                            .replace('%1', installment).replace('%2', monthlyInstallmentAmount) +
                        `</span>` +
                        `<img src="/media/younitedpay/logo-younitedpay-payment.png" alt="Younited Pay">`;

                    this.maturities.push(maturity)
                }
            }
            return this.maturities;
        },
    });
});
