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

use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeInvoiceRequest;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedShipGroupIterable;
use eBayEnterprise\RetailOrderManagement\Payload\IPayload;

class Radial_Tax_Model_Request_Builder_Invoice
{
    /** @var ITaxDutyFeeInvoiceRequest */
    protected $_payload;
    /** @var Mage_Sales_Model_Order */
    protected $_order;
    /** @var Radial_Tax_Helper_Factory */
    protected $_taxFactory;
    /** @var Radial_Core_Model_Config_Registry */
    protected $_taxConfig;
    /** @var Mage_Sales_Model_Order_Invoice */
    protected $_invoice;

    /**
     * @param array $args Must contain key/value for:
     *                         - payload => eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeInvoiceRequest
     *                         - order => Mage_Sales_Model_Order
     *                         May contain key/value for:
     *                         - tax_factory => Radial_Tax_Helper_Factory
     *                         - tax_config => Radial_Core_Model_Config_Registry
     *			       - invoice => Mage_Sales_Model_Order_Invoice 
     */
    public function __construct(array $args)
    {
        list(
            $this->_order,
            $this->_payload,
            $this->_taxFactory,
            $this->_taxConfig,
	    $this->_invoice,
	    $this->_type
        ) = $this->_checkTypes(
            $args['order'],
            $args['payload'],
            $this->_nullCoalesce($args, 'tax_factory', Mage::helper('radial_tax/factory')),
            $this->_nullCoalesce($args, 'tax_config', Mage::helper('radial_tax')->getConfigModel()),
	    $args['invoice'],
	    $args['type']
        );
        $this->_populateRequest();
    }

    /**
     * Enforce type checks on constructor args array.
     *
     * @param Mage_Sales_Model_Order
     * @param ITaxDutyFeeInvoiceRequest
     * @param Radial_Tax_Helper_Factory
     * @param Mage_Sales_Model_Order_Invoice
     * @return array
     */
    protected function _checkTypes(
        Mage_Sales_Model_Order $order,
        ITaxDutyFeeInvoiceRequest $payload,
        Radial_Tax_Helper_Factory $taxFactory,
        Radial_Core_Model_Config_Registry $config,
	Mage_Sales_Model_Order_Invoice $invoice,
	$type
    ) {
        return [$order, $payload, $taxFactory, $config, $invoice, $type];
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
     * Get a tax request payload populated with data from the quote.
     *
     * @return ITaxDutyFeeInvoiceRequest
     */
    public function getTaxRequest()
    {
        return $this->_payload;
    }

    /**
     * Inject the request with data from the quote.
     *
     * @return self
     */
    protected function _populateRequest()
    {
        return $this->_injectQuoteData()->_injectAddressData();
    }

    /**
     * Set data from the quote into the request payload. This should only be
     * data that comes only from the quote, such as quote currency.
     *
     * @return self
     */
    protected function _injectQuoteData()
    {
	$customer = Mage::getModel('customer/customer')->load($this->_order->getData('customer_id'));
        $taxVat = $customer->getData('taxvat');

        $this->_payload->setCurrency($this->_order->getOrderCurrencyCode())
            ->setVatInclusivePricingFlag($this->_taxConfig->vatInclusivePricingFlag)
            ->setCustomerTaxId($taxVat)
	    ->setTaxTransactionId($this->_order->getData('radial_tax_transaction_id'))
	    ->setOrderId($this->_order->getIncrementId())
	    ->setInvoiceNumber($this->_invoice->getIncrementId())
	    ->setInvoiceType($this->_type)
	    ->setOrderDateTime(new DateTime($this->_order->getCreatedAt()))
	    ->setShipDateTime(new DateTime($this->_invoice->getCreatedAt()));
        return $this;
    }

    /**
     * Add data to the request for addresses in the quote.
     *
     * @return self
     */
    protected function _injectAddressData()
    {
        $destinationIterable = $this->_payload->getDestinations();
        $shipGroupIterable = $this->_payload->getShipGroups();
        foreach ($this->_order->getAddressesCollection() as $address) {
	    $addressId = $address->getId();

            // Defer responsibility for building ship group and destination
            // payloads to address request builders.
            $addressBuilder = $this->_taxFactory->createRequestBuilderAddress(
                $shipGroupIterable,
                $destinationIterable,
                $address,
		$this->_invoice
            );

            $destinationPayload = $addressBuilder->getDestinationPayload();
            // Billing addresses need to be set separately in the request payload.
            // The addres request builder should have created the destination
            // for the billing address, even if there were no items to add to
            // to the ship group for the billing address. E.g. a billing address
            // is still a destination so the returned payload will still have a
            // destination but may not be a valid ship group (checked separately).
            if ($addressId == $this->_order->getBillingAddress()->getId()) {
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
