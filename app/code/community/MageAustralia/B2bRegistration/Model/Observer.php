<?php

declare(strict_types=1);

use Maho\Config\Observer as MahoObserver;
use Maho\Event\Observer;

/**
 * Maho
 *
 * @package    MageAustralia_B2bRegistration
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Bridges Custom Forms to the trade-account flow: when a submission lands on a
 * configured trade-application form, convert it into a pending customer. Fires
 * for both the server-rendered and headless submission paths (the event is
 * dispatched by the shared Custom Forms pipeline). No area restriction.
 */
class MageAustralia_B2bRegistration_Model_Observer
{
    #[MahoObserver(MageAustralia_CustomForms_Helper_Data::EVENT_SUBMISSION_CREATED, type: 'singleton')]
    public function onSubmission(Observer $observer): void
    {
        /** @var MageAustralia_B2bRegistration_Helper_Data $helper */
        $helper = Mage::helper('b2bregistration');

        $form = $observer->getEvent()->getForm();
        $submission = $observer->getEvent()->getSubmission();
        if (!$form instanceof MageAustralia_CustomForms_Model_Form
            || !$submission instanceof MageAustralia_CustomForms_Model_Submission
        ) {
            return;
        }
        if (!$helper->isEnabled((int) $submission->getStoreId())) {
            return;
        }
        if (!$helper->isTradeForm((string) $form->getCode(), (int) $submission->getStoreId())) {
            return;
        }

        try {
            $helper->createCustomerFromSubmission($form, $submission);
        } catch (Throwable $e) {
            // A registration failure must never break the form submission.
            Mage::logException($e);
        }
    }
}
