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

use eBayEnterprise\RetailOrderManagement\Payload\Order\IFee as IOrderFee;
use eBayEnterprise\RetailOrderManagement\Payload\Order\ITax;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IGifting;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IMailingAddress;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IPhysicalAddress;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedShipGroup;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedGifting;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ICustomizationIterable;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedCustomizationIterable;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ICustomization;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedCustomization;

/**
 * Methods for converting Magento types into corresponding ROM SDK payload
 * types. While this could include all sorts of type translations, it should
 * mostly be limited to types for which there is an obvious translation between
 * the Magneto and ROM SDK types.
 */
class Radial_Tax_Helper_Payload
{
    /**
     * Get a value from an array if it exists or get a default.
     *
     * @codeCoverageIgnore
     * @param array
     * @param string
     * @param mixed
     * @return mixed
     */
    protected function _nullCoalesce(array $array, $key, $default)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    /**
     * Transfer data from a Magento customer address model to a ROM SDK
     * MailingAddress payload.
     *
     * @param Mage_Customer_Model_Address_Abstract
     * @param IMailingAddress
     * @return IMailingAddress
     */
    public function customerAddressToMailingAddressPayload(
        Mage_Customer_Model_Address_Abstract $address,
        IMailingAddress $payload
    ) {
        return $this->customerAddressToPhysicalAddressPayload($address, $payload)
            ->setFirstName($address->getFirstname())
            ->setLastName($address->getLastname())
            ->setMiddleName($address->getMiddlename())
            ->setHonorificName($address->getSuffix());
    }

    /**
     * Transfer data from a Magento customer address to a ROM SDK
     * PhysicalAddress payload.
     *
     * @param Mage_Customer_Model_Address_Abstract
     * @param IPhysicalAddress
     * @return IPhysicalAddress
     */
    public function customerAddressToPhysicalAddressPayload(
        Mage_Customer_Model_Address_Abstract $address,
        IPhysicalAddress $payload
    ) {
        return $payload
            ->setLines($address->getStreetFull())
            ->setCity($address->getCity())
            ->setMainDivision($this->getRegion($address))
            ->setCountryCode($address->getCountry())
            ->setPostalCode($address->getPostcode());
    }

    /**
     * If the country for the Address is US then get the 2 character ISO region code;
     * otherwise, for any other country get the fully qualified region name.
     *
     * @param  Mage_Customer_Model_Address_Abstract
     * @return string
     */
    protected function getRegion(Mage_Customer_Model_Address_Abstract $address)
    {
        return $address->getCountry() === 'US'
            ? $address->getRegionCode()
            : $address->getRegion();
    }

    /**
     * Trasfer data from an "item" with gifting options to a Gifting payload.
     * The "item" may be a quote item or quote address, as either may have
     * gift options data, retrievable in the same way.
     *
     * @param Varien_Object
     * @param IGfiting
     * @return IGifting
     */
    public function giftingItemToGiftingPayload(
        Varien_Object $giftItem,
        IGifting $giftingPayload
    ) {
        $giftPricing = $giftingPayload->getEmptyGiftPriceGroup();
        $giftWrap = Mage::getModel('enterprise_giftwrapping/wrapping')->load($giftItem->getGwId());
        if ($giftWrap->getId()) {
            // For quote items (which will have a quantity), gift wrapping price
            // on the item will be the price for a single item to be wrapped,
            // total will be for cost for all items to be wrapped (qty * amount).
            // For addresses (which will have no quantity), gift wrapping price
            // on the address will be the price for wrapping all items for that
            // address, so total is just amount (1 * amount).
            // Add pricing data for gift wrapping - does not include discounts
            // as Magento does not support applying discounts to gift wrapping
            // out-of-the-box.
           
	    if( $giftItem instanceof Mage_Sales_Model_Order_Item )
	    {
		$giftQty = $giftItem->getQtyOrdered();
	    } else {
		$giftQty = $giftItem->getQty() ?: 1;
	    }
 
	    if( $giftItem && $giftItem->getGwPrice())
            {
                $giftPricing->setUnitPrice($giftItem->getGwPrice())
                    ->setAmount($giftItem->getGwPrice() * $giftQty)
                    ->setTaxClass($giftWrap->getEb2cTaxClass());
            } else {
                $giftPricing->setUnitPrice($giftWrap->getBasePrice())
                    ->setAmount($giftWrap->getBasePrice())
                    ->setTaxClass($giftWrap->getEb2cTaxClass());
            }

	    $giftingPayload
                ->setGiftItemId($giftWrap->getEb2cSku())
                ->setGiftDescription($giftWrap->getDesign())
                ->setGiftPricing($giftPricing);
        }
        return $giftingPayload;
    }

