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

use eBayEnterprise\RetailOrderManagement\Payload\Checkout\ITax;

class EbayEnterprise_Tax_Model_Record extends Varien_Object
{
    // Tax sources - what aspect of the quote/order led to the inclusion of the
    // tax record - e.g. merchandise totals, gifting totals, shipping discounts, etc.
    const SOURCE_MERCHANDISE = 0;
    const SOURCE_SHIPPING = 1;
    const SOURCE_DUTY = 2;
    const SOURCE_MERCHANDISE_DISCOUNT = 3;
    const SOURCE_SHIPPING_DISCOUNT = 4;
    const SOURCE_DUTY_DISCOUNT = 5;
    const SOURCE_ITEM_GIFTING = 6;
    const SOURCE_ADDRESS_GIFTING = 7;
    const SOURCE_CUSTOMIZATION_BASE = 8;
    const SOURCE_CUSTOMIZATION_FEATURE = 9;
    const SOURCE_FEE = 10;
    const SOURCE_FEE_DISCOUNT = 11;
    // These sources are not yet supported but reserved for future use - Magento
    // does not currently support applying discounts to gifting prices.
    const SOURCE_ITEM_GIFTING_DISCOUNT = 12;
    const SOURCE_ADDRESS_GIFTING_DISCOUNT = 13;

    /**
     * Resolve dependencies.
     */
    protected function _construct()
    {
        list(
            $this->_data['tax_source'],
            $this->_data['quote_id'],
            $this->_data['address_id'],
            $taxRecordPayload
        ) = $this->_checkTypes(
            $this->getData('tax_source'),
            $this->getData('quote_id'),
            $this->getData('address_id'),
            $this->getData('tax_record_payload')
        );
        // If a tax record was provided as a data source, extract data from it.
        if ($taxRecordPayload) {
            $this->_populateWithPayload($taxRecordPayload);
        }
        // Do not store the payload as it may lead to issues when storing
        // and retrieving the tax records in the session.
        $this->unsetData('tax_record_payload');
    }

    /**
     * Enforce type checks on constructor args array.
     *
     * @param int
     * @param int
     * @param int
     * @param ITax|null
     * @return array
     */
    protected function _checkTypes(
        $taxSource,
        $quoteId,
        $addressId,
        ITax $taxRecordPayload = null
    ) {
        return [$taxSource, $quoteId, $addressId, $taxRecordPayload];
    }

    /**
     * Populate the tax record with data from a tax payload.
     *
     * @param ITax
     * @return self
     */
    protected function _populateWithPayload(ITax $taxRecordPayload)
    {
        $textCodes = [];
        foreach ($taxRecordPayload->getInvoiceTextCodes() as $textCode) {
            $textCodes[] = $textCode->getCode();
        }

        return $this->setType($taxRecordPayload->getType())
            ->setTaxability($taxRecordPayload->getTaxability())
            ->setSitus($taxRecordPayload->getSitus())
            ->setJurisdiction($taxRecordPayload->getJurisdiction())
            ->setJurisdictionLevel($taxRecordPayload->getJurisdictionLevel())
            ->setJurisdictionId($taxRecordPayload->getJurisdictionId())
            ->setImposition($taxRecordPayload->getImposition())
            ->setImpositionType($taxRecordPayload->getImpositionType())
            ->setEffectiveRate($taxRecordPayload->getEffectiveRate())
            ->setTaxableAmount($taxRecordPayload->getTaxableAmount())
            ->setCalculatedTax($taxRecordPayload->getCalculatedTax())
            ->setSellerRegistrationId($taxRecordPayload->getSellerRegistrationId())
            ->setExemptAmount($taxRecordPayload->getExemptAmount())
            ->setNonTaxableAmount($taxRecordPayload->getNonTaxableAmount())
            ->setInvoiceTextCodes($textCodes);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->getData('type');
    }

    /**
     * @param string
     * @return self
     */
    public function setType($type)
    {
        return $this->setData('type', $type);
    }

    /**
     * @return string
     */
    public function getTaxability()
    {
        return $this->getData('taxability');
    }

    /**
     * @param string
     * @return self
     */
    public function setTaxability($taxability)
    {
        return $this->setData('taxability', $taxability);
    }

    /**
     * @return string
     */
    public function getSitus()
    {
        return $this->getData('situs');
    }

    /**
     * @param string
     * @return self
     */
    public function setSitus($situs)
    {
        return $this->setData('situs', $situs);
    }

    /**
     * @return string
     */
    public function getJurisdiction()
    {
        return $this->getData('jurisdiction');
    }

    /**
     * @param string
     * @return self
     */
    public function setJurisdiction($jurisdiction)
    {
        return $this->setData('jurisdiction', $jurisdiction);
    }

    /**
     * @return string
     */
    public function getJurisdictionLevel()
    {
        return $this->getData('jurisdiction_level');
    }

    /**
     * @param string
     * @return self
     */
    public function setJurisdictionLevel($jurisdictionLevel)
    {
        return $this->setData('jurisdiction_level', $jurisdictionLevel);
    }

