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

class Radial_Tax_Test_Model_Order_Create_OrderTest extends EcomDev_PHPUnit_Test_Case
{
    /** @var EbayEnteprise_Tax_Model_Collector */
    protected $_taxCollector;
    /** @var Radial_Tax_Model_Record[] */
    protected $_taxRecords = [];
    /** @var Radial_Tax_Model_Record */
    protected $_validTaxRecord;
    /** @var Radial_Tax_Model_Record */
    protected $_errorTaxRecord;
    /** @var eBayEnterprise\RetailOrderManagement\Payload\Order\IOrderCreateRequest */
    protected $_orderCreateRequest;
    /** @var Mage_Sales_Model_Order */
    protected $_order;
    /** @var Radial_Tax_Model_Order_Create_Order */
    protected $_orderCreateHandler;

    public function setUp()
    {
        // Create a mock tax collector to provide tax duties and overall
        // tax request success. Constructor disabled to prevent needing
        // to provide dependencies.
        $this->_taxCollector = $this->getModelMockBuilder('radial_tax/collector')
            ->disableOriginalConstructor()
            ->setMethods(['getTaxDuties', 'getTaxRequestSuccess'])
            ->getMock();
        // Valid record should not return a value for the calculation error.
        // Constructor disabled to prevent needing to provide dependencies.
        $this->_validTaxRecord = $this->getModelMockBuilder('radial_tax/record')
            ->disableOriginalConstructor()
            ->setMethods(['getCalculationError'])
            ->getMock();
        // Error record should return some value for the calculation error.
        // Constructor disabled to prevent needing to provide dependencies.
        $this->_errorTaxRecord = $this->getModelMockBuilder('radial_tax/record')
            ->disableOriginalConstructor()
            ->setMethods(['getCalculationError'])
            ->getMock();
        $this->_errorTaxRecord->expects($this->any())
            ->method('getCalculationError')
            ->will($this->returnValue('Calculation Error'));

        $this->_order = Mage::getModel('sales/order');

        $this->_orderCreateRequest = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Payload\Order\IOrderCreateRequest',
            ['setTaxHasErrors']
        );

        $this->_orderCreateHandler = Mage::getModel(
            'radial_tax/order_create_order',
            [
                'tax_collector' => $this->_taxCollector,
                'order' => $this->_order,
                'order_create_request' => $this->_orderCreateRequest,
            ]
        );
    }

    /**
     * When there are no errors in the collector or in any of the collected tax
     * records, the "tax has errors" flag should be set to false on the order
     * create request.
     */
    public function testTaxHeaderWhenNoErrors()
    {
        // Mock the tax collector to return an array of tax all valid tax records.
        $this->_taxRecords = [$this->_validTaxRecord];
        $this->_taxCollector->expects($this->any())
            ->method('getTaxDuties')
            ->will($this->returnValue($this->_taxRecords));

        $this->_taxCollector->expects($this->any())
            ->method('getTaxRequestSuccess')
            ->will($this->returnValue(true));

        $this->_orderCreateRequest->expects($this->once())
            ->method('setTaxHasErrors')
            ->with($this->identicalTo(false))
            ->will($this->returnSelf());

        $this->_orderCreateHandler->addTaxHeaderErrorFlag();
    }

    /**
     * When the tax collector has errors - taxes were unable to be collected or
     * other general error in tax collection - the "tax has errors" flag should
     * be set on the order create request.
     */
    public function testTaxHeaderSetWhenCollectionErrors()
    {
        $this->_taxCollector->expects($this->any())
            ->method('getTaxRequestSuccess')
            ->will($this->returnValue(false));

        // Mock the tax collector to return an array of tax all valid tax records.
        // Even if all records are free of errors, if the tax collector has record
        // of there being a general error, the error flag should till be added.
        $this->_taxRecords = [$this->_validTaxRecord];
        $this->_taxCollector->expects($this->any())
            ->method('getTaxDuties')
            ->will($this->returnValue($this->_taxRecords));

        $this->_orderCreateRequest->expects($this->once())
            ->method('setTaxHasErrors')
            ->with($this->identicalTo(true))
            ->will($this->returnSelf());

        $this->_orderCreateHandler->addTaxHeaderErrorFlag();
    }

    /**
     * When the tax collector has errors - taxes were unable to be collected or
     * other general error in tax collection - the "tax has errors" flag should
     * be set on the order create request.
     */
    public function testTaxHeaderSetWhenRecordsHaveErrors()
    {
        // Add good and bad tax records to the collector. The bad record should
        // trigger the flag to be set.
        $this->_taxRecords = [$this->_validTaxRecord, $this->_errorTaxRecord];
        // Mock the tax collector to return the array of tax records containing
        // at least one error tax record.
        $this->_taxCollector->expects($this->any())
            ->method('getTaxDuties')
            ->will($this->returnValue($this->_taxRecords));

        // Set there to be no errors in collecting taxes (prevent duplicate of
        // case in testTaxHeaderSetWhenCollectionErrors test).
        $this->_taxCollector->expects($this->any())
            ->method('getTaxRequestSuccess')
            ->will($this->returnValue(true));

        $this->_orderCreateRequest->expects($this->once())
            ->method('setTaxHasErrors')
            ->with($this->identicalTo(true))
            ->will($this->returnSelf());

        $this->_orderCreateHandler->addTaxHeaderErrorFlag($this->_orderCreateRequest);
    }
}
