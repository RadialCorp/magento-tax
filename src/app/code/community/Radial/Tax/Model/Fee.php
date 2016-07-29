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

use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedFee;

class Radial_Tax_Model_Fee extends Varien_Object
{
    /**
     * Resolve dependencies.
     */
    protected function _construct()
    {
        list(
            $this->_data['item_id'],
            $this->_data['address_id'],
            $feePayload
        ) = $this->_checkTypes(
            $this->_data['item_id'],
            $this->_data['address_id'],
            $this->getData('fee_payload')
        );
        // If a tax record was provided as a data source, extract data from it.
        if ($feePayload) {
            $this->_populateWithPayload($feePayload);
        }
        // Do not store the payload as it may lead to issues when storing
        // and retrieving the tax records in the session.
        $this->unsetData('fee_payload');
    }

    /**
     * Enforce type checks on constructor args array.
     *
     * @param int
     * @param ITaxedFee|null
     * @return array
     */
    protected function _checkTypes(
        $itemId,
        $addressId,
        ITaxedFee $feePayload = null
    ) {
        return [$itemId, $addressId, $feePayload];
    }

    /**
     * Extract data from the fee payload and use it to populate data.
     *
     * @param ITaxedFee
     * @return self
     */
    protected function _populateWithPayload(ITaxedFee $feePayload)
    {
        return $this->setType($feePayload->getType())
            ->setDescription($feePayload->getDescription())
            ->setCharge($feePayload->getCharge())
            ->setFeeId($feePayload->getId());
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->getData('type');
    }

    /**
     * @param string
     * @return string
     */
    public function setType($type)
    {
        return $this->setData('type', $type);
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->getData('description');
    }

    /**
     * @param string
     * @return self
     */
    public function setDescription($description)
    {
        return $this->setData('description', $description);
    }

    /**
     * @return 
     */
    public function getCharge()
    {
        return $this->getData('charge');
    }

    /**
     * @param 
     * @return self
     */
    public function setCharge($charge)
    {
        return $this->setData('charge', $charge);
    }

    /**
     * @return string
     */
    public function getFeeId()
    {
        return $this->getData('fee_id');
    }

    /**
     * @param string
     * @return self
     */
    public function setFeeId($id)
    {
        return $this->setData('fee_id', $id);
    }
}
