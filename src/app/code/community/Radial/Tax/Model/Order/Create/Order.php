<?php
/**
 * Copyright (c) 2013-2016 Radial Commerce Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright   Copyright (c) 2013-2016 Radial Commerce Inc. (http://www.radial.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use eBayEnterprise\RetailOrderManagement\Payload\Order\IOrderCreateRequest;

/**
 * Adds Tax information to an OrderItem payload and its sub payloads.
 */
class Radial_Tax_Model_Order_Create_Order
{
    /** @var Radial_Tax_Model_Collector  */
    protected $_taxCollector;
    /** @var IOrderCreateRequest */
    protected $_orderCreateRequest;

    /**
     * @param array $args Must contain key/value for:
     *                         - order_create_request => eBayEnterprise\RetailOrderManagement\Payload\Order\IOrderCreateRequest
     *                         May contain key/value for:
     *                         - tax_collector => Radial_Tax_Model_Collector
     */
    public function __construct(array $args = [])
    {
        list(
            $this->_taxCollector,
            $this->_orderCreateRequest
        ) = $this->_checkTypes(
            $this->_nullCoalesce($args, 'tax_collector', Mage::getModel('radial_tax/collector')),
            $args['order_create_request']
        );
    }

    /**
     * Enforce type checks on construct args array.
     *
     * @param Radial_Tax_Model_Collector
     * @param Mage_Sales_Model_Order
     * @param IOrderCreateRequest
     * @return array
     */
    protected function _checkTypes(
        Radial_Tax_Model_Collector $taxCollector,
        IOrderCreateRequest $orderCreateRequest
    ) {
        return [$taxCollector, $orderCreateRequest];
    }

    /**
     * Fill in default values.
     *
     * @param string
     * @param array
     * @param mixed
     * @return mixed
     */
    protected function _nullCoalesce(array $arr, $key, $default)
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /**
     * Set the tax has errors flag on the order create request if the tax
     * collector or any of the collected tax records contain an error.
     *
     * @param  IOrderCreateRequest
     * @param  Mage_Sales_Model_Order
     * @return self
     */
    public function addTaxHeaderErrorFlag()
    {
        $hasErrors = ($this->_taxCollectorHasErrors() || $this->_itemsHaveErrors());
        $this->_orderCreateRequest->setTaxHasErrors($hasErrors);
        return $this;
    }

    /**
     * Determine if there were any errors in collecting tax records.
     *
     * @return bool
     */
    protected function _taxCollectorHasErrors()
    {
        return !$this->_taxCollector->getTaxRequestSuccess();
    }

    /**
     * Check if there are any errors in the taxes.
     * @param  Mage_Sales_Model_Order_Address
     * @return bool
     */
    protected function _itemsHaveErrors()
    {
        foreach ($this->_taxCollector->getTaxDuties() as $duty) {
            if ($duty->getCalculationError()) {
                return true;
            }
        }
        return false;
    }
}
