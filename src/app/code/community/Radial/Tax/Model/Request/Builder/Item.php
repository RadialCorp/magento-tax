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

use eBayEnterprise\RetailOrderManagement\Payload\IPayload;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IOrderItemRequest;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IOrderItemRequestIterable;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IDiscountContainer;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedDiscountIterable;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedDiscountContainer;

class Radial_Tax_Model_Request_Builder_Item
{
    /** @var IOrderItemRequestIterable */
    protected $_orderItemIterable;
    /** @var IOrderItemRequest */
    protected $_orderItem;
    /** @var Mage_Customer_Model_Address_Abstract */
    protected $_address;
    /** @var Mage_Core_Model_Abstract */
    protected $_item;
    /** @var Mage_Catalog_Model_Product */
    protected $_itemProduct;
    /** @var Radial_Tax_Helper_Data */
    protected $_taxHelper;
    /** @var Radial_Tax_Helper_Payload */
    protected $_payloadHelper;
    /** @var Radial_Core_Model_Config_Registry */
    protected $_taxConfig;
    /** @var Radial_Core_Helper_Discount */
    protected $_discountHelper;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $_logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $_logContext;
    /** @var Mage_Sales_Model_Abstract */
    protected $_invoice;
    /** @var boolean */
    protected $_first;

    /**
     * @param array $args Must contain key/value for:
     *                         - order_item_iterable => eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IOrderItemRequestIterable | eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IITaxedOrderItemIterable
     *                         - address => Mage_Customer_Model_Address_Abstract
     *                         - item => Mage_Core_Model_Abstract
     *                         May contain key/value for:
     *                         - tax_helper => Radial_Tax_Helper_Data
     *                         - payload_helper => Radial_Tax_Helper_Payload
     *                         - tax_config => Radial_Core_Model_Config_Registry
     *                         - discount_helper => Radial_Core_Helper_Discount
     *                         - logger => EbayEnterprise_MageLog_Helper_Data
     *                         - log_context => EbayEnterprise_MageLog_Helper_Context
     *			       - invoice => Mage_Sales_Model_Abstract
     *			       - first => boolean
     */
    public function __construct(array $args)
    {
        list(
            $this->_orderItemIterable,
            $this->_address,
            $this->_item,
            $this->_taxHelper,
            $this->_payloadHelper,
            $this->_taxConfig,
            $this->_discountHelper,
            $this->_logger,
            $this->_logContext,
	    $this->_invoice,
	    $this->_first
        ) = $this->_checkTypes(
            $args['order_item_iterable'],
            $args['address'],
            $args['item'],
            $this->_nullCoalesce($args, 'tax_helper', Mage::helper('radial_tax')),
            $this->_nullCoalesce($args, 'payload_helper', Mage::helper('radial_tax/payload')),
            $this->_nullCoalesce($args, 'tax_config', Mage::helper('radial_tax')->getConfigModel()),
            $this->_nullCoalesce($args, 'discount_helper', Mage::helper('radial_core/discount')),
            $this->_nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->_nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context')),
	    $args['invoice'],
	    $args['first']
        );
        $this->_itemProduct = $this->getItemProduct($this->_item);
        $this->_orderItem = $this->_orderItemIterable->getEmptyOrderItem();
        $this->_populateRequest();
    }

    /**
     * Enforce type checks on constructor args array.
     *
     * @param IOrderItemRequestIterable | ITaxedOrderItemIterable
     * @param Mage_Customer_Model_Address_Abstract
     * @param Mage_Core_Model_Abstract
     * @param Radial_Tax_Helper_Data
     * @param Radial_Tax_Helper_Payload
     * @param Radial_Core_Model_Config_Registry
     * @param Radial_Core_Helper_Discount
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @param Mage_Sales_Model_Abstract
     * @param first
     * @return array
     */
    protected function _checkTypes(
        IPayload $orderItemIterable,
        Mage_Customer_Model_Address_Abstract $address,
        Mage_Core_Model_Abstract $item,
        Radial_Tax_Helper_Data $taxHelper,
        Radial_Tax_Helper_Payload $payloadHelper,
        Radial_Core_Model_Config_Registry $taxConfig,
        Radial_Core_Helper_Discount $discountHelper,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext,
	Mage_Sales_Model_Abstract $invoice,
	$first
    ) {
        return [
            $orderItemIterable,
            $address,
            $item,
            $taxHelper,
            $payloadHelper,
            $taxConfig,
            $discountHelper,
            $logger,
            $logContext,
	    $invoice,
	    $first
        ];
    }

