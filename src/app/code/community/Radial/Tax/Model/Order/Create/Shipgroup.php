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

use eBayEnterprise\RetailOrderManagement\Payload\Order\IGifting;
use eBayEnterprise\RetailOrderManagement\Payload\Order\ITax;

/**
 * Adds Tax information to an OrderItem payload and its sub payloads.
 */
class Radial_Tax_Model_Order_Create_Shipgroup
{
    /** @var Radial_Tax_Model_Collector */
    protected $_taxCollector;
    /** @var Radial_Tax_Helper_Payload */
    protected $_payloadHelper;
    /** @var Mage_Sales_Model_Order_Address */
    protected $_address;
    /** @var IGifting */
    protected $_shipGroup;

    /**
     * @param array $args Must contain key/value for:
     *                         - address => Mage_Sales_Model_Order_Address
     *                         - ship_group => eBayEnterprise\RetailOrderManagement\Payload\Order\IGifting
     *                         May contain key/value for:
     *                         - tax_collector => Radial_Tax_Model_Collector
     *                         - payload_helper => Radial_Tax_Helper_Payload
     */
    public function __construct(array $args = array())
    {
        list(
            $this->_address,
            $this->_shipGroup,
            $this->_taxCollector,
            $this->_payloadHelper
        ) = $this->_checkTypes(
            $args['address'],
            $args['ship_group'],
            $this->_nullCoalesce($args, 'tax_collector', Mage::getModel('radial_tax/collector')),
            $this->_nullCoalesce($args, 'payload_helper', Mage::helper('radial_tax/payload'))
        );
    }


    /**
     * Enforce type checks on constructor args array.
     *
     * @param Mage_Sales_Model_Order_Address
     * @param IGifting
     * @param Radial_Tax_Model_Collector
     * @param Radial_Tax_Helper_Payload
     * @return array
     */
    protected function _checkTypes(
        Mage_Sales_Model_Order_Address $address,
        IGifting $shipGroup,
        Radial_Tax_Model_Collector $taxCollector,
        Radial_Tax_Helper_Payload $payloadHelper
    ) {
        return [$address, $shipGroup, $taxCollector, $payloadHelper];
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
     * Add taxes to the ship group for any tax records related to address
     * level gifting.
     *
     * @return self
     */
    public function addGiftTaxesToPayload()
    {
        $giftTaxes = $this->_getAddressGiftTaxRecords();
        if ($giftTaxes) {
            $taxContainer = $this->_getTaxContainer();
            $taxIterable = $taxContainer->getTaxes();
            foreach ($giftTaxes as $giftTax) {
                $taxPayload = $this->_payloadHelper
                    ->taxRecordToTaxPayload($giftTax, $taxIterable->getEmptyTax());
                // Somewhat idiosyncratic way of adding to the iterable...required
                // due to the iterable currently being implemented as an SPLObjectStorage
                // which requires objects stored to be set as keys, not values.
                $taxIterable[$taxPayload] = null;
            }
            $taxContainer->setTaxes($taxIterable);
        }
        return $this;
    }

    /**
     * Get all tax records for this address that should were applied to
     * address level gifting prices.
     *
     * @return Radial_Tax_Model_Record[]
     */
    protected function _getAddressGiftTaxRecords()
    {
        $addressId = $this->_address->getQuoteAddressId();
        // Filter down the list of tax records to only those with a matching
        // quote address id, with a source indicating they apply to address level
        // gifting, and do not indicate a calculation error for the record.
        return array_filter(
            $this->_taxCollector->getTaxRecords(),
            function ($record) use ($addressId) {
                return $record->getAddressId() === $addressId
                    && $record->getTaxSource() === Radial_Tax_Model_Record::SOURCE_ADDRESS_GIFTING;
            }
        );
    }

    /**
     * Get the tax container for address level gifting taxes.
     *
     * @return ITaxContainer
     */
    protected function _getTaxContainer()
    {
        $taxContainer = $this->_shipGroup->getGiftPricing();
        if (!$taxContainer) {
            $taxContainer = $this->_shipGroup->getEmptyGiftingPriceGroup();
            $this->_shipGroup->setGiftPricing($taxContainer);
        }
        return $taxContainer;
    }
}