    /**
     * Add the printed card from Magento Enterprise to the tax call for invoice / quote
     * Note that this is the ultimate section for product customizations.. so extending this to support a true iterable might need to be required.
     *
     * @param Varien_Object
     * @param ICustomizationIterable
     * @return ICustomization
     */
    public function transferGwPrintedCard(Varien_Object $salesObject, ICustomizationIterable $customizations)
    {
        /** @var ICustomization $printCardCustomization */
	$printCardCustomization = $customizations->getEmptyCustomization();
       
	$quote = Mage::getModel('sales/quote')->load($salesObject->getQuoteId());

	/* Printed Card Data */
        $customizationData = array();

	if( $salesObject instanceof Mage_Sales_Model_Order_Item )
	{
		$order = Mage::getModel('sales/order')->load($salesObject->getOrderId());

		$customizationData['unit_price'] = $order->getGwCardPrice(); 
        	$customizationData['amount'] = $order->getGwCardPrice();
	
		if( !$order->getRadialGwPrintedCardTaxClass() )
        	{
                	$customizationData['tax_class'] = Mage::getStoreConfig('radial_core/radial_tax_core/printedcardtaxclass');
        	} else {
                	$customizationData['tax_class'] = $order->getRadialGwPrintedCardTaxClass();
       	 	}

        	if( !$order->getRadialGwPrintedCardSku())
        	{
                	$customizationData['item_id'] = Mage::getStoreConfig('radial_core/radial_tax_core/printedcardsku');
        	} else {
        	        $customizationData['item_id'] = $order->getRadialGwPrintedCardSku();
        	}
	} else {
		$customizationData['unit_price'] = Mage::getStoreConfig('sales/gift_options/printed_card_price');
        	$customizationData['amount'] = Mage::getStoreConfig('sales/gift_options/printed_card_price');
		$customizationData['tax_class'] = Mage::getStoreConfig('radial_core/radial_tax_core/printedcardtaxclass');
        	$customizationData['item_id'] = Mage::getStoreConfig('radial_core/radial_tax_core/printedcardsku');
	}

	$customizationData['description'] = "MAGE Printed Card";

	$printCardCustomization = $this->_fillOutCustomization($printCardCustomization, $customizationData);

	return $printCardCustomization;
    }

