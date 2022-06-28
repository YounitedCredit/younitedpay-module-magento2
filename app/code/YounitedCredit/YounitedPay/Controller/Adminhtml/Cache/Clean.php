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

namespace YounitedCredit\YounitedPay\Controller\Adminhtml\Cache;

use Magento\Backend\App\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use YounitedCredit\YounitedPay\Helper\Config;
use YounitedPaySDK\Client;
use YounitedPaySDK\Model\BestPrice;
use YounitedPaySDK\Request\BestPriceRequest;

/**
 * Class Clean
 *
 * @package YounitedCredit\YounitedPay\Controller\Adminhtml\Cache
 */
class Clean extends Action
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Clean constructor.
     *
     * @param Action\Context $context
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Action\Context $context,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $apiMode = $this->scopeConfig->getValue(Config::XML_PATH_API_DEV_MODE, 'store', 1);
        $clientId = $this->scopeConfig->getValue(Config::XML_PATH_API_CLIENT_ID, 'store', 1);
        $clientSecret = $this->scopeConfig->getValue(Config::XML_PATH_API_CLIENT_SECRET, 'store', 1);

        if (!$clientId || !$clientSecret) {
            // @todo foreach continue
        }


        \Zend_Debug::dump($apiMode);
        \Zend_Debug::dump($clientId);
        \Zend_Debug::dump($clientSecret);

        $client = new Client();
        $body = new BestPrice();
        $body->setBorrowedAmount(149.01);
        $request = (new BestPriceRequest())->enableSandbox()->setModel($body);

        try {

            $response = $client->setCredential($clientId, $clientSecret)->sendRequest($request);
            if ($response->getStatusCode() === 200) {
                \Zend_Debug::dump($response->getModel());
            } else {
                \Zend_Debug::dump($response->getStatusCode());
                \Zend_Debug::dump($response->getReasonPhrase());
                \Zend_Debug::dump($response->getModel());
            }
        } catch (Exception $e) {
            \Zend_Debug::dump('error catched here');
            echo($e->getMessage() . $e->getFile() . ':' . $e->getLine() . $e->getTraceAsString());
        }

//        $this->messageManager;
        die('ok');

        return $this->defaultRedirect();
    }

    /**
     * @return mixed
     */
    protected function defaultRedirect()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
    }
}
