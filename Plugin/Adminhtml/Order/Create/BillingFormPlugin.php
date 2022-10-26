<?php

namespace YounitedCredit\YounitedPay\Plugin\Adminhtml\Order\Create;

/**
 * Class BillingFormPlugin
 *
 * @package YounitedCredit\YounitedPay\Plugin\Adminhtml\Order\Create
 */
class BillingFormPlugin
{
    /**
     * @param \Magento\Sales\Block\Adminhtml\Order\Create\Billing\Method\Form $subject
     * @param callable $proceed
     *
     * @return mixed
     */
    public function aroundGetMethods(
        \Magento\Sales\Block\Adminhtml\Order\Create\Billing\Method\Form $subject,
        callable $proceed
    ) {
        $methods = $proceed();

        /** @var \Magento\Payment\Model\MethodInterface $method */
        foreach ($methods as $key => $method) {
            if ($method->getCode() == 'younited') {
                unset($methods[$key]);
            }
        }

        return $methods;
    }
}
