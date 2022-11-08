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

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Psr\Log\LoggerInterface;
use YounitedCredit\YounitedPay\Helper\Config;
use YounitedCredit\YounitedPay\Helper\Maturity;
use YounitedPaySDK\Model\CancelContract;
use YounitedPaySDK\Model\WithdrawContract;
use YounitedPaySDK\Request\CancelContractRequest;
use YounitedPaySDK\Request\WithdrawContractRequest;

class WithdrawCredit extends RequestHandler
{
    /**
     * @var CreditmemoRepositoryInterface
     */
    protected $creditmemoRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * WithdrawCredit constructor.
     *
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param DateTime $date
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Maturity $maturityHelper,
        CreditmemoRepositoryInterface $creditmemoRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ScopeConfigInterface $scopeConfig,
        DateTime $date,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        parent::__construct($maturityHelper, $date, $messageManager, $logger);

        $this->creditmemoRepository = $creditmemoRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();

        // Check only new credit memo
        if ($creditmemo->getIncrementId() !== null) {
            return $this;
        }

        $order = $creditmemo->getOrder();
        $payment = $order->getPayment();

        // Check only younited credit orders
        if (!$payment instanceof DataObject && !$payment instanceof OrderPaymentInterface) {
            return $this;
        }
        if ($payment->getMethod() != "younited") {
            return $this;
        }

        $credentials = $this->maturityHelper->getApiCredentials($order->getStoreId());
        $informations = $payment->getAdditionalInformation();
        $orderTotal = (float)$order->getGrandTotal();
        $refundTotal = $creditmemo->getGrandTotal();
        $status = false;

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('order_id', $order->getId())->create();
        $creditmemos = $this->creditmemoRepository->getList($searchCriteria);
        $creditmemoRecords = $creditmemos->getItems();

        foreach ($creditmemoRecords as $memo) {
            $refundTotal += (float)$memo->getGrandTotal();
        }

        $allowCall = true;
        if ($informations["Payment Status"] == Config::CREDIT_STATUS_CANCELED) {
            $allowCall = false;
        }

        if (
            $refundTotal >= $orderTotal
            && $informations["Payment Status"] != Config::CREDIT_STATUS_CANCELED
            && $informations["Payment Status"] != Config::CREDIT_STATUS_ACTIVATED
        ) {
            // Total refund
            $status = Config::CREDIT_STATUS_CANCELED;
            $request = new CancelContractRequest();
            $body = new CancelContract();
        } else if ($allowCall) {
            // Partial refund
            if ($informations["Payment Status"] != Config::CREDIT_STATUS_ACTIVATED) {
                throw new LocalizedException(__('Younited Pay : please consider either make a total refund, or make a partial refund AFTER you ship the order.'));
            }
            $request = new WithdrawContractRequest();
            $body = new WithdrawContract();
            $body->setAmount($creditmemo->getGrandTotal());
        }

        $order->setDisableYounitedCall(true);

        if ($allowCall) {
            $informations = $this->sendRequest(
                $body, $request, $informations, $order->getStoreId(), $status,
                'An error occured with Younited Payment refund. Please do it manually from Younited Payment dashboard.',
                'Younited Payment contract successfully updated.'
            );
        }

        if ($informations) {
            $order->getPayment()->setAdditionalInformation($informations);
        }

        return $this;
    }
}
