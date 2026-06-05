<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_B2bRegistration
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Db\Ddl\Table;

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()->newTable($installer->getTable('b2bregistration/application'));
$table
    ->addColumn('application_id', Table::TYPE_INTEGER, null, [
        'identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true,
    ], 'Application ID')
    ->addColumn('submission_id', Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => true], 'Custom Form submission')
    ->addColumn('customer_id', Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false], 'Applicant customer')
    ->addColumn('store_id', Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => true], 'Store')
    ->addColumn('status', Table::TYPE_TEXT, 16, ['nullable' => false, 'default' => 'pending'], 'pending|approved|declined')
    ->addColumn('is_new_account', Table::TYPE_SMALLINT, null, ['nullable' => false, 'default' => 0], 'Account was created by this application')
    ->addColumn('created_at', Table::TYPE_DATETIME, null, ['nullable' => false], 'Created at')
    ->addColumn('decided_at', Table::TYPE_DATETIME, null, ['nullable' => true], 'Decided at')
    ->addIndex($installer->getIdxName('b2bregistration/application', ['customer_id']), ['customer_id'])
    ->addIndex($installer->getIdxName('b2bregistration/application', ['status']), ['status'])
    ->setComment('B2B trade-account applications');

$installer->getConnection()->createTable($table);

$installer->endSetup();