    /**
     * Fill in default values.
     *
     * @param string
     * @param array
     * @param mixed
     * @return mixed
     */
    protected function _nullCoalesce(array $arr, $key, $default)
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /**
     * Get the order item payload for the item.
     *
     * @return IOrderItemRequest|null
     */
    public function getOrderItemPayload()
    {
        return $this->_orderItem;
    }

    /**
     * Create an order item payload and inject it with data from the item.
     *
     * @return self
     */
    protected function _populateRequest()
    {
        return $this->_injectItemData()
            ->_injectOriginData()
            ->_injectPricingData()
            ->_injectGiftingData()
	    ->_injectCustomizationData();
    }

    /**
     * Inject general item data into the order item payload.
     *
     * @return self
     */
    protected function _injectItemData()
    {
        $this->_orderItem
            ->setLineNumber($this->_item->getId())
            ->setItemId($this->_item->getSku())
            ->setQuantity((int) $this->_item->getTotalQty())
            ->setDescription($this->_item->getName())
            ->setHtsCode($this->_taxHelper->getProductHtsCodeByCountry($this->_itemProduct, $this->_address->getCountryId()))
            ->setManufacturingCountryCode($this->_itemProduct->getCountryOfManufacture())
            ->setScreenSize($this->_itemProduct->getScreenSize());

	if((int)$this->_item->getTotalQty() === 0 )
	{
		$this->_orderItem->setQuantity((int) $this->_item->getQty());
	}

	return $this;
    }

    /**
     * Add admin and shipping origin data to the item payload.
     *
     * @return self
     */
    protected function _injectOriginData()
    {
        // Admin origin set in configuration.
        $adminOrigin = Mage::getModel('customer/address', [
            'street' => rtrim(
                implode([
                    $this->_taxConfig->adminOriginLine1,
                    $this->_taxConfig->adminOriginLine2,
                    $this->_taxConfig->adminOriginLine3,
                    $this->_taxConfig->adminOriginLine4
                ]),
                "\n"
            ),
            'city' => $this->_taxConfig->adminOriginCity,
            'region_id' => $this->_taxConfig->adminOriginMainDivision,
            'country_id' => $this->_taxConfig->adminOriginCountryCode,
            'postcode' => $this->_taxConfig->adminOriginPostalCode,
        ]);

	// In the PTF Model, we have no idea where items are being shipped from except if defined in the Magento Admin Under 
	// System -> Configuration -> Shipping Settings -> Origin

	$shippingOrigin = Mage::getModel('customer/address', [
            'street' => rtrim(
                implode([
                    Mage::getStoreConfig('shipping/origin/street_line1'),
                    Mage::getStoreConfig('shipping/origin/street_line2')
		]),
                "\n"
            ),
            'city' => Mage::getStoreConfig('shipping/origin/city'),
            'region_id' => Mage::getStoreConfig('shipping/origin/region_id'),
            'country_id' => Mage::getStoreConfig('shipping/origin/country_id'),
            'postcode' => Mage::getStoreConfig('shipping/origin/postcode'),
        ]);

        $this->_orderItem
            ->setAdminOrigin($this->_payloadHelper->customerAddressToPhysicalAddressPayload(
                $adminOrigin,
                $this->_orderItem->getEmptyPhysicalAddress()->setOriginAddressNodeName('AdminOrigin')
            ))
            ->setShippingOrigin($this->_payloadHelper->customerAddressToPhysicalAddressPayload(
                $shippingOrigin,
                $this->_orderItem->getEmptyPhysicalAddress()->setOriginAddressNodeName('ShippingOrigin')
            ));
        return $this;
    }

