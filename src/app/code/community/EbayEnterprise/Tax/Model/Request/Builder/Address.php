<?php
/**
 * Copyright (c) 2013-2014 eBay Enterprise, Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright   Copyright (c) 2013-2014 eBay Enterprise, Inc. (http://www.ebayenterprise.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IShipGroupIterable;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IDestinationIterable;

class EbayEnterprise_Tax_Model_Request_Builder_Address
{
    const SHIPPING_CHARGE_TYPE = 'FLAT';

    /** @var IShipGroupIterable */
    protected $_shipGroupIterable;
    /** @var IShipGroup */
    protected $_shipGroup;
    /** @var IDestinationIterable */
    protected $_destinationIterable;
    /** @var IDestination */
    protected $_destination;
    /** @var Mage_Sales_Model_Quote_Address */
    protected $_address;
    /** @var EbayEnterprise_Tax_Helper_Item_Selection */
    protected $_selectionHelper;
    /** @var EbayEnterprise_Tax_Helper_Payload */
    protected $_payloadHelper;
    /** @var EbayEnterprise_Tax_Helper_Factory */
    protected $_taxFactory;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $_logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $_logContext;

    /**
     * @param array $args Must contain key/value for:
     *    - ship_group_iterable => eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IShipGroupIterable
     *    - destination_iterable => eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IDestinationIterable
     *    - address => Mage_Sales_Model_Quote_Address
     *    May contain key/value for:
     *    - selection_helper => EbayEnterprise_Tax_Helper_Item_Selection
     *    - payload_helper => EbayEnterprise_Tax_Helper_Payload
     *    - tax_factory => EbayEnterprise_Tax_Helper_Factory
     *    - logger => EbayEnterprise_MageLog_Helper_Data
     *    - log_context => EbayEnterprise_MageLog_Helper_Context
     */
    public function __construct(array $args)
    {
        list(
            $this->_shipGroupIterable,
            $this->_destinationIterable,
            $this->_address,
            $this->_selectionHelper,
            $this->_payloadHelper,
            $this->_taxFactory,
            $this->_logger,
            $this->_logContext
        ) = $this->_checkTypes(
            $args['ship_group_iterable'],
            $args['destination_iterable'],
            $args['address'],
            $this->_nullCoalesce($args, 'selection_helper', Mage::helper('ebayenterprise_tax/item_selection')),
            $this->_nullCoalesce($args, 'payload_helper', Mage::helper('ebayenterprise_tax/payload')),
            $this->_nullCoalesce($args, 'tax_factory', Mage::helper('ebayenterprise_tax/factory')),
            $this->_nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->_nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context'))
        );
        $this->_populateRequest();
    }

    /**
     * Enforce type checks on constructor args array.
     *
     * @param IShipGroupIterable
     * @param IDestinationIterable
     * @param Mage_Sales_Model_Quote_Address
     * @param EbayEnterprise_Tax_Helper_Item_Selection
     * @param EbayEnterprise_Tax_Helper_Payload
     * @param EbayEnterprise_Tax_Helper_Factory
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @return array
     */
    protected function _checkTypes(
        IShipGroupIterable $shipGroupIterable,
        IDestinationIterable $destinationIterable,
        Mage_Sales_Model_Quote_Address $address,
        EbayEnterprise_Tax_Helper_Item_Selection $selectionHelper,
        EbayEnterprise_Tax_Helper_Payload $payloadHelper,
        EbayEnterprise_Tax_Helper_Factory $taxFactory,
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
    protected function _nullCoalesce(array $arr, $key, $default)
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /**
     * Get the destination payload for the address.
     *
     * @return IDestination|null
     */
    public function getDestinationPayload()
    {
        return $this->_destination;
    }

    /**
     * Get the ship group payload for the address.
     *
     * @return IShipGroup|null
     */
    public function getShipGroupPayload()
    {
        return $this->_shipGroup;
    }

    /**
     * Create and populate payloads for the address.
     *
     * @return self
     */
    protected function _populateRequest()
    {
        if ($this->_validateAddressIsDestination()) {
            $this->_destination = $this->_payloadHelper->customerAddressToMailingAddressPayload(
                $this->_address,
                $this->_destinationIterable->getEmptyMailingAddress()
            );
            // If there is a destination for the address, copy it over to the
            // address object so it can be metched up to the destination in
            // the response payloads.
            $this->_address->setDestinationId($this->_destination->getId());
        }

        if ($this->_validateAddressIsShipGroup()) {
            $this->_shipGroup = $this->_shipGroupIterable->getEmptyShipGroup()
                ->setDestination($this->_destination)
                // Shipping charge type is always "FLAT" due to how Magento
                // reports shipping charges - total only at available at address
                // level and not item level.
                ->setChargeType(self::SHIPPING_CHARGE_TYPE);

            if ($this->_checkAddressHasGifting()) {
                $this->_payloadHelper->giftingItemToGiftingPayload($this->_address, $this->_shipGroup);
            }

            $this->_injectItemData();
        }
        return $this;
    }

    /**
     * Add order items to the ship group for each item shipping to the address.
     *
     * @return self
     */
    protected function _injectItemData()
    {
        $orderItemIterable = $this->_shipGroup->getItems();
        // The first item needs to include shipping totals, use this flag to
        // track when item is the first item.
        $first = true;
        foreach ($this->_selectionHelper->selectFrom($this->_address->getAllItems()) as $item) {
            // Add shipping amounts to the first item - necessary way of sending
            // address level shipping totals which is the only way Magento can
            // report shipping totals.
            if ($first) {
                $item->setIncludeShippingTotals(true);
            }

            $itemBuilder = $this->_taxFactory->createRequestBuilderItem(
                $orderItemIterable,
                $this->_address,
                $item
            );

            $itemPayload = $itemBuilder->getOrderItemPayload();
            if ($itemPayload) {
                $orderItemIterable[$itemPayload] = null;
            }
            // After the first iteration, this should always be false.
            $first = false;
        }
        return $this;
    }

    /**
     * Determine if the address is a valid destination.
     *
     * @return bool
     */
    protected function _validateAddressIsDestination()
    {
        return $this->_address->getAddressType() === Mage_Sales_Model_Quote_Address::TYPE_BILLING
            || $this->_validateAddressIsShipGroup();
    }

    /**
     * Determine if the addres is a valid ship group.
     *
     * @return bool
     */
    protected function _validateAddressIsShipGroup()
    {
        // This assume that the address is in a valid state to be
        // included and does not check for individual data constraints
        // to be met.
        return (bool) count($this->_address->getAllItems());
    }

    /**
     * Determine if the address contains gifting amounts to send.
     *
     * @return bool
     */
    protected function _checkAddressHasGifting()
    {
        return $this->_address->getGwId() && $this->_address->getGwPrice();
    }
}
