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

use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedOrderItem;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedPriceGroup;

class EbayEnterprise_Tax_Model_Response_Parser_Item extends EbayEnterprise_Tax_Model_Response_Parser_Abstract
{
    /** @var ITaxedOrderItem */
    protected $_orderItem;
    /** @var int */
    protected $_quoteId;
    /** @var int */
    protected $_addressId;
    /** @var Mage_Sales_Model_Quote_Item_Abstract */
    protected $_item;
    /** @var int */
    protected $_itemId;
    /** @var EbayEnterprise_Tax_Helper_Factory */
    protected $_taxFactory;

    /**
     * @param array $args Must contain key/value for:
     *                         - order_item => eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedOrderItem
     *                         - item => Mage_Sales_Model_Quote_Item_Abstract
     *                         - address_id => int
     *                         - quote_id => int
     *                         May contain key/value for:
     *                         - tax_factory => EbayEnterprise_Tax_Helper_Factory
     */
    public function __construct(array $args)
    {
        list(
            $this->_orderItem,
            $this->_item,
            $this->_addressId,
            $this->_quoteId,
            $this->_taxFactory
        ) = $this->_checkTypes(
            $args['order_item'],
            $args['item'],
            $args['address_id'],
            $args['quote_id'],
            $this->_nullCoalesce($args, 'tax_factory', Mage::helper('ebayenterprise_tax/factory'))
        );
        $this->_itemId = $this->_item->getId();
    }

    /**
     * Enforce type checks on constructor args array.
     *
     * @param ITaxedOrderItem
     * @param Mage_Sales_Model_Quote_Item_Abstract
     * @param int
     * @param int
     * @param EbayEnterprise_Tax_Helper_Factory
     * @return array
     */
    protected function _checkTypes(
        ITaxedOrderItem $orderItem,
        Mage_Sales_Model_Quote_Item_Abstract $item,
        $addressId,
        $quoteId,
        EbayEnterprise_Tax_Helper_Factory $taxFactory
    ) {
        return [$orderItem, $item, $addressId, $quoteId, $taxFactory];
    }

    /**
     * Extract all tax data for the item from the payload.
     *
     * @return self
     */
    protected function _extractTaxData()
    {
        $this->_taxRecords = $this->_extractTaxRecords();
        $this->_taxDuties = $this->_extractDuties();
        $this->_taxFees = $this->_extractFees();
        return $this;
    }

    /**
     * Extract tax records from the item payload.
     *
     * @return EbayEnterprise_Tax_Model_Record[]
     */
    protected function _extractTaxRecords()
    {
        return array_merge(
            $this->_extractPricingTaxRecords(
                EbayEnterprise_Tax_Model_Record::SOURCE_MERCHANDISE,
                EbayEnterprise_Tax_Model_Record::SOURCE_MERCHANDISE_DISCOUNT,
                $this->_orderItem->getMerchandisePricing()
            ),
            $this->_extractPricingTaxRecords(
                EbayEnterprise_Tax_Model_Record::SOURCE_SHIPPING,
                EbayEnterprise_Tax_Model_Record::SOURCE_SHIPPING_DISCOUNT,
                $this->_orderItem->getShippingPricing()
            ),
            $this->_extractPricingTaxRecords(
                EbayEnterprise_Tax_Model_Record::SOURCE_DUTY,
                EbayEnterprise_Tax_Model_Record::SOURCE_DUTY_DISCOUNT,
                $this->_orderItem->getDutyPricing()
            ),
            $this->_extractFees(),
            $this->_extractItemCustomizationTaxRecords(),
            $this->_extractGiftingTaxRecords()
        );
    }

    /**
     * Extract duties from the item payload.
     *
     * There will only ever be one duty record for an item but returns an array
     * to remain consistent with interfaces of other response parsers and tax
     * record sets.
     *
     * @return EbayEnterprise_Tax_Model_Duty[]
     */
    protected function _extractDuties()
    {
        $dutyPricing = $this->_orderItem->getDutyPricing();
        $duties = [];
        if ($dutyPricing) {
            $duties[] = $this->_taxFactory->createTaxDuty(
                $dutyPricing,
                $this->_itemId,
                $this->_addressId
            );
        }
        return $duties;
    }

