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

namespace YounitedCredit\YounitedPay\Block\Order;

/**
 * Base payment information block
 *
 * @api
 * @since 100.0.2
 */
class Info extends \Magento\Framework\View\Element\Template
{
    /**
     * Payment rendered specific information
     *
     * @var \Magento\Framework\DataObject
     */
    protected $_paymentSpecificInformation;

    /**
     * @var string
     */
    protected $_template = 'YounitedCredit_YounitedPay::order/info.phtml';

    /**
     * Retrieve info model
     *
     * @return \Magento\Payment\Model\InfoInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getInfo()
    {
        $info = $this->getData('info');
        if (!$info instanceof \Magento\Payment\Model\InfoInterface) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('We cannot retrieve the payment info model object.')
            );
        }
        return $info;
    }

    /**
     * Retrieve payment method model
     *
     * @return \Magento\Payment\Model\MethodInterface
     */
    public function getMethod()
    {
        return $this->getInfo()->getMethodInstance();
    }

    /**
     * Retrieve payment method model
     *
     * @return \Magento\Payment\Model\MethodInterface
     */
    public function getYpImage()
    {
        $asset = $this->_assetRepo->createAsset('YounitedCredit_YounitedPay::images/logo-younitedpay.png');
        return $asset->getUrl();
    }

    /**
     * Render as PDF
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('Magento_Payment::info/pdf/default.phtml');
        return $this->toHtml();
    }

    /**
     * Getter for children PDF, as array. Analogue of $this->getChildHtml()
     *
     * Children must have toPdf() callable
     * Known issue: not sorted
     * @return array
     */
    public function getChildPdfAsArray()
    {
        $result = [];
        foreach ($this->getLayout()->getChildBlocks($this->getNameInLayout()) as $child) {
            if (method_exists($child, 'toPdf') && is_callable([$child, 'toPdf'])) {
                $result[] = $child->toPdf();
            }
        }
        return $result;
    }

    /**
     * Get some specific information in format of array($label => $value)
     *
     * @return array
     */
    public function getSpecificInformation()
    {
        return $this->_prepareSpecificInformation()->getData();
    }

    /**
     * Render the value as an array
     *
     * @param mixed $value
     * @param bool $escapeHtml
     * @return array
     */
    public function getValueAsArray($value, $escapeHtml = false)
    {
        if (empty($value)) {
            return [];
        }
        if (!is_array($value)) {
            $value = [$value];
        }
        if ($escapeHtml) {
            foreach ($value as $_key => $_val) {
                $value[$_key] = $this->escapeHtml($_val);
            }
        }
        return $value;
    }

    /**
     * Check whether payment information should show up in secure mode
     * true => only "public" payment information may be shown
     * false => full information may be shown
     *
     * @return bool
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getIsSecureMode()
    {
        if ($this->hasIsSecureMode()) {
            return (bool)(int)$this->_getData('is_secure_mode');
        }

        $method = $this->getMethod();
        if (!$method) {
            return true;
        }

        $store = $method->getStore();
        if (!$store) {
            return false;
        }

        $methodStore = $this->_storeManager->getStore($store);
        return $methodStore->getCode() != \Magento\Store\Model\Store::ADMIN_CODE;
    }

    /**
     * Prepare information specific to current payment method
     *
     * @param null|\Magento\Framework\DataObject|array $transport
     * @return \Magento\Framework\DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        if (null === $this->_paymentSpecificInformation) {
            if (null === $transport) {
                $transport = new \Magento\Framework\DataObject();
            } elseif (is_array($transport)) {
                $transport = new \Magento\Framework\DataObject($transport);
            }
            $this->_paymentSpecificInformation = $transport;
        }
        return $this->_paymentSpecificInformation;
    }
}
