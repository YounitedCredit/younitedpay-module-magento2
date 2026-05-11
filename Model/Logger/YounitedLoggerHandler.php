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

namespace YounitedCredit\YounitedPay\Model\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Monolog\Logger;

class YounitedLoggerHandler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * @var string
     */
    protected $fileName = '/var/log/younited.log';

    /**
     * @param DriverInterface $filesystem
     * @param string|null $filePath
     * @param string|null $fileName
     */
    public function __construct(
        DriverInterface $filesystem,
        ?string $filePath = null,
        ?string $fileName = null
    ) {
        $fileNameFinal = empty($fileName) === true ? $this->fileName : $fileName;
        $this->fileName = str_replace('.log', date('Ymd') . '.log', $fileNameFinal);
        parent::__construct($filesystem, $filePath, $this->fileName);
    }
}