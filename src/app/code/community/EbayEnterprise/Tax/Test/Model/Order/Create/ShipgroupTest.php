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

class EbayEnterprise_Tax_Test_Model_Order_Create_ShipgroupTest extends EcomDev_PHPUnit_Test_Case
{
    /** @var EbayEnterprise_Tax_Model_Collector */
    protected $_taxCollector;
    /** @var EbayEnterprise_Tax_Helper_Payload */
    protected $_payloadHelper;
    /** @var Mage_Sales_Model_Order_Address */
    protected $_address;
    /** @var int Quote address id of the address being processed. */
    protected $_quoteAddressId = 89;
    /** @var eBayEnterprise\RetailOrderManagement\Payload\Order\IGifting */
    protected $_shipGroup;
    /** @var eBayEnterprise\RetailOrderManagement\Payload\Order\ITaxContainer */
    protected $_taxContainer;
    /** @var eBayEnterprise\RetailOrderManagement\Payload\Order\ITaxIterable */
    protected $_taxIterable;
    /** @var eBayEnterprise\RetailOrderManagement\Payload\Order\ITax */
    protected $_taxPayload;
    /** @var eBayEnterprise\RetailOrderManagement\Payload\Order\ITax */
    protected $_completeTaxPayload;
    /** @var EbayEnteprrise_Tax_Model_Record Tax record for address level gifting tax. */
    protected $_addressGiftTax;
    /** @var EbayEnteprrise_Tax_Model_Record Tax record for item level gifting tax. */
    protected $_itemGiftTax;
    /** @var EbayEnteprrise_Tax_Model_Record Tax record for non-gifting tax. */
    protected $_merchGiftTax;
    /** @var EbayEnterprise_Tax_Model_Record[] */
    protected $_taxRecords;
    /** @var EbayEnterprise_Tax_Model_Order_Create_Shipgroup */
    protected $_shipGroupHandler;

