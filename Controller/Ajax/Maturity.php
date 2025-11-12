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

namespace YounitedCredit\YounitedPay\Controller\Ajax;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\LayoutFactory;

class Maturity extends \Magento\Framework\App\Action\Action
{
    /**
     * @var LayoutFactory
     */
    protected $layoutFactory;

    /**
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonResultFactory;

    /**
     * @var \Magento\Framework\View\Result\LayoutFactory
     */
    protected $resultLayoutFactory;

    /**
     * @var \YounitedCredit\YounitedPay\Helper\Maturity
     */
    protected $maturityHelper;

    /**
     * Maturity constructor.
     *
     * @param Context $context
     * @param LayoutFactory $layoutFactory
     * @param \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
     * @param \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory
     * @param \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper
     */
    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        LayoutFactory $layoutFactory,
        JsonFactory $jsonResultFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper
    ) {
        $this->layoutFactory = $layoutFactory;
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->resultLayoutFactory = $resultLayoutFactory;
        $this->maturityHelper = $maturityHelper;
    }

    /**
     * Execute method
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $request = $this->getRequest();
        if (!$request->getParam('location')) {
            return $this->executeCheckout();
        }
        $layout = $this->layoutFactory->create();

        /** @var \YounitedCredit\YounitedPay\Block\Product $block */
        $block = $layout->createBlock('YounitedCredit\YounitedPay\Block\Product\Widget');
        $block->setTemplate('YounitedCredit_YounitedPay::product/widget.phtml');

        $resultRaw = $this->resultRawFactory->create();

        $params = ['amount', 'type', 'location', 'store'];
        $data = ['ajax' => true];
        foreach ($params as $oneParam) {
            if (!$request->getParam($oneParam)) {
                return $resultRaw->setContents($block->toHtml());    
            }
            $data[$oneParam] = $request->getParam($oneParam);
            $data[$oneParam] = $oneParam === 'amount' ? str_replace('-', '.', $data[$oneParam]) : $data[$oneParam];
        }
        $data['location'] = 'ajax';

        $block->setData($data);
        return $resultRaw->setContents($block->toHtml());
    }

    private function executeCheckout()
    {
        $result = $this->jsonResultFactory->create();
        $request = $this->getRequest();

        if (!$request->getParam('amount') || !$request->getParam('store') || !$request->isAjax()) {
            $result->setData([]);
            return $result;
        }

        $data = $this->maturityHelper->getInstallments(
            str_replace('-', '.', $request->getParam('amount')),
            $request->getParam('store')
        );

        $result->setData($data);
        return $result;
    }
}
