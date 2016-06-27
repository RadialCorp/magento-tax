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

class Radial_Tax_Test_Model_CollectorTest extends EcomDev_PHPUnit_Test_Case
{
    /** @var Radial_Tax_Helper_Data */
    protected $_taxHelper;
    /** @var Radial_Tax_Model_Session */
    protected $_taxSession;
    /** @var Radial_Tax_Model_Record[] */
    protected $_taxRecords = [];
    /** @var Radial_Tax_Model_Duty[] */
    protected $_taxDuties = [];
    /** @var Radial_Tax_Model_Fee[] */
    protected $_taxFees = [];
    /** @var Radial_Tax_Model_Result */
    protected $_taxResult;
    /** @var Radial_Tax_Model_Record */
    protected $_shipTaxRecord;
    /** @var Radial_Tax_Model_Record */
    protected $_merchTaxRecord;
    /** @var Radial_Tax_Model_Record */
    protected $_altMerchTaxRecord;
    /** @var Mage_Sales_Model_Quote_Address */
    protected $_address;
    /** @var Mage_Sales_Model_Quote_Address */
    protected $_billingAddress;
    /** @var int Entity id of the quote address */
    protected $_addressId = 1;
    /** @var int Entity if of some other, alternate address */
    protected $_altAddressId = 3;
    /** @var int Entity id of a quote item */
    protected $_itemId = 9;
    /** @var Mage_Sales_Model_Quote */
    protected $_quote;
    /** @var Radial_Tax_Model_Collector */
    protected $_taxCollector;

    public function setUp()
    {
        // Mock out logging context to prevent session hits while getting log context.
        $logContext = $this->getHelperMock('ebayenterprise_magelog/context');
        $logContext->expects($this->any())
            ->method('getMetaData')
            ->will($this->returnValue([]));

        // Mock out a tax SDK helper - responsible for making the SDK request
        // for tax data.
        $this->_taxHelper = $this->getHelperMock('radial_tax', ['requestTaxesForQuote']);

        // Mock out session storage - constructor disabled to prevent actually
        // a session and trying to write cookies.
        $this->_taxSession = $this->getModelMockBuilder('radial_tax/session')
            ->disableOriginalConstructor()
            ->setMethods([
                'getTaxRecords',
                'setTaxRecords',
                'setTaxDuties',
                'setTaxFees',
                'getTaxRequestSuccess',
                'setTaxRequestSuccess'
            ])
            ->getMock();

        // Create the tax records stored in the session. Ship tax and merch
        // tax records are for the address to be used in tests.
        $this->_shipTaxRecord = $this->_buildMockTaxRecord($this->_addressId);
        $this->_merchTaxRecord = $this->_buildMockTaxRecord($this->_addressId);
        // Alt merch tax record is a tax record for an "alternate" address.
        $this->_altMerchTaxRecord = $this->_buildMockTaxRecord($this->_altAddressId);
        $this->_taxRecords = [$this->_shipTaxRecord, $this->_altMerchTaxRecord, $this->_merchTaxRecord];

        $this->_taxDuties = [$this->_buildMockTaxDuty($this->_itemId)];
        $this->_taxFees = [$this->_buildMockTaxFee($this->_itemId)];

        $this->_taxResult = $this->getModelMockBuilder('radial_tax')
            ->disableOriginalConstructor()
            ->setMethods(['getTaxRecords', 'getTaxDuties', 'getTaxFees'])
            ->getMock();

        $this->_address = $this->getModelMock('sales/quote_address', ['getId']);
        $this->_address->expects($this->any())
            ->method('getId')
            ->will($this->returnValue($this->_addressId));

        // Create a billing address to be used as the quote billing address.
        $this->_billingAddress = $this->getModelMock(
            'sales/quote_address',
            ['getFirstname', 'getLastname', 'getStreetFull', 'getCountryId']
        );

        // Set up the quote to be used for testing tax collection.
        $this->_quote = $this->getModelMock('sales/quote', ['getBillingAddress', 'getItemsCount']);
        $this->_quote->expects($this->any())
            ->method('getBillingAddress')
            ->will($this->returnValue($this->_billingAddress));

        $this->_taxCollector = Mage::getModel(
            'radial_tax/collector',
            [
                'tax_session' => $this->_taxSession,
                'tax_helper' => $this->_taxHelper,
                'log_context' => $logContext,
            ]
        );
    }

