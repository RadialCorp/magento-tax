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

class EbayEnterprise_Tax_Test_Model_Total_Quote_Address_TaxTest extends EcomDev_PHPUnit_Test_Case
{
    /** @var Mage_Sales_Model_Quote_Address */
    protected $_address;
    /** @var int */
    protected $_addressId = 4;
    /** @var EbayEnterprise_Tax_Model_Quote_Address_Tax */
    protected $_taxTotal;
    /** @var EbayEnterprise_Tax_Model_Collector */
    protected $_taxCollector;
    /** @var EbayEnterprise_Tax_Model_Record */
    protected $_salesTaxRecord;
    /** @var EbayEnterprise_Tax_Model_Record */
    protected $_shipTaxRecord;
    /** @var EbayEnterprise_Tax_Model_Record[] */
    protected $_taxRecords;
    /** @var float */
    protected $_salesTaxAmount = 3.00;
    /** @var float */
    protected $_shipTaxAmount = 2.00;
    /** @var float */
    protected $_dutyAmount = 1.00;
    /** @var float */
    protected $_feeAmount = 2.00;
    /** @var float */
    protected $_calculatedTaxAmount = 5.00;
    /** @var float */
    protected $_totalTaxAmount = 8.00;

    public function setUp()
    {
        $logContext = $this->getHelperMock('ebayenterprise_magelog/context', ['getMetaData']);
        $logContext->expects($this->any())
            ->method('getMetaData')
            ->will($this->returnValue([]));

        $this->_address = $this->getModelMock(
            'sales/quote_address',
            ['addTotal', 'setTotalAmount', 'setBaseTotalAmount', 'getTotalAmount', 'getId']
        );
        $this->_address->expects($this->any())
            ->method('getId')
            ->will($this->returnValue($this->_addressId));

        $this->_helper = $this->getHelperMock('ebayenterprise_tax/data', ['__']);
        $this->_helper->expects($this->any())
            ->method('__')
            ->will($this->returnArgument(0));

        $this->_salesTaxRecord = $this->_buildMockTaxRecord($this->_salesTaxAmount);
        $this->_shipTaxRecord = $this->_buildMockTaxRecord($this->_shipTaxAmount);
        $this->_taxRecords = [$this->_salesTaxRecord, $this->_shipTaxRecord];

        $this->_duty = $this->_buildMockTaxDuty($this->_dutyAmount);
        $this->_duties = [$this->_duty];

        $this->_fee = $this->_buildMockTaxFee($this->_feeAmount);
        $this->_fees = [$this->_fee];

        $this->_taxCollector = $this->getModelMock(
            'ebayenterprise_tax/collector',
            ['getTaxRecordsByAddressId', 'getTaxDutiesByAddressId', 'getTaxFeesByAddressId']
        );

        $this->_taxTotal = Mage::getModel(
            'ebayenterprise_tax/total_quote_address_tax',
            [
                'tax_collector' => $this->_taxCollector,
                'helper' => $this->_helper,
                'log_context' => $logContext,
            ]
        );
    }

    /**
     * Build a mock tax record scripted to return the provided calculated tax amount.
     *
     * @param float
     * @return EbayEnterprise_Tax_Model_Record
     */
    protected function _buildMockTaxRecord($calculatedTaxAmount)
    {
        $record = $this->getModelMockBuilder('ebayenterprise_tax/record')
            // Disable constructor to prevent the need to construct tax records with
            // a full tax payload, quote and quote address id.
            ->disableOriginalConstructor()
            ->setMethods(['getCalculatedTax'])
            ->getMock();
        $record->expects($this->any())
            ->method('getCalculatedTax')
            ->will($this->returnValue($calculatedTaxAmount));
        return $record;
    }

    /**
     * Build a mock tax duty scripted to return the provided amount.
     *
     * @param float
     * @return EbayEnterprise_Tax_Model_Duty
     */
    protected function _buildMockTaxDuty($dutyAmount)
    {
        $record = $this->getModelMockBuilder('ebayenterprise_tax/duty')
            // Disable constructor to prevent the need to construct tax records with
            // a full tax payload, quote and quote address id.
            ->disableOriginalConstructor()
            ->setMethods(['getAmount'])
            ->getMock();
        $record->expects($this->any())
            ->method('getAmount')
            ->will($this->returnValue($dutyAmount));
        return $record;
    }

