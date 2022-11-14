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

namespace YounitedCredit\YounitedPay\Setup;

// phpcs:ignoreFile
class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface
{
    /**
     * Install database table
     *
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     *
     * @return void
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(
        \Magento\Framework\Setup\SchemaSetupInterface $setup,
        \Magento\Framework\Setup\ModuleContextInterface $context
    ) {
        $installer = $setup;
        $installer->startSetup();

        $tableName = 'younitedcredit_younitedpay_maturity';
        $tableId = 'maturity_id';
        if (!$installer->tableExists($tableName)) {
            $table = $installer->getConnection()->newTable(
                $installer->getTable($tableName)
            )
            ->addColumn(
                $tableId,
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'nullable' => false,
                    'primary'  => true,
                    'unsigned' => true,
                ],
                'ID'
            )
            ->addColumn(
                'maturity',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                3,
                ['nullable' => false],
                '*X months'
            )
            ->addColumn(
                'minimum',
                \Magento\Framework\DB\Ddl\Table::TYPE_FLOAT,
                null,
                [],
                'minimum'
            )
            ->addColumn(
                'maximum',
                \Magento\Framework\DB\Ddl\Table::TYPE_FLOAT,
                null,
                [],
                'maximum'
            )
            ->addColumn(
                'currency',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                3,
                ['nullable' => false, 'default' => 'EUR'],
                'currency'
            )
            ->addIndex(
                $installer->getIdxName($tableName, [$tableId]),
                [$tableId]
            )
            ->setComment('Younited Credit');

            $installer->getConnection()->createTable($table);

            /*
                // example to add index
                $installer->getConnection()->addIndex(
                    $installer->getTable($tableName),
                    $installer->getIdxName(
                        $installer->getTable($tableName),
                        [ 'column1', 'column2', ... ],
                        \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT
                    ),
                    [ 'column1', 'column2', ... ],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT
                );
            */
        }

        $installer->endSetup();
    }
}
