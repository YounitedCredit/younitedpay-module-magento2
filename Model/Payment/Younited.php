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

namespace YounitedCredit\YounitedPay\Model\Payment;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\PaymentInterface;
use YounitedCredit\YounitedPay\Helper\Maturity;

class Younited extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Maturity
     */
    protected $maturityHelper;

    /**
     * @var string
     */
    protected $_formBlockType = \Magento\Payment\Block\Form::class;

    /**
     * @var string
     */
    protected $_infoBlockType = \YounitedCredit\YounitedPay\Block\Order\Info::class;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var bool
     */
    protected $_canCancelInvoice = false;

    /**
     * @var bool
     */
    protected $_stripeApi = false;

    /**
     * @var bool
     */
    protected $_countryFactory;

    /**
     * @var float|null
     */
    protected $_minAmount = null;

    /**
     * @var float|null
     */
    protected $_maxAmount = null;

    /**
     * @var string[]
     */
    protected $_supportedCurrencyCodes = ['EUR'];

    /**
     * @var string
     */
    protected $_code = 'younited';

    /**
     * Younited constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param Maturity $maturityHelper
     * @param array $data
     * @param DirectoryHelper|null $directory
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        Maturity $maturityHelper,
        array $data = [],
        DirectoryHelper $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            null,
            null,
            $data,
            $directory
        );

        $this->storeManager = $storeManager;
        $this->maturityHelper = $maturityHelper;

        $this->_minAmount = $this->getConfigData('min_order_total');
        $this->_maxAmount = $this->getConfigData('max_order_total');
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function validate()
    {
        /*
         * calling parent validate function
         */
        parent::validate();

        return $this;
    }

    /**
     * Authorize payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
        }
        return $this;
    }

    /**
     * Capture payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canCapture()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The capture action is not available.'));
        }
        return $this;
    }

    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }
        return $this;
    }

    /**
     * Assign data to info model instance
     *
     * @param \Magento\Framework\DataObject|mixed $data
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ?: []);
        }

        /** @var DataObject $info */
        $info = $this->getInfoInstance();
        $info->addData(
            [
                'yp-data' => 'test OK'
            ]
        );

        return $this;
    }

    /**
     * Check whether there are CC types set in configuration
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     *
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$quote) {
            return false;
        }

        try {
            $code = $this->storeManager->getStore($quote->getStoreId())->getCurrentCurrency()->getCode();
            if ($code != 'EUR') {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        if (empty($quote->getData('grand_total'))) {
            return false;
        }

        $maturities = $this->maturityHelper->getInstallments($quote->getData('grand_total'), $quote->getStoreId());
        if (!$maturities || empty($maturities)) {
            return false;
        }

        return parent::isAvailable($quote);
    }
}