    /**
     * @return string
     */
    public function getJurisdictionId()
    {
        return $this->getData('jurisdiction_id');
    }

    /**
     * @param string
     * @return self
     */
    public function setJurisdictionId($jurisdictionId)
    {
        return $this->setData('jurisdiction_id', $jurisdictionId);
    }

    /**
     * @return string
     */
    public function getImposition()
    {
        return $this->getData('imposition');
    }

    /**
     * @param string
     * @return self
     */
    public function setImposition($imposition)
    {
        return $this->setData('imposition', $imposition);
    }

    /**
     * @return string
     */
    public function getImpositionType()
    {
        return $this->getData('imposition_type');
    }

    /**
     * @param string
     * @return self
     */
    public function setImpositionType($impositionType)
    {
        return $this->setData('imposition_type', $impositionType);
    }

    /**
     * @return float
     */
    public function getEffectiveRate()
    {
        return $this->getData('effective_rate');
    }

    /**
     * @param float
     * @return self
     */
    public function setEffectiveRate($effectiveRate)
    {
        return $this->setData('effective_rate', $effectiveRate);
    }

    /**
     * @return float
     */
    public function getTaxableAmount()
    {
        return $this->getData('taxable_amount');
    }

    /**
     * @param float
     * @return self
     */
    public function setTaxableAmount($taxableAmount)
    {
        return $this->setData('taxable_amount', $taxableAmount);
    }

    /**
     * @return float
     */
    public function getExemptAmount()
    {
        return $this->getData('exempt_amount');
    }

    /**
     * @param float
     * @return self
     */
    public function setExemptAmount($exemptAmount)
    {
        return $this->setData('exempt_amount', $exemptAmount);
    }

    /**
     * @return float
     */
    public function getNonTaxableAmount()
    {
        return $this->getData('non_taxable_amount');
    }

    /**
     * @param float
     * @return self
     */
    public function setNonTaxableAmount($nonTaxableAmount)
    {
        return $this->setData('non_taxable_amount', $nonTaxableAmount);
    }

    /**
     * @return float
     */
    public function getCalculatedTax()
    {
        return $this->getData('calculated_tax');
    }

    /**
     * @param float
     * @return self
     */
    public function setCalculatedTax($calculatedTax)
    {
        return $this->setData('calculated_tax', $calculatedTax);
    }

    /**
     * @return string
     */
    public function getSellerRegistrationId()
    {
        return $this->getData('seller_registration_id');
    }

    /**
     * @param string
     * @return self
     */
    public function setSellerRegistrationId($sellerRegistrationId)
    {
        return $this->setData('seller_registration_id', $sellerRegistrationId);
    }

    /**
     * @return string[]
     */
    public function getInvoiceTextCodes()
    {
        return (array) $this->getData('invoice_text_codes');
    }

    /**
     * @param string[]
     * @return self
     */
    public function setInvoiceTextCodes($invoiceTextCodes)
    {
        return $this->setData('invoice_text_codes', $invoiceTextCodes);
    }

    /**
     * Get the source of tax data.
     *
     * @return int
     */
    public function getTaxSource()
    {
        return $this->getData('tax_source');
    }

    /**
     * @param int
     * @return self
     */
    public function setTaxSource($taxSource)
    {
        return $this->setData('tax_source', $taxSource);
    }

    /**
     * @return int
     */
    public function getQuoteId()
    {
        return $this->getData('quote_id');
    }

    /**
     * @param int
     * @return self
     */
    public function setQuoteId($quoteId)
    {
        return $this->setData('quote_id', $quoteId);
    }

    /**
     * @return int
     */
    public function getAddressId()
    {
        return $this->getData('address_id');
    }

    /**
     * @param int
     * @return self
     */
    public function setAddressId($quoteAddressId)
    {
        return $this->setData('address_id', $quoteAddressId);
    }

    /**
     * Id of the quote item to which the tax applies. Not all tax records will
     * have an item id, e.g. address level gifting taxes.
     *
     * @return int
     */
    public function getItemId()
    {
        return $this->getData('item_id');
    }

    /**
     * @param int
     * @return self
     */
    public function setItemId($quoteItemId)
    {
        return $this->setData('item_id', $quoteItemId);
    }

    /**
     * Id of the discount the tax record applied to. Only taxes that apply to
     * a discount will have a discount id.
     *
     * @return int
     */
    public function getDiscountId()
    {
        return $this->getData('discount_id');
    }

    /**
     * @param int
     * @return self
     */
    public function setDiscountId($discountId)
    {
        return $this->setData('discount_id', $discountId);
    }

    /**
     * Id of the fee the tax record applies to. Only taxes that apply to a fee
     * will have a fee id.
     *
     * @return string
     */
    public function getFeeId()
    {
        return $this->getData('fee_id');
    }

    /**
     * @param string
     * @return self
     */
    public function setFeeId($feeId)
    {
        return $this->setData('fee_id', $feeId);
    }
}
