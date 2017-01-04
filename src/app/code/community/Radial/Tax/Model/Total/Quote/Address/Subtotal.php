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

class Radial_Tax_Model_Total_Quote_Address_Subtotal extends Mage_Tax_Model_Sales_Total_Quote_Subtotal
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

    /**
     * @param array $args May contain key/value for:
     *                         - _helper => Radial_Tax_Helper_Data
     *                         - tax_collector => Radial_Tax_Model_Collector
     *                         - logger => EbayEnterprise_MageLog_Helper_Data
     *                         - log_context => EbayEnterprise_MageLog_Helper_Context
     *			       - config => Mage_Tax_Model_Config
     *                         - calculator => Mage_Tax_Model_Calculation
     */
    public function __construct(array $args = [])
    {
	$this->setCode('shipping');
        $this->_calculator  = Mage::getSingleton('tax/calculation');
        $this->_helper      = Mage::helper('tax');

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
     * Calculate item price including/excluding tax, row total including/excluding tax
     * and subtotal including/excluding tax.
     * Determine discount price if needed
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     *
     * @return  Mage_Tax_Model_Sales_Total_Quote_Subtotal
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $this->_store = $address->getQuote()->getStore();
        $this->_address = $address;

        $items = $this->_getAddressItems($address);
        if (!$items) {
            return $this;
        }

        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            if ($item->getHasChildren() && $item->isChildrenCalculated()) {
                foreach ($item->getChildren() as $child) {
			$this->_processItemRadial($item, $address);
		}
                $this->_recalculateParent($item);
            } else {
                $this->_processItemRadial($item, $address);
            }

	    $this->_addSubtotalAmountRadial($address, $item);
        }
        return $this;
    }

    /**
     * Calculate item price and row total with values from Radial TDF
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     * @param Mage_Sales_Model_Quote_Address $address
     *
     * @return Mage_Tax_Model_Sales_Total_Quote_Subtotal
     */
    protected function _processItemRadial(Mage_Sales_Model_Quote_Item_Abstract $item, Mage_Sales_Model_Quote_Address $address) 
    {
	$qty = $item->getTotalQty();
        $price = $taxPrice = $this->_calculator->round($item->getCalculationPriceOriginal());
        $basePrice = $baseTaxPrice = $this->_calculator->round($item->getBaseCalculationPriceOriginal());
        $subtotal = $taxSubtotal = $this->_calculator->round($item->getRowTotal());
        $baseSubtotal = $baseTaxSubtotal = $this->_calculator->round($item->getBaseRowTotal());

        $taxRecords = $this->_taxCollector->getTaxRecordsByAddressId($address->getId());
	$merchItemTaxTotal = false;

        foreach( $taxRecords as $taxRecord )
        {
        	if (($taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_MERCHANDISE || $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_MERCHANDISE_DISCOUNT) && $taxRecord->getItemId() == $item->getItemId() )
                {
                	$merchItemTaxTotal += ($taxRecord->getCalculatedTax() / $item->getQty());
                } 
        }

        if ($item->hasCustomPrice()) {
        	/**
                 * Initialize item original price before declaring custom price
                 */
                 $item->getOriginalPrice();
                 $item->setCustomPrice($price);
                 $item->setBaseCustomPrice($basePrice);
        }
        $item->setPrice($basePrice);
        $item->setBasePrice($basePrice);
        $item->setRowTotal($subtotal);
        $item->setBaseRowTotal($baseSubtotal);

        if ($this->_config->priceIncludesTax($this->_store)) {
		$taxable = $price;
                $baseTaxable = $basePrice;
        	$tax = $merchItemTaxTotal;
                $baseTax = $merchItemTaxTotal;
                $taxPrice        = $price;
                $baseTaxPrice    = $basePrice;
                $taxSubtotal     = $subtotal;
                $baseTaxSubtotal = $baseSubtotal;
                $price = $price - $tax;
                $basePrice = $basePrice - $baseTax;
                $subtotal = $price * $qty;
                $baseSubtotal = $basePrice * $qty;
                $isPriceInclTax  = true;
                               
                $item->setRowTax($tax * $qty);
                $item->setBaseRowTax($baseTax * $qty);
        } else {
		$taxable = $price;
                $baseTaxable = $basePrice;
                $tax             = $merchItemTaxTotal;
                $baseTax         = $merchItemTaxTotal;
                $taxPrice        = $price + $tax;
                $baseTaxPrice    = $basePrice + $baseTax;
                $taxSubtotal     = $taxPrice * $qty;
                $baseTaxSubtotal = $baseTaxPrice * $qty;
                $isPriceInclTax  = false;
        }

        $item->setPriceInclTax($taxPrice);
        $item->setBasePriceInclTax($baseTaxPrice);
        $item->setRowTotalInclTax($taxSubtotal);
        $item->setBaseRowTotalInclTax($baseTaxSubtotal);
        $item->setTaxableAmount($taxable);
        $item->setBaseTaxableAmount($baseTaxable);
        $item->setIsPriceInclTax($isPriceInclTax);
        if ($this->_config->discountTax($this->_store)) {
        	$item->setDiscountCalculationPrice($taxPrice);
                $item->setBaseDiscountCalculationPrice($baseTaxPrice);
        }

	return $this;
    }

    /**
     * Add row total item amount to subtotal
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @param   Mage_Sales_Model_Quote_Item_Abstract $item
     *
     * @return  Mage_Tax_Model_Sales_Total_Quote_Subtotal
     */
    protected function _addSubtotalAmountRadial(Mage_Sales_Model_Quote_Address $address, $item)
    {
        $address->setSubtotalInclTax($address->getSubtotalInclTax() + $item->getRowTotalInclTax());
        $address->setBaseSubtotalInclTax($address->getBaseSubtotalInclTax() + $item->getBaseRowTotalInclTax());
        return $this;
    }
}
