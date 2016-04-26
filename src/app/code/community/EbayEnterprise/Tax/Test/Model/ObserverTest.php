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

class EbayEnterprise_Tax_Test_Model_ObserverTest extends EcomDev_PHPUnit_Test_Case
{
    /** @var EbayEnterprise_Eb2cCre_Model_Session */
    protected $coreSession;
    /** @var EbayEnterprise_Tax_Model_Collector */
    protected $taxCollector;
    /** @var Mage_Sales_Model_Quote */
    protected $quote;
    /** @var Varien_Event */
    protected $event;
    /** @var Varien_Event_Observer */
    protected $eventObserver;
    /** @var EbayEnterprise_Tax_Model_Observer */
    protected $taxObserver;

    public function setUp()
    {
        // Mock log context to prevent session hits when collecting log metadata.
        $logContext = $this->getHelperMock('ebayenterprise_magelog/context', ['getMetaData']);
        $logContext->expects($this->any())
            ->method('getMetaData')
            ->will($this->returnValue([]));

        $this->coreSession = $this->getModelMockBuilder('eb2ccore/session')
            ->disableOriginalConstructor()
            ->setMethods(['isTaxUpdateRequired', 'updateWithQuote', 'resetTaxUpdateRequired'])
            ->getMock();
        $this->taxCollector = $this->getModelMock('ebayenterprise_tax/collector', ['collectTaxes']);
        $this->quote = $this->getModelMock('sales/quote', ['collectTotals']);

        $this->event = new Varien_Event(['quote' => $this->quote]);
        $this->eventObserver = new Varien_Event_Observer(['event' => $this->event]);

        $this->taxObserver = Mage::getModel(
            'ebayenterprise_tax/observer',
            ['core_session' => $this->coreSession, 'tax_collector' => $this->taxCollector, 'log_context' => $logContext]
        );
    }

    /**
     * Test that when taxes are successfully collected, session data and flags
     * are updated and quote total re-collection is triggered.
     */
    public function testHandleSalesQuoteCollectTotalsAfterSuccess()
    {
        // Eb2cCore_Session Interactions
        // Tax update required flag should be checked before doing anything.
        // Should only collect new taxes when true.
        $this->coreSession->expects($this->any())
            ->method('isTaxUpdateRequired')
            ->will($this->returnValue(true));
        // When the TDF request succeeds, session store of quote data should
        // get updated so quote will reflect state of quote taxes were collected for.
        $this->coreSession->expects($this->once())
            ->method('updateWithQuote')
            ->with($this->identicalTo($this->quote))
            ->will($this->returnSelf());
        // After a successful TDF request, the flag to update tax data should be
        // reset so taxes are not requested again until the quote changes to
        // require a new tax request.
        $this->coreSession->expects($this->once())
            ->method('resetTaxUpdateRequired')
            ->will($this->returnSelf());

        // So long as the tax collector doesn't throw an exception, should
        // consider taxes to have been successfully collected.
        $this->taxCollector->expects($this->once())
            ->method('collectTaxes')
            ->with($this->identicalTo($this->quote))
            ->will($this->returnSelf());

        // Expect that when tax records have been collected, quote totals should
        // be recollected.
        $this->quote->expects($this->once())
            ->method('collectTotals')
            ->will($this->returnSelf());

        $this->taxObserver->handleSalesQuoteCollectTotalsAfter($this->eventObserver);
    }

    /**
     * When tax updates are not required, no new tax data should be collected,
     * session data should not be updated and quote totals do not need to be
     * recollected.
     */
    public function testHandleSalesQuoteCollectTotalsNoTaxUpdate()
    {
        // Indicate tax updates are not required in the session flags.
        $this->coreSession->expects($this->any())
            ->method('isTaxUpdateRequired')
            ->will($this->returnValue(false));
        // Ensure that taxes are not re-collected, session data is not updated
        // and quote totals are not re-collected.
        $this->coreSession->expects($this->never())
            ->method('updateWithQuote');
        $this->taxCollector->expects($this->never())
            ->method('collectTaxes');
        $this->quote->expects($this->never())
            ->method('collectTotals');

        $this->taxObserver->handleSalesQuoteCollectTotalsAfter($this->eventObserver);
    }

    /**
     * When the tax collector fails to collect new tax data, the session should
     * not get updated and quote totals should not be recollected.
     */
    public function testHandleSalesQuoteCollectTotalsCollectFailure()
    {
        // Indicate new tax collection is required in the session flags.
        $this->coreSession->expects($this->any())
            ->method('isTaxUpdateRequired')
            ->will($this->returnValue(true));

        // Set the tax collector to fail to make the TDF request and throw
        // an exception.
        $this->taxCollector->expects($this->once())
            ->method('collectTaxes')
            ->will($this->throwException(Mage::exception('EbayEnterprise_Tax_Exception_Collector')));

        // Ensure session quote data is not updated, flags are not reset and
        // quote totals are not re-collected.
        $this->coreSession->expects($this->never())
            ->method('updateWithQuote');
        $this->coreSession->expects($this->never())
            ->method('resetTaxUpdateRequired');
        $this->quote->expects($this->never())
            ->method('collectTotals');

        $this->taxObserver->handleSalesQuoteCollectTotalsAfter($this->eventObserver);
    }
}
