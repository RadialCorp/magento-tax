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

class Radial_Tax_Model_Observer
{
    /** @var bool Lock to guard against too much recursion in quote collect totals */
    protected static $lockRecollectTotals = false;
    /** @var Radial_Tax_Model_Collector */
    protected $taxCollector;
    /** @var Radial_Core_Model_Session */
    protected $coreSession;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $logContext;

    /**
     * @param array
     */
    public function __construct(array $args = [])
    {
        list(
            $this->taxCollector,
            $this->coreSession,
            $this->logger,
            $this->logContext
        ) = $this->checkTypes(
            $this->nullCoalesce($args, 'tax_collector', Mage::getModel('radial_tax/collector')),
            $this->nullCoalesce($args, 'core_session', null),
            $this->nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context'))
        );
    }

    /**
     * Enforce type checks on construct args array.
     *
     * @param Radial_Tax_Model_Collector
     * @param Radial_Tax_Model_Session
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @return array
     */
    protected function checkTypes(
        Radial_Tax_Model_Collector $taxCollector,
        Radial_Core_Model_Session $coreSession = null,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext
    ) {
        return func_get_args();
    }

    /**
     * Fill in default values.
     *
     * @param string
     * @param array
     * @param mixed
     * @return mixed
     */
    protected function nullCoalesce(array $arr, $key, $default)
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /**
     * Get the session instance containing collected tax data for the quote.
     * Populates the class property if not set when requested. The property
     * will not be set during construction to minimize the risk of initializing
     * the session instance before the user session has been started.
     *
     * @return Radial_Core_Model_Session
     */
    protected function getCoreSession()
    {
        if (!$this->coreSession) {
            $this->coreSession = Mage::getSingleton('radial_core/session');
        }
        return $this->coreSession;
    }

    /**
     * Collect new tax totals if necessary after collecting quote totals.
     * Tax totals collected after all other quote totals so tax totals for the
     * entire quote may be collected at one - all other totals for all other
     * addresses must have already been collected.
     *
     * If new taxes are collected, all quote totals must be recollected.
     *
     * @param Varien_Event_Observer
     * @return self
     */
    public function handleSalesQuoteCollectTotalsAfter(Varien_Event_Observer $observer)
    {
        $coreSession = $this->getCoreSession();
        if ($coreSession->isTaxUpdateRequired()) {
            /** @var Mage_Sales_Model_Quote */
            $quote = $observer->getEvent()->getQuote();
            try {
                $this->taxCollector->collectTaxes($quote);
            } catch (Radial_Tax_Exception_Collector_InvalidQuote_Exception $e) {
                // Exception for when a quote is not yet ready for making
                // a tax request. Not an entirely uncommon situation and
                // does not necessarily indicate anything is actually wrong
                // unless the quote is expected to be valid but isn't.
                $this->logger->debug('Quote not valid for tax request.', $this->logContext->getMetaData(__CLASS__));
                return $this;
            } catch (Radial_Tax_Exception_Collector_Exception $e) {
                // Want TDF to be non-blocking so exceptions from making the
                // request should be caught. Still need to exit here when there
                // is an exception, however, to allow the TDF to be retried
                // (don't reset update required flag) and prevent totals from being
                // recollected (nothing to update and, more imporantly, would
                // continue to loop until PHP crashes or a TDF request succeeds).
                $this->logger->warning('Tax request failed.', $this->logContext->getMetaData(__CLASS__, [], $e));
                return $this;
            }
            // After retrieving new tax records, update the session with data
            // from the quote used to make the request and reset the tax
            // update required flag as another update should not be required
            // until some other change has been detected.
            $this->logger->debug('Update session flags after tax collection.', $this->logContext->getMetaData(__CLASS__));
            $coreSession->updateWithQuote($quote)->resetTaxUpdateRequired();
            // Need to trigger a re-collection of quote totals now that taxes
            // for the quote have been retrieved. On the second pass, tax totals
            // just collected should be applied to the quote and any totals
            // dependent upon tax totals - like grand total - should update
            // to include the tax totals.
            $this->recollectTotals($quote);
        }
        return $this;
    }