    /**
     * Build a mock tax record with the given quote address id.
     *
     * @param int
     * @return Radial_Tax_Model_Record
     */
    protected function _buildMockTaxRecord($quoteAddressId)
    {
        // Create a mock tax record that will return the provided
        // address id.
        // Constructor disabled to prevent needing to provide dependencies.
        $record = $this->getModelMockBuilder('radial_tax/record')
            ->disableOriginalConstructor()
            ->setMethods(['getAddressId'])
            ->getMock();
        $record->expects($this->any())
            ->method('getAddressId')
            ->will($this->returnValue($quoteAddressId));
        return $record;
    }

    /**
     * Build a mock tax record with the given quote address id.
     *
     * @param int
     * @return Radial_Tax_Model_Duty
     */
    protected function _buildMockTaxDuty($itemId)
    {
        // Build a mock duty model that will return the provided
        // item id. Constructor disabled to prevent needing to
        // provide dependencies.
        $duty = $this->getModelMockBuilder('radial_tax/duty')
            ->disableOriginalConstructor()
            ->setMethods(['getItemId'])
            ->getMock();
        $duty->expects($this->any())
            ->method('getItemid')
            ->will($this->returnValue($itemId));
        return $duty;
    }

    /**
     * Build a mock tax record with the given quote address id.
     *
     * @param int
     * @return Radial_Tax_Model_Record
     */
    protected function _buildMockTaxFee($itemId)
    {
        // Build a mock fee model that will return the provided
        // item id. Constructor disabled to prevent needing to
        // provide dependencies.
        $fee = $this->getModelMockBuilder('radial_tax/fee')
            ->disableOriginalConstructor()
            ->setMethods(['getItemId'])
            ->getMock();
        $fee->expects($this->any())
            ->method('getItemId')
            ->will($this->returnValue($itemId));
        return $fee;
    }

    /**
     * Set up the quote and billing address mocks to be considered
     * valid by the tax collector.
     *
     * @return self
     */
    protected function _makeQuoteValid()
    {
        $this->_billingAddress->expects($this->any())
            ->method('getFirstname')
            ->will($this->returnValue('Firstname'));
        $this->_billingAddress->expects($this->any())
            ->method('getLastname')
            ->will($this->returnValue('Lastname'));
        $this->_billingAddress->expects($this->any())
            ->method('getStreetFull')
            ->will($this->returnValue('Street Address'));
        $this->_billingAddress->expects($this->any())
            ->method('getCountryId')
            ->will($this->returnValue('US'));
        $this->_quote->expects($this->any())
            ->method('getItemsCount')
            ->will($this->returnValue(3));
        return $this;
    }

    /**
     * Test getting just tax records for a given address. Only tax records with
     * a matching quote address id should be returned.
     */
    public function testGetTaxRecordsForAddress()
    {
        $this->_taxSession->expects($this->any())
            ->method('getTaxRecords')
            ->will($this->returnValue($this->_taxRecords));

        $recordsForAddress = $this->_taxCollector->getTaxRecordsByAddressId($this->_addressId);
        $this->assertCount(2, $recordsForAddress);
        // Strict in_array checks necessary to prevent attempting to match recursively
        // through the mocked objects, allowing these checks to work - loose checks
        // cause fatal errors due to excessive nesting, possibly recursive structures in mocks.
        $this->assertTrue(in_array($this->_shipTaxRecord, $recordsForAddress, true));
        $this->assertTrue(in_array($this->_merchTaxRecord, $recordsForAddress, true));
        $this->assertFalse(in_array($this->_altMerchTaxRecord, $recordsForAddress, true));
    }

    /**
     * Getting tax records must always return an array, even if there are not
     * tax records to return.
     */
    public function testGetTaxRecordsAlwaysReturnsArray()
    {
        // Simulate session having no records set - returns null.
        $this->_taxSession->expects($this->any())
            ->method('getTaxRecords')
            ->will($this->returnValue(null));
        // When no tax records are in the session, mult still return an array.
        $this->assertSame([], $this->_taxCollector->getTaxRecords());
    }

