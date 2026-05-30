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
 * Seed a ready-made "Trade Account Application" Custom Form so the flow works
 * out of the box. The form lives here (B2B side); Custom Forms stays generic.
 * Merchants can then edit it freely in the builder.
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$this->startSetup();

if (class_exists('MageAustralia_CustomForms_Model_Form')) {
    /** @var MageAustralia_CustomForms_Model_Form $form */
    $form = Mage::getModel('customforms/form')->loadByCode('trade_application');
    if (!$form->getId()) {
        $schema = [
            'version' => 1,
            'title'   => 'Trade Account Application',
            'fields'  => [
                ['type' => 'text', 'key' => 'company_name', 'label' => 'Company name', 'required' => true],
                ['type' => 'text', 'key' => 'firstname', 'label' => 'First name', 'width' => 'half', 'required' => true],
                ['type' => 'text', 'key' => 'lastname', 'label' => 'Last name', 'width' => 'half', 'required' => true],
                ['type' => 'email', 'key' => 'email', 'label' => 'Email', 'required' => true],
                ['type' => 'phone', 'key' => 'telephone', 'label' => 'Phone', 'required' => true],
                ['type' => 'text', 'key' => 'taxvat', 'label' => 'VAT / ABN', 'required' => true],
                ['type' => 'select', 'key' => 'business_type', 'label' => 'Business type', 'options' => [
                    ['value' => 'retailer', 'label' => 'Retailer'],
                    ['value' => 'wholesaler', 'label' => 'Wholesaler'],
                    ['value' => 'distributor', 'label' => 'Distributor'],
                    ['value' => 'other', 'label' => 'Other'],
                ]],
                ['type' => 'textarea', 'key' => 'trade_reference', 'label' => 'Trade references'],
            ],
        ];
        $settings = [
            'successMessage' => 'Thank you. Your trade-account application is under review; we will be in touch.',
            'captcha'        => true,
            'notify'         => [],
        ];
        $form->setCode('trade_application')
            ->setName('Trade Account Application')
            ->setIsActive(1)
            ->setStoreIds('')
            ->setSchema(json_encode($schema, JSON_THROW_ON_ERROR))
            ->setSettings(json_encode($settings, JSON_THROW_ON_ERROR))
            ->save();
    }
}

$this->endSetup();
