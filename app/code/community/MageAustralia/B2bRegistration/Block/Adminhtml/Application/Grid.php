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
 * Trade-account applications with approve/decline. Customer email is joined from
 * customer_entity (a flat column); the full application data lives on the linked
 * Custom Forms submission.
 */
class MageAustralia_B2bRegistration_Block_Adminhtml_Application_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('b2bApplicationGrid');
        $this->setUseAjax(true);
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        /** @var MageAustralia_B2bRegistration_Model_Resource_Application_Collection $collection */
        $collection = Mage::getResourceModel('b2bregistration/application_collection');
        $customerTable = Mage::getSingleton('core/resource')->getTableName('customer/entity');
        $collection->getSelect()->joinLeft(
            ['ce' => $customerTable],
            'ce.entity_id = main_table.customer_id',
            ['customer_email' => 'ce.email'],
        );
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('application_id', [
            'header' => $this->__('ID'), 'index' => 'application_id', 'type' => 'number', 'width' => '60px',
        ]);
        $this->addColumn('customer_email', [
            'header' => $this->__('Customer'), 'index' => 'customer_email', 'filter_index' => 'ce.email',
        ]);
        $this->addColumn('is_new_account', [
            'header'  => $this->__('Type'),
            'index'   => 'is_new_account',
            'type'    => 'options',
            'options' => [0 => $this->__('Group upgrade'), 1 => $this->__('New account')],
            'width'   => '120px',
        ]);
        $this->addColumn('status', [
            'header'         => $this->__('Status'),
            'index'          => 'status',
            'type'           => 'options',
            'options'        => Mage::getModel('b2bregistration/source_applicationStatus')->toOptionHash(),
            'frame_callback' => [$this, 'decorateStatus'],
            'width'          => '110px',
        ]);
        $this->addColumn('created_at', [
            'header' => $this->__('Applied'), 'index' => 'created_at', 'type' => 'datetime', 'width' => '150px',
        ]);
        $this->addColumn('decided_at', [
            'header' => $this->__('Decided'), 'index' => 'decided_at', 'type' => 'datetime', 'width' => '150px',
        ]);
        $this->addColumn('action', [
            'header'   => $this->__('Action'),
            'type'     => 'action',
            'getter'   => 'getId',
            'filter'   => false,
            'sortable' => false,
            'width'    => '150px',
            'actions'  => [
                ['caption' => $this->__('Approve'), 'url' => ['base' => '*/*/approve'], 'field' => 'id'],
                ['caption' => $this->__('Decline'), 'url' => ['base' => '*/*/decline'], 'field' => 'id'],
            ],
        ]);
        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('application_id');
        $this->getMassactionBlock()->setFormFieldName('application');
        $this->getMassactionBlock()->addItem('approve', [
            'label' => $this->__('Approve'), 'url' => $this->getUrl('*/*/massApprove'),
        ]);
        $this->getMassactionBlock()->addItem('decline', [
            'label' => $this->__('Decline'), 'url' => $this->getUrl('*/*/massDecline'),
        ]);
        return $this;
    }

    public function decorateStatus(?string $value, MageAustralia_B2bRegistration_Model_Application $row, Mage_Adminhtml_Block_Widget_Grid_Column $column, bool $isExport): string
    {
        $class = match ((string) $row->getStatus()) {
            'approved' => 'grid-severity-notice',
            'declined' => 'grid-severity-critical',
            default    => 'grid-severity-minor',
        };
        return '<span class="' . $class . '"><span>' . $this->escapeHtml((string) $value) . '</span></span>';
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('adminhtml/customer/edit', ['id' => $row->getCustomerId()]);
    }
}
