<?php
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

namespace YounitedCredit\YounitedPay\Helper;

class Config
{
    /**
     * Config keys
     */
    const XML_PATH_IS_ACTIVE = 'payment/younited/active'; // phpcs:ignore
    const XML_PATH_TRIGGER_STATUS = 'payment/younited/credit_trigger_status'; // phpcs:ignore
    const XML_PATH_MATURITIES = 'payment/younited/maturities'; // phpcs:ignore
    const XML_PATH_ORDER_STATUS_PROCESSING = 'payment/younited/order_status_processing'; // phpcs:ignore

    const XML_PATH_IS_ON_PRODUCT_PAGE = 'younited_appearance/general/display_on_product_page'; // phpcs:ignore
    const XML_PATH_PRODUCT_PAGE_LOCATION = 'younited_appearance/general/product_page_location'; // phpcs:ignore

    const XML_PATH_IS_IP_WHITELIST = 'younited_setup/general/enable_ip_whitelist'; // phpcs:ignore
    const XML_PATH_IP_WHITELIST = 'younited_setup/general/ip_whitelist'; // phpcs:ignore
    const XML_PATH_API_DEV_MODE = 'younited_setup/general/mode'; // phpcs:ignore
    const XML_PATH_API_CLIENT_ID = 'younited_setup/general/client_id'; // phpcs:ignore
    const XML_PATH_API_CLIENT_SECRET = 'younited_setup/general/client_secret'; // phpcs:ignore
    const XML_PATH_API_SECRET_WEBHOOK = 'younited_setup/general/secret_webhook'; // phpcs:ignore

    const CREDIT_STATUS_TO_CONFIRME = 'To confirme'; // phpcs:ignore
    const CREDIT_STATUS_CONFIRMED = 'Confirmed'; // phpcs:ignore
    const CREDIT_STATUS_CANCELED = 'Canceled'; // phpcs:ignore
    const CREDIT_STATUS_ACTIVATED = 'Activated'; // phpcs:ignore
}
