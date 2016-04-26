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

class EbayEnterprise_Tax_Model_Result
{
    /** @var EbayEnterprise_Tax_Model_Record[] */
    protected $_taxRecords;
    /** @var EbayEnterprise_Tax_Model_Duty[] */
    protected $_duties;
    /** @var EbayEnterprise_Tax_Model_Fee[] */
    protected $_fees;

    public function __construct(array $args = [])
    {
        list(
            $this->_taxRecords,
            $this->_duties,
            $this->_fees
        ) = $this->_checkTypes(
            $args['tax_records'],
            $args['duties'],
            $args['fees']
        );
    }

    /**
     * Enforce type checks on constructor init params.
     *
     * @param EbayEnterprise_Tax_Model_Record[]
     * @param EbayEnterprise_Tax_Model_Duty[]
     * @param EbayEnterprise_Tax_Model_Fee[]
     * @return array
     */
    protected function _checkTypes(
        array $taxRecords,
        array $duties,
        array $fees
    ) {
        return [$taxRecords, $duties, $fees];
    }

    /**
     * @return EbayEnterprise_Tax_Model_Record[]
     */
    public function getTaxRecords()
    {
        return (array) $this->_taxRecords;
    }

    /**
     * @return EbayEnterprise_Tax_Model_Duty[]
     */
    public function getTaxDuties()
    {
        return (array) $this->_duties;
    }

    /**
     * @return EbayEnterprise_Tax_Model_Fee[]
     */
    public function getTaxFees()
    {
        return (array) $this->_fees;
    }
}
