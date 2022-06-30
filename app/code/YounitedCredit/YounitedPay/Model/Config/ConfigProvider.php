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

namespace YounitedCredit\YounitedPay\Model\Config;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartTotalRepositoryInterface;

/**
 * Class ConfigProvider
 *
 * @package YounitedCredit\YounitedPay\Model\Config
 */
class ConfigProvider implements ConfigProviderInterface
{

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Store\Api\Data\StoreInterface
     */
    protected $store;

    /**
     * @var string|int
     */
    protected $storeCode;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CartTotalRepositoryInterface
     */
    protected $cartTotalRepository;

    /**
     * @var \YounitedCredit\YounitedPay\Helper\Maturity
     */
    protected $maturityHelper;

    /**
     * ConfigProvider constructor.
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        CartTotalRepositoryInterface $cartTotalRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->cartTotalRepository = $cartTotalRepository;
        $this->storeManager = $storeManager;
        $this->maturityHelper = $maturityHelper;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        $grandTotal = $this->getTotalsData()['base_grand_total'];
        return [
            'payment' => [
                'younited' => [
                    'isActive' => 'yesy',
                    'maturities' => $this->maturityHelper->getInstallments($grandTotal, $this->getStoreCode()),
//                    'style' => [
//                        'shape' => $this->config->getButtonShape(Config::BUTTON_AREA_CHECKOUT),
//                        'size' => $this->config->getButtonSize(Config::BUTTON_AREA_CHECKOUT),
//                        'color' => $this->config->getButtonColor(Config::BUTTON_AREA_CHECKOUT)
//                    ]
                ]
            ]
        ];
    }

    /**
     * Return quote totals data
     *
     * @return array
     */
    private function getTotalsData()
    {
        /** @var \Magento\Quote\Api\Data\TotalsInterface $totals */
        $totals = $this->cartTotalRepository->get($this->checkoutSession->getQuote()->getId());
        $items = [];
        /** @var  \Magento\Quote\Model\Cart\Totals\Item $item */
        foreach ($totals->getItems() as $item) {
            $items[] = $item->__toArray();
        }
        $totalSegmentsData = [];
        /** @var \Magento\Quote\Model\Cart\TotalSegment $totalSegment */
        foreach ($totals->getTotalSegments() as $totalSegment) {
            $totalSegmentArray = $totalSegment->toArray();
            if (is_object($totalSegment->getExtensionAttributes())) {
                $totalSegmentArray['extension_attributes'] = $totalSegment->getExtensionAttributes()->__toArray();
            }
            $totalSegmentsData[] = $totalSegmentArray;
        }
        $totals->setItems($items);
        $totals->setTotalSegments($totalSegmentsData);
        $totalsArray = $totals->toArray();
        if (is_object($totals->getExtensionAttributes())) {
            $totalsArray['extension_attributes'] = $totals->getExtensionAttributes()->__toArray();
        }
        return $totalsArray;
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface
     */
    public function getStore()
    {
        if (!$this->store) {
            try {
                $this->store = $this->storeManager->getStore();
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                $this->store = $this->storeManager->getStores()[0];
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
}