<?php
/**
 * Magento Enterprise Edition
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Magento Enterprise Edition End User License Agreement
 * that is bundled with this package in the file LICENSE_EE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.magento.com/license/enterprise-edition
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Enterprise
 * @package     Enterprise_GiftWrapping
 * @copyright Copyright (c) 2006-2014 X.commerce, Inc. (http://www.magento.com)
 * @license http://www.magento.com/license/enterprise-edition
 */


/**
 * GiftWrapping total tax calculator for invoice
 *
 */
class Radial_Tax_Model_Total_Invoice_Tax_Giftwrapping extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
   /**
     * Collect gift wrapping tax totals
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @return Enterprise_GiftWrapping_Model_Total_Invoice_Tax_Giftwrapping
     */
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $order = $invoice->getOrder();

		Mage::Log("Collecting Tax Totals For Invoice:");
		Mage::Log("Invoice OBJ: ". print_r($invoice->debug(), true));

        /**
         * Wrapping for items
         */
        $invoiced = 0;
        $baseInvoiced = 0;
		$singleInv = 0;
		$baseSingleInv = 0;
		$qtySingle = false;
		$multiGwItem = false;
		$count = 0;
		$orderGwTax = 0;
	
		foreach ( $order->getAllItems() as $orderItem ) {
			if ($orderItem->getGwId() && $orderItem->getGwBaseTaxAmount()) {
				$count += 1;
			}
		}
		if ( $count > 1 ) {
			//$multiGwItem = true;
		}
			
		Mage::Log("Order Gw Id: ".print_r($order->getGiftMessageId(), true));
        foreach ($invoice->getAllItems() as $invoiceItem) {
		    Mage::Log("Invoice Item: ". print_r($invoiceItem->debug(), true));
	
            if (!$invoiceItem->getQty() || $invoiceItem->getQty() == 0) {
                continue;
            }
            $orderItem = $invoiceItem->getOrderItem();
		
		    Mage::Log("Order Item Info:");
		    Mage::Log("Gw Id: ". $orderItem->getGwId());
		    Mage::Log("Gw Base Tax Amount: ". $orderItem->getGwBaseTaxAmount());
		    Mage::Log("Order Item Gw Base Tax Amount Invoiced: ". $orderItem->getGwBaseTaxAmountInvoiced());
			
            if ($orderItem->getGwId() && $orderItem->getGwBaseTaxAmount()) {
				Mage::Log("Set GW Base Tax Amount Invoiced - ORDER ITEM: ". $orderItem->getGwBaseTaxAmount());
                $orderItem->setGwBaseTaxAmountInvoiced($orderItem->getGwBaseTaxAmount());
	
				Mage::Log("Set GW Tax Amount Invoiced - ORDER ITEM: ". $orderItem->getGwTaxAmount());
                $orderItem->setGwTaxAmountInvoiced($orderItem->getGwTaxAmount());
	
                $baseInvoiced += $orderItem->getGwBaseTaxAmount() * $invoiceItem->getQty();
				Mage::Log("Base Invoiced: ". $baseInvoiced);
	
                $invoiced += $orderItem->getGwTaxAmount() * $invoiceItem->getQty();
				Mage::Log("Invoiced: ". $invoiced);
	
				$singleInv += $orderItem->getGwTaxAmount();
				$baseSingleInv += $orderItem->getGwBaseTaxAmount();
	
				if ($invoiceItem->getQty() == 1) {
					//$qtySingle = true;
				} elseif (count($invoice->getAllItems()) == 1) {
					$order->setGwBaseTaxAmountInvoiced($order->getGwBaseTaxAmount());
					$order->setGwTaxAmountInvoiced($order->getGwTaxAmount());
					$invoice->setGwBaseTaxAmount($order->getGwBaseTaxAmount());
					$invoice->setGwTaxAmount($order->getGwTaxAmount());
					
					//$qtySingle = true;
				}
	    	}
        }
        if ($invoiced > 0 || $baseInvoiced > 0) {
		    //$order->setTaxInvoiced($order->getTaxInvoiced() + $singleInv);
		    //$order->setBaseTaxInvoiced($order->getBaseTaxInvoiced() + $baseSingleInv);
	
		    $newGwItemsBaseInvoice = $order->getGwItemsBaseTaxInvoiced() + $baseInvoiced;
	
		    Mage::Log("Order - Set Gw Items Base Tax Invoiced: ". $newGwItemsBaseInvoice);
	        $order->setGwItemsBaseTaxInvoiced($order->getGwItemsBaseTaxInvoiced() + $baseInvoiced);
	
		    $gwItemsInvoice = $order->getGwItemsTaxInvoiced() + $invoiced;
	
		    Mage::Log("Order - Set Gw Items Tax Invoiced: ". $gwItemsInvoice);
	        $order->setGwItemsTaxInvoiced($order->getGwItemsTaxInvoiced() + $invoiced);
	
		    Mage::Log("Set Gw Items Base Tax Amount: ". $baseInvoiced);
	        $invoice->setGwItemsBaseTaxAmount($baseInvoiced);
	
		    Mage::Log("Set Gw Items Tax Amount: ". $invoiced);
	        $invoice->setGwItemsTaxAmount($invoiced);
        }

        /**
         * Wrapping for order
         */
        if ($order->getGwId() && $order->getGwBaseTaxAmount()
            && $order->getGwBaseTaxAmount() != $order->getGwBaseTaxAmountInvoiced()) {
			Mage::Log("Wrapping for order");
            $order->setGwBaseTaxAmountInvoiced($order->getGwBaseTaxAmount());
            $order->setGwTaxAmountInvoiced($order->getGwTaxAmount());
            $invoice->setGwBaseTaxAmount($order->getGwBaseTaxAmount());
            $invoice->setGwTaxAmount($order->getGwTaxAmount());
        }

        /**
         * Printed card
         */
        if ($order->getGwAddCard() && $order->getGwCardBaseTaxAmount()
            && $order->getGwCardBaseTaxAmount() != $order->getGwCardBaseTaxInvoiced()) {
			Mage::Log("Printed card");
            $order->setGwCardBaseTaxInvoiced($order->getGwCardBaseTaxAmount());
            $order->setGwCardTaxInvoiced($order->getGwCardTaxAmount());
            $invoice->setGwCardBaseTaxAmount($order->getGwCardBaseTaxAmount());
            $invoice->setGwCardTaxAmount($order->getGwCardTaxAmount());
        }

        if (!$invoice->isLast() || $qtySingle || $multiGwItem) {
		    Mage::Log("Invoice has Item Not In Last");

		    $baseTaxAmount = $invoice->getGwItemsBaseTaxAmount();
            $taxAmount = $invoice->getGwItemsTaxAmount();

		    Mage::Log("Gw Items Base Tax Amount - INVOICE: ". $invoice->getGwItemsBaseTaxAmount());
            $baseTaxAmount = $baseTaxAmount
                + $invoice->getGwBaseTaxAmount();
//                + $invoice->getGwCardBaseTaxAmount();
		    Mage::Log("Gw Items + Gw Order + Gw Card Base Tax Amount Total for Last Invoice: ". $baseTaxAmount);

		    Mage::Log("Gw Items Tax Amount - INVOICE: ". $invoice->getGwItemsTaxAmount());
            $taxAmount = $taxAmount
                + $invoice->getGwTaxAmount();
//                + $invoice->getGwCardTaxAmount();
		    Mage::Log("Gw Items + Gw Order + Gw Card Tax Amount Total for Last Invoice: ". $taxAmount);

		    $newBaseTotal = $invoice->getBaseTaxAmount() + $baseTaxAmount;

            Mage::Log("Set Base Tax Amount - INVOICE: ". $newBaseTotal);
            $invoice->setBaseTaxAmount($invoice->getBaseTaxAmount() + $baseTaxAmount);

            $newTotal = $invoice->getTaxAmount() + $taxAmount;

            Mage::Log("Set Tax Amount - INVOICE: ". $newTotal);
            $invoice->setTaxAmount($invoice->getTaxAmount() + $taxAmount);

            $newBaseGTotal = $invoice->getBaseGrandTotal() + $baseTaxAmount;

            Mage::Log("Set Base Grand Total - INVOICE: ". $newBaseGTotal);
            $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseTaxAmount);

            $newGTotal = $invoice->getGrandTotal() + $taxAmount;

            Mage::Log("Set Grand Total - INVOICE: ". $newGTotal);
            $invoice->setGrandTotal($invoice->getGrandTotal() + $taxAmount);
        }

        return $this;
    }
}
