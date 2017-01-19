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
    protected $__helper;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $_logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $_logContext;
    /** @var Mage_Tax_Model_Config */
    protected $_config;
    /** @var Enterprise_GiftWrapping_Helper_Data */
    protected $_enterpriseGwHelper;

    /**
     * @param array $args May contain key/value for:
     *                         - _helper => Radial_Tax_Helper_Data
     *                         - tax_collector => Radial_Tax_Model_Collector
     *                         - logger => EbayEnterprise_MageLog_Helper_Data
     *                         - log_context => EbayEnterprise_MageLog_Helper_Context
     *                         - config => Mage_Tax_Model_Config
     *			       - enterprise_gw_helper => Enterprise_GiftWrapping_Helper_Data
     */
    public function __construct(array $args = [])
    {
        list(
            $this->_helper,
            $this->_taxCollector,
            $this->_logger,
            $this->_logContext,
	    $this->_config,
	    $this->_enterpriseGwHelper
        ) = $this->_checkTypes(
            $this->_nullCoalesce($args, 'helper', Mage::helper('radial_tax')),
            $this->_nullCoalesce($args, 'tax_collector', Mage::getModel('radial_tax/collector')),
            $this->_nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->_nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context')),
	    $this->_nullCoalesce($args, 'config', Mage::getSingleton('tax/config')),
	    $this->_nullCoalesce($args, 'enterprise_gw_helper', Mage::helper('enterprise_giftwrapping'))
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
     * @param Enterprise_GiftWrapping_Helper_Data
     * @return array
     */
    protected function _checkTypes(
        Radial_Tax_Helper_Data $_helper,
        Radial_Tax_Model_Collector $taxCollector,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext,
	Mage_Tax_Model_Config $config,
	Enterprise_GiftWrapping_Helper_Data $enterpriseGiftwrappingHelper
    ) {
        return [
            $_helper,
            $taxCollector,
            $logger,
            $logContext,
	    $config,
            $enterpriseGiftwrappingHelper
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
	if( $address->getAddressType() === Mage_Sales_Model_Quote_Address::TYPE_SHIPPING )
	{
		$store = $address->getQuote()->getStore();
		$toggleFees = Mage::getStoreConfig('radial_core/radial_tax_core/displayfees', Mage::app()->getStore()->getStoreId());
		$toggleDuties = Mage::getStoreConfig('radial_core/radial_tax_core/displayduties', Mage::app()->getStore()->getStoreId());
        	$addressId = $address->getId();
        	$records = $this->_totalTaxRecordsCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($addressId));
        	$duties = $this->_totalDuties($this->_taxCollector->getTaxDutiesByAddressId($addressId));
        	$fees = $this->_totalFees($this->_taxCollector->getTaxFeesByAddressId($addressId));

		$merchTotalTax = $this->_totalTaxRecordsMerchCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($address->getId()));

		/**
         	 * Modify subtotal
         	 */ 
        	if ($this->_config->displayCartSubtotalBoth($store) || $this->_config->displayCartSubtotalInclTax($store)) {
			if ($address->getSubtotalInclTax() > 0) {
                		$subtotalInclTax = $address->getSubtotalInclTax();
            		} else {
                        	$subtotalInclTax = $address->getSubtotal() + $merchTotalTax - $address->getShippingTaxAmount();
			}

            		$address->addTotal(array(
                		'code' => 'subtotal',
                		'title' => Mage::helper('sales')->__('Subtotal'),
                		'value' => $subtotalInclTax,
                		'value_incl_tax' => $subtotalInclTax,
                		'value_excl_tax' => $address->getSubtotal(),
            		));
        	}

		if($toggleFees && $toggleDuties )
		{
			if( $records )
			{
				$address->addTotal([
	        		        'code' => self::TOTAL_CODE,
	        		        'value' => $records,
	        		        'title' => $this->_helper->__(self::TAX_TOTAL_TITLE),
                		]);
			}
			if( $duties )
			{
				$address->addTotal([
                		        'code' => self::TOTAL_CODE_DUTIES,
                		        'value' => $duties,
                		        'title' => $this->_helper->__(self::TAX_TOTAL_TITLE_DUTIES),
                		]);
			}	
			if ($fees )
			{
				$address->addTotal([
               			        'code' => self::TOTAL_CODE_FEES,
                		        'value' => $fees,
               		        	'title' => $this->_helper->__(self::TAX_TOTAL_TITLE_FEES),
                		]);
			}
		} else if ($toggleFees && !$toggleDuties) {
			$taxAmount = $records + $duties;
			if( $taxAmount )
			{
				$address->addTotal([
                		        'code' => self::TOTAL_CODE,
                		        'value' => $taxAmount,
                		        'title' => $this->_helper->__(self::TAX_TOTAL_TITLE),
                		]);
			}
			if( $fees )
			{
				$address->addTotal([
                		        'code' => self::TOTAL_CODE_FEES,
                		        'value' => $fees,
                		        'title' => $this->_helper->__(self::TAX_TOTAL_TITLE_FEES),
                		]);
			}
		} else if (!$toggleFees && $toggleDuties ) {
			$taxAmount = $records + $fees;
			if( $taxAmount )
			{
				$address->addTotal([
                		        'code' => self::TOTAL_CODE,
                		        'value' => $taxAmount,
                		        'title' => $this->_helper->__(self::TAX_TOTAL_TITLE),
                		]);
			}
			if( $duties )
			{
                		$address->addTotal([
                		        'code' => self::TOTAL_CODE_DUTIES,
                		        'value' => $duties,
                		        'title' => $this->_helper->__(self::TAX_TOTAL_TITLE_DUTIES),
                		]);
			}
		} else {
        		$taxAmount = $records + $duties + $fees;
			if($taxAmount)
			{
        			$address->addTotal([
        			            'code' => self::TOTAL_CODE,
        			            'value' => $taxAmount,
        			            'title' => $this->_helper->__(self::TAX_TOTAL_TITLE),
        	        	]);
			}
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
	if( $address->getAddressType() === Mage_Sales_Model_Quote_Address::TYPE_SHIPPING )
        {
        	$addressId = $address->getId();
        	$taxTotal = $this->_totalTaxRecordsCalculatedTaxes($this->_taxCollector->getTaxRecordsByAddressId($addressId));
        	$dutyTotal = $this->_totalDuties($this->_taxCollector->getTaxDutiesByAddressId($addressId));
        	$feeTotal = $this->_totalFees($this->_taxCollector->getTaxFeesByAddressId($addressId));
        	$total = $taxTotal + $dutyTotal + $feeTotal;
        	$this->_logger->debug("Collected tax totals of: tax - $taxTotal, duty - $dutyTotal, fee - $feeTotal, total - $total.", $this->_logContext->getMetaData(__CLASS__, ['address_type' => $address->getAddressType()]));

		$items = $this->_getAddressItems($address);
        	if (!count($items)) {
        	    return $this;
        	}

        	$store = $address->getQuote()->getStore();

        	foreach ($items as $item) 
		{
        	    if ($item->getParentItem()) {
        	        continue;
        	    }
	
	            if ($item->getHasChildren() && $item->isChildrenCalculated()) {
        	        foreach ($item->getChildren() as $child) {
        	        	$taxRecords = $this->_taxCollector->getTaxRecordsByAddressId($address->getId());
				$taxDuties = $this->_taxCollector->getTaxDutiesByAddressId($address->getId());
        			$taxFees = $this->_taxCollector->getTaxFeesByAddressId($address->getId());
			        $merchItemTaxTotal = false;

				if( $taxRecords )
				{
			        	foreach( $taxRecords as $taxRecord )
			        	{
			       	        	if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_MERCHANDISE || $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_MERCHANDISE_DISCOUNT && $taxRecord->getItemId() == $child->getItemId() )
                				{
                        				$merchItemTaxTotal += $taxRecord->getCalculatedTax();
                				}
        				}
				}

				if( $taxDuties )
        			{
                			foreach( $taxDuties as $taxDuty )
                			{
                        			if( $taxDuty->getItemId() == $child->getItemId())
                        			{
                        			        $merchItemTaxTotal += $taxDuty->getAmount();
                        			}
                			}
        			}

        			if( $taxFees )
        			{
                			foreach( $taxFees as $taxFee )
                			{
                        			if( $taxFee->getItemId() == $child->getItemId())
                        			{
                        			        $merchItemTaxTotal += $taxFee->getAmount();
                        			}
                			}
        			}
			}

			$child->setTaxAmount($merchItemTaxTotal);
			$child->setBaseTaxAmount($merchItemTaxTotal);

        	        $this->_recalculateParent($item);
        	    } else {
                       $taxRecords = $this->_taxCollector->getTaxRecordsByAddressId($address->getId());
		       $taxDuties = $this->_taxCollector->getTaxDutiesByAddressId($address->getId());
                       $taxFees = $this->_taxCollector->getTaxFeesByAddressId($address->getId());
                       $merchItemTaxTotal = false;

		       if( $taxRecords )
		       {
				foreach( $taxRecords as $taxRecord )
                       		{
	                	       if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_MERCHANDISE || $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_MERCHANDISE_DISCOUNT && $taxRecord->getItemId() == $item->getItemId() )
                        	       {
                        	       		$merchItemTaxTotal += $taxRecord->getCalculatedTax();
                                       }
                        	}
			} 

			if( $taxDuties )
                        {
                        	foreach( $taxDuties as $taxDuty )
                                {
                                	if( $taxDuty->getItemId() == $item->getItemId())
                                        {
                                        	$merchItemTaxTotal += $taxDuty->getAmount();
                                        }
                                }
                        }

                        if( $taxFees )
                        {
                        	foreach( $taxFees as $taxFee )
                                {
                                	if( $taxFee->getItemId() == $item->getItemId())
                                        {
                                        	$merchItemTaxTotal += $taxFee->getAmount();
                                        }
                                } 
                        }

                        $item->setTaxAmount($merchItemTaxTotal);
                        $item->setBaseTaxAmount($merchItemTaxTotal);
		    }
            	}

		$address->setTaxAmount($total);
                $address->setBaseTaxAmount($total);

                // Always overwrite amounts for this total. The total calculated from
                // the collector's tax records will be the complete tax amount for
                // the address.
                $this->_setAmount($total)->_setBaseAmount($total);
	}
        return $this;
    }
    /**
     * Get the total tax amount for merchandise.
     *
     * @param Radial_Tax_Model_Record[]
     * @return float
     */
    protected function _totalTaxRecordsMerchCalculatedTaxes(array $taxRecords)
    {
        return array_reduce(
            $taxRecords,
            function ($total, $taxRecord) {
		if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_MERCHANDISE || $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_MERCHANDISE_DISCOUNT ) {
                	return $total + $taxRecord->getCalculatedTax();
		} else {
			return $total;
		}
            },
            0.00
        );
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
