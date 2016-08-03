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

use eBayEnterprise\RetailOrderManagement\Payload\Order\IOrderItem;
use eBayEnterprise\RetailOrderManagement\Payload\Order\ITaxContainer;
use eBayEnterprise\RetailOrderManagement\Payload\Order\IDiscountContainer;

/**
 * Adds Tax information to an OrderItem payload and its sub payloads.
 */
class Radial_Tax_Model_Order_Create_Orderitem
{
    /** @var Mage_Sales_Model_Order_Item */
    protected $_item;
    /** @var Mage_Catalog_Model_Product */
    protected $_itemProduct;
    /** @var Radial_Tax_Helper_Payload */
    protected $_payloadHelper;
    /** @var Radial_Tax_Model_Record[] */
    protected $_taxRecords;
    /** @var Radial_Tax_Model_Duty */
    protected $_duty;
    /** @var Radial_Tax_Model_Fee[] */
    protected $_fees;
    /** @var IOrderItem */
    protected $_orderItemPayload;
    /** @var EbayEnterpise_Core_Model_Config_Registry */
    protected $_taxConfig;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $_logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $_logContext;
    /** @var array */
    protected $_taxRecordsBySource = [];
    /**
     * @param array $args Must contain key/values for:
     *                         - item => Mage_Sales_Model_Order_Item
     *                         - tax_records => Radial_Tax_Model_Record[]
     *                         - order_item_payload => eBayEnterprise\RetailOrderManagement\Payload\Order\IOrderItem
     *                         May contain key/values for:
     *                         - logger => EbayEnterprise_MageLog_Helper_Data
     *                         - log_context => EbayEnterprise_MageLog_Helper_Context
     *                         - payload_helper => Radial_Tax_Helper_Payload
     *                         - tax_config => Radial_Core_Model_Config_Registry
     *                         - fees => Radial_Tax_Model_Fee[]
     *                         - duty => Radial_Tax_Model_Duty
     */
    public function __construct(array $args = [])
    {
        list(
            $this->_item,
            $this->_taxRecords,
            $this->_orderItemPayload,
            $this->_logger,
            $this->_logContext,
            $this->_payloadHelper,
            $this->_taxConfig,
            $this->_fees,
            $this->_duty
        ) = $this->_checkTypes(
            $args['item'],
            $args['tax_records'],
            $args['order_item_payload'],
            $this->_nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->_nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context')),
            $this->_nullCoalesce($args, 'payload_helper', Mage::helper('radial_tax/payload')),
            $this->_nullCoalesce($args, 'tax_config', Mage::helper('radial_tax')->getConfigModel()),
            $this->_nullCoalesce($args, 'fees', []),
            $this->_nullCoalesce($args, 'duty', null)
        );
        $this->_indexTaxRecords();

        $this->_itemProduct = $this->getItemProduct($this->_item);
    }

