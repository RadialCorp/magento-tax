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

/**
 * Block for rendering eBay Enterprise tax data for email templates.
 */
class Radial_Tax_Block_Sales_Order_Tax extends Mage_Core_Block_Abstract
{
    const TAX_LABEL = 'Radial_Tax_Order_Total_Tax_Title';
    const TAX_LABEL_FEES = 'Radial_Tax_Order_Total_Tax_Title_Fees';
    const TAX_LABEL_DUTIES = 'Radial_Tax_Order_Total_Tax_Title_Duties';
    const TOTAL_CODE = 'radial_tax';
    const TOTAL_CODE_FEES = 'radial_tax_fees';
    const TOTAL_CODE_DUTIES = 'radial_tax_duties';

    /** @var Radial_Tax_Model_Collector */
    protected $taxCollector;
    /** @var Radial_Tax_Helper_Data */
    protected $helper;

    /**
     * @param array May contain:
     *              - tax_collector => Radial_Tax_Model_Collector
     */
    public function __construct(array $args = [])
    {
        list(
            $this->taxCollector,
            $this->helper
        ) = $this->checkTypes(
            $this->nullCoalesce($args, 'tax_collector', Mage::getModel('radial_tax/collector')),
            $this->nullCoalesce($args, 'helper', Mage::helper('radial_tax'))
        );
        parent::__construct($args);
    }

    /**
     * Enforce type checks on construct args array.
     *
     * @param Radial_Tax_Model_Collector
     * @param Radial_Tax_Helper_Data
     * @return array
     */
    protected function checkTypes(
        Radial_Tax_Model_Collector $taxCollector,
        Radial_Tax_Helper_Data $helper
    ) {
        return func_get_args();
    }

    /**
     * Fill in default values.
     *
     * @param array
     * @param string
     * @param mixed
     * @return mixed
     */
    protected function nullCoalesce(array $arr, $key, $default)
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /**
     * Add totals for collected taxes to the parent block. Totals added to the
     * parent will be displayed with order totals.
     *
     * @return self
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();

	$toggleFees = Mage::getStoreConfig('radial_core/radial_tax_core/displayfees', Mage::app()->getStore()->getStoreId());
	$toggleDuties = Mage::getStoreConfig('radial_core/radial_tax_core/displayduties', Mage::app()->getStore()->getStoreId());

	$records = array_reduce($this->taxCollector->getTaxRecords(), function ($total, $item) { return $total + $item->getCalculatedTax(); }, 0.00);
        $duties = array_reduce($this->taxCollector->getTaxDuties(), function ($total, $item) { return $total + $item->getAmount(); }, 0.00);
        $fees = array_reduce($this->taxCollector->getTaxFees(), function ($total, $item) { return $total + $item->getAmount(); }, 0.00);

	if($toggleFees && $toggleDuties )
	{
		$parent->addTotal(
	            new Varien_Object([
	                'code' => self::TOTAL_CODE,
	                'value' => $records,
	                'base_value' => $records,
	                'label' => $this->helper->__(self::TAX_LABEL),
                    ]),
                   'discount'
                );

		$parent->addTotal(
                    new Varien_Object([
                        'code' => self::TOTAL_CODE_DUTIES,
                        'value' => $duties,
                        'base_value' => $duties,
                        'label' => $this->helper->__(self::TAX_LABEL_DUTIES),
                    ]),
                   'discount'
                );		

		$parent->addTotal(
                    new Varien_Object([
                        'code' => self::TOTAL_CODE_FEES,
                        'value' => $fees,
                        'base_value' => $fees,
                        'label' => $this->helper->__(self::TAX_LABEL_FEES),
                    ]),
                   'discount'
                );
	} else if ($toggleFees && !$toggleDuties) {
		$taxAmount = $records + $duties;
		$parent->addTotal(
                    new Varien_Object([
                        'code' => self::TOTAL_CODE,
                        'value' => $taxAmount,
                        'base_value' => $taxAmount,
                        'label' => $this->helper->__(self::TAX_LABEL),
                    ]),
                   'discount'
                );

		$parent->addTotal(
                    new Varien_Object([
                        'code' => self::TOTAL_CODE_FEES,
                        'value' => $fees,
                        'base_value' => $fees,
                        'label' => $this->helper->__(self::TAX_LABEL_FEES),
                    ]),
                   'discount'
                );
	} else if (!$toggleFees && $toggleDuties ) {
		$taxAmount = $records + $fees;

		$parent->addTotal(
                    new Varien_Object([
                        'code' => self::TOTAL_CODE,
                        'value' => $taxAmount,
                        'base_value' => $taxAmount,
                        'label' => $this->helper->__(self::TAX_LABEL),
                    ]),
                   'discount'
                );

                $parent->addTotal(
                    new Varien_Object([
                        'code' => self::TOTAL_CODE_DUTIES,
                        'value' => $duties,
                        'base_value' => $duties,
                        'label' => $this->helper->__(self::TAX_LABEL_DUTIES),
                    ]),
                   'discount'
                );
	} else {
        	$taxAmount = $records + $duties + $fees;
        	$parent->addTotal(
        	        new Varien_Object([
        	            'code' => self::TOTAL_CODE,
        	            'value' => $taxAmount,
        	            'base_value' => $taxAmount,
        	            'label' => $this->helper->__(self::TAX_LABEL),
        	        ]),
                    'discount'
                );
	}

        return $this;
    }
}
