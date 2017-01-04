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

class Radial_Tax_Model_Total_Quote_Address_Shipping extends Mage_Tax_Model_Sales_Total_Quote_Shipping
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
     * Collect totals information about shipping
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  Mage_Sales_Model_Quote_Address_Total_Shipping
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        Mage_Sales_Model_Quote_Address_Total_Abstract::collect($address);

	$store              = $address->getQuote()->getStore();
	$priceIncludesTax = $this->_config->shippingPriceIncludesTax($store);

        $calc               = $this->_calculator;
        $store              = $address->getQuote()->getStore();
        $shipping           = $taxShipping = $address->getShippingAmount();
        $baseShipping       = $baseTaxShipping = $address->getBaseShippingAmount();
        if ($priceIncludesTax) {
            if ($this->_areTaxRequestsSimilar) {
                $tax            = $this->_totalTaxRecordsShippingCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
                $baseTax        = $this->_totalTaxRecordsShippingCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
                $taxShipping    = $shipping;
                $baseTaxShipping = $baseShipping;
                $shipping       = $shipping - $tax;
                $baseShipping   = $baseShipping - $baseTax;
                $taxable        = $taxShipping;
                $baseTaxable    = $baseTaxShipping;
                $isPriceInclTax = true;
                $address->setTotalAmount('shipping', $shipping);
                $address->setBaseTotalAmount('shipping', $baseShipping);
            } else {
                $storeTax       = $this->_totalTaxRecordsShippingCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
                $baseStoreTax   = $this->_totalTaxRecordsShippingCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
                $shipping       = $calc->round($shipping - $storeTax);
                $baseShipping   = $calc->round($baseShipping - $baseStoreTax);
                $tax            = $this->_totalTaxRecordsShippingCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
                $baseTax        = $this->_totalTaxRecordsShippingCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
                $taxShipping    = $shipping + $tax;
                $baseTaxShipping = $baseShipping + $baseTax;
                $taxable        = $taxShipping;
                $baseTaxable    = $baseTaxShipping;
                $isPriceInclTax = true;
                $address->setTotalAmount('shipping', $shipping);
                $address->setBaseTotalAmount('shipping', $baseShipping);
            }
        } else {
            $tax            = $this->_totalTaxRecordsShippingCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
            $baseTax        = $this->_totalTaxRecordsShippingCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));
            $taxShipping    = $shipping + $tax;
            $baseTaxShipping = $baseShipping + $baseTax;
            $taxable        = $shipping;
            $baseTaxable    = $baseShipping;
            $isPriceInclTax = false;
            $address->setTotalAmount('shipping', $shipping);
            $address->setBaseTotalAmount('shipping', $baseShipping);
        }
	$address->setShippingTaxAmount($tax);
	$address->setBaseShippingTaxAmount($baseTax);
        $address->setShippingInclTax($taxShipping);
        $address->setBaseShippingInclTax($baseTaxShipping);
        $address->setShippingTaxable($taxable);
        $address->setBaseShippingTaxable($baseTaxable);
        $address->setIsShippingInclTax($isPriceInclTax);
        if ($this->_config->discountTax($store)) {
            $address->setShippingAmountForDiscount($taxShipping);
            $address->setBaseShippingAmountForDiscount($baseTaxShipping);
        }
        return $this;
    }

    /**
     * Get the total shipping tax amount for an address.
     *
     * @param Radial_Tax_Model_Record[]
     * @return float
     */
    protected function _totalTaxRecordsShippingCalculatedTaxes(array $taxRecords)
    {
	return array_reduce(
            $taxRecords,
            function ($total, $taxRecord) {
		if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_SHIPPING || $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_SHIPPING_DISCOUNT ) {	
			return $total + $taxRecord->getCalculatedTax();
                } else {
                        return $total;
                }
            },
            0.00
        );
    }
}
