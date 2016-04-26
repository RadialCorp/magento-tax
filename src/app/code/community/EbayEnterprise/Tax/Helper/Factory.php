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

use eBayEnterprise\RetailOrderManagement\Payload\Checkout\ITax;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IDestinationIterable;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IOrderItemRequestIterable;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IShipGroupIterable;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteReply;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteRequest;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedDutyPriceGroup;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedFee;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedFeeIterable;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedOrderItem;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedShipGroup;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxContainer;

class EbayEnterprise_Tax_Helper_Factory
{
    /**
     * Construct a new tax record.
     *
     * @param int $taxSource Should be one of the tax record source consts.
     * @param int
     * @param int
     * @param int|null $itemId Address level taxes may not have an associated item id.
     * @param ITax $taxPayload|null SDK payload of tax data to use to populate the tax record.
     * @param array $recordData Tax record data to be set directly on the tax record. May be used in place of an ITax payload.
     * @return EbayEnterprise_Tax_Model_Record
     */
    public function createTaxRecord(
        $taxSource,
        $quoteId,
        $addressId,
        ITax $taxPayload = null,
        array $recordData = []
    ) {
        $data = array_merge(
            $recordData,
            [
                'tax_record_payload' => $taxPayload,
                'tax_source' => $taxSource,
                'quote_id' => $quoteId,
                'address_id' => $addressId,
            ]
        );
        return Mage::getModel('ebayenterprise_tax/record', $data);
    }

    /**
     * Create a tax record for each tax payload in the tax iterable.
     *
     * @param ITaxContainer
     * @param int $taxSource Should be one of hte tax record source consts.
     * @param int
     * @param int
     * @return EbayEnterprise_Tax_Model_Record[]
     */
    public function createTaxRecordsForTaxContainer(
        $taxSource,
        $quoteId,
        $addressId,
        ITaxContainer $taxContainer = null,
        array $recordData = []
    ) {
        $records = [];
        if ($taxContainer) {
            foreach ($taxContainer->getTaxes() as $tax) {
                $records[] = $this->createTaxRecord(
                    $taxSource,
                    $quoteId,
                    $addressId,
                    $tax,
                    $recordData
                );
            }
        }
        return $records;
    }

    /**
     * Create a new tax duty model.
     *
     * @param ITaxedFee
     * @param int
     * @param int
     * @return EbayEnterprise_Tax_Model_Fee
     */
    public function createTaxFee(ITaxedFee $fee, $itemId, $addressId)
    {
        return Mage::getModel(
            'ebayenterprise_tax/duty',
            ['item_id' => $itemId, 'address_id' => $addressId, 'fee_payload' => $fee]
        );
    }

    /**
     * Create a fee model for each fee payload in the iterable of fees.
     *
     * @param ITaxedFeeIterable
     * @param int
     * @param int
     * @return EbayEnterprise_Tax_Model_Fee[]
     */
    public function createFeeForFeeIterable(ITaxedFeeIterable $feeIterable, $itemId, $addressId)
    {
        $fees = [];
        foreach ($feeIterable as $fee) {
            $fees[] = $this->createTaxFee($fee, $itemId, $addressId);
        }
        return $fees;
    }

    /**
     * Create a new tax duty model.
     *
     * @param ITaxedDutyPriceGroup
     * @param int
     * @param int
     * @return EbayEnterprise_Tax_Model_Duty
     */
    public function createTaxDuty(
        ITaxedDutyPriceGroup $dutyPriceGroup,
        $itemId,
        $addressId
    ) {
        return Mage::getModel(
            'ebayenterprise_tax/duty',
            ['item_id' => $itemId, 'address_id' => $addressId, 'duty_payload' => $dutyPriceGroup]
        );
    }

    /**
     * Construct a new quote tax response parser.
     *
     * @param ITaxDutyFeeQuoteReply
     * @param Mage_Sales_Model_Quote
     * @return EbayEnterprise_Tax_Model_Response_Parser_Quote
     */
    public function createResponseQuoteParser(
        ITaxDutyFeeQuoteReply $taxResponse,
        Mage_Sales_Model_Quote $quote
    ) {
        return Mage::getModel(
            'ebayenterprise_tax/response_parser_quote',
            ['tax_response' => $taxResponse, 'quote' => $quote,]
        );
    }

