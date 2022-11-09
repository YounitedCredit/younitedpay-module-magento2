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

namespace YounitedCredit\YounitedPay\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use YounitedCredit\YounitedPay\Helper\Config;
use YounitedCredit\YounitedPay\Helper\Maturity;
use YounitedPaySDK\Model\ActivateContract;
use YounitedPaySDK\Model\ConfirmContract;
use YounitedPaySDK\Request\ActivateContractRequest;
use YounitedPaySDK\Request\ConfirmContractRequest;

class ValidateCredit extends RequestHandler
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * ValidateCredit constructor.
     *
     * @param Maturity $maturityHelper
     * @param DateTime $date
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Maturity $maturityHelper,
        DateTime $date,
        ManagerInterface $messageManager,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($maturityHelper, $date, $messageManager, $logger);

        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Execute method
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        $storeId = $order->getStoreId();
        $triggerStatus = $this->scopeConfig->getValue(
            Config::XML_PATH_TRIGGER_STATUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $triggerStatus = explode(',', $triggerStatus);

        if (!$order instanceof \Magento\Framework\Model\AbstractModel) {
            return $this;
        }

        if ($order->getDisableYounitedCall()) {
            return $this;
        }

        $payment = $order->getPayment();
        if (!$payment instanceof DataObject && !$payment instanceof OrderPaymentInterface) {
            return $this;
        }

        if ($payment->getMethod() != 'younited') {
            return $this;
        }

        $informations = $payment->getAdditionalInformation();

        if ($order->getState() == 'processing'
            && $informations['Payment Status'] == Config::CREDIT_STATUS_TO_CONFIRME) {
            $this->checkInfos($informations);
            $request = new ConfirmContractRequest();
            $body = new ConfirmContract();
            $body->setMerchantOrderId($order->getIncrementId());

            $informations = $this->sendRequest(
                $body,
                $request,
                $informations,
                $order->getStoreId(),
                Config::CREDIT_STATUS_CONFIRMED
            );
        }

        if (in_array($order->getStatus(), $triggerStatus) &&
            $informations['Payment Status'] == Config::CREDIT_STATUS_CONFIRMED) {
            $this->checkInfos($informations);
            $request = new ActivateContractRequest();
            $body = new ActivateContract();

            $informations = $this->sendRequest(
                $body,
                $request,
                $informations,
                $order->getStoreId(),
                Config::CREDIT_STATUS_ACTIVATED,
                'Younited Credit activation failed.'
            );
        }

        if ($informations) {
            $order->getPayment()->setAdditionalInformation($informations);
        }

        return $this;
    }

    /**
     * @param $informations
     *
     * @throws LocalizedException
     */
    public function checkInfos($informations)
    {
        if (!isset($informations['Payment ID']) || !isset($informations['Payment Status'])) {
            throw new LocalizedException(__('Cannot find Younited Credit payment informations.'));
        }
    }
}