    /**
     * Enforce type checks on construct args array.
     *
     * @param Mage_Sales_Model_Order_Item
     * @param Radial_Tax_Model_Record[]
     * @param IOrderItem
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @param Radial_Tax_Helper_Payload
     * @param Radial_Core_Model_Config_Registry
     * @param Radial_Tax_Model_Fee[]
     * @param Radial_Tax_Model_Duty|null
     * @return array
     */
    protected function _checkTypes(
        Mage_Sales_Model_Order_Item $item,
        array $taxRecords,
        IOrderItem $orderItemPayload,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext,
        Radial_Tax_Helper_Payload $payloadHelper,
        Radial_Core_Model_Config_Registry $taxConfig,
        array $fees = [],
        Radial_Tax_Model_Duty $duty = null
    ) {
        return [
            $item,
            $taxRecords,
            $orderItemPayload,
            $logger,
            $logContext,
            $payloadHelper,
            $taxConfig,
            $fees,
            $duty,
        ];
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
     * add tax data to the given IOrderItem.
     * PriceGroups are created as necessary.
     * An exception may be thrown if an expected discount payload is not found.
     *
     * @return self
     */
    public function addTaxesToPayload()
    {
        $this->_addMerchandiseTaxes()
            ->_addShippingTaxes()
            ->_addDutyAmounts()
            ->_addGiftingTaxes()
            ->_addTaxDisplayType();
        return $this;
    }

    /**
     * Add tax data for tax totals for the merchandise prices and discounts.
     *
     * @return self
     */
    protected function _addMerchandiseTaxes()
    {
        $taxes = $this->_getTaxRecordsBySource(Radial_Tax_Model_Record::SOURCE_MERCHANDISE);
        $discountTaxes = $this->_getTaxRecordsBySource(Radial_Tax_Model_Record::SOURCE_MERCHANDISE_DISCOUNT);

        if ($taxes || $discountTaxes) {
            $priceGroup = $this->_orderItemPayload->getMerchandisePricing()
                ?: $this->_orderItemPayload->getEmptyPriceGroup();

            $priceGroup->setTaxClass($this->_itemProduct->getTaxCode());
            $this->_addTaxRecordsToContainer($taxes, $priceGroup)
                ->_addDiscountTaxRecords($discountTaxes, $priceGroup);
            $this->_orderItemPayload->setMerchandisePricing($priceGroup);
        }

        return $this;
    }

    /**
     * Add tax data for tax totals for shipping prices and discounts.
     *
     * @return self
     */
    protected function _addShippingTaxes()
    {
        $taxes = $this->_getTaxRecordsBySource(Radial_Tax_Model_Record::SOURCE_SHIPPING);
        $discountTaxes = $this->_getTaxRecordsBySource(Radial_Tax_Model_Record::SOURCE_SHIPPING_DISCOUNT);

        if ($taxes || $discountTaxes) {
            $priceGroup = $this->_orderItemPayload->getShippingPricing()
                ?: $this->_orderItemPayload->getEmptyPriceGroup();
            $priceGroup->setTaxClass($this->_taxConfig->shippingTaxClass);
            $this->_addTaxRecordsToContainer($taxes, $priceGroup)
                ->_addDiscountTaxRecords($discountTaxes, $priceGroup);

            $this->_orderItemPayload->setShippingPricing($priceGroup);
        }

        return $this;
    }

    /**
     * Add tax data for duty amounts and taxes for duty prices and discounts.
     *
     * @return self
     */
    protected function _addDutyAmounts()
    {
        if ($this->_includeDutyPricing()) {
            $taxes = $this->_getTaxRecordsBySource(Radial_Tax_Model_Record::SOURCE_DUTY);
            $discountTaxes = $this->_getTaxRecordsBySource(Radial_Tax_Model_Record::SOURCE_DUTY_DISCOUNT);

            $priceGroup = $this->_orderItemPayload->getDutyPricing()
                ?: $this->_orderItemPayload->getEmptyPriceGroup();

            if ($this->_duty) {
                $priceGroup->setAmount($this->_duty->getAmount())
                    ->setTaxClass($this->_duty->getTaxClass());
            }

            $this->_addTaxRecordsToContainer($taxes, $priceGroup)
                ->_addDiscountTaxRecords($discountTaxes, $priceGroup);

            $this->_orderItemPayload->setDutyPricing($priceGroup);
        }
        return $this;
    }

    /**
     * Add fees calculated for the item.
     *
     * @return self
     */
    protected function _addFees()
    {
        $feeTaxRecords = $this->_getTaxRecordsBySource(Radial_Tax_Model_Record::SOURCE_FEE);
        $feeIterable = $this->_orderItemPayload->getFees();
        foreach ($this->_fees as $fee) {
            $feeId = $fee->getId();
            // Tax records that apply to a fee will have a fee id matching
            // the id of the fee. Filter the tax records that may apply to any
            // fee for the item to only the tax records that apply to the fee
            // being processed.
            // @TODO This is probably terribly inefficient due to having to
            // array_filter the full set of tax records for every fee. For
            // now, at least, it should be a small enough data set to not demand
            // immediate remediation.
            $feeTaxes = array_filter(
                $feeTaxRecords,
                function ($taxRecord) use ($feeId) {
                    return $feeId && $feeId === $taxRecord->getFeeId();
                }
            );
            $feePayload = $feeIterable->getEmptyFee();
            $this->_payloadHelper->taxFeeToOrderFeePayload($fee, $feePayload, $feeTaxes);
            $feeIterable[$feePayload] = null;
        }
        $this->_orderItemPayload->setFees($feeIterable);
        return $this;
    }

    /**
     * Add taxes for item level gifting.
     *
     * @return self
     */
    protected function _addGiftingTaxes()
    {
        $giftTaxes = $this->_getTaxRecordsBySource(Radial_Tax_Model_Record::SOURCE_ITEM_GIFTING);
        if ($giftTaxes) {
            $this->_addTaxRecordsToContainer($giftTaxes, $this->_getGiftTaxContainer());
        }
        return $this;
    }

    /**
     * Add the tax, duty, fees display type.
     *
     * @return self
     */
    protected function _addTaxDisplayType()
    {
        $this->_orderItemPayload
            ->setTaxAndDutyDisplayType($this->_getTaxAndDutyDisplayType());
        return $this;
    }

    /**
     * Get all tax records of a given type.
     *
     * @param int $taxSource Source of tax records.
     * @return Radial_Tax_Model_Record[]
     */
    protected function _getTaxRecordsBySource($taxSource)
    {
        return $this->_nullCoalesce($this->_taxRecordsBySource, $taxSource, []);
    }

    /**
     * Add tax records to discount payloads within the discount container.
     *
     * @param Radial_Tax_Model_Record[]
     * @param IDiscountContainer
     * @return self
     */
    protected function _addDiscountTaxRecords(array $taxRecords, IDiscountContainer $discountContainer)
    {
        foreach ($discountContainer->getDiscounts() as $discountPayload) {
            $discountId = $discountPayload->getId();
            // Tax records that apply to a discount will have a discount id matching
            // the id of the discount. Filter the tax records that may apply to any
            // discount for the item to only the tax records that apply to the
            // discount being processed.
            // @TODO This is probably terribly inefficient due to having to
            // array_filter the full set of tax records for every discount. For
            // now, at least, it should be a small enough data set to not demand
            // immediate remediation.
            $this->_addTaxRecordsToContainer(
                array_filter($taxRecords, function ($taxRecord) use ($discountId) {
                    return $discountId && $taxRecord->getDiscountId() == $discountId;
                }),
                $discountPayload
            );
        }
        return $this;
    }

    /**
     * Add a tax payload for each tax record to the container.
     *
     * @param Radial_Tax_Model_Record[]
     * @param ITaxContainer
     * @return self
     */
    protected function _addTaxRecordsToContainer(array $taxRecords, ITaxContainer $taxContainer)
    {
        $taxIterable = $taxContainer->getTaxes();
        foreach ($taxRecords as $taxRecord) {
            $taxPayload = $this->_payloadHelper->taxRecordToTaxPayload($taxRecord, $taxIterable->getEmptyTax());
            $taxIterable[$taxPayload] = null;
        }
        $taxContainer->setTaxes($taxIterable);
        return $this;
    }

    /**
     * Get the tax container for gifting taxes for this item.
     *
     * @return ITaxContainer
     */
    protected function _getGiftTaxContainer()
    {
        $taxContainer = $this->_orderItemPayload->getGiftPricing();
        if (!$taxContainer) {
            $taxContainer = $this->_orderItemPayload->getEmptyGiftingPriceGroup();
            $this->_orderItemPayload->setGiftPricing($taxContainer);
        }
        return $taxContainer;
    }

    /**
     * Get the appropriate value for the tax and duty display type.
     *
     * Currently will always use the display type indicating that all taxes
     * should be displayed as a single amount.
     *
     * @return string
     */
    protected function _getTaxAndDutyDisplayType()
    {
        return IOrderItem::TAX_AND_DUTY_DISPLAY_SINGLE_AMOUNT;
    }

    /**
     * Determine if duty pricing should be included for the item.
     *
     * @return bool
     */
    protected function _includeDutyPricing()
    {
        // Duty pricing should only be included if there are taxes to add - total
        // or discount taxes - or the item has an associated duty record with
        // data to include - an amount or tax class.
        return $this->_getTaxRecordsBySource(Radial_Tax_Model_Record::SOURCE_DUTY)
            || $this->_getTaxRecordsBySource(Radial_Tax_Model_Record::SOURCE_DUTY_DISCOUNT)
            || ($this->_duty
                && ($this->_duty->getAmount() || $this->_duty->getTaxClass())
            );
    }

    /**
     * Create a map of tax records, indexed by source. As tax records will need
     * to be repeatedly filtered by type, creating this index allows a single,
     * initial pass to create the index, and then constant time lookups to
     * get all tax records with a given source.
     *
     * @return self
     */
    protected function _indexTaxRecords()
    {
        $this->_taxRecordsBySource = [];
        foreach ($this->_taxRecords as $taxRecord) {
            $recordSource = $taxRecord->getTaxSource();
            if (!isset($this->_taxRecordsBySource[$recordSource])) {
                $this->_taxRecordsBySource[$recordSource] = [];
            }
            $this->_taxRecordsBySource[$recordSource][] = $taxRecord;
        }
        return $this;
    }

    /**
     * Get the product the item represents.
     *
     * @param Mage_Sales_Model_Order_Item
     * @return Mage_Catalog_Model_Product
     */
    protected function getItemProduct(Mage_Sales_Model_Order_Item $item)
    {
        // When dealing with configurable items, need to get tax data from
        // the child product and not the parent.
        if ($item->getProductType() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $sku = $item->getSku();
            $children = $item->getChildrenItems();
            if ($children) {
                /** @var Mage_Sales_Model_Order_Item $childItem */
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
        return $item->getProduct() ?: Mage::getModel('catalog/product')->load($item->getProductId());
    }
}