    /**
     * Add the printed card from Magento Enterprise to the tax call for invoice / quote
     * Note that this is the ultimate section for product customizations.. so extending this to support a true iterable might need to be required.
     *
     * @param Varien_Object
     * @param ITaxedCustomizationIterable
     * @return ITaxedCustomization
     */
    public function transferGwPrintedCardInvoice(Varien_Object $salesObject, ITaxedCustomizationIterable $customizations)
    {
        /** @var ITaxedCustomization $printCardCustomization */
        $printCardCustomization = $customizations->getEmptyCustomization();

	$item = Mage::getModel('sales/order_item')->getCollection()
                                 ->addFieldToFilter('item_id', array('eq' => $salesObject->getOrderItemId()))
				 ->getFirstItem();

	$order = Mage::getModel('sales/order')->load($item->getOrderId());

        /* Printed Card Data */
        $customizationData = array();

	if( $salesObject instanceof Mage_Sales_Model_Order_Creditmemo_Item )
	{
        	$customizationData['unit_price'] = -$order->getGwCardPrice();
        	$customizationData['amount'] = -$order->getGwCardPrice();
	} else {
		$customizationData['unit_price'] = $order->getGwCardPrice();
                $customizationData['amount'] = $order->getGwCardPrice();
	}

	if( !$order->getRadialGwPrintedCardTaxClass() )
	{
		$customizationData['tax_class'] = Mage::getStoreConfig('radial_core/radial_tax_core/printedcardtaxclass');
	} else {
		$customizationData['tax_class'] = $order->getRadialGwPrintedCardTaxClass();
	}

	if( !$order->getRadialGwPrintedCardSku())
	{
		$customizationData['item_id'] = Mage::getStoreConfig('radial_core/radial_tax_core/printedcardsku');
	} else {
		$customizationData['item_id'] = $order->getRadialGwPrintedCardSku();
	}
        $customizationData['description'] = "MAGE Printed Card";

        $printCardCustomization = $this->_fillOutCustomizationInvoice($printCardCustomization, $customizationData);

	return $printCardCustomization;
    }

    /**
     * Fill out the data in an ICustomization
     *
     * @param ICustomization
     * @param array $customizationData
     * @return ICustomization
     */
    protected function _fillOutCustomization(ICustomization $customizationPayload, array $customizationData)
    {
	$customizationPricing = $customizationPayload->getEmptyPriceGroup();
	$customizationPricing->setUnitPrice($this->_nullCoalesce($customizationData, 'unit_price', null))
			     ->setAmount($this->_nullCoalesce($customizationData, 'amount', null))	
			     ->setTaxClass($this->_nullCoalesce($customizationData, 'tax_class', null));

	$customizationPayload->setItemId($this->_nullCoalesce($customizationData, 'item_id', null))
			     ->setItemDescription($this->_nullCoalesce($customizationData, 'description', null))
			     ->setUpCharge($customizationPricing);

	return $customizationPayload;
    }

    /**
     * Fill out the data in an ITaxedCustomization
     *
     * @param ITaxedCustomization
     * @param array $customizationData
     * @return ITaxedCustomization
     */
    protected function _fillOutCustomizationInvoice(ITaxedCustomization $customizationPayload, array $customizationData)
    {
        $customizationPricing = $customizationPayload->getEmptyPriceGroup();
        $customizationPricing->setUnitPrice($this->_nullCoalesce($customizationData, 'unit_price', null))
                             ->setAmount($this->_nullCoalesce($customizationData, 'amount', null))
                             ->setTaxClass($this->_nullCoalesce($customizationData, 'tax_class', null));

        $customizationPayload->setItemId($this->_nullCoalesce($customizationData, 'item_id', null))
                             ->setItemDescription($this->_nullCoalesce($customizationData, 'description', null))
                             ->setUpCharge($customizationPricing);

        return $customizationPayload;
    }

