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

namespace YounitedCredit\YounitedPay\Model\Config\Source;

/**
 * Class Location
 *
 * @package YounitedCredit\YounitedPay\Model\Config\Source
 */
class Location implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'product_price_after', 'label' => __('After product price')],
            ['value' => 'product_addtocart_after', 'label' => __('After Add To Cart button')],
            ['value' => 'product_informations', 'label' => __('Product Information')],
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'product_price_after' => __('After product price'),
            'product_addtocart_after' => __('After Add To Cart button'),
            'product_informations' => __('In Product Information')
        ];
    }
}
