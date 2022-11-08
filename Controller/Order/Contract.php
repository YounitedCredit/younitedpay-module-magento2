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

namespace YounitedCredit\YounitedPay\Controller\Order;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Information;
use YounitedCredit\YounitedPay\Helper\Config;
use YounitedPaySDK\Client;
use YounitedPaySDK\Model\Address;
use YounitedPaySDK\Model\Basket;
use YounitedPaySDK\Model\BasketItem;
use YounitedPaySDK\Model\InitializeContract;
use YounitedPaySDK\Model\MerchantOrderContext;
use YounitedPaySDK\Model\MerchantUrls;
use YounitedPaySDK\Model\PersonalInformation;
use YounitedPaySDK\Request\InitializeContractRequest;

class Contract extends \Magento\Checkout\Controller\Onepage
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \YounitedCredit\YounitedPay\Helper\Maturity
     */
    protected $maturityHelper;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $date;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Contract constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
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
     * @param \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagement
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param UrlInterface $urlBuilder
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
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
        \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        UrlInterface $urlBuilder,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->maturityHelper = $maturityHelper;
        $this->orderManagement = $orderManagement;
        $this->cartRepository = $cartRepository;
        $this->urlBuilder = $urlBuilder;
        $this->date = $date;
        $this->logger = $logger;

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
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $session = $this->getOnepage()->getCheckout();
        if (!$this->_objectManager->get(\Magento\Checkout\Model\Session\SuccessValidator::class)->isValid()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $orderId = $session->getLastOrderId();
        $order = $this->orderRepository->get($orderId);

        $items = [];
        $simplePrice = 0;
        foreach ($order->getItems() as $item) {

            $apiItem = new BasketItem();
            $qty = $item->getQtyOrdered();

            if ($simplePrice) {
                $price = $simplePrice;
                $simplePrice = 0;
            } else {
                $price = round($item->getPriceInclTax(), 2);
            }

            if ($item->getProductType() == 'configurable') {
                $simplePrice = $price;
                continue;
            }

            if ((int)$qty == $qty) {
                // Quantity is an integer
                $qty = (int)$qty;
            } else {
                $price = round($item->getPriceInclTax() * $qty, 2);
                $qty = 1;
            }

            $apiItem->setUnitPrice($price);
            $apiItem->setQuantity($qty);
            $apiItem->setItemName($item->getName());

            $items[] = $apiItem;
        }

        $cart = new Basket();
        $cart->setItems($items);
        $cart->setBasketAmount(round($order->getGrandTotal(), 2));

        $storeCode = $this->maturityHelper->getStore()->getCode();
        $storeName = $this->maturityHelper->getConfig(Information::XML_PATH_STORE_INFO_NAME);
        $merchantRef = $storeName ? $storeName : $storeCode; // Id du panier

        $context = new MerchantOrderContext();
        $context->setAgentEmailAddress($this->maturityHelper->getConfig('trans_email/ident_general/email'));
        $context->setChannel('ONLINE');
        $context->setMerchantReference($order->getIncrementId());
        // ShopCode is defined in YounitedPay Backoffice
        // $context->setShopCode(null);

        $merchantUrls = new MerchantUrls();
        $merchantUrls->setOnApplicationFailedRedirectUrl($this->getContractUrl('failed'));
        $merchantUrls->setOnApplicationSucceededRedirectUrl($this->getContractUrl('success'));
        $merchantUrls->setOnCanceledWebhookUrl($this->getContractUrl('webhook', ['action' => 'cancel', 'order' => $orderId]));
        $merchantUrls->setOnWithdrawnWebhookUrl($this->getContractUrl('webhook', ['action' => 'withdrawn', 'order' => $orderId]));

        $address = $order->getBillingAddress();
        $street = implode(', ', $order->getBillingAddress()->getStreet());
        $customerAddress = new Address();
        $customerAddress->setAdditionalAddress($address->getCompany());
        $customerAddress->setCity($address->getCity());
        $customerAddress->setCountryCode($address->getCountryId());
        $customerAddress->setPostalCode($address->getPostcode());
        $customerAddress->setStreetName($street);
        $customerAddress->setStreetNumber(null);

        $customerInfo = new PersonalInformation();
        $customerInfo->setAddress($customerAddress);
        $customerInfo->setCellPhoneNumber($address->getTelephone());
        $customerInfo->setEmailAddress($order->getCustomerEmail());
        $customerInfo->setFirstName($order->getCustomerFirstname());
        $customerInfo->setLastName($order->getCustomerLastname());
//        $customerInfo->setBirthDate($order->getCustomerDob());
//        $customerInfo->setGenderCode($order->getCustomerGender());

        $client = new Client();
        $body = new InitializeContract();

        $body->setBasket($cart);
        $body->setMerchantOrderContext($context);
        $body->setMerchantUrls($merchantUrls);
        $body->setPersonalInformation($customerInfo);
        $body->setRequestedMaturity((int)$this->getRequest()->getParam('maturity'));

        $credentials = $this->maturityHelper->getApiCredentials($storeCode);
        $request = (new InitializeContractRequest());
        $request = $request->setModel($body);

        if ($credentials['mode'] == 'dev') {
            $request = $request->enableSandbox();
        }

        try {
            $response = $client->setCredential($credentials['clientId'],
                $credentials['clientSecret'])->sendRequest($request);

            if ($response->getStatusCode() !== 200) {
                return $this->redirectOnError($order, __(
                    'Cannot contact Younited Credit API. Status code: %1 - %2.',
                    $response->getStatusCode(),
                    $response->getReasonPhrase()
                ));
            }
        } catch (Exception $e) {
            $this->logger->critical('Younited API Error', ['exception' => $e]);
            return $this->redirectOnError($order, __($e->getMessage()));
        }

        /** @var \YounitedPaySDK\Model\ArrayCollection $result */
        $result = $response->getModel()->getArrayCopy();
        if (!isset($result["contractReference"]) || !isset($result["redirectUrl"])) {
            return $this->redirectOnError($order, __('Younited API returns an invalid response.'));
        }

        $date = $this->date->date();
        $informations = $order->getPayment()->getAdditionalInformation();
        $informations['Payment ID'] = $result["contractReference"];
        $informations['Payment Status'] = Config::CREDIT_STATUS_TO_CONFIRME;
        $informations['Maturity'] = $this->getRequest()->getParam('maturity');
        $informations['Payment Date'] = $date;
        $informations['Payment Status updated on'] = $date;

        $order->getPayment()->setAdditionalInformation($informations)->save();

        $order->addStatusHistoryComment(__('Younited Credit transaction started. Reference: %1',
            $informations['Payment ID']))
            ->setIsCustomerNotified(false)
            ->save();

//        \Zend_Debug::dump($result["contractReference"]);
//        \Zend_Debug::dump($result["redirectUrl"]);
//        die('ok');

        return $this->resultRedirectFactory->create()
            ->setRefererUrl($this->urlBuilder->getUrl('younited/contract/cancel'))
            ->setUrl($result["redirectUrl"]);
    }

    /**
     * @param string $controller
     * @param array $params
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getContractUrl($controller, $params = [])
    {
        return $this->maturityHelper->getStore()->getUrl('younited/contract/' . $controller, $params);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Magento\Framework\Phrase $message
     */
    public function redirectOnError(\Magento\Sales\Api\Data\OrderInterface $order, \Magento\Framework\Phrase $message)
    {
        $this->messageManager->addErrorMessage($message);
        $quote = $this->cartRepository->get($order->getQuoteId());
        $quote->setIsActive(true);
        $this->cartRepository->save($quote);
        $this->getOnepage()->getCheckout()->replaceQuote($quote)->unsLastRealOrderId();

        try {
            $this->orderManagement->cancel($orderId);
        } catch (\Exception $e) {
            // Do nothing
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }
}