    /**
     * Inject gifting data for the item.
     *
     * @return self
     */
    protected function _injectGiftingData()
    {
	if( $this->_invoice->getId() )
	{
		$itemC = Mage::getModel('sales/order_item')->getCollection()
                                ->addFieldToFilter('item_id', array('eq' => $this->_item->getOrderItemId()));

		if( $itemC->getSize() > 0 )
		{
			$item = $itemC->getFirstItem();

			if ($item->getGwId() && $item->getGwPrice())
			{
				$this->_payloadHelper->giftingItemToGiftingPayloadInvoice($this->_item, $this->_orderItem, $item, '');
			}
		}
	} else {
        	if ($this->_itemHasGifting()) {
        	    // Given payload will be updated to include gifting data from the
        	    // item, so no need to handle the return value as the side-effects
        	    // of the method will accomplish all that is needed to add gifting
        	    // data to the payload.
        	    $this->_payloadHelper->giftingItemToGiftingPayload($this->_item, $this->_orderItem);
		}
	}
        return $this;
    }

    /**
     * Inject Customization Data for Printed Cards (for now)
     *
     * @return self
     */
    protected function _injectCustomizationData()
    {
	$gwCardSku = Mage::getStoreConfig('radial_core/radial_tax_core/printedcardsku');
        $gwCardTaxClass = Mage::getStoreConfig('radial_core/radial_tax_core/printedcardtaxclass');
	$gwCardPriceStore = Mage::getStoreConfig('sales/gift_options/printed_card_price');

	if( $this->_invoice->getId())
	{
		$order = $this->_invoice->getOrder();

		if( $this->_invoice instanceof Mage_Sales_Model_Order_Invoice )
                {
                        // Only Send Gift Wrap on First Invoice
                        $invoiceCol = $order->getInvoiceCollection()->addAttributeToSort('increment_id', 'ASC');

                        if( strcmp($invoiceCol->getFirstItem()->getIncrementId(), $this->_invoice->getIncrementId()) === 0 )
                        {
                                if( $order->getGwAddCard() && $order->getGwCardPrice() && $gwCardSku && $gwCardTaxClass && $gwCardPriceStore && $this->_first)
                                {
                                        $customizations = $this->_orderItem->getCustomizations();
                                        $customization = $this->_payloadHelper->transferGwPrintedCardInvoice($this->_item, $customizations);
                                        $customizations[$customization] = $customization;
                                        $this->_orderItem->setCustomizations($customizations);
                                }
                        }
                } else {
                        // Only Send Gift Wrap on First Creditmemo
                        $creditmemoCol = Mage::getResourceModel('sales/order_creditmemo_collection')->addAttributeToSort('increment_id', 'ASC')
                                                ->addAttributeToFilter('order_id', $order->getId());

                        if( strcmp($creditmemoCol->getFirstItem()->getIncrementId(), $this->_invoice->getIncrementId()) === 0 )
                        {
                                if( $order->getGwAddCard() && $order->getGwCardPrice() && $gwCardSku && $gwCardTaxClass && $gwCardPriceStore && $this->_first)
                                {
                                        $customizations = $this->_orderItem->getCustomizations();
                                        $customization = $this->_payloadHelper->transferGwPrintedCardInvoice($this->_item, $customizations);
                                        $customizations[$customization] = $customization;
                                        $this->_orderItem->setCustomizations($customizations);
                                }
                        }
                }
	} else {
                if( $this->_address->getGwAddCard() && $this->_address->getGwCardPrice() && $gwCardSku && $gwCardTaxClass && $gwCardPriceStore && $this->_first)
                {
			$customizations = $this->_orderItem->getCustomizations();
                        $customization = $this->_payloadHelper->transferGwPrintedCard($this->_item, $customizations);
			$customizations[$customization] = $customization;
			$this->_orderItem->setCustomizations($customizations);
                }
	}
    }

