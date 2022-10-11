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

namespace YounitedCredit\YounitedPay\Block\Adminhtml\System\Config;

use Magento\Store\Model\ScopeInterface;
use YounitedCredit\YounitedPay\Helper\Config;
use YounitedPaySDK\Client;
use YounitedPaySDK\Model\BestPrice;
use YounitedPaySDK\Request\BestPriceRequest;

/**
 * Class Requirements
 *
 * @package YounitedCredit\YounitedPay\Block\Adminhtml\System\Config
 */
class Requirements extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $html = '<div class="config-additional-comment-title">' . $element->getLabel() . '</div>';
        $html .= '<div class="config-additional-comment-content">' . $element->getComment() . '</div>';
        return $this->decorateRowHtml($element, $html);
    }

    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @param string $html
     *
     * @return string
     */
    private function decorateRowHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element, $html)
    {
        $format = '<div id="row_%s" class="col3-config-blocks last"><div class="config-younited-comment">%s</div>';

        $isValid = 'invalid';
        $curl_version = 'Not installed';
        $ssl_version = '';
        $tls_version = 0;
        if (function_exists('curl_version')) {
            $isValid = 'valid';
            $curl_info = curl_version();
            $curl_version = 'version v' . $curl_info['version'];
            $ssl_version = $curl_info['ssl_version'];

            $ch = curl_init('https://www.howsmyssl.com/a/check');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            curl_close($ch);

            $json = json_decode($data);
            $tls_version = (float)str_replace('TLS ', '', $json->tls_version);
        }

        $format .= '<div class="config-younited-server"><span class="' . $isValid . '"></span> CURL - '
            . $curl_version . ' ' . $ssl_version . '</div>';

        /**
         * @see https://stackoverflow.com/questions/27904854/verify-if-curl-is-using-tls
         */
        $isValid = 'invalid';
        $isEnabled = 'Not enabled';
        if ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off' && $tls_version >= 1.2) {
            $isValid = 'valid';
            $isEnabled = 'Enabled';
        }
        $format .= '<div class="config-younited-server"><span class="' . $isValid . '"></span> SSL & TLS1.2 - ' . $isEnabled . ' on all the shop</div>';

        try {
            $isValid = $this->isApiConnected() ? 'valid' : 'invalid';
            $format .= '<div class="config-younited-server"><span class="' . $isValid . '"></span> Connected to Younited API</div>';
        } catch (\Exception $e) {
            $format .= '<div class="config-younited-server">Please Check SDK installation: ' . $e->getMessage() . '</div>';
        }

        $isValid = $this->isWebhookConnected() ? 'valid' : 'invalid';
        $format .= '<div class="config-younited-server"><span class="' . $isValid . '"></span> WebHook contacted</div>';

        if ($this->getRequest()->getParam('store')) {
            $mode = $this->_scopeConfig->getValue("younited_setup/general/mode", ScopeInterface::SCOPE_STORE,
                $this->getRequest()->getParam('store'));
        } else {
            if ($this->getRequest()->getParam('website')) {
                $mode = $this->_scopeConfig->getValue("younited_setup/general/mode", ScopeInterface::SCOPE_WEBSITE,
                    $this->getRequest()->getParam('website'));
            } else {
                $mode = $this->_scopeConfig->getValue("younited_setup/general/mode");
            }
        }
        $isValid = $mode == 'prod' ? 'valid' : 'invalid';
        $format .= '<div class="config-younited-server"><span class="' . $isValid . '"></span> Production enviroment</div>';

        $format .= '</div>';

        return sprintf(
            $format,
            $element->getHtmlId(),
            $html
        );
    }

    /**
     * Check API connection
     */
    private function isWebhookConnected()
    {
        $storeId = $this->getRequest()->getParam('store');
        $websiteId = $this->getRequest()->getParam('website');
        if (!$storeId && !$websiteId) {
            return false;
        }

        if ($storeId) {
            $webHookValue = $this->_scopeConfig->getValue(Config::XML_PATH_API_SECRET_WEBHOOK,
                ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            $webHookValue = $this->_scopeConfig->getValue(Config::XML_PATH_API_SECRET_WEBHOOK,
                ScopeInterface::SCOPE_WEBSITE, $websiteId);
        }

        return $webHookValue;
    }

    /**
     * Check API connection
     */
    private function isApiConnected()
    {
        $storeId = $this->getRequest()->getParam('store');
        $websiteId = $this->getRequest()->getParam('website');
        if (!$storeId && !$websiteId) {
            return false;
        }

        if ($storeId) {
            $apiMode = $this->_scopeConfig->getValue(Config::XML_PATH_API_DEV_MODE, ScopeInterface::SCOPE_STORE,
                $storeId);
            $clientId = $this->_scopeConfig->getValue(Config::XML_PATH_API_CLIENT_ID, ScopeInterface::SCOPE_STORE,
                $storeId);
            $clientSecret = $this->_scopeConfig->getValue(Config::XML_PATH_API_CLIENT_SECRET,
                ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            $apiMode = $this->_scopeConfig->getValue(Config::XML_PATH_API_DEV_MODE, ScopeInterface::SCOPE_WEBSITE,
                $websiteId);
            $clientId = $this->_scopeConfig->getValue(Config::XML_PATH_API_CLIENT_ID, ScopeInterface::SCOPE_WEBSITE,
                $websiteId);
            $clientSecret = $this->_scopeConfig->getValue(Config::XML_PATH_API_CLIENT_SECRET,
                ScopeInterface::SCOPE_WEBSITE, $websiteId);
        }

        if (!$clientId || !$clientSecret) {
            return false;
        }

        $client = new Client();
        $body = new BestPrice();
        $body->setBorrowedAmount(149.01);

        if ($apiMode !== 'dev') {
            $request = (new BestPriceRequest())->setModel($body);
        } else {
            $request = (new BestPriceRequest())->enableSandbox()->setModel($body);
        }

        try {
            $response = $client->setCredential($clientId, $clientSecret)->sendRequest($request);
            return ($response->getStatusCode() === 200);
        } catch (Exception $e) {
            // Do nothing
        }
        return false;
    }
}