    /**
     * Collect new tax totals if necessary before submitting tax invoice. 
     * Tax totals collected after all other quote totals so tax totals for the
     * entire quote may be collected at one - all other totals for all other
     * addresses must have already been collected.
     *
     * If new taxes are collected, all quote totals must be recollected.
     *
     * @param Varien_Event_Observer
     * @return self
     */
    public function processTaxInvoiceForInvoice(Varien_Event_Observer $observer)
    {
	$invoice = $observer->getEvent()->getInvoice();
	$order = $invoice->getOrder();

	$comment = "Tax Invoice Successfully Queued for Invoice: ". $invoice->getIncrementId();

	$invoice->setData('radial_tax_transmit', 0);
	$invoice->addComment($comment, false, true);
	$invoice->save();

	//Mark the invoice comments as sent.
        $history = Mage::getModel('sales/order_status_history')
                      ->setStatus($order->getStatus())
                      ->setComment("Tax Invoice Successfully Queued for Invoice: ". $invoice->getIncrementId())
                      ->setEntityName('order');
        $order->addStatusHistory($history);
	$order->save();	

        return $this;
    }

    /**
     * Collect new tax totals if necessary before submitting tax invoice.
     * Tax totals collected after all other quote totals so tax totals for the
     * entire quote may be collected at one - all other totals for all other
     * addresses must have already been collected.
     *
     * If new taxes are collected, all quote totals must be recollected.
     *
     * @param Varien_Event_Observer
     * @return self
     */
    public function processTaxInvoiceForCreditmemo(Varien_Event_Observer $observer)
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
	$order = $creditmemo->getOrder();

        $creditmemo->setData('radial_tax_transmit', 0);
	$comment = "Tax Invoice Successfully Queued for Creditmemo: ". $creditmemo->getIncrementId();
	$creditmemo->addComment($comment, false, true);
        $creditmemo->save();

	//Mark the invoice comments as sent.
        $history = Mage::getModel('sales/order_status_history')
                       ->setStatus($order->getStatus())
                       ->setComment($comment)
                       ->setEntityName('order');
        $order->addStatusHistory($history);
	$order->save();

