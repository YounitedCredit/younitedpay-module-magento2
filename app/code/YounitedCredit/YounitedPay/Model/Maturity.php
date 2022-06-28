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

namespace YounitedCredit\YounitedPay\Model;

/**
 * Class Maturity
 *
 * @package YounitedCredit\YounitedPay\Model
 *
 * @method \YounitedCredit\YounitedPay\Model\ResourceModel\Maturity getResource()
 * @method \YounitedCredit\YounitedPay\Model\ResourceModel\Maturity\Collection getCollection()
 */
class Maturity extends \Magento\Framework\Model\AbstractModel implements \YounitedCredit\YounitedPay\Api\Data\MaturityInterface,
    \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'younitedcredit_younitedpay_maturity';
    protected $_cacheTag = 'younitedcredit_younitedpay_maturity';
    protected $_eventPrefix = 'younitedcredit_younitedpay_maturity';

    protected function _construct()
    {
        $this->_init('YounitedCredit\YounitedPay\Model\ResourceModel\Maturity');
    }

    /**
     * @return string[]
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
