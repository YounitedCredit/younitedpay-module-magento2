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
define(
    [
        'jquery',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/checkout-data'
    ], function ($, selectPaymentMethodAction, checkoutData) {
        'use strict';

        return function (originalComponent) {
            return originalComponent.extend({
                setShippingInformation: function () {
                    var _this = this;

                    this.diableSelection();
                    _this._super();
                },

                diableSelection: function () {
                    // Disable payment selection to reload infos
                    if (checkoutData.getSelectedPaymentMethod() == 'younited') {
                        selectPaymentMethodAction({
                            'method': null,
                            'po_number': null,
                            'additional_data': null
                        });
                        checkoutData.setSelectedPaymentMethod(null);
                    }

                    $('.yp-info').hide()
                    $('.mat_radio').prop("checked", false);
                    $('#yp-checkout').prop("disabled", true);
                }
            });
        };
    }
);
