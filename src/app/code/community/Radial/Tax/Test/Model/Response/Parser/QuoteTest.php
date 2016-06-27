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

class Radial_Tax_Test_Model_Response_Parser_QuoteTest extends EcomDev_PHPUnit_Test_Case
{
    /** @var Radial_Tax_Model_Helper_Factory */
    protected $_taxFactory;
    /** @var eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeResponse */
    protected $_taxResponsePayload;
    /** @var Iterable $_shipGroups Should be IShipGroupIterable but swapping out for just the ITerable interface required. */
    protected $_shipGroups;
    /** @var eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IShipGroup */
    protected $_shipGroup;
    /** @var eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IDestination */
    protected $_shipDestination;
    /** @var int */
    protected $_destinationId = '_9';
    /** @var Mage_Sales_Model_Quote */
    protected $_quote;
    /** @var Mage_Sales_Model_Resource_Quote_Address_Collection */
    protected $_addressCollection;
    /** @var Mage_Sales_Model_Quote_Address */
    protected $_address;
    /** @var Radial_Tax_Model_Response_Parser_Address */
    protected $_addressParser;
    /** @var Radial_Tax_Model_Response_Parser_Quote */
    protected $_quoteParser;

    public function setUp()
    {
        // Mock the log context helper to prevent session hits when
        // getting log metadata.
        $logContext = $this->getHelperMock('ebayenterprise_magelog/context', ['getMetaData']);
        $logContext->expects($this->any())
            ->method('getMetaData')
            ->will($this->returnValue([]));

        // SDK Payloads
        $this->_taxResponsePayload = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteReply',
            ['getShipGroups']
        );
        $this->_shipGroup = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedShipGroup',
            ['getDestination']
        );
        $this->_shipGroups = new ArrayIterator([$this->_shipGroup]);
        $this->_shipDestination = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IDestination',
            ['getId']
        );

        // Link together the payloads.
        $this->_taxResponsePayload->expects($this->any())
            ->method('getShipGroups')
            ->will($this->returnValue($this->_shipGroups));
        $this->_shipGroup->expects($this->any())
            ->method('getDestination')
            ->will($this->returnValue($this->_shipDestination));
        $this->_shipDestination->expects($this->any())
            ->method('getId')
            ->will($this->returnValue($this->_destinationId));

        // Mocks needed to map ship groups to quote addresses.
        $this->_quote = $this->getModelMock('sales/quote', ['getAddressesCollection']);
        $this->_addressCollection = $this->getResourceModelMock('sales/quote_address_collection', ['getItemByColumnValue']);
        $this->_address = Mage::getModel('sales/quote_address');

        $this->_quote->expects($this->any())
            ->method('getAddressesCollection')
            ->will($this->returnValue($this->_addressCollection));
        // The address collection should return the expected address when given
        // the expected destination id. Otherwise, should return null.
        $this->_addressCollection->expects($this->any())
            ->method('getItemByColumnValue')
            ->will($this->returnValueMap([
                ['destination_id', $this->_destinationId, $this->_address],
            ]));

        // Mocks for building tax records for ship groups.
        $this->_taxFactory = $this->getHelperMock('radial_tax/factory', ['createResponseAddressParser']);

        // Create an address response parser to synthesize extracting additional
        // tax data from addresses. Constructor disabled to prevent needing to
        // provide dependencies.
        $this->_addressParser = $this->getModelMockBuilder('radial_tax/response_parser_address')
            ->disableOriginalConstructor()
            ->setMethods(['getTaxRecords', 'getTaxDuties', 'getTaxFees'])
            ->getMock();

        // Create some mock tax data - record, duty and fee - to stand in for tax data
        // extracted from an address. All constructors disabled to prevent needing to
        // provide dependencies.
        $this->_addressTaxRecord = $this->getModelMockBuilder('radial_tax/record')
            ->disableOriginalConstructor()
            ->getMock();
        $this->_addressTaxDuty = $this->getModelMockBuilder('radial_tax/duty')
            ->disableOriginalConstructor()
            ->getMock();
        $this->_addressTaxFee = $this->getModelMockBuilder('radial_tax/fee')
            ->disableOriginalConstructor()
            ->getMock();

        $this->_addressTaxRecords = [$this->_addressTaxRecord];
        $this->_addressTaxDuties = [$this->_addressTaxDuty];
        $this->_addressTaxFees = [$this->_addressTaxFee];

        // Set the expected address parser to return the mocked up set of
        // address tax records.
        $this->_addressParser->expects($this->any())
            ->method('getTaxRecords')
            ->will($this->returnValue($this->_addressTaxRecords));
        $this->_addressParser->expects($this->any())
            ->method('getTaxDuties')
            ->will($this->returnValue($this->_addressTaxDuties));
        $this->_addressParser->expects($this->any())
            ->method('getTaxFees')
            ->will($this->returnValue($this->_addressTaxFees));

        $this->_quoteParser = Mage::getModel(
            'radial_tax/response_parser_quote',
            [
                'quote' => $this->_quote,
                'tax_response' => $this->_taxResponsePayload,
                'tax_factory' => $this->_taxFactory,
                'log_context' => $logContext,
            ]
        );
    }

    /**
     * Getting tax records for a quote should extract all tax records for all
     * addresses belonging to that quote.
     */
    public function testBuildingTaxRecordsForQuote()
    {
        // Set the tax factory to expect to get the expected ship group and
        // address and return the address parser that will give the expected
        // address tax records.
        $this->_taxFactory->expects($this->once())
            ->method('createResponseAddressParser')
            ->with($this->identicalTo($this->_shipGroup, $this->_address))
            ->will($this->returnValue($this->_addressParser));

        // In this case there is one ship group, related to one address so the
        // tax records returned, should be the tax records for that address.
        $this->assertSame($this->_addressTaxRecords, $this->_quoteParser->getTaxRecords());
        $this->assertSame($this->_addressTaxDuties, $this->_quoteParser->getTaxDuties());
        $this->assertSame($this->_addressTaxFees, $this->_quoteParser->getTaxFees());
    }
}
