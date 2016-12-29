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
    /** @var Radial_Tax_Helper_Data */
    protected $helper;

    /**
     * @param array
     */
    public function __construct(array $args = [])
    {
        list(
            $this->taxCollector,
            $this->coreSession,
            $this->logger,
            $this->logContext,
	    $this->helper
        ) = $this->checkTypes(
            $this->nullCoalesce($args, 'tax_collector', Mage::getModel('radial_tax/collector')),
            $this->nullCoalesce($args, 'core_session', null),
            $this->nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context')),
	    $this->nullCoalesce($args, 'helper', Mage::helper('radial_tax'))
        );
    }

    /**
     * Enforce type checks on construct args array.
     *
     * @param Radial_Tax_Model_Collector
     * @param Radial_Tax_Model_Session
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @param Radial_Tax_Helper_Data
     * @return array
     */
    protected function checkTypes(
        Radial_Tax_Model_Collector $taxCollector,
        Radial_Core_Model_Session $coreSession = null,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext,
	Radial_Tax_Helper_Data $helper
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

	/** @var Mage_Sales_Model_Quote */
        $quote = $observer->getEvent()->getQuote();

	$enabled = Mage::getStoreConfig('radial_core/radial_tax_core/enabledmod', $quote->getStoreId());

        $effectiveFrom = Mage::getStoreConfig('radial_core/radial_tax_core/effectivefrom', $quote->getStoreId());
        $effectiveTo = Mage::getStoreConfig('radial_core/radial_tax_core/effectiveto', $quote->getStoreId());
        $currentTime = Mage::getModel('core/date')->date('Y-m-d H:i:s');

        if( $effectiveFrom && DateTime::createFromFormat('Y-m-d H:i:s', $effectiveFrom) > DateTime::createFromFormat('Y-m-d H:i:s', $currentTime))
        {
		  $this->logger->debug('Tax Calculation Occured Before the Radial Tax Effective From Date, Please Check System Configuration', $this->logContext->getMetaData(__CLASS__));
                  return $this;
        }

        if( $effectiveTo && DateTime::createFromFormat('Y-m-d H:i:s', $effectiveTo) < DateTime::createFromFormat('Y-m-d H:i:s', $currentTime))
        {
		  $this->logger->debug('Tax Calculation Occured After the Radial Tax Effective To Date, Please Check System Configuration', $this->logContext->getMetaData(__CLASS__));
                  return $this;
        }

	if( !$enabled )
        {
                $quote->setData('radial_tax_transmit', 0);
                $quote->save();
        }

        if ($coreSession->isTaxUpdateRequired() && $enabled) {
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
		$quote->setData('radial_tax_transmit', 0);
                $quote->save();
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
            $quote->setData('radial_tax_transmit', -1);
            $quote->save();
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

	$enabled = Mage::getStoreConfig('radial_core/radial_tax_core/enabledmod', $order->getStoreId());

        $effectiveFrom = Mage::getStoreConfig('radial_core/radial_tax_core/effectivefrom', $order->getStoreId());
        $effectiveTo = Mage::getStoreConfig('radial_core/radial_tax_core/effectiveto', $order->getStoreId());
        $currentTime = Mage::getModel('core/date')->date('Y-m-d H:i:s');

        if( $effectiveFrom && DateTime::createFromFormat('Y-m-d H:i:s', $effectiveFrom) > DateTime::createFromFormat('Y-m-d H:i:s', $currentTime))
        {
                  $this->logger->debug('Tax Invoice Calculation Occured Before the Radial Tax Effective From Date, Please Check System Configuration', $this->logContext->getMetaData(__CLASS__));
                  return $this;
        }

        if( $effectiveTo && DateTime::createFromFormat('Y-m-d H:i:s', $effectiveTo) < DateTime::createFromFormat('Y-m-d H:i:s', $currentTime))
        {
                  $this->logger->debug('Tax Invoice Calculation Occured After the Radial Tax Effective To Date, Please Check System Configuration', $this->logContext->getMetaData(__CLASS__));
                  return $this;
        }

	if( $enabled )
	{
		$transmitFlag = $invoice->getRadialTaxTransmit();

		if( $transmitFlag != -1 )
		{
			if( $order->getRadialTaxTransmit() == -1 )
			{
				$qty = 0;

                                foreach( $invoice->getAllItems() as $invoiceItem )
                                {
                                	$qty += $invoiceItem->getQty();
                                }

                                if( $qty )
                                {
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
				}
			}
		}
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
    public function processTaxInvoiceForCreditmemo(Varien_Event_Observer $observer)
    {
	$creditmemo = $observer->getEvent()->getCreditmemo();
        $order = $creditmemo->getOrder();
	
	$enabled = Mage::getStoreConfig('radial_core/radial_tax_core/enabledmod', $order->getStoreId());

        $effectiveFrom = Mage::getStoreConfig('radial_core/radial_tax_core/effectivefrom', $order->getStoreId());
        $effectiveTo = Mage::getStoreConfig('radial_core/radial_tax_core/effectiveto', $order->getStoreId());
        $currentTime = Mage::getModel('core/date')->date('Y-m-d H:i:s');

        if( $effectiveFrom && DateTime::createFromFormat('Y-m-d H:i:s', $effectiveFrom) > DateTime::createFromFormat('Y-m-d H:i:s', $currentTime))
        {
                  $this->logger->debug('Tax Invoice Creditmemo - Calculation Occured Before the Radial Tax Effective From Date, Please Check System Configuration', $this->logContext->getMetaData(__CLASS__));
                  return $this;
        }

        if( $effectiveTo && DateTime::createFromFormat('Y-m-d H:i:s', $effectiveTo) < DateTime::createFromFormat('Y-m-d H:i:s', $currentTime))
        {
                  $this->logger->debug('Tax Invoice Creditmemo - Calculation Occured After the Radial Tax Effective To Date, Please Check System Configuration', $this->logContext->getMetaData(__CLASS__));
                  return $this;
        }

	if( $enabled )
	{
		$transmitFlag = $creditmemo->getRadialTaxTransmit();

		if( $transmitFlag != -1 )
		{
			if( $order->getRadialTaxTransmit() == -1 )
			{                        
				$qty = 0;

                                foreach( $creditmemo->getAllItems() as $creditmemoItem )
                                {
                                	$qty += $creditmemoItem->getQty();
                            	}

                                if( $qty )
                                {
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
				}
			}
		}
	}

        return $this;
    }

    /**
     * @param    Varien_Event_Observer
     * @return   self
     */
    public function copyFromQuoteToOrder(Varien_Event_Observer $observer)
    {
	$quote = $observer->getEvent()->getQuote();
	$taxTransmit = $quote->getData('radial_tax_transmit');
	
        $enabled = Mage::getStoreConfig('radial_core/radial_tax_core/enabledmod', $quote->getStoreId());

	$taxFees = unserialize($quote->getData('radial_tax_fees'));
	$taxDuties = unserialize($quote->getData('radial_tax_duties'));
	$taxRecords = unserialize($quote->getData('radial_tax_taxrecords'));
	$taxTransactionId = $quote->getData('radial_tax_transaction_id');

	$orderC = Mage::getModel('sales/order')->getCollection()
                                        ->addFieldToFilter('quote_id', array('eq' => $quote->getId()));

	foreach( $orderC as $order )
	{
		foreach( $order->getAllItems() as $orderItem )
		{
			$product = Mage::getModel('catalog/product')->load($orderItem->getProductId());

			$orderItem->setData('radial_hts_code', $this->helper->getProductHtsCodeByCountry($product, $order->getShippingAddress()->getCountryId()));
			$orderItem->setData('radial_screen_size', $product->getScreenSize());
			$orderItem->setData('radial_manufacturing_country_code', $product->getCountryOfManufacture());
			$orderItem->setData('radial_tax_code', $product->getTaxCode());

			$orderItem->save();
		}

		$order->setData('radial_tax_transmit', $taxTransmit);
		$order->getResource()->saveAttribute($order, 'radial_tax_transmit');

		if( !$enabled )
                {
                        $order->addStatusHistoryComment("Warning: Tax's Not Collected for Order: ". $order->getIncrementId() . " Tax Module is Disabled!");
                        $order->save();
                        continue;
                }

                if( $taxTransmit != -1 )
                {
                        $order->addStatusHistoryComment("Warning: Tax's Not Collected for Order: ". $order->getIncrementId() . " Error with Tax Quotation - Contact Radial Support");
                        $order->save();
                }

		$taxTotal = 0;

		if( count($taxRecords) > 0 )
		{
			foreach( $taxRecords as $taxRecord )
			{
				if( $taxRecord->getCalculatedTax() > 0 )
				{
					$quoteAddress = Mage::getModel('sales/quote_address')->load($taxRecord->getAddressId());
					$orderAddress = $order->getShippingAddress();

					$quoteData = $this->serializeAddress($quoteAddress);
     					$orderData = $this->serializeAddress($orderAddress);

     					if (strcmp($orderData, $quoteData) === 0) 
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
		}

		if( count($taxDuties) > 0 )
		{
			foreach( $taxDuties as $taxDuty )
			{
				if( $taxDuty->getAmount() > 0 )
				{
					$quoteAddress = Mage::getModel('sales/quote_address')->load($taxRecord->getAddressId());
                                        $orderAddress = $order->getShippingAddress();

                                        $quoteData = $this->serializeAddress($quoteAddress);
                                        $orderData = $this->serializeAddress($orderAddress);

                                        if (strcmp($orderData, $quoteData) === 0)
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
		}
	
		if( count($taxFees) > 0 )
		{
			foreach( $taxFees as $taxFee )
        		{
				if( $taxFee->getAmount() > 0 )
				{
					$quoteAddress = Mage::getModel('sales/quote_address')->load($taxRecord->getAddressId());
                                        $orderAddress = $order->getShippingAddress();

                                        $quoteData = $this->serializeAddress($quoteAddress);
                                        $orderData = $this->serializeAddress($orderAddress);

                                        if (strcmp($orderData, $quoteData) === 0)
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

		/* MPTF-281 - Set GW Card Sku / Tax Class to Order Table */
		$order->setData('radial_gw_printed_card_tax_class', Mage::getStoreConfig('radial_core/radial_tax_core/printedcardtaxclass'));
		$order->setData('radial_gw_printed_card_sku', Mage::getStoreConfig('radial_core/radial_tax_core/printedcardsku'));

		$order->getResource()->saveAttribute($order, 'radial_gw_printed_card_tax_class');
		$order->getResource()->saveAttribute($order, 'radial_gw_printed_card_sku');
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

    protected function serializeAddress(Mage_Customer_Model_Address_Abstract $address)  {  
        return serialize(
            array(
                 'firstname' => $address->getFirstname(),
                 'lastname'  => $address->getLastname(),
                 'street'    => $address->getStreet(),
                 'city'      => $address->getCity(),
                 'postcode'  => $address->getPostcode(),
		 'region_id' => $address->getRegionId(),
            )
        );
    }
 
    public function transferRemainingGwPrices(Varien_Event_Observer $observer)
    {
    	$_invoice = $observer->getEvent()->getInvoice();
	$_order = $_invoice->getOrder();
	if ($_invoice->getUpdatedAt() == $_invoice->getCreatedAt()) 
	{
		$subtotalTax = 0;			
		$taxAmt = 0;
	
		$gwItemsP = 0;
		$gwItemsTaxP = 0;
		$gwPrice = 0;
		$gwTax =0;
	
		foreach( $_invoice->getAllItems() as $invoiceItem )
		{
			$itemC = Mage::getModel('sales/order_item')->getCollection()
 	                       ->addFieldToFilter('item_id', array('eq' => $invoiceItem->getOrderItemId()));
	
			if( $itemC->getSize() > 0 )
			{
				//Force the Unit Price Incl Tax
				$item = $itemC->getFirstItem();
				$gwPrice = $item->getGwPrice();
				$gwTax = $item->getGwTaxAmount();
					
				$invoiceQty = $invoiceItem->getQty();
	
				$gwItemsP += $gwPrice * $invoiceQty;
				$gwItemsTaxP += $gwTax * $invoiceQty;
	
				$prev = $item->getPrice() + ($item->getTaxAmount() / $item->getQtyOrdered());
				$invoiceItem->setData('price_incl_tax', $prev);
				$invoiceItem->setData('base_price_incl_tax', $prev);
				$invoiceItem->save();
				
				$subtotalTax += $prev * $invoiceQty;
				$taxAmt = $taxAmt + $invoiceItem->getTaxAmount();
	
				if( $gwPrice && $gwTax )
				{
					$taxAmt = $taxAmt + $gwTax * $invoiceQty;
				}
			}
		}
			
		if( !$_invoice->getGwPrice() )
		{
			$prevGrandtotal = $_invoice->getGrandTotal();
			$prevSubtotalTax = $_invoice->getSubtotalInclTax();
	
			// Somehow GW Tax is Being Added In to Subtotal, so subtract.
			$_invoice->setData('subtotal_incl_tax', $subtotalTax);
			$_invoice->setData('base_subtotal_incl_tax', $subtotalTax);
			$_invoice->setData('tax_amount', $taxAmt);
	
			if( !$gwItemsP)	
			{
				$gwItemsP = 0;
			}
	
			if( !$gwItemsTaxP )
			{
				$gwItemsTaxP = 0;
			}
	
			$_invoice->setData('gw_items_price', $gwItemsP);
            		$_invoice->setData('gw_items_base_price', $gwItemsP);
            		$_invoice->setData('gw_items_base_tax_amount', $gwItemsTaxP);
            		$_invoice->setData('gw_items_tax_amount', $gwItemsTaxP);
			$_invoice->setData('grand_total', $prevGrandtotal);
			$_invoice->setData('base_grand_total', $prevGrandtotal);
	
            		$_invoice->getResource()->saveAttribute($_invoice, 'gw_items_price');
            		$_invoice->getResource()->saveAttribute($_invoice, 'gw_items_base_price');
            		$_invoice->getResource()->saveAttribute($_invoice, 'gw_items_base_tax_amount');
            		$_invoice->getResource()->saveAttribute($_invoice, 'gw_items_tax_amount');
			$_invoice->getResource()->saveAttribute($_invoice, 'grand_total');
			$_invoice->getResource()->saveAttribute($_invoice, 'base_grand_total');
			$_invoice->getResource()->saveAttribute($_invoice, 'subtotal_incl_tax');
			$_invoice->getResource()->saveAttribute($_invoice, 'base_subtotal_incl_tax');
			$_invoice->getResource()->saveAttribute($_invoice, 'tax_amount');
		} else {
			$prevGrandtotal = $_invoice->getGrandTotal();
			$_invoice->setData('base_grand_total', $prevGrandtotal);
			$_invoice->getResource()->saveAttribute($_invoice, 'base_grand_total');
		}
	}
    }
    public function transferRemainingGwPricesCreditMemo(Varien_Event_Observer $observer)
    {
        $_creditmemo = $observer->getEvent()->getCreditmemo();
        $_order = $_creditmemo->getOrder();
        if ($_creditmemo->getUpdatedAt() == $_creditmemo->getCreatedAt())
        {
	    $subtotalTax = 0;
            $taxAmt = 0;
            $gwItemsP = 0;
	    $gwItemsTaxP = 0;
	    $gwPrice = 0;
	    $gwTax = 0;
            
            foreach( $_creditmemo->getAllItems() as $creditmemoItem )
            {
	            $itemC = Mage::getModel('sales/order_item')->getCollection()
	                    ->addFieldToFilter('item_id', array('eq' => $creditmemoItem->getOrderItemId()));
	
	            if( $itemC->getSize() > 0 )
	            {
                    	//Force the Unit Price Incl Tax
                    	$item = $itemC->getFirstItem();
                    	$gwPrice = $item->getGwPrice();
                    	$gwTax = $item->getGwTaxAmount();
                    	if( !$gwPrice )
                    	{
                    	    $gwPrice = 0;
                    	}
                    	if( !$gwTax )
                    	{
                        	$gwTax = 0;
                    	}
                    	$creditmemoQty = $creditmemoItem->getQty();
                    	$gwItemsP = $gwItemsP + $gwPrice * $creditmemoQty;
                    	$gwItemsTaxP = $gwItemsTaxP + $gwTax * $creditmemoQty;
                    	$prev = $item->getPrice() + ($item->getTaxAmount() / $item->getQtyOrdered());
                    	$creditmemoItem->setData('price_incl_tax', $prev);
                    	$creditmemoItem->setData('base_price_incl_tax', $prev);
                    	$creditmemoItem->save();
                    	$subtotalTax += $prev * $creditmemoQty;
                    	$taxAmt = $taxAmt + $creditmemoItem->getTaxAmount();
                    	if( $gwPrice && $gwTax )
                    	{
                    	    $taxAmt = $taxAmt + $gwTax * $creditmemoQty;
                    	}
	            }
            }
	    if( !$_creditmemo->getGwPrice() )
            {
                $prevGrandtotal = $_creditmemo->getGrandTotal();
                $prevSubtotalTax = $_creditmemo->getSubtotalInclTax();
                // Somehow GW Tax is Being Added In to Subtotal, so subtract.
                $_creditmemo->setData('subtotal_incl_tax', $subtotalTax);
                $_creditmemo->setData('base_subtotal_incl_tax', $subtotalTax);
                $_creditmemo->setData('tax_amount', $taxAmt);
                $_creditmemo->setData('gw_items_price', $gwItemsP);
                $_creditmemo->setData('gw_items_base_price', $gwItemsP);
                $_creditmemo->setData('gw_items_base_tax_amount', $gwItemsTaxP);
                $_creditmemo->setData('gw_items_tax_amount', $gwItemsTaxP);
                $_creditmemo->setData('grand_total', $prevGrandtotal);
                $_creditmemo->setData('base_grand_total', $prevGrandtotal);
                $_creditmemo->getResource()->saveAttribute($_creditmemo, 'gw_items_price');
                $_creditmemo->getResource()->saveAttribute($_creditmemo, 'gw_items_base_price');
                $_creditmemo->getResource()->saveAttribute($_creditmemo, 'gw_items_base_tax_amount');
                $_creditmemo->getResource()->saveAttribute($_creditmemo, 'gw_items_tax_amount');
                $_creditmemo->getResource()->saveAttribute($_creditmemo, 'grand_total');
                $_creditmemo->getResource()->saveAttribute($_creditmemo, 'base_grand_total');
                $_creditmemo->getResource()->saveAttribute($_creditmemo, 'subtotal_incl_tax');
                $_creditmemo->getResource()->saveAttribute($_creditmemo, 'base_subtotal_incl_tax');
                $_creditmemo->getResource()->saveAttribute($_creditmemo, 'tax_amount');
            } else {
                $prevGrandtotal = $_creditmemo->getGrandTotal();
                $_creditmemo->setData('base_grand_total', $prevGrandtotal);
                $_creditmemo->getResource()->saveAttribute($_creditmemo, 'base_grand_total');
            }
	}
    }
}
