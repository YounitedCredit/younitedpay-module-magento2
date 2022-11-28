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

namespace YounitedCredit\YounitedPay\Helper;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use YounitedPaySDK\Client;
use YounitedPaySDK\Model\BestPrice;
use YounitedPaySDK\Request\BestPriceRequest;

class Maturity
{
    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Math\Random
     */
    protected $mathRandom;

    /**
     * @var Json
     */
    protected $serializer;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Store\Model\Store
     */
    protected $store;

    /**
     * @var array
     */
    protected $maturityCache = [];

    /**
     * Maturity constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Math\Random $mathRandom
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Psr\Log\LoggerInterface $logger
     * @param Json|null $serializer
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Math\Random $mathRandom,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Psr\Log\LoggerInterface $logger,
        Json $serializer = null
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->mathRandom = $mathRandom;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
    }

    /**
     * Retrieve fixed amount value
     *
     * @param int|float|string|null $amount
     *
     * @return float|null
     */
    public function fixAmount($amount)
    {
        return !empty($amount) ? (float)$amount : null;
    }

    /**
     * Generate a storable representation of a value
     *
     * @param int|float|string|array $value
     *
     * @return string
     */
    protected function serializeValue($value)
    {
        if (is_numeric($value)) {
            $data = (float)$value;
            return (string)$data;
        } elseif (is_array($value)) {
            $data = [];
            foreach ($value as $installment => $amount) {
                if (!array_key_exists($installment, $data)) {
                    $data[$installment] = [
                        'min' => $this->fixAmount($amount['min']),
                        'max' => $this->fixAmount($amount['max'])
                    ];
                }
            }
            if (count($data) == 1 && array_key_exists($this->getDefaultMaturity(), $data)) {
                return (string)$data[$this->getDefaultMaturity()];
            }
            return $this->serializer->serialize($data);
        } else {
            return $value;
        }
    }

    /**
     * Create a value from a storable representation
     *
     * @param int|float|string $value
     *
     * @return array
     */
    protected function unserializeValue($value)
    {
        if (is_numeric($value)) {
            return [$this->getDefaultMaturity() => $this->fixAmount($value)];
        } elseif (is_string($value) && !empty($value)) {
            return $this->serializer->unserialize($value);
        } else {
            return [];
        }
    }

