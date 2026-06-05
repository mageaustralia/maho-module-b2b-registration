<?php

declare(strict_types=1);

use Maho\Config\Route;

/**
 * Maho
 *
 * @package    MageAustralia_B2bRegistration
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Admin "Trade Applications" grid + approve/decline. Approving assigns the
 * configured B2B group (and opens a new-prospect account). Routing via
 * #[Maho\Config\Route] attributes - run `composer dump-autoload` after install.
 */
class MageAustralia_B2bRegistration_Adminhtml_B2bregistration_ApplicationController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'customer/b2bregistration';

    #[\Override]
    public function preDispatch(): static
    {
        $this->_setForcedFormKeyActions(['approve', 'decline', 'massApprove', 'massDecline']);
        return parent::preDispatch();
    }

    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('customer/b2bregistration')
            ->_title($this->__('Customers'))->_title($this->__('Trade Applications'));
        return $this;
    }

    #[Route('/admin/b2bregistration_application/index')]
    public function indexAction(): void
    {
        $this->_initAction()->renderLayout();
    }

    #[Route('/admin/b2bregistration_application/grid')]
    public function gridAction(): void
    {
        $this->loadLayout(false)->renderLayout();
    }

    #[Route('/admin/b2bregistration_application/approve')]
    public function approveAction(): void
    {
        $this->_decide(true);
    }

    #[Route('/admin/b2bregistration_application/decline')]
    public function declineAction(): void
    {
        $this->_decide(false);
    }

    #[Route('/admin/b2bregistration_application/massApprove', methods: ['POST'])]
    public function massApproveAction(): void
    {
        $this->_decideMass(true);
    }

    #[Route('/admin/b2bregistration_application/massDecline', methods: ['POST'])]
    public function massDeclineAction(): void
    {
        $this->_decideMass(false);
    }

    private function _decide(bool $approve): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $application = $this->_loadApplication($id);
            $helper = Mage::helper('b2bregistration');
            if ($approve) {
                if ($helper->getB2bGroupId((int) $application->getStoreId()) <= 0) {
                    Mage::throwException($this->__('Set a B2B customer group in System Config first.'));
                }
                $helper->approveApplication($application);
                $this->_getSession()->addSuccess($this->__('Application approved; customer moved to the B2B group.'));
            } else {
                $helper->declineApplication($application);
                $this->_getSession()->addSuccess($this->__('Application declined.'));
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('Could not update the application.'));
        }
        $this->_redirect('*/*/index');
    }

    private function _decideMass(bool $approve): void
    {
        $ids = $this->getRequest()->getParam('application');
        if (!is_array($ids) || $ids === []) {
            $this->_getSession()->addError($this->__('Please select one or more applications.'));
            $this->_redirect('*/*/index');
            return;
        }
        $helper = Mage::helper('b2bregistration');
        $count = 0;
        foreach ($ids as $id) {
            try {
                $application = $this->_loadApplication((int) $id);
                if ($approve) {
                    if ($helper->getB2bGroupId((int) $application->getStoreId()) <= 0) {
                        continue;
                    }
                    $helper->approveApplication($application);
                } else {
                    $helper->declineApplication($application);
                }
                $count++;
            } catch (Throwable $e) {
                Mage::logException($e);
            }
        }
        $this->_getSession()->addSuccess($this->__('%s application(s) updated.', $count));
        $this->_redirect('*/*/index');
    }

    private function _loadApplication(int $id): MageAustralia_B2bRegistration_Model_Application
    {
        /** @var MageAustralia_B2bRegistration_Model_Application $application */
        $application = Mage::getModel('b2bregistration/application')->load($id);
        if (!$application->getId()) {
            Mage::throwException($this->__('This application no longer exists.'));
        }
        return $application;
    }
}
