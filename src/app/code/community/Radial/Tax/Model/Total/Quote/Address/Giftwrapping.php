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

class Radial_Tax_Model_Total_Quote_Address_Giftwrapping extends Enterprise_GiftWrapping_Model_Total_Quote_Tax_Giftwrapping
{
    /**
     * Code used to determine the block renderer for the address line.
     * @see Mage_Checkout_Block_Cart_Totals::_getTotalRenderer
     * @var string
     */
    /** @var Radial_Tax_Model_Collector */
    protected $_taxCollector;
    /** @var Radial_Tax_Helper_Data */
    protected $__helper;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $_logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $_logContext;
    /** @var Mage_Tax_Model_Config
    protected $_config;

    /**
     * @param array $args May contain key/value for:
     *                         - _helper => Radial_Tax_Helper_Data
     *                         - tax_collector => Radial_Tax_Model_Collector
     *                         - logger => EbayEnterprise_MageLog_Helper_Data
     *                         - log_context => EbayEnterprise_MageLog_Helper_Context
     */
    public function __construct(array $args = [])
    {
	$this->setCode('shipping');
        $this->_calculator  = Mage::getSingleton('tax/calculation');
        $this->_helper      = Mage::helper('tax');
        $this->_config      = Mage::getSingleton('tax/config');

        list(
            $this->_helper,
            $this->_taxCollector,
            $this->_logger,
            $this->_logContext,
	    $this->_config
        ) = $this->_checkTypes(
            $this->_nullCoalesce($args, 'helper', Mage::helper('radial_tax')),
            $this->_nullCoalesce($args, 'tax_collector', Mage::getModel('radial_tax/collector')),
            $this->_nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->_nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context')),
	    $this->_nullCoalesce($args, 'config', Mage::getSingleton('tax/config'))
        );
    }

