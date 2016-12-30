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
     * Collect taxes for order, making an SDK tax request if necessary.
     *
     * @param Mage_Sales_Model_Order
     * @param orderId (optional)
     * @return self
     * @throws Radial_Tax_Exception_Collector_Exception If TDF cannot be collected.
     */
    public function collectTaxesForOrder(Mage_Sales_Model_Order $order)
    {
        $this->_logger->debug('Collecting new tax data for order (retry).', $this->_logContext->getMetaData(__CLASS__));
        try {
            $taxResults = $this->_taxHelper->requestTaxesForOrder($order);
        } catch (Radial_Tax_Exception_Collector_Exception $e) {
            // If tax records needed to be updated but could be collected,
            // any previously collected taxes need to be cleared out to
            // prevent tax data that is no longer applicable to the quote
            // from being preserved. E.g. taxes for an item no longer in
            // the quote or calculated for a different shipping/billing
            // address cannot be preserved. Complexity of individually
            // pruning tax data in this case does not seem worth the
            // cost at this time.
            throw $e;
        }
        $order->setData("radial_tax_taxrecords", serialize($taxResults->getTaxRecords()));
        $order->setData("radial_tax_duties", serialize($taxResults->getTaxDuties()));
        $order->setData("radial_tax_fees", serialize($taxResults->getTaxFees()));
	$order->setData("radial_tax_transmit", -1);

	$order->getResource()->saveAttribute($order, 'radial_tax_taxrecords');
	$order->getResource()->saveAttribute($order, 'radial_tax_duties');
	$order->getResource()->saveAttribute($order, 'radial_tax_fees');
	$order->getResource()->saveAttribute($order, 'radial_tax_transmit');

        return -1;
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

	$this->updateQuoteTotals($quote, $taxResults->getTaxFees(), $taxResults->getTaxDuties(), $taxResults->getTaxRecords());

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

    /**
     * @param    Mage_Sales_Model_Quote, array Radial_Tax_Model_Fee, array Radial_Tax_Model_Duty, array Radial_Tax_Model_Record
     * @return   self
     */
    public function updateQuoteTotals(Mage_Sales_Model_Quote $quote, array $taxFees, array $taxDuties, array $taxRecords)
    {
	$taxTotal = 0;

	if( count($taxRecords) > 0 )
	{
		foreach( $taxRecords as $taxRecord )
		{
			if( $taxRecord->getCalculatedTax() > 0 )
			{
				$taxTotal += $taxRecord->getCalculatedTax();

				// Tabulate Address Level Gifting Outside of Item Level Stuff
				if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_ADDRESS_GIFTING && $quote->getGwId() && $quote->getGwPrice()) 
				{
					$prev = $quote->getGwTaxAmount();
                                     	$quote->setData('gw_base_tax_amount', $prev + $taxRecord->getCalculatedTax());
                                       	$quote->setData('gw_tax_amount', $prev + $taxRecord->getCalculatedTax());
					continue;
                                }

				// If its a customizable feature / base its most likely related to print cards
				if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_CUSTOMIZATION_BASE || $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_CUSTOMIZATION_FEATURE && $quote->getGwCardPrice() && $quote->getGwAddCard())
				{
					$prev = $quote->getGwCardTaxAmount();
					$quote->setData('gw_card_tax_amount', $prev + $taxRecord->getCalculatedTax());
					$quote->setData('gw_card_base_tax_amount', $prev + $taxRecord->getCalculatedTax());
					continue;
				}

				//$itemC = Mage::getModel('sales/quote_item')->getCollection()
				//		->setQuote($quote)
   				//		->addFieldToFilter('item_id', array('eq' => $taxRecord->getItemId()))
				//		->addFieldToFilter('quote_id', array('eq' => $quote->getId()));

				$cart=Mage::getSingleton('checkout/cart');
				$item = $cart->getQuote()->getItemById($taxRecord->getItemId());
	
				//if( $itemC->getSize() > 0 )
				//{
					//$item = $itemC->getFirstItem();

					if( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_ITEM_GIFTING )
					{
						$prev = $item->getGwTaxAmount();
						if( $prev )
						{
							$prev = $item->getGwTaxAmount();
						} else {
							$prev = 0;
						}	

						$div = $prev + ($taxRecord->getCalculatedTax() / $item->getQty());
						$new = $div;

                             	        	$item->setData('gw_base_tax_amount', $new);
                                	       	$item->setData('gw_tax_amount', $new);
						$item->save();

						$prevTotal = $quote->getGwItemsTaxAmount();
						$quote->setData('gw_items_base_tax_amount', $prevTotal + $taxRecord->getCalculatedTax());
						$quote->setData('gw_items_tax_amount', $prevTotal + $taxRecord->getCalculatedTax());
					} else if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_SHIPPING || $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_SHIPPING_DISCOUNT ) {
						$prev = $quote->getShippingTaxAmount();

						$quote->setData('base_shipping_tax_amount', $prev + $taxRecord->getCalculatedTax());
						$quote->setData('shipping_tax_amount', $prev + $taxRecord->getCalculatedTax());

						$prev = $quote->getShippingInclTax();	
						$quote->setData('shipping_incl_tax', $prev + $taxRecord->getCalculatedTax());
						$quote->setData('base_shipping_incl_tax', $prev + $taxRecord->getCalculatedTax());
					} else if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_MERCHANDISE || $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_MERCHANDISE_DISCOUNT ) {
						// Update Item Record

						if( $item->getTaxAmount())
						{
							$prev = $item->getTaxAmount();
						} else {
							$prev = 0;
						}

						$new = $prev + $taxRecord->getCalculatedTax();
						$item->setTaxAmount($new);

						$new = $taxRecord->getCalculatedTax() / $item->getQty();

						$newP = $new + $item->getPriceInclTax();

						$item->setPriceInclTax($newP);
						$item->setBasePriceInclTax($newP);				

						$newS = $taxRecord->getCalculatedTax() + $item->getRowTotalInclTax();
						$item->setRowTotalInclTax($newS);
						$item->setBaseRowTotalInclTax($newS);

						Mage::Log("Before Quote Item: ". print_r($item->debug(), true));

						$item->save();

						Mage::Log("After Quote Item Save: ". print_r($item->debug(), true));
	
						// Update Order Record
						$prev = $quote->getSubtotalInclTax();
						$quote->setData('base_subtotal_incl_tax', $prev + $taxRecord->getCalculatedTax());
						$quote->setData('subtotal_incl_tax', $prev + $taxRecord->getCalculatedTax());
					} else {
						// Customizations
						Mage::Log("Outlier Tax Records: ". print_r($taxRecord, true));
					}
				//}
			}
		}
	}

	if( count($taxDuties) > 0 )
	{
		foreach( $taxDuties as $taxDuty )
		{
			if( $taxDuty->getAmount() > 0 )
			{
                        	//$itemC = Mage::getModel('sales/quote_item')->getCollection()
				//	->setQuote($quote)
                        	//        ->addFieldToFilter('item_id', array('eq' => $taxRecord->getItemId()))
                        	//        ->addFieldToFilter('quote_id', array('eq' => $quote->getId()));

				$cart=Mage::getSingleton('checkout/cart');
                                $item = $cart->getQuote()->getItemById($taxDuty->getItemId());

				//if( $itemC->getSize() > 0 )
                		//{
                        	//	$item = $itemC->getFirstItem();
                        		if( $item->getTaxAmount())
                        		{
                                		$prev = $item->getTaxAmount();
                        		} else {
                                		$prev = 0;
                        		}
                        		$new = $prev + $taxDuty->getAmount();
                        		$item->setTaxAmount($new);

					$newD = $taxDuty->getAmount() / $item->getQty();
                                    	$newP = $newD + $item->getPriceInclTax();

                                       	$item->setPriceInclTax($newP);
                                       	$item->setBasePriceInclTax($newP);

                                       	$newS = $taxDuty->getAmount() + $item->getRowTotalInclTax();
                                       	$item->setRowTotalInclTax($newS);
                                       	$item->setBaseRowTotalInclTax($newS);
                        		$item->save();

					// Update Order Record
                                       	$prev = $quote->getSubtotalInclTax();
                                       	$quote->setData('base_subtotal_incl_tax', $prev + $taxDuty->getAmount());
                                       	$quote->setData('subtotal_incl_tax', $prev + $taxDuty->getAmount());

					$taxTotal += $taxDuty->getAmount();
				//}
				
			}
		}
	}

	if( count($taxFees) > 0 )
	{
		foreach( $taxFees as $taxFee )
        	{
			if( $taxFee->getAmount() > 0 )
			{
                        	//$itemC = Mage::getModel('sales/quote_item')->getCollection()
				//		->setQuote($quote)
                        	//	        ->addFieldToFilter('item_id', array('eq' => $taxRecord->getItemId()))
                        	//	        ->addFieldToFilter('quote_id', array('eq' => $quote->getId()));

				$cart=Mage::getSingleton('checkout/cart');
                                $item = $cart->getQuote()->getItemById($taxFee->getItemId());

        			//if( $itemC->getSize() > 0 )
                        	//{
                        	//        $item = $itemC->getFirstItem();
                        	        if( $item->getTaxAmount())
                                	{
                                        	$prev = $item->getTaxAmount();
                                	} else {
                                	        $prev = 0;
                                	}
                                	$new = $prev + $taxFee->getAmount();
                                	$item->setTaxAmount($new);
		
					$div = $taxFee->getAmount() / $item->getQty();	
					$newP = $div + $item->getPriceInclTax();

                                   	$item->setPriceInclTax($newP);
                                        $item->setBasePriceInclTax($newP);
                        
                                        $newS = $taxFee->getAmount() + $item->getRowTotalInclTax();
                                        $item->setRowTotalInclTax($newS);
                                        $item->setBaseRowTotalInclTax($newS);
                                       	$item->save();

                                        // Update Order Record
                                        $prev = $quote->getSubtotalInclTax();
                                        $quote->setData('base_subtotal_incl_tax', $prev + $taxFee->getAmount());
                                        $quote->setData('subtotal_incl_tax', $prev + $taxFee->getAmount());

					$taxTotal += $taxFee->getAmount();
				//}
			}
		}
	}

	$newTotal = $quote->getData('grand_total') + $taxTotal;
	$newDue = $quote->getData('total_due') + $taxTotal;

	$quote->setData('tax_amount', $taxTotal);
	$quote->setData('base_grand_total', $newTotal);
	$quote->setData('grand_total', $newTotal);
	$quote->setData('total_due', $newDue);
	$quote->setData('base_total_due', $newDue);

	$quote->setData("radial_tax_taxrecords", serialize($taxRecords));
        $quote->setData("radial_tax_duties", serialize($taxDuties));
        $quote->setData("radial_tax_fees", serialize($taxFees));

	$quote->save();

	return $this;
    }
}
