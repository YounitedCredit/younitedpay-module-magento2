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

/**
 * HTML select element block with maturity options
 */
class Maturities extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @var string[]
     */
    private $_maturities;

    /**
     * @var int maximum of maturities
     */
    private $maxMaturity = 84;

    /**
     * Maturities constructor.
     *
     * @param \Magento\Framework\View\Element\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
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
            while ($i <= $this->maxMaturity) {
                $this->_maturities[$i] = $i . 'x';
                $i++;
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
