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
 * GiftWrapping total tax calculator for creditmemo
 *
 */
class Radial_Tax_Model_Total_Creditmemo_Tax_Giftwrapping extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
   /**
     * Collect gift wrapping tax totals
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @return Enterprise_GiftWrapping_Model_Total_Creditmemo_Tax_Giftwrapping
     */
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();

        /**
         * Wrapping for items
         */
        $creditmemod = 0;
        $baseRefunded = 0;
	$singleRef = 0;
	$baseSingleRef = 0;
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
			
        foreach ($creditmemo->getAllItems() as $creditmemoItem) {
            if (!$creditmemoItem->getQty() || $creditmemoItem->getQty() == 0) {
                continue;
            }
            $orderItem = $creditmemoItem->getOrderItem();
		
            if ($orderItem->getGwId() && $orderItem->getGwBaseTaxAmount()) {
                $orderItem->setGwBaseTaxAmountInvoiced($orderItem->getGwBaseTaxAmount());
                $orderItem->setGwTaxAmountInvoiced($orderItem->getGwTaxAmount());
	
                $baseRefunded += $orderItem->getGwBaseTaxAmount() * $creditmemoItem->getQty();
                $creditmemod += $orderItem->getGwTaxAmount() * $creditmemoItem->getQty();
		$singleRef += $orderItem->getGwTaxAmount();
		$baseSingleRef += $orderItem->getGwBaseTaxAmount();
	
		if ($creditmemoItem->getQty() == 1) {
			//$qtySingle = true;
		} elseif (count($creditmemo->getAllItems()) == 1) {
			$order->setGwBaseTaxAmountInvoiced($order->getGwBaseTaxAmount());
			$order->setGwTaxAmountInvoiced($order->getGwTaxAmount());
			$creditmemo->setGwBaseTaxAmount($order->getGwBaseTaxAmount());
			$creditmemo->setGwTaxAmount($order->getGwTaxAmount());
					
			//$qtySingle = true;
		}
	    }
        }
        if ($creditmemod > 0 || $baseRefunded > 0) {
		$newGwItemsBaseInvoice = $order->getGwItemsBaseTaxInvoiced() + $baseRefunded;
	        $order->setGwItemsBaseTaxInvoiced($order->getGwItemsBaseTaxInvoiced() + $baseRefunded);
		$gwItemsInvoice = $order->getGwItemsTaxInvoiced() + $creditmemod;
	        $order->setGwItemsTaxInvoiced($order->getGwItemsTaxInvoiced() + $creditmemod);
	        $creditmemo->setGwItemsBaseTaxAmount($baseRefunded);
	        $creditmemo->setGwItemsTaxAmount($creditmemod);
        }

        /**
         * Wrapping for order
         */
        if ($order->getGwId() && $order->getGwBaseTaxAmount()
            && $order->getGwBaseTaxAmount() != $order->getGwBaseTaxAmountInvoiced()) {
            $order->setGwBaseTaxAmountInvoiced($order->getGwBaseTaxAmount());
            $order->setGwTaxAmountInvoiced($order->getGwTaxAmount());
            $creditmemo->setGwBaseTaxAmount($order->getGwBaseTaxAmount());
            $creditmemo->setGwTaxAmount($order->getGwTaxAmount());
        }

        /**
         * Printed card
         */
        if ($order->getGwAddCard() && $order->getGwCardBaseTaxAmount()
            && $order->getGwCardBaseTaxAmount() != $order->getGwCardBaseTaxInvoiced()) {
            $order->setGwCardBaseTaxInvoiced($order->getGwCardBaseTaxAmount());
            $order->setGwCardTaxInvoiced($order->getGwCardTaxAmount());
            $creditmemo->setGwCardBaseTaxAmount($order->getGwCardBaseTaxAmount());
            $creditmemo->setGwCardTaxAmount($order->getGwCardTaxAmount());
        }

	$baseTaxAmount = $creditmemo->getGwItemsBaseTaxAmount();
        $taxAmount = $creditmemo->getGwItemsTaxAmount();

        $baseTaxAmount = $baseTaxAmount
            + $creditmemo->getGwBaseTaxAmount();
//          + $creditmemo->getGwCardBaseTaxAmount();
        $taxAmount = $taxAmount
            + $creditmemo->getGwTaxAmount();
//          + $creditmemo->getGwCardTaxAmount();

	$newBaseTotal = $creditmemo->getBaseTaxAmount() + $baseTaxAmount;
        $creditmemo->setBaseTaxAmount($creditmemo->getBaseTaxAmount() + $baseTaxAmount);

        $newTotal = $creditmemo->getTaxAmount() + $taxAmount;
        $creditmemo->setTaxAmount($creditmemo->getTaxAmount() + $taxAmount);
        $newBaseGTotal = $creditmemo->getBaseGrandTotal() + $baseTaxAmount;
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseTaxAmount);
        $newGTotal = $creditmemo->getGrandTotal() + $taxAmount;
        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $taxAmount);

	$creditmemo->setBaseCustomerBalanceReturnMax($creditmemo->getBaseCustomerBalanceReturnMax() + $baseTaxAmount);
        $creditmemo->setCustomerBalanceReturnMax($creditmemo->getCustomerBalanceReturnMax() + $taxAmount);

        return $this;
    }
}
