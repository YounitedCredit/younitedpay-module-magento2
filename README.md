# Younited Pay module for Magento 2

The **Younited Pay module for Magento 2** is a PHP module which allows you to accept payments in your Magento 2 online store. It offers innovative features to reduce shopping cart abandonment rates, optimize success rates and enhance the purchasing process on merchants sites in order to significantly increase business volumes without additional investments in the Magento 2 e-commerce CMS solution.

## Getting started

### Manual installation

With PHP >= 7.2 :
- Install SDK using Composer: `composer require 202ecommerce/younitedpay-sdk:3.1`

- Copy/Paste module files to your Magento root directory (app/code/YounitedCredit/YounitedPay)
- Run: `php bin/magento module:enable YounitedCredit_YounitedPay`
- Run: `php bin/magento setup:upgrade`
- Run: `php bin/magento setup:static-content:deploy`
- Run: `php bin/magento cache:enable younitedpay`
- Flush caches with: `php bin/magento cache:flush`

### Composer installation

For Magento 2.3+ only.

- Install module and dependencies using Composer: `composer require younitedcredit/younitedpay-module-magento2`
- Run: `php bin/magento module:enable YounitedCredit_YounitedPay`
- Run: `php bin/magento setup:upgrade`
- Run: `php bin/magento setup:static-content:deploy`
- Run: `php bin/magento cache:enable younitedpay`
- Flush caches with: `php bin/magento cache:flush`

## Update with Composer

To update the extension to the latest available version (depending on your `composer.json`), run these commands in your terminal:

```
composer update younitedcredit/younitedpay-module-magento2 --with-dependencies
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

## Maintenance mode

You may want to enable the maintenance mode when installing or updating the module, __especially when working on a production website__. To do so, run the two commands below before and after running the other setup commands:

```
php bin/magento maintenance:enable
# Other setup commands
php bin/magento maintenance:disable
```
********
## Compatibility

| Branch  | Magento versions  |
| ------- | ----------------- |
| `0.x`   | **>=** `2.2.x`    |

## Unit testing

You can run unit tests with the following commands :

- **Composer installation :** php ./vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist vendor/younitedcredit/younitedpay-module-magento2/Test/Unit
  
- **Manual installation :** php ./vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/YounitedCredit/YounitedPay/Test/Unit

## Resources

- [Issues][project-issues] — To report issues, submit pull requests and get involved (see [Academic Free License][project-license])

## Features

## License

The **Younited Credit module for Magento 2** is available under the **Academic Free License (AFL 3.0)**. Check out the [license file][project-license] for more information.

[project-issues]: https://github.com/YounitedCredit/younitedpay-module-magento2/issues
[project-license]: LICENSE.md