    /**
     * Extract fees for the item from the payload.
     *
     * @return EbayEnterprise_Tax_Model_Fee[]
     */
    protected function _extractFees()
    {
        $fees = [];
        foreach ($this->_orderItem->getFees() as $fee) {
            $fees[] = $this->_taxFactory->createTaxFee($fee, $this->_itemId, $this->_addressId);
        }
        return $this->_flattenArray($fees);
    }

    /**
     * Extract price group taxes and discount taxes.
     *
     * @param ITaxedPriceGroup
     * @param int
     * @param int
     * @return EbayEnterprise_Tax_Model_Record[]
     */
    protected function _extractPricingTaxRecords(
        $source,
        $discountSource,
        ITaxedPriceGroup $priceGroup = null,
        $recordData = []
    ) {
        $taxRecords = [];
        if ($priceGroup) {
            $taxRecords[] = $this->_taxFactory->createTaxRecordsForTaxContainer(
                $source,
                $this->_quoteId,
                $this->_addressId,
                $priceGroup,
                array_merge($recordData, ['item_id' => $this->_itemId])
            );
            foreach ($priceGroup->getDiscounts() as $discount) {
                $taxRecords[] = $this->_taxFactory->createTaxRecordsForTaxContainer(
                    $discountSource,
                    $this->_quoteId,
                    $this->_addressId,
                    $discount,
                    array_merge(
                        $recordData,
                        ['item_id' => $this->_itemId, 'discount_id' => $discount->getId()]
                    )
                );
            }
        }
        return $this->_flattenArray($taxRecords);
    }

    /**
     * Extract tax records from fees.
     *
     * @return EbayEnterprise_Tax_Model_Record[]
     */
    protected function _extractFeesTaxRecords()
    {
        $taxRecords = [];
        foreach ($this->_orderItem->getFees() as $fee) {
            $taxRecords[] = $this->_extractPricingTaxRecords(
                EbayEnterprise_Tax_Model_Record::SOURCE_FEE,
                EbayEnterprise_Tax_Model_Record::SOURCE_FEE_DISCOUNT,
                $fee->getPricing(),
                ['fee_id' => $fee->getId()]
            );
        }
        return $this->_flattenArray($taxRecords);
    }

    /**
     * Extract taxes from customization payloads - base and feature taxes.
     *
     * @return EbayEnterprise_Tax_Model_Record[]
     */
    protected function _extractItemCustomizationTaxRecords()
    {
        $taxRecords = [$this->_taxFactory->createTaxRecordsForTaxContainer(
            EbayEnterprise_Tax_Model_Record::SOURCE_CUSTOMIZATION_BASE,
            $this->_quoteId,
            $this->_addressId,
            $this->_orderItem->getCustomizationBasePricing(),
            ['item_id' => $this->_itemId]
        )];
        foreach ($this->_orderItem->getCustomizations() as $customFeature) {
            $taxRecords[] = $this->_taxFactory->createTaxRecordsForTaxContainer(
                EbayEnterprise_Tax_Model_Record::SOURCE_CUSTOMIZATION_FEATURE,
                $this->_quoteId,
                $this->_addressId,
                $customFeature->getUpCharge(),
                ['item_id' => $this->_itemId]
            );
        }
        return $this->_flattenArray($taxRecords);
    }

    /**
     * Extract tax records from a gifting payload.
     *
     * @return EbayEnterprise_Tax_Model_Record
     */
    protected function _extractGiftingTaxRecords()
    {
        return $this->_taxFactory->createTaxRecordsForTaxContainer(
            EbayEnterprise_Tax_Model_Record::SOURCE_ITEM_GIFTING,
            $this->_quoteId,
            $this->_addressId,
            $this->_orderItem->getGiftPricing(),
            ['item_id' => $this->_itemId]
        );
    }
}