    /**
     * Construct a new address tax response parser.
     *
     * @param ITaxedShipGroup
     * @param Mage_Sales_Model_Quote_Address
     * @return EbayEnterprise_Tax_Model_Response_Parser_Address
     */
    public function createResponseAddressParser(
        ITaxedShipGroup $shipGroup,
        Mage_Sales_Model_Quote_Address $address
    ) {
        return Mage::getModel(
            'ebayenterprise_tax/response_parser_address',
            [
                'ship_group' => $shipGroup,
                'address' => $address,
            ]
        );
    }

    /**
     * Construct a new item tax response parser.
     *
     * @param ITaxedOrderItem
     * @param Mage_Sales_Model_Quote_Item_Abstract
     * @param int
     * @param int
     * @return EbayEnterprise_Tax_Model_Response_Parser_Item
     */
    public function createResponseItemParser(
        ITaxedOrderItem $orderItem,
        Mage_Sales_Model_Quote_Item_Abstract $item,
        $addressId,
        $quoteId
    ) {
        return Mage::getModel(
            'ebayenterprise_tax/response_parser_item',
            [
                'order_item' => $orderItem,
                'item' => $item,
                'address_id' => $addressId,
                'quote_id' => $quoteId,
            ]
        );
    }

    /**
     * Construct a new quote tax request builder.
     *
     * @param ITaxDutyFeeQuoteRequest
     * @param Mage_Sales_Model_Quote
     * @return EbayEnterprise_Tax_Model_Request_Builder_Quote
     */
    public function createRequestBuilderQuote(
        ITaxDutyFeeQuoteRequest $payload,
        Mage_Sales_Model_Quote $quote
    ) {
        return Mage::getModel(
            'ebayenterprise_tax/request_builder_quote',
            [
                'payload' => $payload,
                'quote' => $quote,
            ]
        );
    }

    /**
     * Construct a new quote tax request builder.
     *
     * @param IShipGroupIterable
     * @param IDestinationIterable
     * @param Mage_Sales_Model_Quote_Address
     * @return EbayEnterprise_Tax_Model_Request_Builder_Address
     */
    public function createRequestBuilderAddress(
        IShipGroupIterable $shipGroupIterable,
        IDestinationIterable $destinationIterable,
        Mage_Sales_Model_Quote_Address $address
    ) {
        return Mage::getModel(
            'ebayenterprise_tax/request_builder_address',
            [
                'ship_group_iterable' => $shipGroupIterable,
                'destination_iterable' => $destinationIterable,
                'address' => $address,
            ]
        );
    }

    /**
     * Construct a new quote tax request builder.
     *
     * @param IOrderItemRequestIterable
     * @param Mage_Sales_Model_Quote_Address
     * @param Mage_Sales_Model_Quote_Item_Abstract
     * @return EbayEnterprise_Tax_Model_Request_Builder_Item
     */
    public function createRequestBuilderItem(
        IOrderItemRequestIterable $orderItemIterable,
        Mage_Sales_Model_Quote_Address $address,
        Mage_Sales_Model_Quote_Item_Abstract $item
    ) {
        return Mage::getModel(
            'ebayenterprise_tax/request_builder_item',
            [
                'order_item_iterable' => $orderItemIterable,
                'address' => $address,
                'item' => $item,
            ]
        );
    }

    /**
     * Create a set of tax results from tax data.
     *
     * @param EbayEnterprise_Tax_Model_Record[]
     * @param EbayEnterprise_Tax_Model_Duty[]
     * @param EbayEnterprise_Tax_Model_Fee[]
     * @return EbayEnterprise_Tax_Model_Result
     */
    public function createTaxResults(array $records, array $duties, array $fees)
    {
        return Mage::getModel(
            'ebayenterprise_tax/result',
            ['tax_records' => $records, 'duties' => $duties, 'fees' => $fees]
        );
    }
}
