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

/**
 * Block for rendering eBay Enterprise tax data for email templates.
 */
class EbayEnterprise_Tax_Block_Sales_Order_Tax extends Mage_Core_Block_Abstract
{
    const TAX_LABEL = 'EbayEnterprise_Tax_Order_Total_Tax_Title';
    const TOTAL_CODE = 'ebayenterprise_tax';

    /** @var EbayEnterprise_Tax_Model_Collector */
    protected $taxCollector;
    /** @var EbayEnterprise_Tax_Helper_Data */
    protected $helper;

    /**
     * @param array May contain:
     *              - tax_collector => EbayEnterprise_Tax_Model_Collector
     */
    public function __construct(array $args = [])
    {
        list(
            $this->taxCollector,
            $this->helper
        ) = $this->checkTypes(
            $this->nullCoalesce($args, 'tax_collector', Mage::getModel('ebayenterprise_tax/collector')),
            $this->nullCoalesce($args, 'helper', Mage::helper('ebayenterprise_tax'))
        );
        parent::__construct($args);
    }

    /**
     * Enforce type checks on construct args array.
     *
     * @param EbayEnterprise_Tax_Model_Collector
     * @param EbayEnterprise_Tax_Helper_Data
     * @return array
     */
    protected function checkTypes(
        EbayEnterprise_Tax_Model_Collector $taxCollector,
        EbayEnterprise_Tax_Helper_Data $helper
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

        $taxAmount = $this->totalTaxAmount();
        $parent->addTotal(
            new Varien_Object([
                'code' => self::TOTAL_CODE,
                'value' => $taxAmount,
                'base_value' => $taxAmount,
                'label' => $this->helper->__(self::TAX_LABEL),
            ]),
            'discount'
        );

        return $this;
    }

    /**
     * Total all taxes associated with the current order.
     *
     * @return float
     */
    protected function totalTaxAmount()
    {
        $records = array_reduce($this->taxCollector->getTaxRecords(), function ($total, $item) { return $total + $item->getCalculatedTax(); }, 0.00);
        $duties = array_reduce($this->taxCollector->getTaxDuties(), function ($total, $item) { return $total + $item->getAmount(); }, 0.00);
        $fees = array_reduce($this->taxCollector->getTaxFees(), function ($total, $item) { return $total + $item->getAmount(); }, 0.00);

        return $records + $duties + $fees;
    }
}
