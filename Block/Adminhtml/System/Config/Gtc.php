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

class Gtc extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * Render method
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {        
        $locale = 'en_EN';
        try {
            $locale = $this->getLocale();
        } catch (\Exception $ex) {
        }

        $text = __('In order to comply with the legislation, please add to your General Terms and Conditions (GTC)'
            . ' with the hyperlinks and replace [the Seller] with your company name:');
        $text .= '<br />';
        $text .= '"' . __("[The Seller] offers its Customers the credit service of Younited Pay for the settlement"
                . " of their purchases and the execution of the payment. This is conditional on the Customer's"
                . " acceptance of the credit agreement offered by Younited.") . '"';
        $text .= '<br />';
        $text .= '"' . __("Any refusal by Younited to grant credit for an order may result in the cancellation"
                . " of the order.");
        $text .= '<br />';
        $text .= __("Any termination of the T&Cs binding the Customer and [the Seller] shall result in the"
                . " termination of the credit agreement between Younited and the Customer.") . '"';
        if ($locale === 'es_ES') {
            $text .= '<br />"';
            $text .= __('In addition, also add to your General Terms and Conditions (GTC) (in accordance with'
                . ' Article L312-45, under penalty of fine):') . '"';
        } else {
            $text .= '<br />';
            $text .= __('In addition, also add to your General Terms and Conditions (GTC) (in accordance with'
                . ' Article L312-45, under penalty of fine):');
            $text .= ' "' . __("The amount is paid by a credit granted by Younited registered on the REGAFI"
                    . " under number 13156.") . '"';

        }

        $html = $element->getLabel() ? '<div class="config-additional-comment-title"><strong>' . $element->getLabel()
            . '</strong></div>' : '';
        $html .= '<div class="config-additional-comment-content">' . $text . '</div>';
        return $this->decorateRowHtml($element, $html);
    }

    /**
     * Decorate HTML row
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @param string $html
     *
     * @return string
     */
    private function decorateRowHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element, $html)
    {
        return sprintf(
            '<tr id="row_%s"><td colspan="3"><div class="config-additional-comment">%s</div></td></tr>',
            $element->getHtmlId(),
            $html
        );
    }

    /**
     * Get current user connected locale code - Spain users have different GTC
     */
    private function getLocale()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
        /** @var \Magento\Backend\Model\Auth\Session $authSession */
        $authSession = $objectManager->get('\Magento\Backend\Model\Auth\Session'); 
        return $authSession->getUser()->getInterfaceLocale();
    }
}
