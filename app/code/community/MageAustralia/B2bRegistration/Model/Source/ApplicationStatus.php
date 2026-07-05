<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_B2bRegistration
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_B2bRegistration_Model_Source_ApplicationStatus
{
    /** @return list<array{value:string,label:string}> */
    public function toOptionArray(): array
    {
        $h = Mage::helper('b2bregistration');
        return [
            ['value' => 'pending',  'label' => $h->__('Pending')],
            ['value' => 'approved', 'label' => $h->__('Approved')],
            ['value' => 'declined', 'label' => $h->__('Declined')],
        ];
    }

    /** @return array<string,string> */
    public function toOptionHash(): array
    {
        $hash = [];
        foreach ($this->toOptionArray() as $o) {
            $hash[$o['value']] = $o['label'];
        }
        return $hash;
    }
}