    /**
     * Enforce type checks on constructor init params.
     *
     * @param Radial_Tax_Helper_Data
     * @param Radial_Tax_Model_Collector
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @param Mage_Tax_Model_Config
     * @return array
     */
    protected function _checkTypes(
        Radial_Tax_Helper_Data $_helper,
        Radial_Tax_Model_Collector $taxCollector,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext,
	Mage_Tax_Model_Config $config
    ) {
        return [
            $_helper,
            $taxCollector,
            $logger,
            $logContext,
	    $config
        ];
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
     * Collect gift wrapping tax totals
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  Enterprise_GiftWrapping_Model_Total_Quote_Tax_Giftwrapping
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        Mage_Sales_Model_Quote_Address_Total_Abstract::collect($address);
        if ($address->getAddressType() != Mage_Sales_Model_Quote_Address::TYPE_SHIPPING) {
            return $this;
        }

        $this->_quote = $address->getQuote();
        $quote = $this->_quote;
        if ($quote->getIsMultiShipping()) {
            $this->_quoteEntity = $address;
        } else {
            $this->_quoteEntity = $quote;
        }

        $this->_collectWrappingForItems($address)
            ->_collectWrappingForQuote($address)
            ->_collectPrintedCard($address);

        $baseTaxAmount = $this->_totalTaxRecordsItemGWCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()))
            + $this->_totalTaxRecordsOrderGWCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()))
            + $this->_totalTaxRecordsCustomizableCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
        $taxAmount = $this->_totalTaxRecordsItemGWCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()))
            + $this->_totalTaxRecordsOrderGWCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()))
            + $this->_totalTaxRecordsCustomizableCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));

        if ($quote->getIsNewGiftWrappingTaxCollecting()) {
            $quote->setGwItemsBaseTaxAmount(0);
            $quote->setGwItemsTaxAmount(0);
            $quote->setGwBaseTaxAmount(0);
            $quote->setGwTaxAmount(0);
            $quote->setGwCardBaseTaxAmount(0);
            $quote->setGwCardTaxAmount(0);
            $quote->setIsNewGiftWrappingTaxCollecting(false);
        }
        $quote->setGwItemsBaseTaxAmount($this->_totalTaxRecordsItemGWCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId())) + $quote->getGwItemsBaseTaxAmount());
        $quote->setGwItemsTaxAmount($this->_totalTaxRecordsItemGWCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId())) + $quote->getGwItemsTaxAmount());
        $quote->setGwBaseTaxAmount($this->_totalTaxRecordsOrderGWCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId())) + $quote->getGwBaseTaxAmount());
        $quote->setGwTaxAmount($this->_totalTaxRecordsOrderGWCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId())) + $quote->getGwTaxAmount());
        $quote->setGwPrintedCardBaseTaxAmount(
             $this->_totalTaxRecordsCustomizableCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId())) + $quote->getGwPrintedCardBaseTaxAmount()
        );
        $quote->setGwPrintedCardTaxAmount(
            $this->_totalTaxRecordsCustomizableCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId())) + $quote->getGwPrintedCardTaxAmount()
        );

        return $this;
    }

    /**
     * Get the total for item level gift wrapping tax amount for an address.
     *
     * @param Radial_Tax_Model_Record[]
     * @return float
     */
    protected function _totalTaxRecordsItemGWCalculatedTaxes(array $taxRecords)
    {
	return array_reduce(
            $taxRecords,
            function ($total, $taxRecord) {
		if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_ITEM_GIFTING ) {
			return $total + $taxRecord->getCalculatedTax();
                } else {
                        return $total;
                }
            },
            0.00
        );
    }

    /**
     * Get the total for order level gift wrapping tax amount for an address.
     *
     * @param Radial_Tax_Model_Record[]
     * @return float
     */
    protected function _totalTaxRecordsOrderGWCalculatedTaxes(array $taxRecords)
    {
        return array_reduce(
            $taxRecords,
            function ($total, $taxRecord) {
                if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_ADDRESS_GIFTING ) {        
			return $total + $taxRecord->getCalculatedTax();
                } else {
                        return $total;
                }
            },
            0.00
        );
    }

    /**
     * Get the total customizable / printed card tax amount for an address.
     *
     * @param Radial_Tax_Model_Record[]
     * @return float
     */
    protected function _totalTaxRecordsCustomizableCalculatedTaxes(array $taxRecords)
    {
        return array_reduce(
            $taxRecords,
            function ($total, $taxRecord) {
                if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_CUSTOMIZATION_BASE || $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_CUSTOMIZATION_FEATURE ) {        
			return $total + $taxRecord->getCalculatedTax();
                } else {
                        return $total;
                }
            },
            0.00
        );
    }

      /**
     * Collect wrapping tax total for items
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  Enterprise_GiftWrapping_Model_Total_Quote_Tax_Giftwrapping
     */
    protected function _collectWrappingForItems($address)
    {
        $items = $this->_getAddressItems($address);
        $wrappingForItemsBaseTaxAmount = false;
        $wrappingForItemsTaxAmount = false;

        foreach ($items as $item) {
            if ($item->getProduct()->isVirtual() || $item->getParentItem() || !$item->getGwId()) {
                continue;
            }

	    $taxRecords = $this->_taxCollector->getTaxRecordsByAddressId($address->getId());
	    $itemGwTotal = false;

	    foreach( $taxRecords as $taxRecord ) 
	    {
		if( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_ITEM_GIFTING && $taxRecord->getItemId() == $item->getItemId() )
		{
                	$itemGwTotal += ($taxRecord->getCalculatedTax() / $item->getQty());
		}
	    }

            $wrappingBaseTaxAmount = $itemGwTotal;
            $wrappingTaxAmount = $itemGwTotal;
            $item->setGwBaseTaxAmount($wrappingBaseTaxAmount);
            $item->setGwTaxAmount($wrappingTaxAmount);

            $wrappingForItemsBaseTaxAmount += $wrappingBaseTaxAmount * $item->getQty();
            $wrappingForItemsTaxAmount += $wrappingTaxAmount * $item->getQty();
        }
        $address->setGwItemsBaseTaxAmount($wrappingForItemsBaseTaxAmount);
        $address->setGwItemsTaxAmount($wrappingForItemsTaxAmount);
        return $this;
    }

    /**
     * Collect wrapping tax total for quote
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  Enterprise_GiftWrapping_Model_Total_Quote_Tax_Giftwrapping
     */
    protected function _collectWrappingForQuote($address)
    {
        $wrappingBaseTaxAmount = false;
        $wrappingTaxAmount = false;
        if ($this->_quoteEntity->getGwId()) {
            $wrappingBaseTaxAmount = $this->_totalTaxRecordsOrderGWCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
            $wrappingTaxAmount = $this->_totalTaxRecordsOrderGWCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
        }
        $address->setGwBaseTaxAmount($wrappingBaseTaxAmount);
        $address->setGwTaxAmount($wrappingTaxAmount);
        return $this;
    }

    /**
     * Collect printed card tax total for quote
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  Enterprise_GiftWrapping_Model_Total_Quote_Tax_Giftwrapping
     */
    protected function _collectPrintedCard($address)
    {
        $printedCardBaseTaxAmount = false;
        $printedCardTaxAmount = false;
        if ($this->_quoteEntity->getGwAddCard()) {
            $printedCardBaseTaxAmount = $this->_totalTaxRecordsCustomizableCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
            $printedCardTaxAmount = $this->_totalTaxRecordsCustomizableCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
        }
        $address->setGwCardBaseTaxAmount($printedCardBaseTaxAmount);
        $address->setGwCardTaxAmount($printedCardTaxAmount);
        return $this;
    }
}
