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

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Service\InvoiceService;
use YounitedCredit\YounitedPay\Helper\Config;
use YounitedCredit\YounitedPay\Helper\YounitedClient;
use YounitedPaySDK\Client;
use YounitedPaySDK\Model\LoadContract;
use YounitedPaySDK\Request\LoadContractRequest;
use YounitedPaySDK\Response\AbstractResponse;

class Success extends \Magento\Checkout\Controller\Onepage implements \Magento\Framework\App\CsrfAwareActionInterface
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \YounitedCredit\YounitedPay\Model\Logger\YounitedLogger
     */
    protected $logger;

    /**
     * @var \YounitedCredit\YounitedPay\Helper\Maturity
     */
    protected $maturityHelper;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var YounitedClient
     */
    private $client;

    /**
     * Success constructor.
     *
     * @param Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountManagementInterface $accountManagement
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Framework\Translate\InlineInterface $translateInline
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\View\LayoutFactory $layoutFactory
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory
     * @param \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param Transaction $transaction
     * @param \YounitedCredit\YounitedPay\Model\Logger\YounitedLogger $logger
     * @param \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagement
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param YounitedClient $client
     */
    public function __construct(
        Context $context,
        \Magento\Customer\Model\Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
        AccountManagementInterface $accountManagement,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\Translate\InlineInterface $translateInline,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\View\LayoutFactory $layoutFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        Transaction $transaction,
        \YounitedCredit\YounitedPay\Model\Logger\YounitedLogger $logger,
        \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        YounitedClient $client
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->cartRepository = $cartRepository;
        $this->client = $client;
        $this->logger = $logger;
        $this->maturityHelper = $maturityHelper;
        $this->orderManagement = $orderManagement;

        parent::__construct(
            $context,
            $customerSession,
            $customerRepository,
            $accountManagement,
            $coreRegistry,
            $translateInline,
            $formKeyValidator,
            $scopeConfig,
            $layoutFactory,
            $quoteRepository,
            $resultPageFactory,
            $resultLayoutFactory,
            $resultRawFactory,
            $resultJsonFactory
        );
    }

    /**
     * Execute method
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $orderId = $this->getRequest()->getParam('order', false);
        if ($orderId !== false) {
            // We are in the webhook case
            $order = $this->orderRepository->get($orderId);
            $credentials = $this->maturityHelper->getApiCredentials($order->getStoreId());
            $webHookSecret = $credentials['webHookSecret'] ?? null;
            if (empty($webHookSecret)) {
                $this->logger->debug('[younited pay] - webhook refused no secret configured for store with id ' . $order->getStoreId());
                return $this->returnResponse(400, false, "Webhook secret is not configured for this store");
            }

            $client = new Client();
            $client->setCredential('', $webHookSecret);

            /** @var AbstractResponse $response */
            $response = $client->retrieveCallbackResponse();
            if ($response->getStatusCode() === 401) {
                $this->logger->debug('[younited pay] - Webhook is not valid - invalid secret or bad signature.');
                return $this->returnResponse(401, false, "Webhook is not valid - invalid secret or bad signature.");
            }

            if ($this->isContractConfirmed($order) === false) {
                $this->logger->debug('[younited pay] - on granted URL refused no contract confirmed');
                $message = 'Contract not confirmed - bad status returned by API';
                return $this->returnResponse(200, true, $message);
            }
            
            $message = 'Order processed successfully';
            try {
                $this->executeOrder($order);
            } catch (\Exception $e) {
                $this->logger->debug('executeOrder exception: ' . $e->getMessage());
                $message = 'Error during order processing: ' . $e->getMessage();
            }
            return $this->returnResponse(200, true, $message);
        } 

        // User redirect case - we check contract and if not confirmed we cancel order and redirect to cart with error message
        $session = $this->getOnepage()->getCheckout();
        if (!$this->_objectManager->get(\Magento\Checkout\Model\Session\SuccessValidator::class)->isValid()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $orderId = $session->getLastOrderId();
        $order = $this->orderRepository->get($orderId);
        if ($this->isContractConfirmed($order) === true) {
            return $this->executeOrder($order);
        }
        
        // Contract is not confirmed, we cancel the order and redirect to cart with error message
        $this->logger->debug('[younited pay] - success URL refused no contract confirmed');
        $message = 'Payment not confirmed - cannot validate order';

        $session = $this->getOnepage()->getCheckout();
        if (!$this->_objectManager->get(\Magento\Checkout\Model\Session\SuccessValidator::class)->isValid()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $orderId = $session->getLastOrderId();
        $order = $this->orderRepository->get($orderId);

        $this->messageManager->addErrorMessage($message);
        $quote = $this->cartRepository->get($order->getQuoteId());
        $quote->setIsActive(true);
        $this->cartRepository->save($quote);
        $this->getOnepage()->getCheckout()->replaceQuote($quote)->unsLastRealOrderId();

        try {
            $this->orderManagement->cancel($orderId);
        } catch (\Exception $e) {
            // Do nothing
            $this->logger->debug(
                sprintf(
                    '[younited pay] - cannot cancel order with Id %s: %s', 
                    $orderId, 
                    $e->getMessage()
                )
            );
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }

    /**
     * Make the order
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     *
     * @return \Magento\Framework\Controller\Result\Redirect $resultRedirect
     */
    protected function executeOrder($order)
    {
        $orderState = \Magento\Sales\Model\Order::STATE_PROCESSING;
        $orderStatus = $this->scopeConfig->getValue(
            Config::XML_PATH_ORDER_STATUS_PROCESSING,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );

        if ($order->getState() != $orderStatus) {
            if ($order->canInvoice()) {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->register();

                if ($invoice->canCapture()) {
                    $invoice->capture();
                }

                $invoice->save();
                $transactionSave = $this->transaction->addObject(
                    $invoice
                )->addObject(
                    $invoice->getOrder()
                );
                $transactionSave->save();
                $this->invoiceSender->send($invoice);

    //            Send Invoice mail to customer
                $order->addStatusHistoryComment(
                    __('Customer successfully returns from Younited Pay. Invoice creation #%1.', $invoice->getIncrementId())
                )
                    ->setIsCustomerNotified(true);
            }
            $order->setState($orderState)->setStatus($orderStatus);
            $order->save();
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('checkout/onepage/success');

        return $resultRedirect;
    }

    /**
     * Check if contract is correctly confirmed
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     *
     * @return bool
     */
    protected function isContractConfirmed($order)
    {
        $client = $this->client;
        $body = new LoadContract();
        $request = new LoadContractRequest();
        $credentials = $this->maturityHelper->getApiCredentials($order->getStoreId());

        $payment = $order->getPayment();
        $informations = $payment->getAdditionalInformation();

        $body->setContractReference($informations['Payment ID']);
        $request = $request->setModel($body);
        if ($credentials['mode'] === 'dev') {
            $request = $request->enableSandbox();
        }

        $response = $client->setCredential(
            $credentials['clientId'],
            $credentials['clientSecret']
        )->sendRequest($request);

        $statusOrderDone = ['GRANTED', 'CONFIRMED', 'FINANCED'];
        if ($response->getStatusCode() == 200) {
            $output = json_decode($response->getBody(), true);
            if (isset($output['status']) && in_array($output['status'], $statusOrderDone) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return response for Webhooks
     * @param mixed $code - HTTP response code
     * @param mixed $accepted - if the webhook is accepted or not
     * @param mixed $message - message to log and return in response
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function returnResponse($code = 200, $accepted = true, $message = '')
    {
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