    /**
     * Build a mock tax fee scripted to return the provided amount.
     *
     * @param float
     * @return EbayEnterprise_Tax_Model_Fee
     */
    protected function _buildMockTaxFee($feeAmount)
    {
        $record = $this->getModelMockBuilder('ebayenterprise_tax/fee')
            // Disable constructor to prevent the need to construct tax records with
            // a full tax payload, quote and quote address id.
            ->disableOriginalConstructor()
            ->setMethods(['getAmount'])
            ->getMock();
        $record->expects($this->any())
            ->method('getAmount')
            ->will($this->returnValue($feeAmount));
        return $record;
    }

    /**
     * Test that fetching totals will add an array of total data to the address
     * object.
     */
    public function testFetch()
    {
        // Setup the address to have a total amount for the taxes.
        $this->_address->expects($this->any())
            ->method('getTotalAmount')
            ->with($this->identicalTo($this->_taxTotal->getCode()))
            ->will($this->returnValue($this->_totalTaxAmount));
        $expectedTotals = [
            'code' => $this->_taxTotal->getCode(),
            'title' => EbayEnterprise_Tax_Model_Total_Quote_Address_Tax::TAX_TOTAL_TITLE,
            'value' => $this->_totalTaxAmount,
        ];
        // Side-effect test - need to ensure the tax total data is added to
        // the address.
        $this->_address->expects($this->once())
            ->method('addTotal')
            ->with($this->identicalTo($expectedTotals))
            ->will($this->returnSelf());
        $this->_taxTotal->fetch($this->_address);
    }

    /**
     * When there are no tax totals to add - either no records or the calculated
     * tax amount is 0.00, no tax total data should be added to the address.
     */
    public function testFetchNoTaxData()
    {
        // Setup the address to have no total amount for the taxes.
        $this->_address->expects($this->any())
            ->method('getTotalAmount')
            ->with($this->identicalTo($this->_taxTotal->getCode()))
            ->will($this->returnValue(0));

        // Side-effect test - ensure that no tax data is added to the address.
        $this->_address->expects($this->never())
            ->method('addTotal');

        $this->_taxTotal->fetch($this->_address);
    }

    /**
     * Test collected tax totals for an address. Should gather tax records from
     * the tax collector and set relevent fields on the address.
     */
    public function testCollect()
    {
        // Setup tax collector to return some tax records.
        $this->_taxCollector->expects($this->any())
            ->method('getTaxRecordsByAddressId')
            ->with($this->identicalTo($this->_addressId))
            ->will($this->returnValue($this->_taxRecords));
        $this->_taxCollector->expects($this->any())
            ->method('getTaxDutiesByAddressId')
            ->with($this->identicalTo($this->_addressId))
            ->will($this->returnValue($this->_duties));
        $this->_taxCollector->expects($this->any())
            ->method('getTaxFeesByAddressId')
            ->with($this->identicalTo($this->_addressId))
            ->will($this->returnValue($this->_fees));

        // Side-effect test - address should have total amount set for the
        // tax total.
        $this->_address->expects($this->once())
            ->method('setTotalAmount')
            ->with($this->identicalTo($this->_taxTotal->getCode()), $this->identicalTo($this->_totalTaxAmount))
            ->wilL($this->returnSelf());
        // Side-effect test - address should have base total amount set for the
        // tax total.
        $this->_address->expects($this->once())
            ->method('setBaseTotalAmount')
            ->with($this->identicalTo($this->_taxTotal->getCode()), $this->identicalTo($this->_totalTaxAmount))
            ->wilL($this->returnSelf());

        $this->_taxTotal->collect($this->_address);
    }

    /**
     * Test totaling the collected taxes for a set of tax records.
     */
    public function testTotalTaxRecords()
    {
        $this->assertSame(
            $this->_calculatedTaxAmount,
            EcomDev_Utils_Reflection::invokeRestrictedMethod($this->_taxTotal, '_totalTaxRecordsCalculatedTaxes', [$this->_taxRecords])
        );
    }
}
