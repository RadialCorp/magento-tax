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

use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteRequest;

class Radial_Tax_Model_Request_Builder_Order
{
    /** @var ITaxDutyFeeQuoteRequest */
    protected $_payload;
    /** @var Mage_Sales_Model_Order */
    protected $_order;
    /** @var Radial_Tax_Helper_Factory */
    protected $_taxFactory;
    /** @var Radial_Core_Model_Config_Registry */
    protected $_taxConfig;

    /**
     * @param array $args Must contain key/value for:
     *                         - payload => eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteRequest
     *                         - order => Mage_Sales_Model_Order
     *                         May contain key/value for:
     *                         - tax_factory => Radial_Tax_Helper_Factory
     *                         - tax_config => Radial_Core_Model_Config_Registry
     */
    public function __construct(array $args)
    {
        list(
            $this->_order,
            $this->_payload,
            $this->_taxFactory,
            $this->_taxConfig
        ) = $this->_checkTypes(
            $args['order'],
            $args['payload'],
            $this->_nullCoalesce($args, 'tax_factory', Mage::helper('radial_tax/factory')),
            $this->_nullCoalesce($args, 'tax_config', Mage::helper('radial_tax')->getConfigModel())
        );
        $this->_populateRequest();
    }

    /**
     * Enforce type checks on constructor args array.
     *
     * @param Mage_Sales_Model_Order
     * @param ITaxDutyFeeQuoteRequest
     * @param Radial_Tax_Helper_Factory
     * @return array
     */
    protected function _checkTypes(
        Mage_Sales_Model_Order $order,
        ITaxDutyFeeQuoteRequest $payload,
        Radial_Tax_Helper_Factory $taxFactory,
        Radial_Core_Model_Config_Registry $config
    ) {
        return [$order, $payload, $taxFactory, $config];
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
     * Get a tax request payload populated with data from the order.
     *
     * @return ITaxDutyFeeQuoteRequest
     */
    public function getTaxRequest()
    {
        return $this->_payload;
    }

    /**
     * Inject the request with data from the order.
     *
     * @return self
     */
    protected function _populateRequest()
    {
        return $this->_injectOrderData()->_injectAddressData();
    }

    /**
     * Set data from the order into the request payload. This should only be
     * data that comes only from the order, such as order currency.
     *
     * @return self
     */
    protected function _injectOrderData()
    {
        $this->_payload->setCurrency($this->_order->getOrderCurrencyCode())
            ->setVatInclusivePricingFlag($this->_taxConfig->vatInclusivePricingFlag)
            ->setCustomerTaxId($this->_order->getCustomerTaxvat());
        return $this;
    }

    /**
     * Add data to the request for addresses in the order.
     *
     * @return self
     */
    protected function _injectAddressData()
    {
        $destinationIterable = $this->_payload->getDestinations();
        $shipGroupIterable = $this->_payload->getShipGroups();

	$quote = Mage::getModel('sales/quote')->getCollection()
                ->addFieldToFilter('entity_id', $this->_order->getQuoteId())
                ->addFieldToFilter('store_id', $this->_order->getStoreId())
                ->getFirstItem();

        foreach ($quote->getAddressesCollection() as $address) {
            $addressId = $address->getId();

            // Defer responsibility for building ship group and destination
            // payloads to address request builders.
            $addressBuilder = $this->_taxFactory->createRequestBuilderAddress(
                $shipGroupIterable,
                $destinationIterable,
                $address,
                $this->_order
            );

            $destinationPayload = $addressBuilder->getDestinationPayload();
            // Billing addresses need to be set separately in the request payload.
            // The addres request builder should have created the destination
            // for the billing address, even if there were no items to add to
            // to the ship group for the billing address. E.g. a billing address
            // is still a destination so the returned payload will still have a
            // destination but may not be a valid ship group (checked separately).
            if ($address->getAddressType() === Mage_Sales_Model_Quote_Address::TYPE_BILLING) {
                $this->_payload->setBillingInformation($destinationPayload);
            }
            $shipGroupPayload = $addressBuilder->getShipGroupPayload();
            if ($shipGroupPayload) {
                $shipGroupIterable[$shipGroupPayload] = $shipGroupPayload;
            }
        }
        $this->_payload->setShipGroups($shipGroupIterable);
        return $this;
    }
}
