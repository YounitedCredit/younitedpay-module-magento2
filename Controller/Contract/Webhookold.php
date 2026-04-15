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

namespace YounitedCredit\YounitedPay\Controller\Contract;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use YounitedCredit\YounitedPay\Helper\Config;
use YounitedCredit\YounitedPay\Helper\YounitedClient;
use YounitedPaySDK\Model\LoadContract;
use YounitedPaySDK\Request\LoadContractRequest;
use YounitedPaySDK\Response\AbstractResponse;

class Webhookold extends Action
{
    /**
     * @var \YounitedCredit\YounitedPay\Helper\Maturity
     */
    protected $maturityHelper;

    /**
     * @var \YounitedCredit\YounitedPay\Model\Logger\YounitedLogger
     */
    protected $logger;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var \Magento\Sales\Model\RefundOrder
     */
    protected $refundOrder;

    /**
     * @var \Magento\Sales\Model\Order\Creditmemo\ItemCreationFactory
     */
    protected $itemCreationFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var YounitedClient
     */
    private $client;

    /**
     * Webhook constructor.
     *
     * @param JsonFactory $resultJsonFactory
     * @param \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\RefundOrder $refundOrder
     * @param \Magento\Sales\Model\Order\Creditmemo\ItemCreationFactory $itemCreationFactory
     * @param \YounitedCredit\YounitedPay\Model\Logger\YounitedLogger $logger
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagement
     * @param YounitedClient $client
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\RefundOrder $refundOrder,
        \Magento\Sales\Model\Order\Creditmemo\ItemCreationFactory $itemCreationFactory,
        \YounitedCredit\YounitedPay\Model\Logger\YounitedLogger $logger,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        YounitedClient $client
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->maturityHelper = $maturityHelper;
        $this->orderRepository = $orderRepository;
        $this->refundOrder = $refundOrder;
        $this->itemCreationFactory = $itemCreationFactory;
        $this->logger = $logger;
        $this->client = $client;
        $this->orderManagement = $orderManagement;

        parent::__construct($context);
    }

    /**
     * Execute method
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $action = $this->getRequest()->getParam('action');
        $orderId = $this->getRequest()->getParam('order');

        if ($action != "cancel") {
            return $this->returnResponse(400, false,"Order with id " . $orderId . " - webhook action is not treated: " . $action);
        }

        if (!$orderId || empty($orderId)) {
            return $this->returnResponse(400, false, "Order id not provided for webhook with action cancel");
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $order = false;
        }

        if (!$order || !$order->getPayment() || !$order->getId()) {
            return $this->returnResponse(404, false, 'No order or no payment found');
        }

        $credentials = $this->maturityHelper->getApiCredentials($order->getStoreId());
        $webHookSecret = $credentials['webHookSecret'] ?? null;
        if (empty($webHookSecret)) {
            $this->logger->debug('[younited pay] - webhook refused no secret configured for store with id ' . $order->getStoreId());
            return $this->returnResponse(400, false, "Webhook secret is not configured for this store");
        }

        $client = $this->client->setCredential('', $webHookSecret);

        /** @var AbstractResponse $response */
        $response = $client->retrieveCallbackResponse();
        if ($response->getStatusCode() === 401) {
            $this->logger->debug('[younited pay] - Webhook is not valid - invalid secret or bad signature.');
            return $this->returnResponse(401, false, "Webhook is not valid - invalid secret or bad signature.");
        }

        $payment = $order->getPayment();
        $informations = $payment->getAdditionalInformation();

        // We look at yp contract to be sure that it is CANCELED
        $client = $this->client;
        $body = new LoadContract();
        $request = new LoadContractRequest();

        $body->setContractReference($informations['Payment ID']);
        $request = $request->setModel($body);
        if ($credentials['mode'] === 'dev') {
            $request = $request->enableSandbox();
        }

        $response = $client->setCredential(
            $credentials['clientId'],
            $credentials['clientSecret']
        )->sendRequest($request);

        $isContractCanceled = false;
        if ($response->getStatusCode() == 200) {
            $output = json_decode($response->getBody(), true);
            if (isset($output['status']) && $output['status'] == 'CANCELED') {
                $isContractCanceled = true;
            }
        }

        $message = 'Contract already canceled';
        $debugMessage = 'Status on API response : ' . ($output['status'] ?? 'Unknown');
        if ($isContractCanceled === false) {
            $message = 'Contract not canceled on API response.';
        }
        if ($isContractCanceled && $informations['Payment Status'] != Config::CREDIT_STATUS_CANCELED) {
            if ($order->canCreditMemo()) {
                // We have an invoice : refund
                $itemIdsToRefund = [];
                /** @var \Magento\Sales\Api\Data\OrderItemInterface $item */
                foreach ($order->getItems() as $item) {
                    $creditmemoItem = $this->itemCreationFactory->create();
                    $creditmemoItem
                        ->setQty($item->getQtyInvoiced())
                        ->setOrderItemId($item->getItemId());
                    $itemIdsToRefund[] = $creditmemoItem;
                }
                $this->refundOrder->execute($orderId, $itemIdsToRefund);
                $message = "Refunding order with id " . $orderId . " after contract cancellation.";
            } else {
                // We do not have an invoice: cancel
                $this->orderManagement->cancel($orderId);
                $message = "Cancelling (no memo so no refund) of order with id " . $orderId . " after contract cancellation.";
            }

            $payment = $order->getPayment();
            $informations = $payment->getAdditionalInformation();
            $informations['Payment Status'] = Config::CREDIT_STATUS_CANCELED;

            $order->getPayment()->setAdditionalInformation($informations);
        }

        return $this->returnResponse(200, true, $message, $debugMessage);
    }

    /**
     * Return response for Webhooks
     * @param mixed $code - HTTP response code
     * @param mixed $accepted - if the webhook is accepted or not
     * @param mixed $message - message to log and return in response
     * @param mixed $debugMessage - debug message only logged and not returned in response
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function returnResponse($code = 200, $accepted = true, $message = '', $debugMessage = '')
    {
        $this->logger->debug($message . (empty($debugMessage) === false ? ' - ' . $debugMessage : ''));
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData(['response_code' => $code, 'accepted' => $accepted, 'message' => $message]);
    }

    /**
     * For POST requests
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * For POST requests
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
