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

use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedShipGroup;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedOrderItem;

class Radial_Tax_Model_Response_Parser_Addressorder extends Radial_Tax_Model_Response_Parser_Abstract
{
    /** @var ITaxedShipGroup */
    protected $_shipGroup;
    /** @var int */
    protected $_quoteId;
    /** @var Mage_Customer_Model_Address_Abstract */
    protected $_address;
    /** @var int */
    protected $_addressId;
    /** @var Radial_Tax_Helper_Factory */
    protected $_taxFactory;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $_logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $_logContext;
    /** @var Mage_Sales_Model_Order */
    protected $_order;

    /**
     * @param array $args Must contain key/value for:
     *                         - ship_group => eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedShipGroup
     *                         - address => Mage_Customer_Model_Address_Abstract
     *                         May contain key/value for:
     *                         - tax_factory => Radial_Tax_Helper_Factory
     *                         - logger => EbayEnterprise_MageLog_Helper_Data
     *                         - log_context => EbayEnterprise_MageLog_Helper_Context
     */
    public function __construct(array $args)
    {
        list(
            $this->_shipGroup,
            $this->_address,
            $this->_taxFactory,
            $this->_logger,
            $this->_logContext,
	    $this->_order
        ) = $this->_checkTypes(
            $args['ship_group'],
            $args['address'],
            $this->_nullCoalesce($args, 'tax_factory', Mage::helper('radial_tax/factory')),
            $this->_nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->_nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context')),
	    $args['order']
        );
        $this->_addressId = $this->_address->getId();
        $this->_quoteId = $this->_order->getQuoteId();
    }

    /**
     * Enforce type checks on constructor args array.
     *
     * @param ITaxedShipGroup
     * @param Mage_Customer_Model_Address_Abstract
     * @param Radial_Tax_Helper_Factory
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @param Mage_Sales_Model_Order
     * @return array
     */
    protected function _checkTypes(
        ITaxedShipGroup $shipGroups,
        Mage_Customer_Model_Address_Abstract $address,
        Radial_Tax_Helper_Factory $taxFactory,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext,
	Mage_Sales_Model_Order $order
    ) {
        return [$shipGroups, $address, $taxFactory, $logger, $logContext, $order];
    }

    /**
     * Extract tax data from the ship group.
     *
     * Extracts all three sets of tax data as each set of data can be retrieved
     * from the same item parser. Extracting all three sets at once prevents
     * nearly identical steps from being repeated for each item for each type of
     * tax data.
     *
     * @return Radial_Tax_Model_Record[]
     */
    protected function _extractTaxData()
    {
        // Each of these will hold an array of arrays of data extracted from each
        // ship group - e.g. $taxRecords = [[$recordA, $recordB], [$recordC, $recordD]].
        // Prepopulate tax records with data extracted for the address for gifting
        // so it will get merged together with item taxes.
        $taxRecords = [$this->_extractGiftingTaxRecords()];
        $duties = [];
        $fees = [];

        /** @var ITaxedOrderItem $orderItem */
        foreach ($this->_shipGroup->getItems() as $orderItem) {
            /** @var Mage_Sales_Model_Quote_Item $item */
            $item = $this->_getItemForItemPayload($orderItem);
            if ($item) {
                $itemParser = $this->_taxFactory
                    ->createResponseItemParser($orderItem, $item, $this->_addressId, $this->_quoteId);
                $taxRecords[] = $itemParser->getTaxRecords();
                $duties[] = $itemParser->getTaxDuties();
                $fees[] = $itemParser->getTaxFees();
            } else {
                $this->_logger->warning(
                    'Tax response item does not relate to any known quote item.',
                    $this->_logContext->getMetaData(__CLASS__, ['rom_response_body' => $orderItem->serialize()])
                );
            }
        }
        // Flatten each nested array of tax data - allows for a single array_merge
        // instead of iteratively calling array_merge on each pass when extracting
        // tax data for each item.
        $this->_taxRecords = $this->_flattenArray($taxRecords);
        $this->_taxDuties = $this->_flattenArray($duties);
        $this->_taxFees = $this->_flattenArray($fees);
        return $this;
    }

    /**
     * Extract tax records from a gifting payload.
     *
     * @return Radial_Tax_Model_Record[]
     */
    protected function _extractGiftingTaxRecords()
    {
        return $this->_taxFactory->createTaxRecordsForTaxContainer(
            Radial_Tax_Model_Record::SOURCE_ADDRESS_GIFTING,
            $this->_quoteId,
            $this->_addressId,
            $this->_shipGroup->getGiftPricing()
        );
    }

    /**
     * Get the address item the item payload represents.
     *
     * @param ITaxedOrderItem
     * @return Mage_Customer_Model_Address_Abstract_Item|null
     */
    protected function _getItemForItemPayload(ITaxedOrderItem $itemPayload)
    {
	$quote = Mage::getModel('sales/quote')->getCollection()
                        ->addFieldToFilter('entity_id', $this->_order->getQuoteId())
                        ->getFirstItem();

        foreach ($quote->getAllItems() as $item) {
            if ($item->getId() === $itemPayload->getLineNumber()) {
                return $item;
            }
        }
        return null;
    }
}
