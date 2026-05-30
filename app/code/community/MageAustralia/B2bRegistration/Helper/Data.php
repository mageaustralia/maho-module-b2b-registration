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
 * Trade-account registration flow. Turns a Custom Forms trade-application
 * submission into a pending customer: maps the submitted fields onto customer
 * attributes (convention - field key === attribute code - plus an optional
 * override map), creates the account pending (via customer-approval), links the
 * application submission to it, and fires an event so a Company / ERP module can
 * pick up the rest. Non-customer fields (company name, phone, trade refs) stay
 * in the submission for that downstream consumer.
 */
class MageAustralia_B2bRegistration_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'MageAustralia_B2bRegistration';

    public const XML_ENABLED     = 'b2bregistration/general/enabled';
    public const XML_FORM_CODES  = 'b2bregistration/general/form_codes';
    public const XML_EMAIL_FIELD = 'b2bregistration/general/email_field';
    public const XML_OVERRIDES   = 'b2bregistration/mapping/overrides';

    public const EVENT_CUSTOMER_CREATED = 'b2bregistration_customer_created';

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

    /** @return array<string,string> form-field-key => customer-attribute-code */
    public function getOverrides(?int $storeId = null): array
    {
        $decoded = json_decode((string) Mage::getStoreConfig(self::XML_OVERRIDES, $storeId), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Resolve a submission payload to customer attribute values: convention
     * (key === attribute) plus the override map, filtered to the safe allow-list.
     *
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

    /**
     * Create a pending customer from a trade-application submission. Returns the
     * customer, or null when skipped (invalid email, or an account already
     * exists for that email on the website).
     */
    public function createCustomerFromSubmission(
        MageAustralia_CustomForms_Model_Form $form,
        MageAustralia_CustomForms_Model_Submission $submission,
    ): ?Mage_Customer_Model_Customer {
        $storeId   = (int) $submission->getStoreId() ?: (int) Mage::app()->getStore()->getId();
        $store     = Mage::app()->getStore($storeId);
        $websiteId = (int) $store->getWebsiteId();
        $payload   = $submission->getDecodedPayload();

        $email = trim((string) ($payload[$this->getEmailField($storeId)] ?? ''));
        if (!Mage::helper('core')->isValidEmail($email)) {
            Mage::log('b2bregistration: submission #' . $submission->getId() . ' has no valid email; skipped.', Mage::LOG_NOTICE, 'b2bregistration.log');
            return null;
        }

        $existing = Mage::getModel('customer/customer')->setWebsiteId($websiteId)->loadByEmail($email);
        if ($existing->getId()) {
            Mage::log('b2bregistration: account already exists for ' . $email . '; submission #' . $submission->getId() . ' not converted.', Mage::LOG_NOTICE, 'b2bregistration.log');
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
        // Random password: the account is pending and cannot log in yet; the
        // customer uses "forgot password" once approved.
        $customer->setPassword(Mage::helper('core')->getRandomString(16));
        $customer->save();

        // Link the application submission to the new account.
        $submission->setCustomerId((int) $customer->getId())->save();

        // Pending state (hard dependency on customer-approval).
        Mage::helper('customerapproval')->markPending($customer);

        // Seam for the Company / ERP layer (gets the full submission payload).
        Mage::dispatchEvent(self::EVENT_CUSTOMER_CREATED, [
            'customer'   => $customer,
            'form'       => $form,
            'submission' => $submission,
        ]);

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