    /**
     * When new tax records are requested to be collected for a quote, all
     * tax records returned from the SDK (helper) should replace any existing
     * tax records in storage.
     */
    public function testCollectTaxes()
    {
        $this->_makeQuoteValid();
        // Simulate the TDK request returning a set of tax records.
        $this->_taxHelper->expects($this->once())
            ->method('requestTaxesForQuote')
            ->with($this->identicalTo($this->_quote))
            ->will($this->returnValue($this->_taxResult));
        // Set the tax result to return expected sets of tax records, duties
        // and fees.
        $this->_taxResult->expects($this->any())
            ->method('getTaxRecords')
            ->will($this->returnValue($this->_taxRecords));
        $this->_taxResult->expects($this->any())
            ->method('getTaxDuties')
            ->will($this->returnValue($this->_taxDuties));
        $this->_taxResult->expects($this->any())
            ->method('getTaxFees')
            ->will($this->returnValue($this->_taxFees));
        // Side-effect tests - ensure all tax records, duties and fees in the
        // session are replaced by the set of tax data from the result set
        // returned from the request to update tax data.
        $this->_taxSession->expects($this->once())
            ->method('setTaxRecords')
            ->with($this->identicalTo($this->_taxRecords))
            ->will($this->returnSelf());
        $this->_taxSession->expects($this->once())
            ->method('setTaxDuties')
            ->with($this->identicalTo($this->_taxDuties))
            ->will($this->returnSelf());
        $this->_taxSession->expects($this->once())
            ->method('setTaxFees')
            ->with($this->identicalTo($this->_taxFees))
            ->will($this->returnSelf());
        // Side-effect test - ensure that when a tax request was made successfully,
        // that a flag indicating that is set in the session.
        $this->_taxSession->expects($this->once())
            ->method('setTaxRequestSuccess')
            ->with($this->identicalTo(true))
            ->will($this->returnSelf());

        $this->_taxCollector->collectTaxes($this->_quote);
    }

    /**
     * When taxes are requested but the request is not successful, a flag should
     * be set in the session indicating that the last tax request was not
     * successful.
     */
    public function testCollectTaxesFailedRequest()
    {
        $this->_makeQuoteValid();
        // Simulate the TDK request returning a set of tax records.
        $this->_taxHelper->expects($this->once())
            ->method('requestTaxesForQuote')
            ->with($this->identicalTo($this->_quote))
            ->will($this->throwException(new Radial_Tax_Exception_Collector_Exception));
        // Side-effect test - ensure all tax records in the session storage are
        // emptied of existing tax records when a tax request fails to be made.
        $this->_taxSession->expects($this->once())
            ->method('setTaxRecords')
            ->with($this->identicalTo([]))
            ->will($this->returnSelf());
        $this->_taxSession->expects($this->once())
            ->method('setTaxDuties')
            ->with($this->identicalTo([]))
            ->will($this->returnSelf());
        $this->_taxSession->expects($this->once())
            ->method('setTaxFees')
            ->with($this->identicalTo([]))
            ->will($this->returnSelf());
        // Side-effect test - ensure that when a tax request was made successfully,
        // that a flag indicating that is set in the session.
        $this->_taxSession->expects($this->once())
            ->method('setTaxRequestSuccess')
            ->with($this->identicalTo(false))
            ->will($this->returnSelf());

        $this->setExpectedException('Radial_Tax_Exception_Collector_Exception');
        $this->_taxCollector->collectTaxes($this->_quote);
    }

    /**
     * When the quote is invalid for making a tax request, no request should
     * be attempted and existing tax records should be cleared - reset
     * to empty arrays.
     *
     * By default, the quote used in the tests will be considered to be invalid
     * for tax requests - no item count as well as missing address data.
     */
    public function testCollectTaxesFailedInvalidQuote()
    {
        // Simulate the TDK request returning a set of tax records.
        $this->_taxHelper->expects($this->never())
            ->method('requestTaxesForQuote');
        // Side-effect test - ensure all tax records in the session storage are
        // emptied of existing tax records when a tax request fails to be made.
        $this->_taxSession->expects($this->once())
            ->method('setTaxRecords')
            ->with($this->identicalTo([]))
            ->will($this->returnSelf());
        $this->_taxSession->expects($this->once())
            ->method('setTaxDuties')
            ->with($this->identicalTo([]))
            ->will($this->returnSelf());
        $this->_taxSession->expects($this->once())
            ->method('setTaxFees')
            ->with($this->identicalTo([]))
            ->will($this->returnSelf());
        // Side-effect test - ensure that when a tax request was made successfully,
        // that a flag indicating that is set in the session.
        $this->_taxSession->expects($this->once())
            ->method('setTaxRequestSuccess')
            ->with($this->identicalTo(false))
            ->will($this->returnSelf());

        $this->setExpectedException('Radial_Tax_Exception_Collector_InvalidQuote_Exception');
        $this->_taxCollector->collectTaxes($this->_quote);
    }
}
