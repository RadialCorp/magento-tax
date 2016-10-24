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

class Radial_Tax_Model_Total_Quote_Address_Tax extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    const TAX_TOTAL_TITLE = 'Radial_Tax_Total_Quote_Address_Tax_Title';
    const TAX_TOTAL_TITLE_DUTIES = 'Radial_Tax_Total_Quote_Address_Tax_Title_Duties';
    const TAX_TOTAL_TITLE_FEES = 'Radial_Tax_Total_Quote_Address_Tax_Title_Fees';

    const TOTAL_CODE = 'radial_tax';
    const TOTAL_CODE_FEES = 'radial_tax_fees';
    const TOTAL_CODE_DUTIES = 'radial_tax_duties';

    /**
     * Code used to determine the block renderer for the address line.
     * @see Mage_Checkout_Block_Cart_Totals::_getTotalRenderer
     * @var string
     */
    protected $_code = 'radial_tax';
    /** @var Radial_Tax_Model_Collector */
    protected $_taxCollector;
    /** @var Radial_Tax_Helper_Data */
    protected $_helper;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $_logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $_logContext;

    /**
     * @param array $args May contain key/value for:
     *                         - helper => Radial_Tax_Helper_Data
     *                         - tax_collector => Radial_Tax_Model_Collector
     *                         - logger => EbayEnterprise_MageLog_Helper_Data
     *                         - log_context => EbayEnterprise_MageLog_Helper_Context
     */
    public function __construct(array $args = [])
    {
        list(
            $this->_helper,
            $this->_taxCollector,
            $this->_logger,
            $this->_logContext
        ) = $this->_checkTypes(
            $this->_nullCoalesce($args, 'helper', Mage::helper('radial_tax')),
            $this->_nullCoalesce($args, 'tax_collector', Mage::getModel('radial_tax/collector')),
            $this->_nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->_nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context'))
        );
    }

    /**
     * Enforce type checks on constructor init params.
     *
     * @param Radial_Tax_Helper_Data
     * @param Radial_Tax_Model_Collector
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @return array
     */
    protected function _checkTypes(
        Radial_Tax_Helper_Data $helper,
        Radial_Tax_Model_Collector $taxCollector,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext
    ) {
        return [
            $helper,
            $taxCollector,
            $logger,
            $logContext
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
     * Update the address with totals data used for display in a total line,
     * e.g. a total line in the cart.
     *
     * @param Mage_Sales_Model_Quote_Address
     * @return self
     */
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
	$toggleFees = Mage::getStoreConfig('radial_core/radial_tax_core/displayfees', Mage::app()->getStore()->getStoreId());
	$toggleDuties = Mage::getStoreConfig('radial_core/radial_tax_core/displayduties', Mage::app()->getStore()->getStoreId());

        $addressId = $address->getId();
        $records = $this->_totalTaxRecordsCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($addressId));
        $duties = $this->_totalDuties($this->_taxCollector->getTaxDutiesByAddressId($addressId));
        $fees = $this->_totalFees($this->_taxCollector->getTaxFeesByAddressId($addressId));

	if($toggleFees && $toggleDuties )
	{
		if( $records )
		{
			$parent->addTotal(
	        	    new Varien_Object([
	        	        'code' => self::TOTAL_CODE,
	        	        'value' => $records,
	        	        'base_value' => $records,
	        	        'label' => $this->helper->__(self::TAX_TOTAL_TITLE),
                	    ]),
                	   'discount'
                	);
		}

		if( $duties )
		{
			$parent->addTotal(
                	    new Varien_Object([
                	        'code' => self::TOTAL_CODE_DUTIES,
                	        'value' => $duties,
                	        'base_value' => $duties,
                	        'label' => $this->helper->__(self::TAX_TOTAL_TITLE_DUTIES),
                	    ]),
                	   'discount'
                	);	
		}	

		if ($fees )
		{
			$parent->addTotal(
                	    new Varien_Object([
               		         'code' => self::TOTAL_CODE_FEES,
                	        'value' => $fees,
                	        'base_value' => $fees,
               	        	 'label' => $this->helper->__(self::TAX_TOTAL_TITLE_FEES),
               			     ]),
                	   	'discount'
                	);
		}
	} else if ($toggleFees && !$toggleDuties) {
		$taxAmount = $records + $duties;

		if( $taxAmount )
		{
			$parent->addTotal(
                	    new Varien_Object([
                	        'code' => self::TOTAL_CODE,
                	        'value' => $taxAmount,
                	        'base_value' => $taxAmount,
                	        'label' => $this->helper->__(self::TAX_TOTAL_TITLE),
                	    ]),
                   	'discount'
                	);
		}

		if( $fees )
		{
			$parent->addTotal(
                	    new Varien_Object([
                	        'code' => self::TOTAL_CODE_FEES,
                	        'value' => $fees,
                	        'base_value' => $fees,
                	        'label' => $this->helper->__(self::TAX_TOTAL_TITLE_FEES),
                	    ]),
                	   'discount'
                	);
		}
	} else if (!$toggleFees && $toggleDuties ) {
		$taxAmount = $records + $fees;

		if( $taxAmount )
		{
			$parent->addTotal(
                	    new Varien_Object([
                	        'code' => self::TOTAL_CODE,
                	        'value' => $taxAmount,
                	        'base_value' => $taxAmount,
                	        'label' => $this->helper->__(self::TAX_TOTAL_TITLE),
                	    ]),
                	   'discount'
                	);
		}

		if( $duties )
		{
                	$parent->addTotal(
                	    new Varien_Object([
                	        'code' => self::TOTAL_CODE_DUTIES,
                	        'value' => $duties,
                	        'base_value' => $duties,
                	        'label' => $this->helper->__(self::TAX_TOTAL_TITLE_DUTIES),
                	    ]),
                	   'discount'
                	);
		}
	} else {
        	$taxAmount = $records + $duties + $fees;

		if($taxAmount)
		{
        		$parent->addTotal(
        		        new Varien_Object([
        		            'code' => self::TOTAL_CODE,
        		            'value' => $taxAmount,
        		            'base_value' => $taxAmount,
        		            'label' => $this->helper->__(self::TAX_TOTAL_TITLE),
        		        ]),
                	    'discount'
                	);
		}
	}

        return $this;
    }

    /**
     * Update the address totals with tax amounts.
     *
     * @param Mage_Sales_Model_Quote_Address
     * @return self
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        // Necessary for inherited `self::_setAmount` and `self::_setBaseAmount` to behave.
        $this->_setAddress($address);
        $addressId = $address->getId();
        $taxTotal = $this->_totalTaxRecordsCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($addressId));
        $dutyTotal = $this->_totalDuties($this->_taxCollector->getTaxDutiesByAddressId($addressId));
        $feeTotal = $this->_totalFees($this->_taxCollector->getTaxFeesByAddressId($addressId));
        $total = $taxTotal + $dutyTotal + $feeTotal;
        $this->_logger->debug("Collected tax totals of: tax - $taxTotal, duty - $dutyTotal, fee - $feeTotal, total - $total.", $this->_logContext->getMetaData(__CLASS__, ['address_type' => $address->getAddressType()]));
        // Always overwrite amounts for this total. The total calculated from
        // the collector's tax records will be the complete tax amount for
        // the address.
        $this->_setAmount($total)->_setBaseAmount($total);
        return $this;
    }

    /**
     * Get the total tax amount for an address.
     *
     * @param Radial_Tax_Model_Record[]
     * @return float
     */
    protected function _totalTaxRecordsCalculatedTaxes(array $taxRecords)
    {
        return array_reduce(
            $taxRecords,
            function ($total, $taxRecord) {
                return $total + $taxRecord->getCalculatedTax();
            },
            0.00
        );
    }

    /**
     * Get the total of all duties for an address.
     *
     * @param Radial_Tax_Model_Duty[]
     * @return float
     */
    protected function _totalDuties(array $duties)
    {
        return array_reduce(
            $duties,
            function ($total, $duty) {
                return $total + $duty->getAmount();
            },
            0.00
        );
    }

    /**
     * Get the total of all fees for an address.
     *
     * @param Radial_Tax_Model_Fee[]
     * @return float
     */
    protected function _totalFees(array $fees)
    {
        return array_reduce(
            $fees,
            function ($total, $fee) {
                return $total + $fee->getAmount();
            },
            0.00
        );
    }
}
