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
    'jquery'
], function ($) {
    'use strict';

    /**
     * Observers for yp popup actions
     *
     * @private
     */
    return function () {
        $('.blocks_maturities_popup').on('click', function (e) {
            $('.blocks_maturities_popup').removeClass('selected');
            $(this).addClass('selected');
            $('#popup-maturity').text($(this).data('maturity'));
            $('#yp-amount').text($(this).data('amount'));
            $('#yp-cost').text($(this).data('interests'));
            $('#yp-total').text($(this).data('total'));
            $('#yp-percent').text($(this).data('percent') * 100);
            $('#yp-debit').text($(this).data('debit') * 100);
            $('#maturity_installment' + $(this).data('key')).trigger('mouseenter');
        });

        $('#yp-close-popup').on('click', function (e) {
            event.preventDefault();
            $('#younited_popupzone').hide();
        });

        $('.maturity_installment').hover(
            function () {
                $('.maturity_installment.selected').removeClass('selected');
                $(this).addClass('selected')
                $('#yp-maturity').text($(this).data('maturity'));
                $('#yp-installment').text($(this).data('installment'));
            }, function () {
            });

        $('.maturity_installment').on('click', function (e) {
            $('#younited_popupzone').show();
            $('#blocks_maturities_popup' + $(this).data('key')).trigger('click');
        });
    };
});