    /**
     * Add pricing data for the item to the item payload.
     *
     * @return self
     */
    protected function _injectPricingData()
    {
        $canIncludeAmounts = $this->_canIncludeAmounts($this->_item);

	if($this->_invoice->getId())
	{
		if( $this->_invoice instanceof Mage_Sales_Model_Order_Creditmemo )
		{
			$subtotal = -$this->_item->getRowTotal();

			$itemC = Mage::getModel('sales/order_item')->getCollection()
                                ->addFieldToFilter('item_id', array('eq' => $this->_item->getData('order_item_id')));

		        if( $itemC->getSize() > 0 )
        		{
				$data = unserialize($itemC->getFirstItem()->getData('ebayenterprise_order_discount_data'));

                		if($data)
                		{
                	    		foreach ($data as $loneDiscountData) {
                	        	    if( $loneDiscountData['amount'] != 0 )
                	        	    {
                	        	        $appliedCount = $loneDiscountData['applied_count'];
                	        	        $singleDisc = $loneDiscountData['amount'] / $appliedCount;
                	        	        $newAmount = $singleDisc * $this->_item->getQty();
                	        	        $subtotal = $subtotal + $newAmount;
                	        	    }
                	    		}
                		}
			}

			$merchandiseInvoicePricing = $this->_orderItem->getEmptyMerchandisePriceGroup()
			    ->setUnitPrice($canIncludeAmounts ? -$this->_item->getPrice() : 0)
                	    ->setAmount($canIncludeAmounts ? $subtotal : 0)
			    ->setTaxClass($this->_itemProduct->getTaxCode());
		} else {
			$merchandiseInvoicePricing = $this->_orderItem->getEmptyMerchandisePriceGroup()
                            ->setUnitPrice($canIncludeAmounts ? $this->_item->getPrice() : 0)
                            ->setAmount($canIncludeAmounts ? $this->_item->getRowTotal() : 0)
                            ->setTaxClass($this->_itemProduct->getTaxCode());
                        if ($canIncludeAmounts) {
                            $this->_discountHelper->transferInvoiceTaxDiscounts($this->_item, $merchandiseInvoicePricing);
                        }
		}

		$this->_orderItem->setMerchandisePricing($merchandiseInvoicePricing);
	} else {
        	$merchandisePricing = $this->_orderItem->getEmptyMerchandisePriceGroup()
        	    ->setUnitPrice($canIncludeAmounts ? $this->_item->getPrice() : 0)
        	    ->setAmount($canIncludeAmounts ? $this->_item->getRowTotal() : 0)
        	    ->setTaxClass($this->_itemProduct->getTaxCode());
        	if ($canIncludeAmounts) {
        	    $this->_discountHelper->transferTaxDiscounts($this->_item, $merchandisePricing);
        	}

        	$this->_orderItem->setMerchandisePricing($merchandisePricing);
	}

        // This will be set by the parent address when initially creating the
        // item request builder. Each ship group should include shipping on
        // only one item in the ship group for address level shipping totals.
        if ($this->_item->getIncludeShippingTotals()) {
	    if($this->_invoice->getId())
	    {
		if( $this->_invoice instanceof Mage_Sales_Model_Order_Creditmemo )
		{
			//S&H is specifically itemized in returns, so use the entered value here. - RK
                        $shipAmount = -$this->_invoice->getShippingAmount();

                        $invoicePricing = $this->_orderItem->getEmptyInvoicePriceGroup()
                                ->setAmount($shipAmount)
                                ->setTaxClass($this->_taxConfig->shippingTaxClass);

                        $this->_orderItem->setInvoicePricing($invoicePricing);
		} else {
			$order = $this->_invoice->getOrder();
			$shipping = $this->_invoice->getShippingAmount();
		
			$invoicePricing = $this->_orderItem->getEmptyInvoicePriceGroup()
                		->setAmount($shipping)
                		->setTaxClass($this->_taxConfig->shippingTaxClass);
            		$this->_addInvoiceShippingDiscount($invoicePricing);

			$this->_orderItem->setInvoicePricing($invoicePricing);
		}
	    } else {
		$shippingPricing = $this->_orderItem->getEmptyShippingPriceGroup()
                	->setAmount($this->_address->getShippingAmount())
                	->setTaxClass($this->_taxConfig->shippingTaxClass);
                $this->_addShippingDiscount($shippingPricing);

            	$this->_orderItem->setShippingPricing($shippingPricing);
	    }
        }

	//Add Duty Data
	if( $this->_invoice->getId())
	{
		$dutyGroup = unserialize($this->_invoice->getOrder()->getData('radial_tax_duties'));

		if($dutyGroup)
		{
			$orderItemId = $this->_item->getOrderItemId();
                        $orderId = $this->_invoice->getOrder()->getId();

			foreach( $dutyGroup as $duty )
			{
				$itemC = Mage::getModel('sales/order_item')->getCollection()
                                		->addFieldToFilter('item_id', array('eq' => $orderItemId))
						->addFieldToFilter('order_id', array('eq' => $orderId));

				if( $itemC->getSize() > 0 )
				{
					$item = $itemC->getFirstItem();
					if( $duty->getItemId() === $item->getQuoteItemId())
					{
						$amountPer = round($duty->getAmount() / (int) $item->getQtyOrdered(), 2);
                                                $amountPer = $amountPer * (int) $this->_item->getQty();

						if( $this->_invoice instanceof Mage_Sales_Model_Order_Creditmemo )
						{
							$dutyPricing = $this->_orderItem->getEmptyDutyPriceGroup()
								->setCalculationError($dutyGroup->getCalculationError())
								->setAmount(-$amountPer)
								->setTaxClass($dutyGroup->getTaxClass());
						} else {
							$dutyPricing = $this->_orderItem->getEmptyDutyPriceGroup()
                                                                ->setCalculationError($dutyGroup->getCalculationError())
                                                                ->setAmount($amountPer)
                                                                ->setTaxClass($dutyGroup->getTaxClass());
						}

						$this->_orderItem->setDutyPricing($dutyPricing);
					}
				}
			}
		}

		//Add Fees Data
		$taxRecordFees = unserialize($this->_invoice->getOrder()->getData('radial_tax_fees'));
		if( $taxRecordFees )
		{
			$fees = $this->_orderItem->getFees();
			$orderItemId = $this->_item->getOrderItemId();
                        $orderId = $this->_invoice->getOrder()->getId();

			foreach( $taxRecordFees as $taxFeeRecord )
			{
                                $itemC = Mage::getModel('sales/order_item')->getCollection()
                                                ->addFieldToFilter('item_id', array('eq' => $orderItemId))
                                                ->addFieldToFilter('order_id', array('eq' => $orderId));

				if( $itemC->getSize() > 0 )
                                {
                                        $item = $itemC->getFirstItem();
					if( $taxFeeRecord->getItemId() === $item->getQuoteItemId())
                                        {
						$fee = $fees->getEmptyFee()
								->setType($taxFeeRecord->getType())
								->setDescription($taxFeeRecord->getDescription())
								->setId($taxFeeRecord->getFeeId());
	
						$amountPer = round($taxFeeRecord->getAmount() / (int) $item->getQtyOrdered(), 2);
						$amountPer = $amountPer * (int) $this->_item->getQty();

						if( $this->_invoice instanceof Mage_Sales_Model_Order_Creditmemo )
                                                {
							$feePriceGroup = $fee->getEmptyFeePriceGroup()->setAmount(-$amountPer);
						} else {
							$feePriceGroup = $fee->getEmptyFeePriceGroup()->setAmount($amountPer);
						}

						$fee->setCharge($feePriceGroup);

						$fees[$fee] = $fee;	
					}
				}
			}

			$this->_orderItem->setFees($fees);
		}
	}

        return $this;
    }

