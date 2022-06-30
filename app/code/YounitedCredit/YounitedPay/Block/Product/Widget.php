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
     * @var float
     */
    protected $productPrice;

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
     * Check if module is in developper mode
     *
     * @return bool
     */
    public function isDevMode() {
        return $this->getConfig(Config::XML_PATH_API_DEV_MODE);
    }

    /**
     * Check if module is enabled and display allowed
     *
     * @return bool
     */
    public function isEnabled()
    {
        if ($this->getStore()->getCurrentCurrency()->getCode() !== 'EUR') {
            return false;
        }

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
     * Get installments for spécified price
     *
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return array|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getInstallments($product)
    {
        return $this->maturityHelper->getInstallments($this->getProductPrice($product), $this->getStoreCode());
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return float
     */
    public function getProductPrice($product)
    {
        if (!$this->productPrice) {
            if ($product->getTypeId() == 'configurable') {
                $this->productPrice = (float)$product->getPriceInfo()->getPrice('regular_price')
                    ->getMinRegularAmount()->getValue();
            } else if ($product->getTypeId() == 'bundle') {
                /** @var \Magento\Bundle\Pricing\Price\BundleRegularPrice $priceInfo */
                $priceInfo = $product->getPriceInfo()->getPrice('regular_price');
                $this->productPrice = (float)$priceInfo->getMinimalPrice()->getValue();
            } else {
                $this->productPrice = (float)$product->getPrice();
            }
        }
        return $this->productPrice;
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
