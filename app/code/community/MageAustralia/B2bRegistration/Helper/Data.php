<?php

declare(strict_types=1);

use MageAustralia_B2bRegistration_Model_Application as Application;

/**
 * Maho
 *
 * @package    MageAustralia_B2bRegistration
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Trade-account registration flow. A submission of a configured trade form
 * becomes a B2B application:
 *
 *  - Existing customer (logged in, or matched by email) -> a tier-upgrade
 *    request. Their login/group is untouched; they keep shopping as retail
 *    until an admin approves, which assigns the configured B2B group.
 *  - New prospect -> a pending customer account (blocked from login via
 *    customer-approval) + the application; approval opens the account AND
 *    assigns the B2B group.
 *
 * Field data stays on the Custom Forms submission (linked to the customer);
 * standard customer attributes (name, taxvat, ...) are mapped onto the account.
 */
class MageAustralia_B2bRegistration_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'MageAustralia_B2bRegistration';

    public const XML_ENABLED     = 'b2bregistration/general/enabled';
    public const XML_FORM_CODES  = 'b2bregistration/general/form_codes';
    public const XML_EMAIL_FIELD = 'b2bregistration/general/email_field';
    public const XML_B2B_GROUP   = 'b2bregistration/general/b2b_group';
    public const XML_OVERRIDES   = 'b2bregistration/mapping/overrides';

    public const EVENT_CUSTOMER_CREATED = 'b2bregistration_customer_created';
    public const EVENT_GROUP_ASSIGNED   = 'b2bregistration_group_assigned';

    /** Customer attributes a public application form may safely populate. */
    private const array ALLOWED_ATTRIBUTES = [
        'firstname', 'lastname', 'email', 'taxvat',
        'prefix', 'middlename', 'suffix', 'dob', 'gender',
    ];

    public function isEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_ENABLED, $storeId);
    }

    /** @return list<string> */
    public function getTradeFormCodes(?int $storeId = null): array
    {
        $raw = (string) Mage::getStoreConfig(self::XML_FORM_CODES, $storeId);
        return array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
    }

    public function isTradeForm(string $code, ?int $storeId = null): bool
    {
        return in_array($code, $this->getTradeFormCodes($storeId), true);
    }

    public function getEmailField(?int $storeId = null): string
    {
        $field = trim((string) Mage::getStoreConfig(self::XML_EMAIL_FIELD, $storeId));
        return $field !== '' ? $field : 'email';
    }

    public function getB2bGroupId(?int $storeId = null): int
    {
        return (int) Mage::getStoreConfig(self::XML_B2B_GROUP, $storeId);
    }

    /** @return array<string,string> form-field-key => customer-attribute-code */
    public function getOverrides(?int $storeId = null): array
    {
        $decoded = json_decode((string) Mage::getStoreConfig(self::XML_OVERRIDES, $storeId), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,string>
     */
    public function resolveAttributes(array $payload, ?int $storeId = null): array
    {
        $overrides = $this->getOverrides($storeId);
        $resolved = [];
        foreach ($payload as $key => $value) {
            $attr = $overrides[$key] ?? $key;
            if (!in_array($attr, self::ALLOWED_ATTRIBUTES, true)) {
                continue;
            }
            $resolved[$attr] = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
        }
        return $resolved;
    }

    /* ---------------- application intake ---------------- */

    /**
     * Turn a trade-form submission into a B2B application. Returns the
     * application, or null when skipped (e.g. a new prospect with no valid
     * email). Idempotent-ish: an existing open application for the same customer
     * is reused rather than duplicated.
     */
    public function processApplication(
        MageAustralia_CustomForms_Model_Form $form,
        MageAustralia_CustomForms_Model_Submission $submission,
    ): ?Application {
        $storeId = (int) $submission->getStoreId() ?: (int) Mage::app()->getStore()->getId();
        $payload = $submission->getDecodedPayload();

        $customer = $this->_resolveApplicant($submission, $payload, $storeId);
        $isNew = false;
        if (!$customer) {
            $customer = $this->_createPendingCustomer($form, $submission, $payload, $storeId);
            if (!$customer) {
                return null;
            }
            $isNew = true;
        } elseif (!$submission->getCustomerId()) {
            $submission->setCustomerId((int) $customer->getId())->save();
        }

        if (!$isNew && $this->_hasOpenApplication((int) $customer->getId())) {
            Mage::log('b2bregistration: customer #' . $customer->getId() . ' already has an open application; submission #' . $submission->getId() . ' ignored.', Mage::LOG_NOTICE, 'b2bregistration.log');
            return null;
        }

        /** @var Application $application */
        $application = Mage::getModel('b2bregistration/application');
        $application->setSubmissionId((int) $submission->getId())
            ->setCustomerId((int) $customer->getId())
            ->setStoreId($storeId)
            ->setStatus(Application::STATUS_PENDING)
            ->setIsNewAccount($isNew ? 1 : 0)
            ->setCreatedAt(Mage_Core_Model_Locale::nowUtc())
            ->save();

        Mage::dispatchEvent(self::EVENT_CUSTOMER_CREATED, [
            'customer'    => $customer,
            'form'        => $form,
            'submission'  => $submission,
            'application' => $application,
            'is_new'      => $isNew,
        ]);

        return $application;
    }

    /** Approve an application: assign the B2B group (and open the account if new). */
    public function approveApplication(Application $application): void
    {
        $customer = $this->_loadCustomer((int) $application->getCustomerId());
        $groupId = $this->getB2bGroupId((int) $application->getStoreId());
        if ($groupId > 0 && (int) $customer->getGroupId() !== $groupId) {
            $customer->setGroupId($groupId);
            $customer->getResource()->saveAttribute($customer, 'group_id');
        }
        // A new-prospect account is pending; opening it (approve) lets them log in.
        if ($application->getIsNewAccount()) {
            Mage::helper('customerapproval')->approveCustomer($customer);
        }
        $application->setStatus(Application::STATUS_APPROVED)
            ->setDecidedAt(Mage_Core_Model_Locale::nowUtc())
            ->save();

        Mage::dispatchEvent(self::EVENT_GROUP_ASSIGNED, [
            'customer'    => $customer,
            'application' => $application,
            'group_id'    => $groupId,
        ]);
    }

    /** Decline an application (and reject a new-prospect account). */
    public function declineApplication(Application $application): void
    {
        if ($application->getIsNewAccount()) {
            $customer = $this->_loadCustomer((int) $application->getCustomerId());
            Mage::helper('customerapproval')->rejectCustomer($customer);
        }
        $application->setStatus(Application::STATUS_DECLINED)
            ->setDecidedAt(Mage_Core_Model_Locale::nowUtc())
            ->save();
    }

    /* ---------------- internals ---------------- */

    private function _resolveApplicant(
        MageAustralia_CustomForms_Model_Submission $submission,
        array $payload,
        int $storeId,
    ): ?Mage_Customer_Model_Customer {
        $websiteId = (int) Mage::app()->getStore($storeId)->getWebsiteId();

        $customerId = (int) $submission->getCustomerId();
        if ($customerId > 0) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            if ($customer->getId()) {
                return $customer;
            }
        }
        $email = trim((string) ($payload[$this->getEmailField($storeId)] ?? ''));
        if ($email !== '' && Mage::helper('core')->isValidEmail($email)) {
            $customer = Mage::getModel('customer/customer')->setWebsiteId($websiteId)->loadByEmail($email);
            if ($customer->getId()) {
                return $customer;
            }
        }
        return null;
    }

    private function _createPendingCustomer(
        MageAustralia_CustomForms_Model_Form $form,
        MageAustralia_CustomForms_Model_Submission $submission,
        array $payload,
        int $storeId,
    ): ?Mage_Customer_Model_Customer {
        $store     = Mage::app()->getStore($storeId);
        $websiteId = (int) $store->getWebsiteId();

        $email = trim((string) ($payload[$this->getEmailField($storeId)] ?? ''));
        if (!Mage::helper('core')->isValidEmail($email)) {
            Mage::log('b2bregistration: submission #' . $submission->getId() . ' has no valid email; skipped.', Mage::LOG_NOTICE, 'b2bregistration.log');
            return null;
        }

        $attrs = $this->resolveAttributes($payload, $storeId);
        [$firstname, $lastname] = $this->_resolveName($attrs, $payload);

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId($websiteId)->setStore($store);
        $customer->setEmail($email)->setFirstname($firstname)->setLastname($lastname);
        foreach ($attrs as $code => $value) {
            if (!in_array($code, ['firstname', 'lastname', 'email'], true) && $value !== '') {
                $customer->setData($code, $value);
            }
        }
        $customer->setPassword(Mage::helper('core')->getRandomString(16));
        $customer->save();

        $submission->setCustomerId((int) $customer->getId())->save();
        Mage::helper('customerapproval')->markPending($customer);

        return $customer;
    }

    private function _hasOpenApplication(int $customerId): bool
    {
        return (int) Mage::getResourceModel('b2bregistration/application_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('status', Application::STATUS_PENDING)
            ->getSize() > 0;
    }

    private function _loadCustomer(int $id): Mage_Customer_Model_Customer
    {
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')->load($id);
        if (!$customer->getId()) {
            Mage::throwException($this->__('The applicant customer no longer exists.'));
        }
        return $customer;
    }

    /**
     * @param array<string,string> $attrs
     * @param array<string,mixed>  $payload
     * @return array{0:string,1:string}
     */
    private function _resolveName(array $attrs, array $payload): array
    {
        $first = trim((string) ($attrs['firstname'] ?? ''));
        $last  = trim((string) ($attrs['lastname'] ?? ''));
        if ($first === '' || $last === '') {
            $name = trim((string) ($payload['name'] ?? $payload['full_name'] ?? $payload['contact_name'] ?? ''));
            if ($name !== '') {
                $parts = preg_split('/\s+/', $name, 2) ?: [];
                $first = $first !== '' ? $first : (string) ($parts[0] ?? '');
                $last  = $last !== '' ? $last : (string) ($parts[1] ?? '');
            }
        }
        if ($first === '') {
            $first = trim((string) ($payload['company_name'] ?? '')) ?: 'Applicant';
        }
        if ($last === '') {
            $last = '-';
        }
        return [$first, $last];
    }
}