    /**
     * determine if the item's amounts should be put into the request.
     *
     * @param Mage_Core_Model_Abstract
     * @return bool
     */
    protected function _canIncludeAmounts(Mage_Core_Model_Abstract $item)
    {
	$product = Mage::getModel('catalog/product')->loadByAttribute('sku', $item->getSku());

        return !(
            // only the parent item will have the bundle product type
            $product->getProductType() === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE
            && $item->isChildrenCalculated()
        );
    }

    /**
     * Add discounts for shipping discount amount.
     *
     * Does not use the radial_core/discount helper as shipping discount
     * data may not have been collected to be used by the helper - both
     * use the same event so order between the two cannot be guarantted
     * without introducing a hard dependency. In this case, however,
     * discount data is simple enough to collect independently.
     *
     * @param ITaxDiscountContainer
     * @return ITaxDiscountContainer
     */
    protected function _addShippingDiscount(IDiscountContainer $discountContainer)
    {
        $shippingDiscountAmount = $this->_address->getShippingDiscountAmount();
        if ($shippingDiscountAmount) {
            $discounts = $discountContainer->getDiscounts();
            $shippingDiscount = $discounts->getEmptyDiscount()
                ->setAmount($shippingDiscountAmount);
            $discounts[$shippingDiscount] = $shippingDiscount;
            $discountContainer->setDiscounts($discounts);
        }
        return $discountContainer;
    }

