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

namespace YounitedCredit\YounitedPay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use YounitedCredit\YounitedPay\Helper\Maturity;
use YounitedPaySDK\Client;

/**
 * Class RequestHandler
 *
 * @package YounitedCredit\YounitedPay\Observer
 */
abstract class RequestHandler implements ObserverInterface
{
    /**
     * @var Maturity
     */
    protected $maturityHelper;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * RequestHandler constructor.
     *
     * @param Maturity $maturityHelper
     * @param DateTime $date
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Maturity $maturityHelper,
        DateTime $date,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->maturityHelper = $maturityHelper;
        $this->date = $date;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     */
    abstract public function execute(Observer $observer);

    /**
     * @param $body
     * @param $request
     * @param $informations
     * @param $storeId
     * @param $status
     * @param string $errorMessage
     *
     * @return false|string[]
     */
    protected function sendRequest(
        $body,
        $request,
        $informations,
        $storeId,
        $status = false,
        string $errorMessage = '',
        string $successMessage = ''
    ) {
        $client = new Client();
        $credentials = $this->maturityHelper->getApiCredentials($storeId);

        $body->setContractReference($informations['Payment ID']);
        $request = $request->setModel($body);
        if ($credentials['mode'] == 'dev') {
            $request = $request->enableSandbox();
        }

        try {
            $response = $client
                ->setCredential($credentials['clientId'], $credentials['clientSecret'])
                ->sendRequest($request);

            if (($response->getStatusCode() === 204)) {
                if ($status) {
                    $informations['Payment Status'] = $status;
                }
                if ($successMessage) {
                    $this->messageManager->addSuccessMessage(__($successMessage));
                }
                $informations['Payment Status updated on'] = $this->date->date();
                return $informations;
            } else {
                if ($errorMessage) {
                    $this->messageManager->addErrorMessage(__($errorMessage));
                    $this->logger->warning(__($errorMessage));
                }

                $this->logger->warning(__(
                    'Cannot contact Younited Credit API. Status code: %1 - %2.',
                    $response->getStatusCode(),
                    $response->getReasonPhrase()
                ));
            }
        } catch (Exception $e) {
            $this->logger->critical('Younited Credit confirmation failed. Younited API Error',
                ['exception' => $e]);
        }

        return false;
    }
}
