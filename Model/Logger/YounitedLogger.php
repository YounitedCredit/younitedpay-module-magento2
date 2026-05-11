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

namespace YounitedCredit\YounitedPay\Model\Logger;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use YounitedCredit\YounitedPay\Helper\Config;
use YounitedCredit\YounitedPay\Model\Logger\YounitedLoggerMonolog;

class YounitedLogger
{
    /** @var YounitedLoggerMonolog */
    private $logger;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var bool
     */
    protected $debugAPI;

    /**
     * Construct
     *
     * @return void
     */
    public function __construct(
        YounitedLoggerMonolog $logger,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->debugAPI = (bool) $this->scopeConfig->getValue(Config::XML_PATH_API_DEBUG, ScopeInterface::SCOPE_STORE);
    }

    public function info($message)
    {
        $this->logger->info($message);
    }

    public function warning($message)
    {
        $this->logger->warning($message);
    }

    public function debug($message)
    {
        $this->logger->debug($message);
    }

    public function critical($message, array $context = [])
    {
        $this->logger->critical($message, $context);
    }

    public function log($message)
    {
        if ($this->debugAPI === true) {
            $this->info($message);
        }
    }
}
