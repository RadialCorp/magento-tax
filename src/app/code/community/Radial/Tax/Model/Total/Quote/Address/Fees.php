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

class Radial_Tax_Model_Total_Quote_Address_Fees extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    const TAX_TOTAL_TITLE_FEES = 'Radial_Tax_Total_Quote_Address_Tax_Title_Fees';
    const TOTAL_CODE_FEES = 'radial_tax_fees';

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
        Radial_Tax_Helper_Data $_helper,
        Radial_Tax_Model_Collector $taxCollector,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext
    ) {
        return [
            $_helper,
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
	if( $address->getAddressType() === Mage_Sales_Model_Quote_Address::TYPE_SHIPPING )
	{
		$toggleFees = Mage::getStoreConfig('radial_core/radial_tax_core/displayfees', Mage::app()->getStore()->getStoreId());
		$toggleDuties = Mage::getStoreConfig('radial_core/radial_tax_core/displayduties', Mage::app()->getStore()->getStoreId());

        	$addressId = $address->getId();
        	$fees = $this->_totalFees($this->_taxCollector->getTaxFeesByAddressId($addressId));

		if($toggleFees )
		{
			if ($fees )
			{
				$address->addTotal([
               			        'code' => self::TOTAL_CODE_FEES,
                		        'value' => $fees,
               		        	'title' => $this->_helper->__(self::TAX_TOTAL_TITLE_FEES),
                		]);
			}
		}
	}

        return $this;
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
