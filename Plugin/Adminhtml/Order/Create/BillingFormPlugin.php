<?php

namespace YounitedCredit\YounitedPay\Plugin\Adminhtml\Order\Create;

use Magento\Catalog\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Store\Model\ScopeInterface;

class BillingFormPlugin
{
    public function aroundGetMethods(\Magento\Sales\Block\Adminhtml\Order\Create\Billing\Method\Form $subject, callable $proceed)
    {
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