    public function setUp()
    {
        $this->_taxCollector = $this->getModelMock(
            'ebayenterprise_tax/collector',
            ['getTaxRecords']
        );
        $this->_payloadHelper = $this->getHelperMock(
            'ebayenterprise_tax/payload',
            ['taxRecordToTaxPayload']
        );
        $this->_address = $this->getModelMock('sales/order_address', ['getQuoteAddressId']);
        $this->_address->expects($this->any())
            ->method('getQuoteAddressId')
            ->will($this->returnValue($this->_quoteAddressId));

        // SDK Payloads that are expected to be filled out.
        $this->_shipGroup = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Payload\Order\IGifting',
            ['getGiftPricing', 'getEmptyGiftingPriceGroup', 'setGiftPricing']
        );
        $this->_taxContainer = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Payload\Order\IPriceGroup',
            ['getTaxes', 'setTaxes']
        );
        $this->_taxIterable = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Payload\Order\ITaxIterable',
            ['offsetSet', 'getEmptyTax']
        );
        $this->_taxPayload = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Payload\Order\ITax'
        );
        // Create this as the expected tax payload to be added to the
        // tax iterable - assume this to be a tax payload with all data
        // from a tax record set to it.
        $this->_completeTaxPayload = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Payload\Order\ITax'
        );

        // The tax container will always return a tax iterable.
        $this->_taxContainer->expects($this->any())
            ->method('getTaxes')
            ->will($this->returnValue($this->_taxIterable));

        $this->_addressGiftTax = $this->_mockTaxRecord(
            EbayEnterprise_Tax_Model_Record::SOURCE_ADDRESS_GIFTING,
            $this->_quoteAddressId
        );
        $this->_itemGiftTax = $this->_mockTaxRecord(
            EbayEnterprise_Tax_Model_Record::SOURCE_ITEM_GIFTING,
            $this->_quoteAddressId
        );
        $this->_merchGiftTax = $this->_mockTaxRecord(
            EbayEnterprise_Tax_Model_Record::SOURCE_MERCHANDISE,
            $this->_quoteAddressId
        );

        $this->_shipGroupHandler = Mage::getModel(
            'ebayenterprise_tax/order_create_shipgroup',
            [
                'address' => $this->_address,
                'ship_group' => $this->_shipGroup,
                'tax_collector' => $this->_taxCollector,
                'payload_helper' => $this->_payloadHelper,
            ]
        );
    }

    /**
     * Mock a tax record to return the expected tax source and address id.
     */
    protected function _mockTaxRecord($taxSource, $addressId)
    {
        // Create a mock tax record that will return the provided source
        // and address id. Constructor disabled to prevent needing to provide
        // dependencies.
        $record = $this->getModelMockBuilder('ebayenterprise_tax/record')
            ->disableOriginalConstructor()
            ->setMethods(['getAddressId', 'getTaxSource'])
            ->getMock();
        $record->expects($this->any())
            ->method('getTaxSource')
            ->will($this->returnValue($taxSource));
        $record->expects($this->any())
            ->method('getAddressId')
            ->will($this->returnValue($addressId));
        return $record;
    }

    /**
     * When adding gift taxes to a payload, gifting taxes collected for
     * the address should be added to the ship group payload.
     */
    public function testAddGiftTaxesToPayload()
    {
        // Set up happy path for test - ship group has a tax container...
        $this->_shipGroup->expects($this->any())
            ->method('getGiftPricing')
            ->will($this->returnValue($this->_taxContainer));
        // ...tax collector returns tax records.
        $this->_taxCollector->expects($this->any())
            ->method('getTaxRecords')
            ->will($this->returnValue(
                [$this->_itemGiftTax, $this->_addressGiftTax, $this->_merchGiftTax]
            ));

        // Let the tax payload helper return the expected tax payload when
        // given the expected tax record.
        $this->_payloadHelper->expects($this->any())
            ->method('taxRecordToTaxPayload')
            ->with($this->identicalTo($this->_addressGiftTax))
            ->will($this->returnValue($this->_completeTaxPayload));

        // Side-effect test: the tax payload to be populated with data
        // must come from the tax iterable it will be added to. There
        // should be a single record to add for taxes so at least one
        // tax payload must come from the iterable.
        $this->_taxIterable->expects($this->atLeastOnce())
            ->method('getEmptyTax')
            ->will($this->returnValue($this->_taxPayload));
        // Side-effect tests: ensure the expected, complete tax payload is added
        // to the tax iterable.
        $this->_taxIterable->expects($this->once())
            ->method('offsetSet')
            ->with($this->identicalTo($this->_completeTaxPayload), $this->anything())
            ->will($this->returnValue(null));
        // Side-effect test: ensure the tax iterable is set to the tax container.
        $this->_taxContainer->expects($this->once())
            ->method('setTaxes')
            ->with($this->identicalTo($this->_taxIterable))
            ->will($this->returnSelf());

        $this->assertSame(
            $this->_shipGroupHandler,
            $this->_shipGroupHandler->addGiftTaxesToPayload()
        );
    }

    /**
     * When none of the collected tax records apply to the ship group,
     * no taxes should be added to the tax container.
     */
    public function testAddGiftTaxesToPayloadNoTaxesToAdd()
    {
        // Set the tax collector to have no relevent taxes to add to the ship group.
        $this->_taxCollector->expects($this->any())
            ->method('getTaxRecords')
            ->will($this->returnValue([$this->_itemGiftTax, $this->_merchGiftTax]));
        // Ship group can already have a tax container.
        $this->_shipGroup->expects($this->any())
            ->method('getGiftPricing')
            ->will($this->returnValue($this->_taxContainer));

        // Side-effect tests: There should be no tax records for the ship
        // group so no tax payloads should be added to the iterable.
        $this->_taxIterable->expects($this->never())
            ->method('offsetSet');

        $this->_shipGroupHandler->addGiftTaxesToPayload();
    }

    /**
     * If a tax container doesn't exist for the ship group, a new,
     * empty tax container should be created and added.
     */
    public function testAddEmptyTaxContainerIfNoneExists()
    {
        // Set the ship group to not yet have a tax container (gift pricing)
        $this->_shipGroup->expects($this->any())
            ->method('getGiftPricing')
            ->will($this->returnValue(null));
        // Side-effect test: a new tax container must be created by the ship
        // group payload.
        $this->_shipGroup->expects($this->atLeastOnce())
            ->method('getEmptyGiftingPriceGroup')
            ->will($this->returnValue($this->_taxContainer));
        // Side-effect test: the new tax container must be set back to the
        // ship group payload as the tax container (gift pricing) payload.
        $this->_shipGroup->expects($this->once())
            ->method('setGiftPricing')
            ->with($this->identicalTo($this->_taxContainer))
            ->will($this->returnSelf());

        // Ensure the newly created tax container is returned to be
        // populted with tax data.
        $this->assertSame(
            $this->_taxContainer,
            EcomDev_Utils_Reflection::invokeRestrictedMethod(
                $this->_shipGroupHandler,
                '_getTaxContainer'
            )
        );
    }
}