    /**
     * Add discounts for shipping discount amount.
     *
     * Does not use the radial_core/discount helper as shipping discount
     * data may not have been collected to be used by the helper - both
     * use the same event so order between the two cannot be guarantted
     * without introducing a hard dependency. In this case, however,
     * discount data is simple enough to collect independently.
     *
     * @param ITaxedDiscountContainer
     * @return ITaxedDiscountContainer
     */
    protected function _addInvoiceShippingDiscount(ITaxedDiscountContainer $discountContainer)
    {
        $shippingDiscountAmount = $this->_address->getShippingDiscountAmount();
        if ($shippingDiscountAmount) {
            $discounts = $discountContainer->getDiscounts();
            $shippingDiscount = $discounts->getEmptyDiscount()
                ->setAmount($shippingDiscountAmount);
            $discounts[$shippingDiscount] = $shippingDiscount;
            $discountContainer->setDiscounts($discounts);
        }
        return $discountContainer;
    }

    protected function delegateShippingOrigin()
    {
        $address = Mage::getModel('customer/address');
        Mage::dispatchEvent('radial_tax_item_ship_origin', ['item' => $this->_item, 'address' => $address]);
        if ($this->isValidPhysicalAddress($address)) {
            return $address;
        }
        return null;
    }

    /**
     * Check for the item to have shipping origin data set.
     *
     * @return bool
     */
    protected function isValidPhysicalAddress($address)
    {
        return $address->getStreet1()
            && $address->getCity()
            && $address->getCountryId();
    }

    /**
     * Get the product the item represents.
     *
     * @param Mage_Core_Model_Abstract
     * @return Mage_Catalog_Model_Product
     */
    protected function getItemProduct(Mage_Core_Model_Abstract $item)
    {
        // When dealing with configurable items, need to get tax data from
        // the child product and not the parent.
        if ($item->getProductType() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $sku = $item->getSku();
            $children = $item->getChildren();
            if ($children) {
                foreach ($children as $childItem) {
                    $childProduct = $childItem->getProduct();
                    // If the SKU of the child product matches the SKU of the
                    // item, the simple product being ordered was found and should
                    // be used.
                    if ($childProduct->getSku() === $sku) {
                        return $childProduct;
                    }
                }
            }
        }
        return Mage::getModel('catalog/product')->loadByAttribute('sku', $item->getSku());
    }

    /**
     * Check for the item to have gifting data.
     *
     * @param bool
     */
    protected function _itemHasGifting()
    {
        return $this->_item->getGwId() && $this->_item->getGwPrice();
    }
}
