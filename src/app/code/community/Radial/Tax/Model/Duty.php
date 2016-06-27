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

use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedDutyPriceGroup;

class Radial_Tax_Model_Duty extends Varien_Object
{
    /**
     * Resolve dependencies.
     */
    protected function _construct()
    {
        list(
            $this->_data['item_id'],
            $this->_data['address_id'],
            $dutyPayload
        ) = $this->_checkTypes(
            $this->_data['item_id'],
            $this->_data['address_id'],
            $this->getData('duty_payload')
        );
        // If a tax record was provided as a data source, extract data from it.
        if ($dutyPayload) {
            $this->_populateWithPayload($dutyPayload);
        }
        // Do not store the payload as it may lead to issues when storing
        // and retrieving the tax records in the session.
        $this->unsetData('duty_payload');
    }

    /**
     * Enforce type checks on constructor args array.
     *
     * @param int
     * @param int
     * @param ITaxedDutyPriceGroup|null
     * @return array
     */
    protected function _checkTypes(
        $itemId,
        $addressId,
        ITaxedDutyPriceGroup $dutyPayload = null
    ) {
        return [$itemId, $addressId, $dutyPayload];
    }

    /**
     * Extract data from the fee payload and use it to populate data.
     *
     * @param ITaxedDutyPriceGroup
     */
    protected function _populateWithPayload(ITaxedDutyPriceGroup $dutyPayload)
    {
        return $this->setCalculationError($dutyPayload->getCalculationError())
            ->setAmount($dutyPayload->getAmount())
            ->setTaxClass($dutyPayload->getTaxClass());
    }

    /**
     * Id of the quote item the duty applies to.
     *
     * @return int
     */
    public function getItemId()
    {
        return $this->getData('item_id');
    }

    /**
     * @param int
     * @return self
     */
    public function setItemId($itemId)
    {
        return $this->setData('item_id', $itemId);
    }

    /**
     * Id of the quote address the duty applies to.
     *
     * @return int
     */
    public function getAddressId()
    {
        return $this->getData('address_id');
    }

    /**
     * @param int
     * @return self
     */
    public function setAddressId($addressId)
    {
        return $this->setData('address_id', $addressId);
    }

    /**
     * @return string
     */
    public function getCalculationError()
    {
        return $this->getData('calculation_error');
    }

    /**
     * @param string
     * @return self
     */
    public function setCalculationError($calculationError)
    {
        return $this->setData('calculation_error', $calculationError);
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->getData('amount');
    }

    /**
     * @param float
     * @return self
     */
    public function setAmount($amount)
    {
        return $this->setData('amount', $amount);
    }

    /**
     * @return string
     */
    public function getTaxClass()
    {
        return $this->getData('tax_class');
    }

    /**
     * @param string
     * @return self
     */
    public function setTaxClass($taxClass)
    {
        return $this->setData('tax_class', $taxClass);
    }
}