        return $this;
    }

    /**
     * @param    Varien_Event_Observer
     * @return   self
     */
    public function copyFromQuoteToOrder(Varien_Event_Observer $observer)
    {
	$quote = $observer->getEvent()->getQuote();
	$taxFees = unserialize($quote->getData('radial_tax_fees'));
	$taxDuties = unserialize($quote->getData('radial_tax_duties'));
	$taxRecords = unserialize($quote->getData('radial_tax_taxrecords'));
	$taxTransactionId = $quote->getData('radial_tax_transaction_id');

	$orderC = Mage::getModel('sales/order')->getCollection()
                                        ->addFieldToFilter('quote_id', array('eq' => $quote->getId()));

	foreach( $orderC as $order )
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
					if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_ADDRESS_GIFTING && $order->getGwId() && $order->getGwPrice()) 
					{
						$prev = $order->getGwTaxAmount();
                                        	$order->setData('gw_base_tax_amount', $prev + $taxRecord->getCalculatedTax());
                                        	$order->setData('gw_tax_amount', $prev + $taxRecord->getCalculatedTax());
                                        	$order->getResource()->saveAttribute($order, 'gw_tax_amount');
                                        	$order->getResource()->saveAttribute($order, 'gw_base_tax_amount');
						continue;
                                	}

					// If its a customizable feature / base its most likely related to print cards
					if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_CUSTOMIZATION_BASE || $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_CUSTOMIZATION_FEATURE && $order->getGwCardPrice() && $order->getGwAddCard())
					{
						$prev = $order->getGwCardTaxAmount();
						$order->setData('gw_card_tax_amount', $prev + $taxRecord->getCalculatedTax());
						$order->setData('gw_card_base_tax_amount', $prev + $taxRecord->getCalculatedTax());
						$order->getResource()->saveAttribute($order, 'gw_card_base_tax_amount');
						$order->getResource()->saveAttribute($order, 'gw_card_tax_amount');
						continue;
					}

					$itemC = Mage::getModel('sales/order_item')->getCollection()
   						->addFieldToFilter('quote_item_id', array('eq' => $taxRecord->getItemId()))
						->addFieldToFilter('order_id', array('eq' => $order->getId()));

					if( $itemC->getSize() > 0 )
					{
						$item = $itemC->getFirstItem();

						if( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_ITEM_GIFTING )
						{
							$prev = $item->getGwTaxAmount();
							if( $prev )
							{
								$prev = $item->getGwTaxAmount();
							} else {
								$prev = 0;
							}	

							$div = $prev + ($taxRecord->getCalculatedTax() / $item->getQtyOrdered());
							$new = $div;

                                        		$item->setData('gw_base_tax_amount', $new);
                                        		$item->setData('gw_tax_amount', $new);
							$item->save();

							$prevTotal = $order->getGwItemsTaxAmount();
							$order->setData('gw_items_base_tax_amount', $prevTotal + $taxRecord->getCalculatedTax());
							$order->setData('gw_items_tax_amount', $prevTotal + $taxRecord->getCalculatedTax());
							$order->getResource()->saveAttribute($order, 'gw_items_base_tax_amount');
							$order->getResource()->saveAttribute($order, 'gw_items_tax_amount');
						} else if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_SHIPPING || $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_SHIPPING_DISCOUNT ) {
							$prev = $order->getShippingTaxAmount();
	
							$order->setData('base_shipping_tax_amount', $prev + $taxRecord->getCalculatedTax());
							$order->setData('shipping_tax_amount', $prev + $taxRecord->getCalculatedTax());
					
							$prev = $order->getShippingInclTax();	
							$order->setData('shipping_incl_tax', $prev + $taxRecord->getCalculatedTax());
							$order->setData('base_shipping_incl_tax', $prev + $taxRecord->getCalculatedTax());

							$order->getResource()->saveAttribute($order, 'shipping_incl_tax');
							$order->getResource()->saveAttribute($order, 'base_shipping_incl_tax');
							$order->getResource()->saveAttribute($order, 'base_shipping_tax_amount');
							$order->getResource()->saveAttribute($order, 'shipping_tax_amount');
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

							$new = $taxRecord->getCalculatedTax() / $item->getQtyOrdered();

							$newP = $new + $item->getPriceInclTax();

							$item->setPriceInclTax($newP);
							$item->setBasePriceInclTax($newP);				

							$newS = $taxRecord->getCalculatedTax() + $item->getRowTotalInclTax();
							$item->setRowTotalInclTax($newS);
							$item->setBaseRowTotalInclTax($newS);

							$item->save();
	
							// Update Order Record
							$prev = $order->getSubtotalInclTax();
							$order->setData('base_subtotal_incl_tax', $prev + $taxRecord->getCalculatedTax());
							$order->setData('subtotal_incl_tax', $prev + $taxRecord->getCalculatedTax());
							$order->getResource()->saveAttribute($order, 'base_subtotal_incl_tax');
							$order->getResource()->saveAttribute($order, 'subtotal_incl_tax');
						} else {
							// Customizations
							Mage::Log("Outlier Tax Records: ". print_r($taxRecord, true));
						}
					}
				}
			}
		}

		if( count($taxDuties) > 0 )
		{
			foreach( $taxDuties as $taxDuty )
			{
				if( $taxDuty->getAmount() > 0 )
				{
                        		$itemC = Mage::getModel('sales/order_item')->getCollection()
                        		        ->addFieldToFilter('quote_item_id', array('eq' => $taxRecord->getItemId()))
                        		        ->addFieldToFilter('order_id', array('eq' => $order->getId()));
					if( $itemC->getSize() > 0 )
                			{
                        			$item = $itemC->getFirstItem();
                        			if( $item->getTaxAmount())
                        			{
                                			$prev = $item->getTaxAmount();
                        			} else {
                                			$prev = 0;
                        			}
                        			$new = $prev + $taxDuty->getAmount();
                        			$item->setTaxAmount($new);

						$newD = $taxDuty->getAmount() / $item->getQtyOrdered();
                                        	$newP = $newD + $item->getPriceInclTax();

                                        	$item->setPriceInclTax($newP);
                                        	$item->setBasePriceInclTax($newP);

                                        	$newS = $taxDuty->getAmount() + $item->getRowTotalInclTax();
                                        	$item->setRowTotalInclTax($newS);
                                        	$item->setBaseRowTotalInclTax($newS);
                        			$item->save();

						// Update Order Record
                                        	$prev = $order->getSubtotalInclTax();
                                        	$order->setData('base_subtotal_incl_tax', $prev + $taxDuty->getAmount());
                                        	$order->setData('subtotal_incl_tax', $prev + $taxDuty->getAmount());
                                        	$order->getResource()->saveAttribute($order, 'base_subtotal_incl_tax');
                                        	$order->getResource()->saveAttribute($order, 'subtotal_incl_tax');

						$taxTotal += $taxDuty->getAmount();
					}
				}
			}	
		}
	
	
		if( count($taxFees) > 0 )
		{
			foreach( $taxFees as $taxFee )
        		{
				if( $taxFee->getAmount() > 0 )
				{
                        		$itemC = Mage::getModel('sales/order_item')->getCollection()
                        		        ->addFieldToFilter('quote_item_id', array('eq' => $taxRecord->getItemId()))
                        		        ->addFieldToFilter('order_id', array('eq' => $order->getId()));
        				if( $itemC->getSize() > 0 )
                        		{
                        	        	$item = $itemC->getFirstItem();
                        	        	if( $item->getTaxAmount())
                                		{
                                        		$prev = $item->getTaxAmount();
                                		} else {
                                		        $prev = 0;
                                		}
                                		$new = $prev + $taxFee->getAmount();
                                		$item->setTaxAmount($new);
				
						$div = $taxFee->getAmount() / $item->getQtyOrdered();	
						$newP = $div + $item->getPriceInclTax();

                                        	$item->setPriceInclTax($newP);
                                        	$item->setBasePriceInclTax($newP);
                        
                                        	$newS = $taxFee->getAmount() + $item->getRowTotalInclTax();
                                        	$item->setRowTotalInclTax($newS);
                                        	$item->setBaseRowTotalInclTax($newS);
                                        	$item->save();

                                        	// Update Order Record
                                        	$prev = $order->getSubtotalInclTax();
                                        	$order->setData('base_subtotal_incl_tax', $prev + $taxFee->getAmount());
                                        	$order->setData('subtotal_incl_tax', $prev + $taxFee->getAmount());
                                        	$order->getResource()->saveAttribute($order, 'base_subtotal_incl_tax');
                                        	$order->getResource()->saveAttribute($order, 'subtotal_incl_tax');					

						$taxTotal += $taxFee->getAmount();
                        		}
				}
			}
		}

		$order->setData('tax_amount', $taxTotal);
                $order->setData('radial_tax_fees', serialize($taxFees));
                $order->setData('radial_tax_duties', serialize($taxDuties));
                $order->setData('radial_tax_taxrecords', serialize($taxRecords));
                $order->setData('radial_tax_transaction_id', $taxTransactionId);

                $order->getResource()->saveAttribute($order, 'tax_amount');
                $order->getResource()->saveAttribute($order, 'radial_tax_fees');
                $order->getResource()->saveAttribute($order, 'radial_tax_duties');
                $order->getResource()->saveAttribute($order, 'radial_tax_taxrecords');
                $order->getResource()->saveAttribute($order, 'radial_tax_transaction_id');
	}

	return $this;
    }

    /**
     * Recollect quote totals to update amounts based on newly received tax
     * data. This collect totals call is expected to happen recursively within
     * collect totals. The flags in radial_core/session are expected to prevent
     * going beyond a single recursive call to collect totals. As an additional
     * precaution, a lock is also used to prevent unexpected recursion.
     *
     * @param Mage_Sales_Model_Quote
     * @return Mage_Sales_Model_Quote
     */
    protected function recollectTotals(Mage_Sales_Model_Quote $quote)
    {
        // Guard against unexpected recursion. Session flags should prevent
        // this but need to be sure this can't trigger infinite recursion.
        // If the lock is free (set to false), expect to not be within a recursive
        // collectTotals triggered by taxes.
        if (!self::$lockRecollectTotals) {
            // Acquire the lock prior to triggering the recursion. Prevents taxes
            // from being able to trigger further recursion.
            self::$lockRecollectTotals = true;
            $quote->collectTotals();
            // Free the lock once we're clear of the recursive collectTotals.
            self::$lockRecollectTotals = false;
        } else {
            // Do not expect further recursive attempts to occur. Something
            // would be potentially wrong with the session flags if it does.
            $this->logger->warning('Attempted to recollect totals for taxes during a recursive collection. Additional collection averted to prevent further recursion.', $this->logContext->getMetaData(__CLASS__));
        }
        return $quote;
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
