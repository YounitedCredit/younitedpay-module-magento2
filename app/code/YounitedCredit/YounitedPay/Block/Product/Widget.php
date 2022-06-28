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

namespace YounitedCredit\YounitedPay\Block\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use YounitedCredit\YounitedPay\Helper\Config;
use YounitedPaySDK\Client;
use YounitedPaySDK\Model\BestPrice;
use YounitedPaySDK\Request\BestPriceRequest;

/**
 * Class Widget
 *
 * @package YounitedCredit\YounitedPay\Block\Product
 */
class Widget extends \Magento\Catalog\Block\Product\View
{
    /**
     * @var \Magento\Store\Api\Data\StoreInterface
     */
    protected $store;

    /**
     * @var string|int
     */
    protected $storeCode;

    /**
     * @var \YounitedCredit\YounitedPay\Helper\Maturity
     */
    protected $maturityHelper;

    /**
     * Widget constructor.
     *
     * @param \Magento\Catalog\Block\Product\Context $context
     * @param \Magento\Framework\Url\EncoderInterface $urlEncoder
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param \Magento\Catalog\Helper\Product $productHelper
     * @param \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig
     * @param \Magento\Framework\Locale\FormatInterface $localeFormat
     * @param \Magento\Customer\Model\Session $customerSession
     * @param ProductRepositoryInterface $productRepository
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Url\EncoderInterface $urlEncoder,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\Framework\Stdlib\StringUtils $string,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig,
        \Magento\Framework\Locale\FormatInterface $localeFormat,
        \Magento\Customer\Model\Session $customerSession,
        ProductRepositoryInterface $productRepository,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper,
        array $data = []
    ) {
        $this->maturityHelper = $maturityHelper;

        parent::__construct($context, $urlEncoder, $jsonEncoder, $string, $productHelper, $productTypeConfig,
            $localeFormat, $customerSession, $productRepository, $priceCurrency, $data);
    }

    /**
     * Get cache key informative items
     *
     * Provide string array key to share specific info item with FPC placeholder
     *
     * @return string[]
     */
    public function getCacheKeyInfo()
    {
        $info = parent::getCacheKeyInfo();
        $info[] = $this->getData('location');
        return $info;
    }

    /**
     * Check if module is enabled and display allowed
     *
     * @return bool
     */
    public function isEnabled()
    {
        if (!$this->getConfig(Config::XML_PATH_IS_ACTIVE) || !$this->getConfig(Config::XML_PATH_IS_ON_PRODUCT_PAGE)) {
            return false;
        }
        if (
            $this->getData('location') &&
            $this->getConfig(Config::XML_PATH_PRODUCT_PAGE_LOCATION) != $this->getData('location')
        ) {
            return false;
        }

        if ($this->getConfig(Config::XML_PATH_IS_IP_WHITELIST)) {
            $allowedIps = explode(',', $this->getConfig(Config::XML_PATH_IP_WHITELIST));

            /**
             * Check HTTP_X_FORWARDED_FOR in case of CDN
             */
            $ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
            $ip = explode(', ', $ip);
            $ip = $ip[0];

            if (!in_array($ip, $allowedIps)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface
     */
    public function getStore()
    {
        if (!$this->store) {
            try {
                $this->store = $this->_storeManager->getStore();
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                $this->store = $this->_storeManager->getStores()[0];
            }
        }
        return $this->store;
    }

    /**
     * @return int|string
     */
    public function getStoreCode()
    {
        if (!$this->storeCode) {
            $this->storeCode = $this->getStore()->getCode();
        }
        return $this->storeCode;
    }

    /**
     * @param $file string
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getImageSrc(string $file)
    {
        return $this->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'younitedpay/' . $file;
    }

    /**
     * @param $productPrice float
     *
     * @return array|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getInstallments(float $productPrice)
    {
        $maturities = [];
        $apiMode = $this->getConfig(Config::XML_PATH_API_DEV_MODE);
        $clientId = $this->getConfig(Config::XML_PATH_API_CLIENT_ID);
        $clientSecret = $this->getConfig(Config::XML_PATH_API_CLIENT_SECRET);

        if (!$clientId || !$clientSecret) {
            return __('Please check your Magento configuration client_id and client_secret to enable Younited Credit.');
        }

        $client = new Client();
        $body = new BestPrice();
        $body->setBorrowedAmount($productPrice);

        $request = ($apiMode === 'dev')
            ? (new BestPriceRequest())->enableSandbox()->setModel($body)
            : (new BestPriceRequest())->setModel($body);

        try {
            $response = $client->setCredential($clientId, $clientSecret)->sendRequest($request);
            if ($response->getStatusCode() !== 200) {
                return __('Cannot contact Younited Credit API. Status code: %s - %s', $response->getStatusCode(),
                    $response->getReasonPhrase());
            }
        } catch (Exception $e) {
            return __('Exception: ') . $e->getMessage() . $e->getFile() . ':' . $e->getLine() . $e->getTraceAsString();
        }

        $maturityConfig = $this->maturityHelper->getConfigValue($productPrice, $this->getStore()->getCode());

        /** @var \YounitedPaySDK\Model\OfferItem $offers */
        foreach ($response->getModel() as $offers) {
            if (!isset($maturityConfig[$offers->getMaturityInMonths()])) {
                continue;
            }

            $maturity = $maturityConfig[$offers->getMaturityInMonths()];
            $maturity['requestedAmount'] = $offers->getRequestedAmount();
            $maturity['annualPercentageRate'] = $offers->getAnnualPercentageRate();
            $maturity['annualDebitRate'] = $offers->getAnnualDebitRate();
            $maturity['monthlyInstallmentAmount'] = $offers->getMonthlyInstallmentAmount();
            $maturity['creditTotalAmount'] = $offers->getCreditTotalAmount();
            $maturity['interestsTotalAmount'] = $offers->getInterestsTotalAmount();

            $maturities[$offers->getMaturityInMonths()] = $maturity;
        }

        return $maturities;
    }

    /**
     * @param $path
     *
     * @return mixed
     */
    public function getConfig($path)
    {
        return $this->_scopeConfig->getValue(
            $path, ScopeInterface::SCOPE_STORE, $this->getStoreCode()
        );
    }
}
