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
class Radial_Tax_Helper_Duty
{
    /** @var Radial_Tax_Model_Collector */
    protected $taxCollector;

    /**
     * @param array $args May contain key/value for:
     * - tax_collector  => Radial_Tax_Model_Collector
     */
    public function __construct(array $args = [])
    {
        list($this->taxCollector) = $this->checkTypes(
            $this->nullCoalesce($args, 'tax_collector', Mage::getModel('radial_tax/collector'))
        );
    }

    /**
     * Enforce type checks on constructor init params.
     *
     * @param Radial_Tax_Model_Collector
     * @return array
     */
    protected function checkTypes(
        Radial_Tax_Model_Collector $taxCollector
    ) {
        return func_get_args();
    }

    /**
     * Fill in default values.
     *
     * @param array
     * @param mixed
     * @return mixed
     */
    protected function nullCoalesce(array $arr, $key, $default)
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /**
     * Return a triple of tax, duty and fee totals for one or all addresses.
     *
     * @param int|null $addressId If set, return the sum just for this address. Otherwise sum for all addresses in order.
     * @return float[] of size three
     */
    public function getTaxDutyFeeTotals($addressId = null)
    {
        $collector = $this->taxCollector;
        if (is_null($addressId)) {
            $taxRecords = $collector->getTaxRecords();
            $dutyRecords = $collector->getTaxDuties();
            $feeRecords = $collector->getTaxFees();
        } else {
            $taxRecords = $collector->getTaxRecordsByAddressId($addressId);
            $dutyRecords = $collector->getTaxDutiesByAddressId($addressId);
            $feeRecords = $collector->getTaxFeesByAddressId($addressId);
        }
        $taxes = array_sum(array_map(function ($record) { return $record->getCalculatedTax(); }, $taxRecords));
        $duties = array_sum(array_map(function ($record) { return $record->getAmount(); }, $dutyRecords));
        $fees = array_sum(array_map(function ($record) { return $record->getAmount(); }, $feeRecords));
        return [$taxes, $duties, $fees];
    }
}
