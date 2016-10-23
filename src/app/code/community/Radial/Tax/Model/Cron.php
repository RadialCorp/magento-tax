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

class Radial_Tax_Model_Cron
{
    /** @var bool Lock to guard against too much recursion in quote collect totals */
    protected static $lockRecollectTotals = false;
    /** @var Radial_Tax_Model_Collector */
    protected $taxCollector;
    /** @var Radial_Core_Model_Session */
    protected $coreSession;
    /** @var Radial_Tax_Helper_Data */
    protected $helper;
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
     * Collect new tax totals if necessary before submitting an order.
     * Tax totals collected after all other quote totals so tax totals for the
     * entire quote may be collected at one - all other totals for all other
     * addresses must have already been collected.
     *
     * If new taxes are collected, all quote totals must be recollected.
     *
     * @return self
     */
    public function cronOrderTaxQuoteRetry()
    {
        $maxretries = Mage::getStoreConfig('radial_core/radial_tax_core/maxretries');
        $collection= Mage::getResourceModel('sales/order_collection')
                        ->addFieldToFilter('radial_tax_transmit', array('lt' => $maxretries))
                        ->addFieldToFilter('radial_tax_transmit', array('neq' => -1))
                        ->setPageSize(100);

        $pages = $collection->getLastPageNumber();
        $currentPage = 1;

        do
        {
                $collection->setCurPage($currentPage);
                $collection->load();

                foreach( $collection as $order )
                {
			$enabled = Mage::getStoreConfig('radial_core/radial_tax_core/enabledmod', $order->getStoreId());

			if( !$enabled)
			{
				continue;
			}

			try
			{
				$result = $this->taxCollector->collectTaxesForOrder($order);

				if( $result === -1 )
                        	{
                                	$comment = "Tax Quotation on Order Retry Successful For - Order: ". $order->getIncrementId();
                                	//Mark the invoice comments as sent.
                                	$history = Mage::getModel('sales/order_status_history')
                                	        ->setStatus($order->getStatus())
                                	        ->setComment($comment)
                                	        ->setEntityName('order');
                                	$order->addStatusHistory($history);
                                	$order->save();

					$this->updateOrderTotals($order);

        				if( $order->getTotalDue() > 0 && $order->getInvoiceCollection()->getSize() > 0 )
        				{
						$invoiceTaxTotal = 0;
						$invoiceCol = $order->getInvoiceCollection()->addAttributeToSort('increment_id', 'ASC');

						foreach( $order->getInvoiceCollection() as $invoice )
						{
							if( $invoice->getData('radial_tax_transmit') !== -1 )
							{
								if( strcmp($invoiceCol->getFirstItem()->getIncrementId(), $invoice->getIncrementId()) === 0 )
                                                        	{
									if( $invoice->getShippingAmount() )
									{
										$invoiceTaxTotal += $order->getShippingTaxAmount();
									}

									if( $invoice->getGwPrice() )
									{
										$invoiceTaxTotal += $order->getGwTaxAmount();
									}

									if( $invoice->getGwCardPrice() )
									{
										$invoiceTaxTotal += $order->getGwCardTaxAmount();
									}
								}

								foreach( $invoice->getAllItems() as $invoiceItem )
								{
									$itemC = Mage::getModel('sales/order_item')->getCollection()
                                                				->addFieldToFilter('item_id', array('eq' => $invoiceItem->getOrderItemId()))
                                                				->addFieldToFilter('order_id', array('eq' => $order->getId()));

									if( $itemC->getSize() > 0 )
									{
										$item = $itemC->getFirstItem();
										$invoiceTaxTotal += ($item->getTaxAmount() / $item->getQtyOrdered()) * $invoiceItem->getQty();
                                						$gwTax = $item->getGwTaxAmount();
										$invoiceTaxTotal += $gwTax * $invoiceItem->getQty();
									}
								}

								$invoice->setData('radial_tax_transmit', 0);
								$invoice->getResource()->saveAttribute($invoice, 'radial_tax_transmit');
							}
						}

						if( $invoiceTaxTotal > 0 )
						{
							$orderItemAray = array();

                					foreach( $order->getAllItems() as $orderItem )
                					{
                        					$orderItemArray[$orderItem->getId()] = 0;
                					}

                					/** @var Mage_Sales_Model_Service_Order $orderService */
                					$orderService = Mage::getModel('sales/service_order', $order);
                					$invoice = $orderService->prepareInvoice($orderItemArray);

                					$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::NOT_CAPTURE);
							$invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);

							$invoice->setTaxAmount($invoiceTaxTotal);
							$invoice->setBaseTaxAmount($invoiceTaxTotal);
							$invoice->setGrandTotal($invoiceTaxTotal);
							$invoice->setBaseGrandTotal($invoiceTaxTotal);
							$invoice->setRadialTaxTransmit(-1);
                					$invoice->register()->capture();
						}
        				}

					$creditmemoCol = Mage::getResourceModel('sales/order_creditmemo_collection')->addAttributeToSort('increment_id', 'ASC')
                                                ->addAttributeToFilter('order_id', $order->getId());

					// If there are credit memo's without a tax transmission
					if( $creditmemoCol->getSize() > 0 )
                                        {
                                                $creditmemoTaxTotal = 0;
						$creditmemoCol = Mage::getResourceModel('sales/order_creditmemo_collection')->addAttributeToSort('increment_id', 'ASC'); 

                                                foreach( $creditmemoCol as $creditmemo )
                                                {
                                                        if( $creditmemo->getData('radial_tax_transmit') !== -1 )
                                                        {
								if( strcmp($creditmemoCol->getFirstItem()->getIncrementId(), $creditmemo->getIncrementId()) === 0 )
								{
                                                                	if( $creditmemo->getShippingAmount() )
                                                                	{
                                                                        	$creditmemoTaxTotal += $order->getShippingTaxAmount();
                                                                	}

                                                                	if( $creditmemo->getGwPrice() )
                                                                	{
                                                                        	$creditmemoTaxTotal += $order->getGwTaxAmount();
                                                                	}

									if( $creditmemo->getGwCardPrice() )
                                                                	{
                                                                        	$invoiceTaxTotal += $order->getGwCardTaxAmount();
                                                                	}
								}

                                                                foreach( $creditmemo->getAllItems() as $creditmemoItem )
                                                                {
                                                                        $itemC = Mage::getModel('sales/order_item')->getCollection()
                                                                                ->addFieldToFilter('item_id', array('eq' => $creditmemoItem->getOrderItemId()))
                                                                                ->addFieldToFilter('order_id', array('eq' => $order->getId()));

                                                                        if( $itemC->getSize() > 0 )
                                                                        {
                                                                                $item = $itemC->getFirstItem();
                                                                                $creditmemoTaxTotal += ($item->getTaxAmount() / $item->getQtyOrdered()) * $creditmemoItem->getQty();
                                                                                $gwTax = $item->getGwTaxAmount();
                                                                                $creditmemoTaxTotal += $gwTax * $creditmemoItem->getQty();
                                                                        }
                                                                }

								$creditmemo->setData('radial_tax_transmit', 0);
                                                                $creditmemo->getResource()->saveAttribute($creditmemo, 'radial_tax_transmit');
                                                        }
                                                }

						if( $creditmemoTaxTotal > 0 )
                                                {
                                                	/** @var Mage_Sales_Model_Service_Order $orderService */
                                                	$orderService = Mage::getModel('sales/service_order', $order);
                                                	$creditmemo = $orderService->prepareInvoiceCreditmemo($invoice);

							$creditmemo->setTaxAmount($creditmemoTaxTotal);
                                                	$creditmemo->setBaseTaxAmount($creditmemoTaxTotal);
                                                	$creditmemo->setGrandTotal($creditmemoTaxTotal);
                                                	$creditmemo->setBaseGrandTotal($creditmemoTaxTotal);

                                                	$creditmemo->setRequestedCaptureCase(Mage_Sales_Model_Order_Creditmemo::STATE_OPEN);
							$creditmemo->setRadialTaxTransmit(-1);
                                                	$creditmemo->register();
						}
					}
                        	}
			} catch (Radial_Tax_Exception_Collector_InvalidInvoice_Exception $e) {
                            $this->logger->debug('Tax Quote is not valid.', $this->logContext->getMetaData(__CLASS__));
                            throw $e;
                        } catch (Radial_Tax_Exception_Collector_Exception $e) {
                            // Want TDF to be non-blocking so exceptions from making the
                            // request should be caught. Still need to exit here when there
                            // is an exception, however, to allow the TDF to be retried
                            // (don't reset update required flag) and prevent totals from being
                            // recollected (nothing to update and, more imporantly, would
                            // continue to loop until PHP crashes or a TDF request succeeds).

                            $retry = $order->getRadialTaxTransmit();
                            $retryN = $retry + 1;
                            $order->setRadialTaxTransmit($retryN);
                            $order->save();

                            $this->logger->warning('Tax request failed.', $this->logContext->getMetaData(__CLASS__, [], $e));

                            $taxEmailProp = Mage::getStoreConfig('radial_core/radial_tax_core/tax_email');
                            if( $taxEmailProp )
                            {
                                $taxEmailA = explode(',', $taxEmailProp );
                                foreach( $taxEmailA as $taxEmail )
                                {
                                        $taxName = Mage::app()->getStore()->getName() . ' - ' . 'Tax Admin';
                                        $emailTemplate  = Mage::getModel('core/email_template')->loadDefault('custom_email_template3');

                                        //Create an array of variables to assign to template
                                        $emailTemplateVariables = array();
                                        $emailTemplateVariables['myvar1'] = gmdate("Y-m-d\TH:i:s\Z");
                                        $emailTemplateVariables['myvar2'] = $e->getMessage();
                                        $emailTemplateVariables['myvar3'] = $e->getTraceAsString();
                                        $emailTemplateVariables['myvar4'] = htmlspecialchars($requestBody);

                                        $processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables);
                                        //Sending E-Mail to Tax Admin Email.
                                        $mail = Mage::getModel('core/email')
                                                ->setToName($taxName)
                                                ->setToEmail($taxEmail)
                                                ->setBody($processedTemplate)
                                                ->setSubject('Tax - Invoice - Exception Report From: '. __CLASS__ . ' on ' . gmdate("Y-m-d\TH:i:s\Z") . ' UTC')
                                                ->setFromEmail(Mage::getStoreConfig('trans_email/ident_general/email'))
                                                ->setFromName($taxName)
                                                ->setType('html');
                                        try
                                        {
                                                //Confimation E-Mail Send
                                                $mail->send();
                                        }
                                        catch(Exception $error)
                                        {
                                                $logMessage = sprintf('[%s] Error Sending Email: %s', __CLASS__, $error->getMessage());
                                                Mage::log($logMessage, Zend_Log::ERR);
                                        }
                                 }
                            }

			    return $this;
                        }
                }

                $currentPage++;
                $collection->clear();
        } while ($currentPage <= $pages);

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
     * @return self
     */
    public function cronTaxInvoiceForInvoice()
    {
	$maxretries = Mage::getStoreConfig('radial_core/radial_tax_core/maxretries');
	$collection= Mage::getResourceModel('sales/order_invoice_collection')
			->addFieldToFilter('radial_tax_transmit', array('lt' => $maxretries))
			->addFieldToFilter('radial_tax_transmit', array('neq' => -1))
			->setPageSize(100);

	$pages = $collection->getLastPageNumber();
	$currentPage = 1;

	do
	{
		$collection->setCurPage($currentPage);
                $collection->load();

		foreach( $collection as $invoice )
		{
        		$order = $invoice->getOrder();
        		$type = "SALE";

			$enabled = Mage::getStoreConfig('radial_core/radial_tax_core/enabledmod', $order->getStoreId());

                        if( !$enabled)
                        {
                                continue;
                        }

			if( $order->getData('radial_tax_transmit') != -1 ) 
                        {
                                $comment = "Tax Invoice: ". $invoice->getIncrementId() . " Not Submitted - Order: ". $order->getIncrementId() . " Has No Tax Quotation";
                                //Mark the invoice comments as sent.
                                $history = Mage::getModel('sales/order_status_history')
                                        ->setStatus($order->getStatus())
                                        ->setComment($comment)
                                        ->setEntityName('order');
                                $order->addStatusHistory($history);
                                $order->save();

                                $invoice->addComment($comment, false, true);
                                $invoice->save();

				continue;
                        }

        		//Try the invoice
        		try {
			    $qty = 0;

			    foreach( $invoice->getAllItems() as $invoiceItem )
			    {
				$qty += $invoiceItem->getQty();
			    }

			    if( !$qty )
			    {
				$comment = "Tax Invoice: ". $invoice->getIncrementId() . " not submitted, need atleast 1 item";
				//Mark the invoice comments as sent.
                                $history = Mage::getModel('sales/order_status_history')
                                        ->setStatus($order->getStatus())
                                        ->setComment($comment)
                                        ->setEntityName('order');
                                $order->addStatusHistory($history);
                                $order->save();

				$invoice->addComment($comment, false, true);
                                $invoice->save();

                                continue;
			    }

        		    $requestBody = $this->taxCollector->collectTaxesForInvoice($order, $invoice, $type);
			    $comment = "Tax Invoice Successfully Submitted for Invoice: ". $invoice->getIncrementId();

			    //Mark the invoice comments as sent.
			    $history = Mage::getModel('sales/order_status_history')
            				->setStatus($order->getStatus())
            				->setComment($comment)
            				->setEntityName('order');
        		    $order->addStatusHistory($history);
			    $order->save();

			    $invoice->setData('radial_tax_transmit', -1);
			    $invoice->addComment($comment, false, true);
			    $invoice->save();
        		} catch (Radial_Tax_Exception_Collector_InvalidInvoice_Exception $e) {
        		    $this->logger->debug('Tax Invoice is not valid.', $this->logContext->getMetaData(__CLASS__));
        		    throw $e;
        		} catch (Radial_Tax_Exception_Collector_Exception $e) {
        		    // Want TDF to be non-blocking so exceptions from making the
        		    // request should be caught. Still need to exit here when there
        		    // is an exception, however, to allow the TDF to be retried
        		    // (don't reset update required flag) and prevent totals from being
        		    // recollected (nothing to update and, more imporantly, would
        		    // continue to loop until PHP crashes or a TDF request succeeds).

			    $retry = $invoice->getRadialTaxTransmit();
		            $retryN = $retry + 1;
       			    $invoice->setRadialTaxTransmit($retryN);
            		    $invoice->save();

        		    $this->logger->warning('Tax request failed.', $this->logContext->getMetaData(__CLASS__, [], $e));

			    $taxEmailProp = Mage::getStoreConfig('radial_core/radial_tax_core/tax_email');
            		    if( $taxEmailProp )
            		    {
				$taxEmailA = explode(',', $taxEmailProp );
                		foreach( $taxEmailA as $taxEmail )
                		{
                        		$taxName = Mage::app()->getStore()->getName() . ' - ' . 'Tax Admin';
                        		$emailTemplate  = Mage::getModel('core/email_template')->loadDefault('custom_email_template3');

                        		//Create an array of variables to assign to template
                        		$emailTemplateVariables = array();
                        		$emailTemplateVariables['myvar1'] = gmdate("Y-m-d\TH:i:s\Z");
                        		$emailTemplateVariables['myvar2'] = $e->getMessage();
                        		$emailTemplateVariables['myvar3'] = $e->getTraceAsString();
                        		$emailTemplateVariables['myvar4'] = htmlspecialchars($requestBody);

                        		$processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables);
                        		//Sending E-Mail to Tax Admin Email.
                        		$mail = Mage::getModel('core/email')
                                		->setToName($taxName)
                                		->setToEmail($taxEmail)
                                		->setBody($processedTemplate)
                                		->setSubject('Tax - Invoice - Exception Report From: '. __CLASS__ . ' on ' . gmdate("Y-m-d\TH:i:s\Z") . ' UTC')
                                		->setFromEmail(Mage::getStoreConfig('trans_email/ident_general/email'))
                                		->setFromName($taxName)
                                		->setType('html');
                        		try
                        		{
                                		//Confimation E-Mail Send
                                		$mail->send();
                        		}
                        		catch(Exception $error)
                        		{
                                		$logMessage = sprintf('[%s] Error Sending Email: %s', __CLASS__, $error->getMessage());
                                		Mage::log($logMessage, Zend_Log::ERR);
                        		}
                 		 }
            		    }

        		    return $this;
        		}
		}

		$currentPage++;
                $collection->clear();
	} while ($currentPage <= $pages);

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
     * @return self
     */
    public function cronTaxInvoiceForCreditmemo()
    {
	$maxretries = Mage::getStoreConfig('radial_core/radial_tax_core/maxretries');
	$collection= Mage::getResourceModel('sales/order_creditmemo_collection')
                        ->addFieldToFilter('radial_tax_transmit', array('lt' => $maxretries))
			->addFieldToFilter('radial_tax_transmit', array('neq' => -1))
                        ->setPageSize(100);

        $pages = $collection->getLastPageNumber();
        $currentPage = 1;

        do
        {
                $collection->setCurPage($currentPage);
                $collection->load();

                foreach( $collection as $creditmemo )
                {
                        $order = $creditmemo->getOrder();
                        $type = "RETURN";

			$enabled = Mage::getStoreConfig('radial_core/radial_tax_core/enabledmod', $order->getStoreId());

                        if( !$enabled)
                        {
                                continue;
                        }

			if( $order->getData('radial_tax_transmit') != -1 ) 
			{
				$comment = "Tax Invoice For Creditmemo: ". $creditmemo->getIncrementId() . " Not Submitted - Order: ". $order->getIncrementId() . " Has No Tax Quotation";
				//Mark the invoice comments as sent.
                            	$history = Mage::getModel('sales/order_status_history')
                                        ->setStatus($order->getStatus())
                                        ->setComment($comment)
                                        ->setEntityName('order');
                            	$order->addStatusHistory($history);
                            	$order->save();

				$creditmemo->addComment($comment, false, true);
                                $creditmemo->save();

				continue;
			}

                        //Try the Creditmemo Tax Invoice
                        try {
			    $qty = 0;

                            foreach( $creditmemo->getAllItems() as $creditmemoItem )
                            {
                                $qty += $creditmemoItem->getQty();
                            }

                            if( !$qty )
                            {
                            	$requestBody = $this->taxCollector->collectTaxesForInvoice($order, $creditmemo, $type);

			    	$comment = "Tax Invoice Successfully Submitted for Creditmemo: ". $creditmemo->getIncrementId();

			    	//Mark the invoice comments as sent.
                            	$history = Mage::getModel('sales/order_status_history')
                                        ->setStatus($order->getStatus())
                                        ->setComment($comment)
                                        ->setEntityName('order');
                            	$order->addStatusHistory($history);
			    	$order->save();

			    	$creditmemo->addComment($comment, false, true);
			    	$creditmemo->setData('radial_tax_transmit', -1);
			    	$creditmemo->save();
			     }
                        } catch (Radial_Tax_Exception_Collector_InvalidInvoice_Exception $e) {
                            $this->logger->debug('Tax Invoice is not valid.', $this->logContext->getMetaData(__CLASS__));
                            throw $e;
                        } catch (Radial_Tax_Exception_Collector_Exception $e) {
                            // Want TDF to be non-blocking so exceptions from making the
                            // request should be caught. Still need to exit here when there
                            // is an exception, however, to allow the TDF to be retried
                            // (don't reset update required flag) and prevent totals from being
                            // recollected (nothing to update and, more imporantly, would
                            // continue to loop until PHP crashes or a TDF request succeeds).

                            $retry = $creditmemo->getRadialTaxTransmit();
                            $retryN = $retry + 1;
                            $creditmemo->setRadialTaxTransmit($retryN);
                            $creditmemo->save();

                            $this->logger->warning('Tax request failed.', $this->logContext->getMetaData(__CLASS__, [], $e));

			    $taxEmailProperty = Mage::getStoreConfig('radial_core/radial_tax_core/tax_email');
                            if( $taxEmailProperty )
                            {
				$taxEmailA = explode(',', $taxEmailProperty);
                                foreach( $taxEmailA as $taxEmail )
                                {
                                        $taxName = Mage::app()->getStore()->getName() . ' - ' . 'Tax Admin';
                                        $emailTemplate  = Mage::getModel('core/email_template')->loadDefault('custom_email_template3');

                                        //Create an array of variables to assign to template
                                        $emailTemplateVariables = array();
                                        $emailTemplateVariables['myvar1'] = gmdate("Y-m-d\TH:i:s\Z");
                                        $emailTemplateVariables['myvar2'] = $e->getMessage();
                                        $emailTemplateVariables['myvar3'] = $e->getTraceAsString();
                                        $emailTemplateVariables['myvar4'] = htmlspecialchars($requestBody);

                                        $processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables);
                                        //Sending E-Mail to Tax Admin Email.
                                        $mail = Mage::getModel('core/email')
                                                ->setToName($taxName)
                                                ->setToEmail($taxEmail)
                                                ->setBody($processedTemplate)
                                                ->setSubject('Tax - Invoice Creditmemo - Exception Report From: '. __CLASS__ . ' on ' . gmdate("Y-m-d\TH:i:s\Z") . ' UTC')
                                                ->setFromEmail(Mage::getStoreConfig('trans_email/ident_general/email'))
                                                ->setFromName($taxName)
                                                ->setType('html');
                                        try
                                        {
                                                //Confimation E-Mail Send
                                                $mail->send();
                                        }
                                        catch(Exception $error)
                                        {
                                                $logMessage = sprintf('[%s] Error Sending Email: %s', __CLASS__, $error->getMessage());
                                                Mage::log($logMessage, Zend_Log::ERR);
                                        }
                                 }
                            }

                            return $this;
                        }
                }

                $currentPage++;
                $collection->clear();
        } while ($currentPage <= $pages);

        return $this;
    }

    /**
     * @param    Mage_Sales_Model_Order
     * @return   self
     */
    public function updateOrderTotals(Mage_Sales_Model_Order $order)
    {
	$taxFees = unserialize($order->getData('radial_tax_fees'));
	$taxDuties = unserialize($order->getData('radial_tax_duties'));
	$taxRecords = unserialize($order->getData('radial_tax_taxrecords'));

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
   						->addFieldToFilter('item_id', array('eq' => $taxRecord->getItemId()))
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
                        	        ->addFieldToFilter('item_id', array('eq' => $taxRecord->getItemId()))
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
                        		        ->addFieldToFilter('item_id', array('eq' => $taxRecord->getItemId()))
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

	$newTotal = $order->getData('grand_total') + $taxTotal;
	$newDue = $order->getData('total_due') + $taxTotal;

	$order->setData('tax_amount', $taxTotal);
	$order->setData('base_grand_total', $newTotal);
	$order->setData('grand_total', $newTotal);
	$order->setData('total_due', $newDue);
	$order->setData('base_total_due', $newDue);
        $order->getResource()->saveAttribute($order, 'tax_amount');
	$order->getResource()->saveAttribute($order, 'base_grand_total');
	$order->getResource()->saveAttribute($order, 'grand_total');
	$order->getResource()->saveAttribute($order, 'total_due');
	$order->getResource()->saveAttribute($order, 'base_total_due');	

	return $this;
    }
}
