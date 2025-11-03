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
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Store\Model\ScopeInterface;
use YounitedCredit\YounitedPay\Helper\Config;

class Widget extends \Magento\Catalog\Block\Product\View
{
    const DISABLED = 'disabled';
    const PRODUCT_ONLY = 'product';
    const CART_ONLY = 'cart';
    const CART_AND_PRODUCT = 'both';

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
     * @var CheckoutSession
     */
    protected $checkoutSession;

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
     * @param Magento\Checkout\Model\Session $checkoutSession
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
        CheckoutSession $checkoutSession,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $urlEncoder,
            $jsonEncoder,
            $string,
            $productHelper,
            $productTypeConfig,
            $localeFormat,
            $customerSession,
            $productRepository,
            $priceCurrency,
            $data
        );

        $this->maturityHelper = $maturityHelper;
        $this->checkoutSession = $checkoutSession;
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
     * Get Location argument
     *
     * @return string
     */
    public function getLocation()
    {
        $location = $this->getData('location') ?? 'cart';
        return empty($location) === false ? $location : 'cart';
    }

    /**
     * Get Price of product / cart / ajax amount
     *
     * @return float $price
     */
    public function getPrice()
    {
        $location = $this->getLocation();
        switch ($location) {
            case 'cart':
                return (float) $this->checkoutSession->getQuote()->getGrandTotal() ?? 0;
            case 'ajax':
                return (float) $this->getData('amount') ?? 0;
            default:
                return (float) $this->getWidgetProductPrice($this->getProduct());
        }
    }

    /**
     * Get type of product / cart / ajax amount
     *
     * @return string
     */
    public function getType()
    {
        $location = $this->getLocation();
        switch ($location) {
            case 'cart':
                return 'cart';
            case 'ajax':
                return ( $this->getData('type') ?? 'none' ) . '-' . $location;
            default:
                $product = $this->getProduct();
                return $product->getTypeId() ?? 'none';
        }
    }

    /**
     * Check if module is in developper mode
     *
     * @return bool
     */
    public function isDevMode()
    {
        return $this->getConfig(Config::XML_PATH_API_DEV_MODE);
    }

    /**
     * Return active configuration (page active = disabled | product | cart | both)
     * 
     * @return string
     */
    public function getPageActive()
    {
        $pageActive = $this->getConfig(Config::XML_PATH_IS_ON_PRODUCT_PAGE);
        $pageActive = $pageActive === '0' ? self::DISABLED : $pageActive;
        $pageActive = $pageActive === '1' ? self::PRODUCT_ONLY : $pageActive;

        return $pageActive;
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
        
        $pageActive = $this->getPageActive();
        if (!$this->getConfig(Config::XML_PATH_IS_ACTIVE) || $pageActive === self::DISABLED) {
            return false;
        }

        $location = $this->getLocation();
        switch ($location) {
            case 'cart':
                if ($pageActive !== self::CART_ONLY && $pageActive !== self::CART_AND_PRODUCT) {
                    return false;
                }
                break;
            case 'ajax':
                break;
            default:
                if ($location && $this->getConfig(Config::XML_PATH_PRODUCT_PAGE_LOCATION) != $location) {
                    return false;
                }
                if ($pageActive !== self::PRODUCT_ONLY && $pageActive !== self::CART_AND_PRODUCT) {
                    return false;
                }
                $product = $this->getProduct();
                if (!$product->getId() || $product->getTypeId() === 'grouped') {
                    return false;
                }
                break;
        }

        if ($this->getConfig(Config::XML_PATH_IS_IP_WHITELIST)) {
            $allowedIps = explode(',', $this->getConfig(Config::XML_PATH_IP_WHITELIST));

            /**
             * Check HTTP_X_FORWARDED_FOR in case of CDN
             */
            $ip = $this->_request->getServer('HTTP_X_FORWARDED_FOR')
                ? $this->_request->getServer('HTTP_X_FORWARDED_FOR')
                : $this->_request->getServer('REMOTE_ADDR');
            $ip = explode(', ', $ip);
            $ip = $ip[0];

            if (!in_array($ip, $allowedIps)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get current store
     *
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
     * Get current store code
     *
     * @return int|string|null
     */
    public function getStoreCode()
    {
        if (!$this->storeCode) {
            $this->storeCode = ($this->getStore()) ? $this->getStore()->getCode() : null;
        }
        return $this->storeCode;
    }

    /**
     * Get image src field
     *
     * @param string $file
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getImageSrc(string $file)
    {
        $asset = $this->_assetRepo->createAsset('YounitedCredit_YounitedPay::images/' . $file);
        return $asset->getUrl();
    }

    /**
     * Get installments for spécified price
     *
     * @param \Magento\Catalog\Model\Product|null $product
     *
     * @return array|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getInstallments()
    {
        return $this->maturityHelper->getInstallments((float) $this->getPrice(), $this->getStoreCode());
    }

    /**
     * Get Widget Product Price
     *
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return float
     */
    public function getWidgetProductPrice($product)
    {
        if (!$this->productPrice) {
            if ($product->getTypeId() == 'configurable') {
                $this->productPrice = (float)$product->getPriceInfo()->getPrice('regular_price')->getMinRegularAmount()->getValue();
            } else {
                if ($product->getTypeId() == 'bundle') {
                    /** @var \Magento\Bundle\Pricing\Price\BundleRegularPrice $priceInfo */
                    $priceInfo = $product->getPriceInfo()->getPrice('regular_price');
                    $this->productPrice = (float)$priceInfo->getMinimalPrice()->getValue();
                } else {
                    $this->productPrice = (float)$product->getFinalPrice();
                }
            }
        }
        return $this->productPrice;
    }

    /**
     * Get config value
     *
     * @param string $path
     *
     * @return mixed
     */
    public function getConfig(string $path)
    {
        return $this->_scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreCode()
        );
    }
}
