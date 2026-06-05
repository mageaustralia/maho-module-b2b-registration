<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_B2bRegistration
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** Real customer groups (excludes guest + the synthetic ALL) for the target-group select. */
class MageAustralia_B2bRegistration_Model_Source_CustomerGroup
{
    /** @return list<array{value:int|string,label:string}> */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => Mage::helper('b2bregistration')->__('-- Please select --')]];
        foreach (Mage::getResourceModel('customer/group_collection')->setRealGroupsFilter() as $g) {
            $options[] = ['value' => (int) $g->getId(), 'label' => (string) $g->getCustomerGroupCode()];
        }
        return $options;
    }
}
