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
    'mage/translate'
], function ($, $t) {
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

            $('#yp-close-popup').on('click', function (e) {
                event.preventDefault();
                $('#younited_popupzone').hide();
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

        createMainObservers: function createMainObservers() {
            var _this = this;

            $('.blocks_maturities_popup').on('click', function (e) {
                var data = $(this).data()
                $('.blocks_maturities_popup').removeClass('selected');
                $(this).addClass('selected');
                $('#popup-maturity').text(_this.getTotalAmount(data.maturity));
                $('#yp-amount').text(_this.getTotalAmount(data.amount));
                $('#yp-cost').text(_this.getTotalAmount(data.interests));
                $('#yp-total').text(_this.getTotalAmount(data.total));
                $('#yp-percent').text(data.percent * 100);
                $('#yp-debit').text(data.debit * 100);
                $('#maturity_installment' + data.key).trigger('mouseenter');
            });

            $('.maturity_installment').on('click', function (e) {
                $('#younited_popupzone').show();
                $('#blocks_maturities_popup' + $(this).data('key')).trigger('click');
            });

            $('.maturity_installment').hover(function () {
                    var data = $(this).data()
                    $('.maturity_installment.selected').removeClass('selected');
                    $(this).addClass('selected')
                    $('#yp-maturity').text(_this.getTotalAmount(data.maturity));
                    $('#yp-installment').text("x" + data.key);
                }, function () {
                }
            );
        },

        /**
         * Update display on price change only for configurable products
         */
        updateDisplay: function updateDisplay(price) {
            var _this = this,
                maturities = this.maturities[price]
            var count = 0;
            for (const installment in maturities) {
                count++;
                var installementBlock = $('#maturity_installment' + installment);
                if (installementBlock.length > 0) {
                    // Exists, update
                    installementBlock.data('maturity', maturities[installment].monthlyInstallmentAmount);
                } else {
                    // Doesn't exists, create
                    $(`<div class="maturity_installment" id="maturity_installment${installment}" data-key="${installment}"
                            data-maturity="${maturities[installment].monthlyInstallmentAmount}">
                                <span>${installment}x</span>
                       </div>`).appendTo("#yp-current-maturities");
                }

                var installementPopupBlock = $('#blocks_maturities_popup' + installment);
                if (installementPopupBlock.length > 0) {
                    // Exists, update
                    installementPopupBlock.data('maturity', maturities[installment].monthlyInstallmentAmount);
                    installementPopupBlock.data('amount', price);
                    installementPopupBlock.data('percent', maturities[installment].annualPercentageRate);
                    installementPopupBlock.data('debit', maturities[installment].annualDebitRate);
                    installementPopupBlock.data('total', maturities[installment].creditTotalAmount);
                    installementPopupBlock.data('interests', maturities[installment].interestsTotalAmount);
                } else {
                    // Doesn't exists, create
                    $(`<li class="blocks_maturities_popup"
                                    id="blocks_maturities_popup${installment}"
                                    data-key="${installment}"
                                    data-maturity="${maturities[installment].monthlyInstallmentAmount}"
                                    data-amount="${price}"
                                    data-percent="${maturities[installment].annualPercentageRate}"
                                    data-debit="${maturities[installment].annualDebitRate}"
                                    data-total="${maturities[installment].creditTotalAmount}"
                                    data-interests="${maturities[installment].interestsTotalAmount}">
                                    <span class="">${installment} ${$.mage.__('months')}</span>
                                </li>`).appendTo("#yp-popup-maturities");
                }
            }

            if (count === 0) {
                _this.ld.hide();
            } else {
                _this.ld.show();
            }

            // Loop existing objetcs to remove useless ones
            $('.maturity_installment').each(function (i, obj) {
                if (typeof _this.maturities[price][$(obj).data('key')] == 'undefined') $(obj).remove();
            });

            $('.blocks_maturities_popup').each(function (i, obj) {
                if (typeof _this.maturities[price][$(obj).data('key')] == 'undefined') $(obj).remove();
            });

            $('#yp-widget').data('price', price)

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
                price = price.replace(/[^\d.,-]/g, '');
                var qty = parseFloat($(this.options.qtyField).val())
                price = (parseFloat(price) * qty).toFixed(2);
                if (!isNaN(price) && qty > 0 && currentPrice != price) {
                    // @see https://stackoverflow.com/questions/1862130/strip-all-non-numeric-characters-from-string-in-javascript
                    price += '';
                    price = price.replace('.', '-').replace(' ', '');
                    var url = _this.options.url + 'amount/' + price + '/store/' + _this.options.store + '/'


                    if (typeof _this.maturities[price] != 'undefined') {
                        _this.updateDisplay(price);
                    } else {
                        this.requestId++;
                        var currentId = this.requestId;

                        setTimeout(function(){
                            if (currentId == _this.requestId) {
                                _this.ld.loader('show');
                                $.ajax({
                                    'url': url,
                                    'type': 'POST',
                                    'success': function (data) {
                                        _this.maturities[price] = {}
                                        for (const installment in data) {
                                            _this.maturities[price][installment] = data[installment];
                                        }
                                        _this.updateDisplay(price);
                                        _this.ld.loader('hide');
                                    }, 'error': function (request, error) {
                                        console.log("Request error: " + JSON.stringify(request));
                                        _this.ld.loader('hide');
                                    }
                                });
                            }
                        }, 500);
                    }
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
