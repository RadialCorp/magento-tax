<?php
/**
 * Copyright (c) 2013-2014 eBay Enterprise, Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright   Copyright (c) 2013-2014 eBay Enterprise, Inc. (http://www.ebayenterprise.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedFee;

class EbayEnterprise_Tax_Model_Fee extends Varien_Object
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
            ->setAmount($feePayload->getAmount())
            ->setId($feePayload->getItemId())
            ->setTaxClass($feePayload->getTaxClass());
    }

    /**
     * Id of the quote item to which the fee applies.
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
     * Id of the quote address to which the fee applies.
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
    public function getId()
    {
        return $this->getData('id');
    }

    /**
     * @param string
     * @return self
     */
    public function setId($id)
    {
        return $this->setData('id', $id);
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
