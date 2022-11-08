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

use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use YounitedCredit\YounitedPay\Helper\Config;
use YounitedPaySDK\Client;
use YounitedPaySDK\Model\LoadContract;
use YounitedPaySDK\Request\LoadContractRequest;

class Webhook extends AbstractWebhook
{
    /**
     * @var \YounitedCredit\YounitedPay\Helper\Maturity
     */
    protected $maturityHelper;

    /**
     * @var \Psr\Log\LoggerInterface
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
     * Webhook constructor.
     *
     * @param JsonFactory $resultJsonFactory
     * @param \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\RefundOrder $refundOrder
     * @param \Magento\Sales\Model\Order\Creditmemo\ItemCreationFactory $itemCreationFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Action\Context $context
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\RefundOrder $refundOrder,
        \Magento\Sales\Model\Order\Creditmemo\ItemCreationFactory $itemCreationFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Action\Context $context
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->maturityHelper = $maturityHelper;
        $this->orderRepository = $orderRepository;
        $this->refundOrder = $refundOrder;
        $this->itemCreationFactory = $itemCreationFactory;
        $this->logger = $logger;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
//        $this->logger->info('Webhook');
//        $this->logger->info('$params :' . json_encode($params));
        $action = $this->getRequest()->getParam('action');
        $orderId = $this->getRequest()->getParam('order');

        if ($action == "cancel" && $orderId) {
            try {
                $order = $this->orderRepository->get($orderId);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                $order = false;
            }

            // @see https://magento.stackexchange.com/questions/221702/magento-2-programmatically-create-a-credit-memo-by-script
//            \Zend_Debug::dump($order->canCreditMemo());
            if ($order && $order->getId() && $order->canCreditMemo() && $order->getPayment()) {
                $payment = $order->getPayment();
                $informations = $payment->getAdditionalInformation();

                // We look at yp contract to be sure that it is CANCELED
                $client = new Client();
                $body = new LoadContract();
                $request = new LoadContractRequest();
                $credentials = $this->maturityHelper->getApiCredentials($order->getStoreId());

                $body->setContractReference($informations['Payment ID']);
                $request = $request->setModel($body);
                if ($credentials['mode'] === 'dev') {
                    $request = $request->enableSandbox();
                }

                $response = $client->setCredential($credentials['clientId'],
                    $credentials['clientSecret'])->sendRequest($request);

                $isContractCanceled = false;
                if ($response->getStatusCode() == 200) {
                    $output = json_decode($response->getBody(), true);
                    if (isset($output['status']) && $output['status'] == 'CANCELED') {
                        $isContractCanceled = true;
                    }
                }

                if ($isContractCanceled && $informations['Payment Status'] != Config::CREDIT_STATUS_CANCELED) {
//                    \Zend_Debug::dump($order->getIncrementId());
                    $itemIdsToRefund = [];
                    /** @var \Magento\Sales\Api\Data\OrderItemInterface $item */
                    foreach ($order->getItems() as $item) {
//                    \Zend_Debug::dump($item->getItemId());
//                    \Zend_Debug::dump($item->getQtyInvoiced());
                        $creditmemoItem = $this->itemCreationFactory->create();
                        $creditmemoItem
                            ->setQty($item->getQtyInvoiced())
                            ->setOrderItemId($item->getItemId());
                        $itemIdsToRefund[] = $creditmemoItem;
                    }

                    $payment = $order->getPayment();
                    $informations = $payment->getAdditionalInformation();
                    $informations['Payment Status'] = Config::CREDIT_STATUS_CANCELED;

                    $order->getPayment()->setAdditionalInformation($informations);

                    $this->refundOrder->execute($orderId, $itemIdsToRefund);
                }
            }
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData(['response_code' => 200]);
    }

    /**
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
