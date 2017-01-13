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

	$dtEffectiveFrom = new DateTime($effectiveFrom);
        $dtEffectiveTo = new DateTime($effectiveTo);
        $dtCurrentTime = new DateTime($currentTime);

        if( $effectiveFrom && $dtEffectiveFrom > $dtCurrentTime ) 
        {
		  $this->logger->debug('Tax Calculation Occured Before the Radial Tax Effective From Date, Please Check System Configuration', $this->logContext->getMetaData(__CLASS__));
                  return $this;
        }

        if( $effectiveTo && $dtEffectiveTo < $dtCurrentTime )
        {
		  $this->logger->debug('Tax Calculation Occured After the Radial Tax Effective To Date, Please Check System Configuration', $this->logContext->getMetaData(__CLASS__));
                  return $this;
        }

	if( !$enabled )
        {
                $quote->setData('radial_tax_transmit', 0);
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

	    foreach( $quote->getAllItems() as $quoteItem )
	    {
		$product = Mage::getModel('catalog/product')->load($quoteItem->getProductId());
		$quoteItem->setData('radial_hts_code', $this->helper->getProductHtsCodeByCountry($product, $quote->getShippingAddress()->getCountryId()));
		$quoteItem->setData('radial_screen_size', $product->getScreenSize());
		$quoteItem->setData('radial_manufacturing_country_code', $product->getCountryOfManufacture());
		$quoteItem->setData('radial_tax_code', $product->getTaxCode());
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
    public function processTaxInvoiceForInvoice(Varien_Event_Observer $observer)
    {
	$invoice = $observer->getEvent()->getInvoice();
	$order = $invoice->getOrder();

	$enabled = Mage::getStoreConfig('radial_core/radial_tax_core/enabledmod', $order->getStoreId());

        $effectiveFrom = Mage::getStoreConfig('radial_core/radial_tax_core/effectivefrom', $order->getStoreId());
        $effectiveTo = Mage::getStoreConfig('radial_core/radial_tax_core/effectiveto', $order->getStoreId());
        $currentTime = Mage::getModel('core/date')->date('Y-m-d H:i:s');

	$dtEffectiveFrom = new DateTime($effectiveFrom);
	$dtEffectiveTo = new DateTime($effectiveTo);
	$dtCurrentTime = new DateTime($currentTime);

        if( $effectiveFrom && $dtEffectiveFrom > $dtCurrentTime)
        {
                  $this->logger->debug('Tax Invoice Calculation Occured Before the Radial Tax Effective From Date, Please Check System Configuration', $this->logContext->getMetaData(__CLASS__));
                  return $this;
        }

        if( $effectiveTo && $dtEffectiveTo < $dtCurrentTime)
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

	$dtEffectiveFrom = new DateTime($effectiveFrom);
        $dtEffectiveTo = new DateTime($effectiveTo);
        $dtCurrentTime = new DateTime($currentTime);

        if( $effectiveFrom && $dtEffectiveFrom > $dtCurrentTime)
        {
                  $this->logger->debug('Tax Invoice Creditmemo - Calculation Occured Before the Radial Tax Effective From Date, Please Check System Configuration', $this->logContext->getMetaData(__CLASS__));
                  return $this;
        }

        if( $effectiveTo && $dtEffectiveTo < $dtCurrentTime)
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

    public function copyTaxAmount( Varien_Event_Observer $observer )
    {
	$order = $observer->getEvent()->getOrder();

        $taxRecords = unserialize($order->getRadialTaxTaxrecords());
	$taxFees = unserialize($order->getRadialTaxFees());
	$taxDuties = unserialize($order->getRadialTaxDuties());
	
	$addressArray = array();

	if( $taxRecords )
	{	
		// Build a list of Quote Addresses
		foreach( $taxRecords as $taxRecord ) 
		{
			$addressArray[] = $taxRecord->getAddressId();
		}
	}

	if( $taxFees )
	{
		foreach( $taxFees as $taxFee )
		{
			$addressArray[] = $taxFee->getAddressId();
		}
	}

	if( $taxDuties )
	{
		foreach( $taxDuties as $taxDuty )
		{
			$addressArray[] = $taxDuty->getAddressId();
		}
	}

	$addressArray = array_values(array_unique($addressArray));
	$taxTotal = false;

        foreach($addressArray as $quoteAddressId )
        {
                $quoteAddress = Mage::getModel('sales/quote_address')->load($quoteAddressId);
                $orderAddress = $order->getShippingAddress();
                $quoteData = $this->serializeAddress($quoteAddress);
                $orderData = $this->serializeAddress($orderAddress);

                if( $quoteAddress->getAddressType() === Mage_Sales_Model_Quote_Address::TYPE_SHIPPING && strcmp($orderData, $quoteData) === 0 )
                {
                	$itemTaxTotal = array();

                        foreach( $taxRecords as $taxRecord )
                        {
                                if( $taxRecord->getAddressId() == $quoteAddressId )
                                {
                                        if ( $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_MERCHANDISE || $taxRecord['tax_source'] === Radial_Tax_Model_Record::SOURCE_MERCHANDISE_DISCOUNT ) {
						if( array_key_exists( $taxRecord->getItemId(), $itemTaxTotal ))
						{
							$itemTaxTotal[$taxRecord->getItemId()] += $taxRecord->getCalculatedTax();
						} else {
							$itemTaxTotal[$taxRecord->getItemId()] = $taxRecord->getCalculatedTax();
						}
                                        }
                                        $taxTotal += $taxRecord->getCalculatedTax();
                                }
                        }

                        foreach( $taxDuties as $taxDuty )
                        {
                                if( $taxDuty->getAddressId() == $quoteAddressId )
                                {
					if( array_key_exists( $taxDuty->getItemId(), $itemTaxTotal))
					{
                                        	$itemTaxTotal[$taxDuty->getItemId()] += $taxDuty->getAmount();
					} else {
						$itemTaxTotal[$taxDuty->getItemId()] = $taxDuty->getAmount();
					}
                                        $taxTotal += $taxDuty->getAmount();
                                }
                        }

                        foreach( $taxFees as $taxFee )
                        {
                                if( $taxFee->getAddressId() == $quoteAddressId )
                                {
					if( array_key_exists( $taxFee->getItemId(), $itemTaxTotal))
					{
                                        	$itemTaxTotal[$taxFee->getItemId()] += $taxFee->getAmount();
					} else {
						$itemTaxTotal[$taxFee->getItemId()] = $taxFee->getAmount();
					}
                                        $taxTotal += $taxFee->getAmount();
                                }
			}

			foreach( $order->getAllItems() as $orderItem )
		        {
				if( array_key_exists( $orderItem->getQuoteItemId(), $itemTaxTotal))
				{
                			$orderItem->setData('tax_amount', $itemTaxTotal[$orderItem->getQuoteItemId()]);
                			$orderItem->setData('base_tax_amount', $itemTaxTotal[$orderItem->getQuoteItemId()]);
                			$orderItem->save();
				}
        	 	}
		}
         }

         $order->setTaxAmount($taxTotal);
	 $order->setBaseTaxAmount($taxTotal);
	 $order->setData('radial_gw_printed_card_tax_class', Mage::getStoreConfig('radial_core/radial_tax_core/printedcardtaxclass'));
         $order->setData('radial_gw_printed_card_sku', Mage::getStoreConfig('radial_core/radial_tax_core/printedcardsku'));

	 $order->getResource()->saveAttribute($order, "tax_amount");
	 $order->getResource()->saveAttribute($order, "base_tax_amount");

	 /* MPTF-281 - Set GW Card Sku / Tax Class to Order Table */
	 $order->getResource()->saveAttribute($order, 'radial_gw_printed_card_tax_class');
	 $order->getResource()->saveAttribute($order, 'radial_gw_printed_card_sku');

         return $this;
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
        if ($_invoice->getUpdatedAt() == $_invoice->getCreatedAt() )
        {
                $taxTotal = false;
		$baseTaxTotal = false;

                foreach( $_invoice->getAllItems() as $invoiceItem )
                {
                        $taxTotal += $invoiceItem->getRowTotalInclTax();
			$baseTaxTotal += $invoiceItem->getBaseRowTotalInclTax();
                }

                $_invoice->setData('subtotal_incl_tax', $taxTotal);
                $_invoice->setData('base_subtotal_incl_tax', $baseTaxTotal);

                $_invoice->getResource()->saveAttribute($_invoice, 'subtotal_incl_tax');
                $_invoice->getResource()->saveAttribute($_invoice, 'base_subtotal_incl_tax');

		if(!$_invoice->isLast())
		{
			$newGrandTotal = false;
			$newBaseGrandTotal = false;
			$newGrandTotal = $taxTotal + $_invoice->getData('shipping_incl_tax') + $_invoice->getData('gift_cards_amount') + $_invoice->getData('hidden_tax_amount') + $_invoice->getData('gw_price') + $_invoice->getData('gw_items_price') + $_invoice->getData('gw_card_price');
			$newBaseGrandTotal = $baseTaxTotal + $_invoice->getData('base_shipping_incl_tax') + $_invoice->getData('base_gift_cards_amount') + $_invoice->getData('base_hidden_tax_amount') + $_invoice->getData('base_gw_price') + $_invoice->getData('base_gw_items_price') + $_invoice->getData('base_gw_card_price');

			$_invoice->getResource()->saveAttribute($_invoice, 'grand_total', $newGrandTotal);
			$_invoice->getResource()->saveAttribute($_invoice, 'base_grand_total', $newBaseGrandTotal);
		}
        }

        return $this;
    } 

    public function blockOrderNoTax(Varien_Event_Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();
        $acceptnotax = Mage::getStoreConfig('radial_core/radial_tax_core/acceptnotax', $quote->getStoreId());

        if( !$acceptnotax )
        {
                $enabled = Mage::getStoreConfig('radial_core/radial_tax_core/enabledmod', $quote->getStoreId());
                $effectiveFrom = Mage::getStoreConfig('radial_core/radial_tax_core/effectivefrom', $quote->getStoreId());
                $effectiveTo = Mage::getStoreConfig('radial_core/radial_tax_core/effectiveto', $quote->getStoreId());
                $taxErrorMessage = Mage::getStoreConfig('radial_core/radial_tax_core/notaxcalcerror', $quote->getStoreId());
                $currentTime = Mage::getModel('core/date')->date('Y-m-d H:i:s');
                $dtEffectiveFrom = new DateTime($effectiveFrom);
                $dtEffectiveTo = new DateTime($effectiveTo);
                $dtCurrentTime = new DateTime($currentTime);

                if( !$enabled )
                {
                        Mage::getSingleton('checkout/session')->addError($taxErrorMessage);
                        throw Mage::exception('Radial_Tax', Mage::helper('core')->__($taxErrorMessage));
                }

                if( $effectiveFrom && $dtEffectiveFrom > $dtCurrentTime)
                {
                        Mage::getSingleton('checkout/session')->addError($taxErrorMessage);
                        throw Mage::exception('Radial_Tax', Mage::helper('core')->__($taxErrorMessage));
                }

                if( $effectiveTo && $dtEffectiveTo < $dtCurrentTime)
                {
                        Mage::getSingleton('checkout/session')->addError($taxErrorMessage);
                        throw Mage::exception('Radial_Tax', Mage::helper('core')->__($taxErrorMessage));
                }

                $taxRecords = unserialize($quote->getData('radial_tax_taxrecords'));

                if( !$taxRecords )
                {
                        Mage::getSingleton('checkout/session')->addError($taxErrorMessage);
                        throw Mage::exception('Radial_Tax', Mage::helper('core')->__($taxErrorMessage));
                }
        }

        return $this;
    }

    public function getSalesOrderViewInfo(Varien_Event_Observer $observer) {
        $block = $observer->getBlock();
        if (($block->getNameInLayout() == 'order_info') && ($child = $block->getChild('radial.tax.order.info.displaytaxerror'))) {
            $transport = $observer->getTransport();
            if ($transport) {
        	$order = false;
        	$orderTransmit = false;

        	$order = $observer->getEvent()->getBlock()->getOrder();
        	$orderTransmit = $order->getData('radial_tax_transmit');

		$enabled = Mage::getStoreConfig('radial_core/radial_tax_core/enabledmod', $order->getStoreId());
                $effectiveFrom = Mage::getStoreConfig('radial_core/radial_tax_core/effectivefrom', $order->getStoreId());
                $effectiveTo = Mage::getStoreConfig('radial_core/radial_tax_core/effectiveto', $order->getStoreId());
                $orderCreateTime = $order->getCreatedAtStoreDate();

                $dtEffectiveFrom = new DateTime($effectiveFrom);
                $dtEffictiveTo = new DateTime($effectiveTo);
                $dtOrderCreateTime = new DateTime($orderCreateTime);

        	if( $orderTransmit && $orderTransmit != -1 )
        	{
                	$taxRecords = unserialize($order->getData('radial_tax_taxrecords'));

                	if( $effectiveFrom && $dtEffectiveFrom > $dtOrderCreateTime)
                	{
                		$html = $transport->getHtml();
                		$html .= $child->toHtml();
                		$transport->setHtml($html);
			}

                	if( $effectiveTo && $dtEffectiveTo < $dtOrderCreateTime)
                	{
                		$html = $transport->getHtml();
                		$html .= $child->toHtml();
                		$transport->setHtml($html);
			}
	
	                if( !$enabled )
	                {
        	        	$html = $transport->getHtml();
                		$html .= $child->toHtml();
                		$transport->setHtml($html);
			}
	
        	        if( !$taxRecords )
        	        {
        	        	$html = $transport->getHtml();
                		$html .= $child->toHtml();
                		$transport->setHtml($html);
			}
        	}

        	if( !$orderTransmit )
        	{
        		$html = $transport->getHtml();
                	$html .= $child->toHtml();
                	$transport->setHtml($html);
		}
            }
        }
    }
}
