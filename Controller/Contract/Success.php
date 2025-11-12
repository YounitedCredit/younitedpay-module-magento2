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
Use YounitedCredit\YounitedPay\Helper\YounitedClient;
use YounitedPaySDK\Model\LoadContract;
use YounitedPaySDK\Request\LoadContractRequest;

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
     * @var \YounitedCredit\YounitedPay\Model\YounitedLogger
     */
    protected $logger;

    /**
     * @var \YounitedCredit\YounitedPay\Helper\Maturity
     */
    protected $maturityHelper;

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
     * @param \YounitedCredit\YounitedPay\Model\YounitedLogger $logger
     * @param \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper
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
        \YounitedCredit\YounitedPay\Model\YounitedLogger $logger,
        \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->logger = $logger;
        $this->maturityHelper = $maturityHelper;

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
            $order = $this->orderRepository->get($orderId);
            if ($this->isContractConfirmed($order) === false) {
                $this->logger->debug('[younited pay] - on granted URL refused no contract confirmed');
                $resultJson = $this->resultJsonFactory->create();

                return $resultJson->setData(['response_code' => 200, 'accepted' => false]);
            }
        } else {
            // Mettre la commande en processing
            $session = $this->getOnepage()->getCheckout();
            if (!$this->_objectManager->get(\Magento\Checkout\Model\Session\SuccessValidator::class)->isValid()) {
                return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }

            $orderId = $session->getLastOrderId();
            $order = $this->orderRepository->get($orderId);
        }

        return $this->executeOrder($order);
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
        $client = new YounitedClient();
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

        $statusOrderDone = ['INITIALIZED', 'GRANTED', 'CONFIRMED'];
        if ($response->getStatusCode() == 200) {
            $output = json_decode($response->getBody(), true);
            if (isset($output['status']) && in_array($output['status'], $statusOrderDone) === true) {
                return true;
            }
        }

        return false;
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
