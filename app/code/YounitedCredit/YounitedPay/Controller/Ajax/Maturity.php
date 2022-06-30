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

use Magento\Catalog\Controller\Product;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutFactory;
use function str_replace;

/**
 * Class Maturity
 *
 * @package YounitedCredit\YounitedPay\Controller\Ajax
 */
class Maturity extends \Magento\Framework\App\Action\Action
{
    /**
     * @var LayoutFactory
     */
    protected $layoutFactory;

    /**
     * @var JsonFactory
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
     * Shop constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonResultFactory
     * @param LayoutFactory $layoutFactory
     * @param \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonResultFactory,
        LayoutFactory $layoutFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        \YounitedCredit\YounitedPay\Helper\Maturity $maturityHelper
    ) {
        $this->layoutFactory = $layoutFactory;
        parent::__construct($context);
        $this->jsonResultFactory = $jsonResultFactory;
        $this->resultLayoutFactory = $resultLayoutFactory;
        $this->maturityHelper = $maturityHelper;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
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
