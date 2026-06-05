<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_B2bRegistration
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_B2bRegistration_Block_Adminhtml_Application extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_application';
        $this->_blockGroup = 'b2bregistration';
        $this->_headerText = $this->__('Trade Applications');
        parent::__construct();
        $this->_removeButton('add');
    }
}