    /**
     * Check whether value is in form retrieved by _encodeArrayFieldValue()
     *
     * @param string|array $value
     *
     * @return bool
     */
    protected function isEncodedArrayFieldValue($value)
    {
        if (!is_array($value)) {
            return false;
        }
        unset($value['__empty']);
        foreach ($value as $row) {
            if (!is_array($row)
                || !array_key_exists('installments', $row)
                || !array_key_exists('min_amount', $row)
                || !array_key_exists('max_amount', $row)
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Encode value to be used in \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
     *
     * @param array $value
     *
     * @return array
     */
    protected function encodeArrayFieldValue(array $value)
    {
        $result = [];

        foreach ($value as $installment => $amount) {
            $resultId = $this->mathRandom->getUniqueHash('_');
            $result[$resultId] = [
                'installments' => $installment,
                'min_amount' => $this->fixAmount($amount['min']),
                'max_amount' => $this->fixAmount($amount['max'])
            ];
        }
        return $result;
    }

    /**
     * Decode value from used in \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
     *
     * @param array $value
     *
     * @return array
     */
    protected function decodeArrayFieldValue(array $value)
    {
        $result = [];
        unset($value['__empty']);
        foreach ($value as $row) {
            if (!is_array($row)
                || !array_key_exists('installments', $row)
                || !array_key_exists('min_amount', $row)
                || !array_key_exists('max_amount', $row)
            ) {
                continue;
            }
            $installment = $row['installments'];
            $minAmount = $this->fixAmount($row['min_amount']);
            $maxAmount = $this->fixAmount($row['max_amount']);

            $result[$installment] = ['min' => $minAmount, 'max' => $maxAmount];
        }
        return $result;
    }

    /**
     * Retrieve value from config
     *
     * @param float|string $productPrice
     * @param null|string|bool|int|Store $store
     *
     * @return array|null
     */
    public function getConfigValue($productPrice, $store)
    {
        if (!isset($this->maturityCache[$store])) {
            $value = $this->scopeConfig->getValue(
                \YounitedCredit\YounitedPay\Helper\Config::XML_PATH_MATURITIES,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $store
            );
            $value = $this->unserializeValue($value);
            if ($this->isEncodedArrayFieldValue($value)) {
                $value = $this->decodeArrayFieldValue($value);
            }

            $result = [];
            foreach ($value as $installment => $amount) {
                $amount['min'] = $this->fixAmount($amount['min']);
                $amount['max'] = $this->fixAmount($amount['max']);
                if ($productPrice < $amount['min'] || $productPrice > $amount['max']) {
                    continue;
                }
                $result[$installment] = [
                    'min' => $amount['min'],
                    'max' => $amount['max']
                ];
            }
            $this->maturityCache[$store] = $result;
        }
        return $this->maturityCache[$store];
    }

    /**
     * Make value readable by \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
     *
     * @param string|array $value
     *
     * @return array
     */
    public function makeArrayFieldValue($value)
    {
        $value = $this->unserializeValue($value);
        if (!$this->isEncodedArrayFieldValue($value)) {
            $value = $this->encodeArrayFieldValue($value);
        }

        return $value;
    }

    /**
     * Make value ready for store
     *
     * @param string|array $value
     *
     * @return string
     */
    public function makeStorableArrayFieldValue($value)
    {
        if ($this->isEncodedArrayFieldValue($value)) {
            $value = $this->decodeArrayFieldValue($value);
        }

        $value = $this->serializeValue($value);
        return $value;
    }

    /**
     * Get current Store
     *
     * @return \Magento\Store\Api\Data\StoreInterface|Store
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStore()
    {
        if (!$this->store) {
            $this->store = $this->storeManager->getStore();
        }

        return $this->store;
    }

    /**
     * Get Api Credentials
     *
     * @param false|int $storeId
     * @param false|int $website
     *
     * @return array|false
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getApiCredentials($storeId = false, $website = false)
    {

        if ($storeId === false) {
            $storeId = $this->getStore()->getId();
        }

        if ($website) {
            $mode = $this->scopeConfig->getValue(
                Config::XML_PATH_API_DEV_MODE,
                ScopeInterface::SCOPE_WEBSITE,
                $storeId
            );
            $clientId = $this->scopeConfig->getValue(
                Config::XML_PATH_API_CLIENT_ID,
                ScopeInterface::SCOPE_WEBSITE,
                $storeId
            );
            $clientSecret = $this->scopeConfig->getValue(
                Config::XML_PATH_API_CLIENT_SECRET,
                ScopeInterface::SCOPE_WEBSITE,
                $storeId
            );
        } else {
            $mode = $this->getConfig(Config::XML_PATH_API_DEV_MODE, $storeId);
            $clientId = $this->getConfig(Config::XML_PATH_API_CLIENT_ID, $storeId);
            $clientSecret = $this->getConfig(Config::XML_PATH_API_CLIENT_SECRET, $storeId);
        }

        if (!$clientId || !$clientSecret) {
            if ($mode == 'dev') {
                $this->logger->warning(__('Please check your Magento configuration client_id'
                    . ' and client_secret to enable Younited Credit.'));
            }

            return false;
        }

        return [
            'mode' => $mode,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
        ];
    }

    /**
     * Get installments for spécified price
     *
     * @param float $price
     * @param int|string $storeId
     *
     * @return array|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getInstallments(float $price, $storeId)
    {
        $maturities = [];
        $credentials = $this->getApiCredentials($storeId);

        if ($credentials === false) {
            return $maturities;
        }

        $client = new Client();
        $body = new BestPrice();
        $body->setBorrowedAmount($price);

        $request = ($credentials['mode'] === 'dev')
            ? (new BestPriceRequest())->enableSandbox()->setModel($body)
            : (new BestPriceRequest())->setModel($body);

        try {
            $response = $client->setCredential(
                $credentials['clientId'],
                $credentials['clientSecret']
            )->sendRequest($request);

            if ($response->getStatusCode() !== 200) {
                return __(
                    'Cannot contact Younited Credit API. Status code: %1 - %2.',
                    $response->getStatusCode(),
                    $response->getReasonPhrase()
                );
            }
        } catch (Exception $e) {
            return __('Exception: ') . $e->getMessage() . $e->getFile() . ':' . $e->getLine() . $e->getTraceAsString();
        }

        $maturityConfig = $this->getConfigValue($price, $storeId);

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
     * Get config value
     *
     * @param string $path
     * @param bool|int|string $storeId
     *
     * @return mixed
     */
    public function getConfig($path, $storeId = false)
    {
        if ($storeId === false) {
            $storeId = $this->getStore()->getId();
        }

        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Return default maturity
     *
     * @return int
     */
    public function getDefaultMaturity()
    {
        return 1;
    }
}
