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
                    var elem = $(`<div class="maturity_installment" id="maturity_installment${installment}" data-key="${installment}"
                            data-maturity="${maturities[installment].monthlyInstallmentAmount}">
                                <span>${installment}x</span>
                       </div>`);

                    var placed = false;
                    $( ".maturity_installment" ).each(function( index ) {
                        if ($(this).data('key') > installment && placed === false) {
                            $(this).before(elem)
                            placed = true;
                        }
                    });

                    if (!placed) {
                        elem.appendTo("#yp-current-maturities");
                    }
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
                $('#yp-widget').hide();
            } else {
                _this.ld.show();
                $('#yp-widget').show();
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

                        setTimeout(function () {
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
