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
        $enabled = $this->helper->getConfigModel()->enabled;

        if(!$enabled)
        {
                return $this;
        }

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
			try
			{
				$requestBody = $this->taxCollector->collectTaxesForOrder($order);

				if( $order->getData('radial_tax_transmit') != -1 )
                        	{
                                	$comment = "Tax Quotation on Order Retry Successful For - Order: ". $order->getIncrementId() . ";
                                	//Mark the invoice comments as sent.
                                	$history = Mage::getModel('sales/order_status_history')
                                	        ->setStatus($order->getStatus())
                                	        ->setComment($comment)
                                	        ->setEntityName('order');
                                	$order->addStatusHistory($history);
                                	$order->save();
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
	$enabled = $this->helper->getConfigModel()->enabled;

        if(!$enabled)
        {
                return $this;
        }

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

			if( $order->getData('radial_tax_transmit') != -1 ) 
                        {
                                $comment = "Tax Invoice: ". $invoice->getIncrementId . " Not Submitted - Order: ". $order->getIncrementId() . " Has No Tax Quotation";
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

        		//Try the invoice
        		try {
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
	$enabled = $this->helper->getConfigModel()->enabled;

        if(!$enabled)
        {
                return $this;
        }

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

			if( $order->getData('radial_tax_transmit') != -1 ) 
			{
				$comment = "Tax Invoice For Creditmemo: ". $creditmemo->getIncrementId . " Not Submitted - Order: ". $order->getIncrementId() . " Has No Tax Quotation";
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

                        //Try the invoice
                        try {
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
}
