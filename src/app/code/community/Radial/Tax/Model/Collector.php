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
 * Manage storage and retrieval of tax records.
 */
class Radial_Tax_Model_Collector
{
    /** @var Radial_Tax_Helper_Data */
    protected $_taxHelper;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $_logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $_logContext;
    /** @var Radial_Tax_Model_Session */
    protected $_taxSession;

    /**
     * @param array May include keys/value pairs:
     *                  - tax_helper => Radial_Tax_Helper_Data
     *                  - logger => EbayEnterprise_MageLog_Helper_Data
     *                  - log_context => EbayEnterprise_MageLog_Helper_Context
     *                  - tax_session => Radial_Tax_Model_Session
     */
    public function __construct(array $args = [])
    {
        list(
            $this->_taxHelper,
            $this->_logger,
            $this->_logContext,
            $this->_taxSession
            ) = $this->_checkTypes(
            $this->_nullCoalesce($args, 'tax_helper', Mage::helper('radial_tax')),
            $this->_nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->_nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context')),
            $this->_nullCoalesce($args, 'tax_session', null)
        );
    }

    /**
     * Enforce type checks on construct args array.
     *
     * @param Radial_Tax_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @param Radial_Tax_Model_Session
     * @return array
     */
    protected function _checkTypes(
        Radial_Tax_Helper_Data $taxHelper,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext,
        Radial_Tax_Model_Session $taxSession = null
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
    protected function _nullCoalesce(array $arr, $key, $default)
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /**
     * Get the session instance containing collected tax data for the quote.
     * Populates the class property if not set when requested. The property
     * will not be set during construction to minimize the risk of initializing
     * the session instance before the user session has been started.
     *
     * @return Radial_Tax_Model_Session
     */
    protected function _getTaxSession()
    {
        if (!$this->_taxSession) {
            $this->_taxSession = Mage::getSingleton('radial_tax/session');
        }
        return $this->_taxSession;
    }

    /**
     * Get if the last tax request attempted was successful.
     *
     * @return bool
     */
    public function getTaxRequestSuccess()
    {
        return $this->_getTaxSession()->getTaxRequestSuccess();
    }

    /**
     * Set whether the last tax request made was successful.
     *
     * @param bool
     * @return self
     */
    public function setTaxRequestSuccess($success)
    {
        $this->_getTaxSession()->setTaxRequestSuccess($success);
        return $this;
    }

    /**
     * Get all tax records collected.
     *
     * @return Radial_Tax_Model_Record[]
     */
    public function getTaxRecords()
    {
        return (array) $this->_getTaxSession()->getTaxRecords();
    }

    /**
     * Get any tax records relevant to an address.
     *
     * @param int
     * @return Radial_Tax_Model_Record[]
     */
    public function getTaxRecordsByAddressId($addressId)
    {
        return array_filter(
            $this->getTaxRecords(),
            function ($record) use ($addressId) {
                return $record->getAddressId() === $addressId;
            }
        );
    }

    /**
     * Get all tax records associated to the item.
     *
     * @param int
     * @return Radial_Tax_Model_Record[]
     */
    public function getTaxRecordsByItemId($itemId)
    {
        return array_filter(
            $this->getTaxRecords(),
            function ($record) use ($itemId) {
                return $record->getItemId() === $itemId;
            }
        );
    }

    /**
     * Replace collected tax records.
     *
     * @param Radial_Tax_Model_Record[]
     * @return self
     */
    public function setTaxRecords(array $taxRecords = [])
    {
        $this->_getTaxSession()->setTaxRecords($taxRecords);
        return $this;
    }

    /**
     * Get all current duties.
     *
     * @return Radial_Tax_Model_Duty[]
     */
    public function getTaxDuties()
    {
        return (array) $this->_getTaxSession()->getTaxDuties();
    }

    /**
     * Get duty amount available for the item. Each item can only have one
     * duty amount. If more tnan one duty amount for an item is available,
     * the first duty amount encountered will be returned.
     *
     * @param int
     * @return Radial_Tax_Model_Duty|null
     */
    public function getTaxDutyByItemId($itemId)
    {
        foreach ($this->getTaxDuties() as $duty) {
            if ($duty->getItemId() === $itemId) {
                return $duty;
            }
        }
        return null;
    }

    /**
     * Get duty amount available for the address.
     *
     * @param int
     * @return Radial_Tax_Model_Duty[]
     */
    public function getTaxDutiesByAddressId($addressId)
    {
        return array_filter(
            $this->getTaxDuties(),
            function ($duty) use ($addressId) {
                return $duty->getAddressId() === $addressId;
            }
        );
    }

    /**
     * Set current duties.
     *
     * @param Radial_Tax_Model_Duty[]
     * @return self
     */
    public function setTaxDuties(array $taxDuties = [])
    {
        $this->_getTaxSession()->setTaxDuties($taxDuties);
        return $this;
    }

    /**
     * Get all current fees.
     *
     * @return Radial_Tax_Model_Fee[]
     */
    public function getTaxFees()
    {
        return (array) $this->_getTaxSession()->getTaxFees();
    }

    /**
     * Get current fees by quote item id.
     *
     * @param int
     * @return Radial_Tax_Model_Fee[]
     */
    public function getTaxFeesByItemId($itemId)
    {
        return array_filter(
            $this->getTaxFees(),
            function ($fee) use ($itemId) {
                return $fee->getItemId() === $itemId;
            }
        );
    }

    /**
     * Get current fees by quote address id.
     *
     * @param int
     * @return Radial_Tax_Model_Fee[]
     */
    public function getTaxFeesByAddressId($addressId)
    {
        return array_filter(
            $this->getTaxFees(),
            function ($fee) use ($addressId) {
                return $fee->getAddressId() === $addressId;
            }
        );
    }

    /**
     * Set current fees.
     *
     * @param Radial_Tax_Model_Fee[]
     * @return self
     */
    public function setTaxFees(array $taxFees = [])
    {
        $this->_getTaxSession()->setTaxFees($taxFees);
        return $this;
    }

    /**
     * Collect taxes for quote, making an SDK tax request if necessary.
     *
     * @param Mage_Sales_Model_Quote
     * @param orderId (optional)
     * @return self
     * @throws Radial_Tax_Exception_Collector_Exception If TDF cannot be collected.
     */
    public function collectTaxes(Mage_Sales_Model_Quote $quote, $orderId = null)
    {
        $this->_logger->debug('Collecting new tax data.', $this->_logContext->getMetaData(__CLASS__));
        try {
            $this->_validateQuote($quote);
            $taxResults = $this->_taxHelper->requestTaxesForQuote($quote);
        } catch (Radial_Tax_Exception_Collector_Exception $e) {
            // If tax records needed to be updated but could be collected,
            // any previously collected taxes need to be cleared out to
            // prevent tax data that is no longer applicable to the quote
            // from being preserved. E.g. taxes for an item no longer in
            // the quote or calculated for a different shipping/billing
            // address cannot be preserved. Complexity of individually
            // pruning tax data in this case does not seem worth the
            // cost at this time.
            $this->setTaxRecords([])
                ->setTaxDuties([])
                ->setTaxFees([])
                ->setTaxRequestSuccess(false);
            throw $e;
        }
        // When taxes were successfully collected,
        $this->setTaxRecords($taxResults->getTaxRecords())
            ->setTaxDuties($taxResults->getTaxDuties())
            ->setTaxFees($taxResults->getTaxFees())
            ->setTaxRequestSuccess(true);

	$quote->setData("radial_tax_taxrecords", serialize($taxResults->getTaxRecords()));
	$quote->setData("radial_tax_duties", serialize($taxResults->getTaxDuties()));
	$quote->setData("radial_tax_fees", serialize($taxResults->getTaxFees()));
	$quote->save();

        return $this;
    }

    /**
     * Collect taxes for invoice, making an SDK tax request if necessary.
     *
     * @param Mage_Sales_Model_Order
     * @param Mage_Sales_Model_Abstract
     * @param type - Tax Invoice Type
     * @return Request Body XML for Invoice
     * @throws Radial_Tax_Exception_Collector_Exception If TDF cannot be collected.
     */
    public function collectTaxesForInvoice(Mage_Sales_Model_Order $order, Mage_Sales_Model_Abstract $invoice, $type)
    {
        $this->_logger->debug('Collecting invoice tax data.', $this->_logContext->getMetaData(__CLASS__));
        try {
            $taxResults = $this->_taxHelper->requestTaxesForInvoice($order, $invoice, $type);
        } catch (Radial_Tax_Exception_Collector_Exception $e) {
            throw $e;
        }
        return $taxResults;
    }

    /**
     * Determine if taxes can be collected for the quote.
     *
     * @param Mage_Sales_Model_Quote
     * @return self
     * @throws Radial_Tax_Exception_Collector_InvalidQuote_Exception If the quote is not valid for making a tax request.
     */
    protected function _validateQuote(Mage_Sales_Model_Quote $quote)
    {
        // At a minimum, the quote must have at least one item and a billing
        // address with usable information. Currently, a spot check of address
        // data *should* be useful enough to separate a complete address from
        // an incomplete address.
        $billingAddress = $quote->getBillingAddress();
        if ($quote->getItemsCount()
            && $billingAddress->getFirstname()
            && $billingAddress->getLastname()
            && $billingAddress->getStreetFull()
            && $billingAddress->getCountryId()
        ) {
            return $this->_validateAddresses($quote->getAllAddresses());
        }
        throw Mage::exception('Radial_Tax_Exception_Collector_InvalidQuote', 'Quote invalid for tax collection.');
    }

    /**
     * Validate all addresses in the given array of addresses.
     *
     * @param Mage_Sales_Model_Quote_Address[]
     * @return self
     * @throws Radial_Tax_Exception_Collector_Exception If any address is invalid.
     */
    protected function _validateAddresses(array $addresses)
    {
        foreach ($addresses as $address) {
            $this->_validateItems($address->getAllVisibleItems());
        }
        return $this;
    }

    /**
     * Validate each item in the given array of items to be
     * valid for making a tax request.
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract[]
     * @return self
     * @throws Radial_Tax_Exception_Collector_InvalidQuote_Exception
     */
    protected function _validateItems(array $items)
    {
        foreach ($items as $item) {
            if ($item->getId() && $item->getSku()) {
                continue;
            }
            throw Mage::exception('Radial_Tax_Exception_Collector_InvalidQuote', 'Quote item is invalid for tax collection.');
        }
        return $this;
    }
}
