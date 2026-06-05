<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_B2bRegistration
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * A B2B trade-account application. Tracks the pending/approved/declined state of
 * a customer's request for the B2B group (the submission holds the field data).
 */
class MageAustralia_B2bRegistration_Model_Application extends Mage_Core_Model_Abstract
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('b2bregistration/application');
    }
}
