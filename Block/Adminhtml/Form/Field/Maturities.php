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

namespace YounitedCredit\YounitedPay\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Context;
use YounitedCredit\YounitedPay\Helper\Maturity;
use YounitedPaySDK\Client;
use YounitedPaySDK\Request\AvailableMaturitiesRequest;

/**
 * Class Maturities
 *
 * @package YounitedCredit\YounitedPay\Block\Adminhtml\Form\Field
 */
class Maturities extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @var Maturity
     */
    protected $maturityHelper;

    /**
     * @var string[]
     */
    private $_maturities;

    /**
     * Maturities constructor.
     *
     * @param Context $context
     * @param Maturity $maturityHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Maturity $maturityHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->maturityHelper = $maturityHelper;

        $this->setData('cache_key', $this->getCacheKey());
        $this->setData('cache_lifetime', 31536000);
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
        $key = parent::getCacheKeyInfo();
        $key[] = "Maturities_Config";
        return $key;
    }

    /**
     * Retrieve allowed maturities
     *
     * @param int $maturity return name by customer group id
     *
     * @return array|string
     */
    protected function getMaturities($maturity = null)
    {
        if ($this->_maturities === null) {
            $this->_maturities = [];
            $i = 1;

            if ($storeId = $this->getRequest()->getParam('store')) {
                $credentials = $this->maturityHelper->getApiCredentials($storeId);
            } elseif ($websiteId = $this->getRequest()->getParam('website')) {
                $credentials = $this->maturityHelper->getApiCredentials(false, $websiteId);
            }

            $client = new Client();
            $request = new AvailableMaturitiesRequest();
            if ($credentials['mode'] === 'dev') {
                $request = $request->enableSandbox();
            }
            try {
                $response = $client->setCredential($credentials['clientId'],
                    $credentials['clientSecret'])->sendRequest($request);

                if ($response->getStatusCode() == 200) {
                    foreach ($response->getModel() as $val) {
                        $this->_maturities[$val] = $val . 'x';
                    }
                }
            } catch (Exception $e) {
                // Do nothing
            }
        }

        if ($maturity !== null) {
            return $this->_maturities[$maturity] ?? null;
        }
        return $this->_maturities;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            foreach ($this->getMaturities() as $maturity => $maturityLabel) {
                $this->addOption($maturity, addslashes($maturityLabel));
            }
        }
        return parent::_toHtml();
    }
}
