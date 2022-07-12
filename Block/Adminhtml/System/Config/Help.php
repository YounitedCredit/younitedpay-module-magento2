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

/**
 * Provides field with additional information
 */
class Help extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $urlSupport = 'https://github.com/YounitedCredit/younitedpay-module-magento2';
        $urlBtn = $this->getUrl('adminhtml/system_config/edit', ['section' => 'younited_faq']);
        $html = '<div class="config-additional-comment-title"><strong>' . $element->getLabel() . '</strong></div>';
        $html .= '<div class="config-additional-comment-content">
<p>' . __('Have a question about') . ' <a href="mailto:contact@younited-pay.fr">' . __('Younited Pay') . '</a> ?</p>
<p>' . __('You can reach a technical team or your account manager from your back office via our ticketing system.') . '</p>
<p>' . __('If your question concerns technical difficulties with the module, please refer to') . ' <a href="' . $urlSupport . '" target="_blank">' . __('our support team') . '</a></p>
        </div>';
        $html .= '<a class="button action-default" target="_blank" href="' . $urlBtn . '">' .
            __('More informations') . '</a>';
        return $this->decorateRowHtml($element, $html);
    }

    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @param string $html
     * @return string
     */
    private function decorateRowHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element, $html)
    {
        return sprintf(
            '<div id="row_%s" class="col3-config-blocks first"><div class="config-younited-comment">%s</div></div>',
            $element->getHtmlId(),
            $html
        );
    }
}
