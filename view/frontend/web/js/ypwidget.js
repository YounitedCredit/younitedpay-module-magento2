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
    'jquery',
    'loader',
    'mage/translate',
    'Magento_Customer/js/customer-data'
], function ($, loader, $t, customerData) {
    'use strict';

    $.widget('younited.widget', {
        maturities: {},
        requestId: 0,
        ld: null,
        options: {
            qtyField: '#qty',
            loaderField: '#younited_block'
        },

        /**
         * Widget creating.
         * Observers.
         */
        _create: function createWidget() {
            var _this = this;

            this.ld = $(this.options.loaderField);
            this.ld.loader({
                icon: this.options.loader
            });

            /**
             * Create observers
             */
            this.createMainObservers();

            /**
             * Observe qty changes
             */
            $(this.options.qtyField).on('input', function () {
                var price = $('.price-wrapper[data-price-type=finalPrice]').find('.price').html()
                _this.updateAjax(price);
            });

            /**
             * Observe configurable / bundles price changes
             */
            $('.price-wrapper[data-price-type=finalPrice]').on('DOMSubtreeModified', function () {
                var price = $(this).find('.price').html()
                _this.updateAjax(price);
            });
        },

        YpchangeInstallment: function(key)
        {
            var actualOffer = parseInt(key);
            var maturityZone = $($.find('.maturity_installment' + actualOffer.toString()));
            var infoInstallmentAmount = maturityZone.attr('data-amount');
            var currentMaturity = parseInt(maturityZone.attr('data-maturity'));
            var initialAmount = maturityZone.attr('data-initamount');
            var taeg = maturityZone.attr('data-taeg');
            var tdf = maturityZone.attr('data-tdf');
            var totalAmount = maturityZone.attr('data-totalamount');
            var interestTotal = maturityZone.attr('data-interesttotal');
            var infoInstallmentMaturity = currentMaturity + 'x';
            
            $('.maturity_installment').removeClass('yp-bg-black-btn');
            $('.maturity_installment' + key).addClass('yp-bg-black-btn');

            $('.yp-install-amount').text(infoInstallmentAmount + " €");
            $('.yp-install-maturity').text(infoInstallmentMaturity);
            $('.yp-tdf').text(tdf);
            $('.yp-taeg').text(taeg);
            $('.yp-total').text(totalAmount);
            $('.yp-interest').text(interestTotal);
            $('.yp-amount').text(initialAmount);
        },

        createMainObservers: function createMainObservers() {
            var _this = this;

            $('.blocks_maturities_popup').on('click', function (e) {
                var data = $(this).data()
                _this.YpchangeInstallment(data.key);
                $('#younited_popupzone').removeClass('hidden');
                $('#younited_popupzone').show();
            });

            $('.maturity_installment, .yp-kml').on('click', function (e) {
                $('#younited_popupzone').removeClass('hidden');
                $('#younited_popupzone').show();
                $('#blocks_maturities_popup' + $(this).data('key')).trigger('click');
            });

            $('.maturity_installment').hover(function () {
                var data = $(this).data()
                _this.YpchangeInstallment(data.key);
            });

            $('#younited_popupzone').on('click', function (e) {
                e.preventDefault();
                if (e.target === e.currentTarget) {
                    // We are outside of div / span inside popup if different
                    $('#younited_popupzone').addClass('hidden');
                }
            });

            $('.younited_btnhide').on('click', function (e) {
                e.preventDefault();
                $('#younited_popupzone').addClass('hidden');
            });
        },

        /**
         * Update display on price change only for configurable products
         */
        updateDisplay: function updateDisplay(data) {
            var _this = this;
            $('.younitedpay-widget-root').html(data);

            const count = $('.maturity_installment').length;

            if (count === 0) {
                _this.ld.hide();
                $('#yp-widget').hide();
            } else {
                _this.ld.show();
                $('#yp-widget').show();
            }

            // Recreate observers
            this.createMainObservers();
            $('.maturity_installment').first().trigger('mouseenter');
        },

        /**
         * Get total amount including quantity
         */
        updateAjax: function updateAjax(price) {
            var _this = this;
            if (typeof price != 'undefined') {
                var currentPrice = parseFloat($('#yp-widget').data('price')).toFixed(2);
                var type = $('#yp-widget').data('type');
                var location = $('#yp-widget').data('location');
                price = price.replace(/[^\d.,-]/g, '');
                var qty = parseFloat($(this.options.qtyField).val())
                price = (parseFloat(price) * qty).toFixed(2);
                if (!isNaN(price) && qty > 0 && currentPrice != price) {
                    // @see https://stackoverflow.com/questions/1862130/strip-all-non-numeric-characters-from-string-in-javascript
                    price += '';
                    price = price.replace('.', '-').replace(' ', '');
                    var url = _this.options.url + 'amount/' + price + '/';
                    url += 'type/' + type + '/location/' + location;
                    
                    setTimeout(function () {
                        _this.ld.loader('show');
                        $.ajax({
                            'url': url,
                            'type': 'GET',
                            'success': function (data) {
                                _this.updateDisplay(data);
                                _this.ld.loader('hide');
                            }, 'error': function (request, error) {
                                console.log("Request error: " + JSON.stringify(request));
                                _this.ld.loader('hide');
                            }
                        });
                    }, 500);
                }
            }
        },

        /**
         * Get total amount including quantity
         */
        getTotalAmount: function getTotalAmount(amount) {
            return parseFloat(amount).toFixed(2)
        }
    });

    return $.younited.widget;
});