     /**
     * Trasfer data from an "item" with gifting options to a Gifting payload.
     * The "item" may be a quote item or quote address, as either may have
     * gift options data, retrievable in the same way.
     *
     * @param Varien_Object
     * @param ITaxedGifting
     * @param Varien_Object
     * @param Bool
     * @return ITaxedGifting
     */
    public function giftingItemToGiftingPayloadInvoice(
        Varien_Object $giftItem,
        ITaxedGifting $giftingPayload,
 	Varien_Object $invoiceItem = null,
	$isCreditMemo
    ) {
        $giftPricing = $giftingPayload->getEmptyGiftPriceGroup();

	if( $invoiceItem && $invoiceItem->getGwId() )
	{
        	$giftWrap = Mage::getModel('enterprise_giftwrapping/wrapping')->load($invoiceItem->getGwId());
	} else {
		$giftWrap = Mage::getModel('enterprise_giftwrapping/wrapping')->load($giftItem->getGwId());
	}
        if ($giftWrap->getId()) {
            if( $invoiceItem instanceof Mage_Sales_Model_Order_Item && !$isCreditMemo && $invoiceItem->getGwPrice() )
            {
		$giftQty = $giftItem->getQty() ?: 1;
                $giftPricing->setUnitPrice($invoiceItem->getGwPrice())
                    ->setAmount($invoiceItem->getGwPrice() * $giftQty)
                    ->setTaxClass($giftWrap->getEb2cTaxClass());
	    } else if ( $invoiceItem instanceof Mage_Sales_Model_Order_Item && $isCreditMemo && $invoiceItem->getGwPrice() ) {
		$giftQty = $giftItem->getQty() ?: 1;
                $giftPricing->setUnitPrice(-$invoiceItem->getGwPrice())
                    ->setAmount(-$invoiceItem->getGwPrice() * $giftQty)
                    ->setTaxClass($giftWrap->getEb2cTaxClass());
            } else if ( $isCreditMemo && !$invoiceItem->getGwPrice() ) {
                $giftPricing->setUnitPrice(-$giftWrap->getBasePrice())
                    ->setAmount(-$giftWrap->getBasePrice())
                    ->setTaxClass($giftWrap->getEb2cTaxClass());
            } else {
            	$giftPricing->setUnitPrice($giftWrap->getBasePrice())
                    ->setAmount($giftWrap->getBasePrice())
                    ->setTaxClass($giftWrap->getEb2cTaxClass());
            }
	    $giftingPayload
            	    ->setGiftItemId($giftWrap->getEb2cSku())
            	    ->setGiftDescription($giftWrap->getDesign())
            	    ->setGiftPricing($giftPricing);
	}

        return $giftingPayload;
    }

    /**
     * Tnransfer data from a tax record model to a tax payload.
     *
     * @param Radial_Tax_Model_Records
     * @param ITax
     * @return ITax
     */
    public function taxRecordToTaxPayload(
        Radial_Tax_Model_Record $taxRecord,
        ITax $taxPayload
    ) {
        return $taxPayload
            ->setType($taxRecord->getType())
            ->setTaxability($taxRecord->getTaxability())
            ->setSitus($taxRecord->getSitus())
            ->setJurisdiction($taxRecord->getJurisdiction())
            ->setJurisdictionLevel($taxRecord->getJurisdictionLevel())
            ->setJurisdictionId($taxRecord->getJurisdictionId())
            ->setImposition($taxRecord->getImposition())
            ->setImpositionType($taxRecord->getImpositionType())
            ->setEffectiveRate($taxRecord->getEffectiveRate())
            ->setTaxableAmount($taxRecord->getTaxableAmount())
            ->setCalculatedTax($taxRecord->getCalculatedTax())
            ->setSellerRegistrationId($taxRecord->getSellerRegistrationId());
    }

    /**
     * Transfer data from a tax fee record to a fee payload.
     *
     * @param Radial_Tax_Model_Fee
     * @param IOrderFee
     * @param Radial_Tax_Model_Record[]
     * @return IOrderFee
     */
    public function taxFeeToOrderFeePayload(
        Radial_Tax_Model_Fee $fee,
        IOrderFee $orderFee,
        array $taxRecords = []
    ) {
        $taxIterable = $orderFee->getTaxes();
        foreach ($taxRecords as $taxRecord) {
            $taxPayload = $this->taxRecordToTaxPayload($taxRecord, $taxIterable->getEmptyTax());
            $taxIterable[$taxPayload] = null;
        }
        return $orderFee->setType($fee->getType())
            ->setDescription($fee->getDescription())
            ->setAmount($fee->getAmount())
            ->setItemId($fee->getItemId())
            ->setTaxes($taxIterable);
    }
}
