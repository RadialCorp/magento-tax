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

abstract class EbayEnterprise_Tax_Model_Response_Parser_Abstract
{
    /** @var EbayEnterprise_Tax_Model_Record[] */
    protected $_taxRecords = [];
    /** @var EbayEnterprise_Tax_Model_Duty[] */
    protected $_taxDuties = [];
    /** @var EbayEnterprise_Tax_Model_Fee[] */
    protected $_taxFees = [];

    /**
     * Get all tax records included in the tax response.
     *
     * @return EbayEnterprise_Tax_Model_Record[]
     */
    public function getTaxRecords()
    {
        if (!$this->_taxRecords) {
            $this->_extractTaxData();
        }
        return $this->_taxRecords;
    }

    /**
     * Get all duties included in the tax response.
     *
     * @return EbayEnterprise_Tax_Model_Duty[]
     */
    public function getTaxDuties()
    {
        if (!$this->_taxDuties) {
            $this->_extractTaxData();
        }
        return $this->_taxDuties;
    }

    /**
     * Get all fees included in the tax response.
     *
     * @return EbayEnterprise_Tax_Model_Fee[]
     */
    public function getTaxFees()
    {
        if (!$this->_taxFees) {
            $this->_extractTaxData();
        }
        return $this->_taxFees;
    }

    /**
     * Extract tax data from payload.
     *
     * Extracts all three sets of tax data - taxes, duties and fees - as each
     * set of data can be retrieved from the same address parser. Extracting
     * all three sets at once prevents nearly identical steps from being
     * repeated for each ship group for each type of tax data.
     *
     * @return self
     */
    abstract protected function _extractTaxData();

    /**
     * Flatten a single level of nested arrays.
     *
     * [[1,2,3,['foo','bar']], [4,5,6]] => [1,2,3,['foo','bar'],4,5,6]
     *
     * @param array
     * @return array
     */
    protected function _flattenArray($arr = [])
    {
        return $arr ? call_user_func_array('array_merge', $arr) : [];
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
}
