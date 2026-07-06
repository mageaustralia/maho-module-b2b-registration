<?php

/**
 * Maho
 *
 * @package    MageAustralia_B2bRegistration
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Declarative equivalent of the legacy
 *   sql/b2bregistration_setup/upgrade-1.0.0-1.1.0.php
 *   data/b2bregistration_setup/data-install-1.0.0.php
 *
 * The legacy setup scripts stay in place for BC. This declarative file
 * reconciles on ./maho migrate and is idempotent.
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    // Trade-account applications. status transitions: pending -> approved
    // | declined. When an application creates a new customer at intake
    // is_new_account = 1, so admin approve-flow knows to open the account.
    $app = $schema->createTable('b2b_application');
    $app->addColumn('application_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $app->addColumn('submission_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'comment' => 'Backlink to customform_submission']);
    $app->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => true]);
    $app->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $app->addColumn('status', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'pending', 'comment' => 'pending | approved | declined']);
    $app->addColumn('is_new_account', Types::SMALLINT, ['notnull' => true, 'default' => 0, 'comment' => 'Account was created by this application']);
    $app->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
    $app->addColumn('decided_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $app->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('application_id')->create(),
    );
    $app->addIndex(['customer_id'], 'IDX_B2B_APPLICATION_CUSTOMER_ID');
    $app->addIndex(['status'], 'IDX_B2B_APPLICATION_STATUS');
    $app->setComment('B2B trade-account applications');
};
