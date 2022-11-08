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

namespace YounitedCredit\YounitedPay\Test\Unit\Helper;

use YounitedCredit\YounitedPay\Helper\Maturity;

class MaturityTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Maturity
     */
    protected $helper;

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        $objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $this->helper = $objectManager->getObject(Maturity::class);
    }

    public function testFixAmount()
    {
        if (method_exists($this, 'assertIsFloat')) {
            $this->assertIsFloat($this->helper->fixAmount("3.5"));
        } else {
            $this->assertEquals(3.5, $this->helper->fixAmount("3.5"));
        }
    }

    public function testGetDefaultMaturity()
    {
        $this->assertEquals(1, $this->helper->getDefaultMaturity());
    }

}