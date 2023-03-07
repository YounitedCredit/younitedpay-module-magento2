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

declare(strict_types=1);

namespace YounitedCredit\YounitedPay\Block\Adminhtml\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use YounitedCredit\YounitedPay\Helper\Maturity;

class MaturitiesConfig extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{
    /**
     * @var Maturity
     */
    protected $maturityHelper;

    /**
     * @var Maturities
     */
    protected $maturityRenderer;

    /**
     * MaturitiesConfig constructor.
     *
     * @param Context $context
     * @param Maturity $maturityHelper
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        Context $context,
        Maturity $maturityHelper,
        array $data = [],
        $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
        $this->maturityHelper = $maturityHelper;
    }

    /**
     * Retrieve maturity column renderer
     *
     * @return Maturities
     */
    protected function _getMaturityRenderer()
    {
        if (!$this->maturityRenderer) {
            $this->maturityRenderer = $this->getLayout()->createBlock(
                Maturities::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );

            if ($storeId = $this->getRequest()->getParam('store')) {
                $credentials = $this->maturityHelper->getApiCredentials($storeId);
            } elseif ($websiteId = $this->getRequest()->getParam('website')) {
                $credentials = $this->maturityHelper->getApiCredentials(false, $websiteId);
            }

            if ($credentials === false) {
                $this->maturityRenderer->setClass('maturities_block_select empty admin__control-select');
            } else {
                $this->maturityRenderer->setClass('maturities_block_select admin__control-select');
            }
        }
        return $this->maturityRenderer;
    }

    /**
     * Prepare to render
     *
     * @return void
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'installments',
            ['label' => __('Installments'), 'renderer' => $this->_getMaturityRenderer()]
        );
        $this->addColumn(
            'min_amount',
            [
                'label' => __('Min. amount (tax included)'),
                'class' => 'required-entry validate-number validate-greater-than-zero admin__control-text'
            ]
        );
        $this->addColumn(
            'max_amount',
            [
                'label' => __('Max. amount (tax included)'),
                'class' => 'required-entry validate-number validate-greater-than-zero admin__control-text'
            ]
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add a maturity');
    }

    /**
     * Prepare existing row data object
     *
     * @param \Magento\Framework\DataObject $row
     *
     * @return void
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row)
    {
        $optionExtraAttr = [];
        $optionExtraAttr['option_' . $this->_getMaturityRenderer()->calcOptionHash($row->getData('installments'))] =
            'selected="selected"';
        $row->setData(
            'option_extra_attrs',
            $optionExtraAttr
        );
    }
}
