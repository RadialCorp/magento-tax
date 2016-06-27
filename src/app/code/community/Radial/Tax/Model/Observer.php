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

class Radial_Tax_Model_Observer
{
    /** @var bool Lock to guard against too much recursion in quote collect totals */
    protected static $lockRecollectTotals = false;
    /** @var Radial_Tax_Model_Collector */
    protected $taxCollector;
    /** @var Radial_Core_Model_Session */
    protected $coreSession;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $logContext;

    /**
     * @param array
     */
    public function __construct(array $args = [])
    {
        list(
            $this->taxCollector,
            $this->coreSession,
            $this->logger,
            $this->logContext
        ) = $this->checkTypes(
            $this->nullCoalesce($args, 'tax_collector', Mage::getModel('radial_tax/collector')),
            $this->nullCoalesce($args, 'core_session', null),
            $this->nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context'))
        );
    }

    /**
     * Enforce type checks on construct args array.
     *
     * @param Radial_Tax_Model_Collector
     * @param Radial_Tax_Model_Session
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @return array
     */
    protected function checkTypes(
        Radial_Tax_Model_Collector $taxCollector,
        Radial_Core_Model_Session $coreSession = null,
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
    protected function nullCoalesce(array $arr, $key, $default)
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /**
     * Get the session instance containing collected tax data for the quote.
     * Populates the class property if not set when requested. The property
     * will not be set during construction to minimize the risk of initializing
     * the session instance before the user session has been started.
     *
     * @return Radial_Core_Model_Session
     */
    protected function getCoreSession()
    {
        if (!$this->coreSession) {
            $this->coreSession = Mage::getSingleton('radial_core/session');
        }
        return $this->coreSession;
    }

    /**
     * Collect new tax totals if necessary after collecting quote totals.
     * Tax totals collected after all other quote totals so tax totals for the
     * entire quote may be collected at one - all other totals for all other
     * addresses must have already been collected.
     *
     * If new taxes are collected, all quote totals must be recollected.
     *
     * @param Varien_Event_Observer
     * @return self
     */
    public function handleSalesQuoteCollectTotalsAfter(Varien_Event_Observer $observer)
    {
        $coreSession = $this->getCoreSession();
        if ($coreSession->isTaxUpdateRequired()) {
            /** @var Mage_Sales_Model_Quote */
            $quote = $observer->getEvent()->getQuote();
            try {
                $this->taxCollector->collectTaxes($quote);
            } catch (Radial_Tax_Exception_Collector_InvalidQuote_Exception $e) {
                // Exception for when a quote is not yet ready for making
                // a tax request. Not an entirely uncommon situation and
                // does not necessarily indicate anything is actually wrong
                // unless the quote is expected to be valid but isn't.
                $this->logger->debug('Quote not valid for tax request.', $this->logContext->getMetaData(__CLASS__));
                return $this;
            } catch (Radial_Tax_Exception_Collector_Exception $e) {
                // Want TDF to be non-blocking so exceptions from making the
                // request should be caught. Still need to exit here when there
                // is an exception, however, to allow the TDF to be retried
                // (don't reset update required flag) and prevent totals from being
                // recollected (nothing to update and, more imporantly, would
                // continue to loop until PHP crashes or a TDF request succeeds).
                $this->logger->warning('Tax request failed.', $this->logContext->getMetaData(__CLASS__, [], $e));
                return $this;
            }
            // After retrieving new tax records, update the session with data
            // from the quote used to make the request and reset the tax
            // update required flag as another update should not be required
            // until some other change has been detected.
            $this->logger->debug('Update session flags after tax collection.', $this->logContext->getMetaData(__CLASS__));
            $coreSession->updateWithQuote($quote)->resetTaxUpdateRequired();
            // Need to trigger a re-collection of quote totals now that taxes
            // for the quote have been retrieved. On the second pass, tax totals
            // just collected should be applied to the quote and any totals
            // dependent upon tax totals - like grand total - should update
            // to include the tax totals.
            $this->recollectTotals($quote);
        }
        return $this;
    }

    /**
     * set the tax header error flag on the order create request.
     * @param  Varien_Event_Observer
     * @return self
     */
    public function handleOrderCreateBeforeAttachEvent(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        Mage::getModel(
            'radial_tax/order_create_order',
            ['order_create_request' => $event->getPayload()]
        )->addTaxHeaderErrorFlag();
        return $this;
    }

    /**
     * set gifting tax data on the shipgroup payload for the order create request
     * @param  Varien_Event_Observer
     * @return self
     */
    public function handleOrderCreateShipGroupEvent(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        Mage::getModel(
            'radial_tax/order_create_shipgroup',
            [
                'address' => $event->getAddress(),
                'ship_group' => $event->getShipGroupPayload(),
            ]
        )->addGiftTaxesToPayload();
        return $this;
    }

    /**
     * set tax data on the orderitem payload for the order create request
     * @param  Varien_Event_Observer
     * @return self
     */
    public function handleOrderCreateItemEvent(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        $item = $event->getItem();
        $quoteItemId = $item->getQuoteItemId();
        Mage::getModel(
            'radial_tax/order_create_orderitem',
            [
                'item' => $event->getItem(),
                'tax_records' => $this->taxCollector->getTaxRecordsByItemId($quoteItemId),
                'duty' => $this->taxCollector->getTaxDutyByItemId($quoteItemId),
                'fees' => $this->taxCollector->getTaxFeesByItemId($quoteItemId),
                'order_item_payload' => $event->getItemPayload(),
            ]
        )->addTaxesToPayload();
        return $this;
    }

    /**
     * Recollect quote totals to update amounts based on newly received tax
     * data. This collect totals call is expected to happen recursively within
     * collect totals. The flags in radial_core/session are expected to prevent
     * going beyond a single recursive call to collect totals. As an additional
     * precaution, a lock is also used to prevent unexpected recursion.
     *
     * @param Mage_Sales_Model_Quote
     * @return Mage_Sales_Model_Quote
     */
    protected function recollectTotals(Mage_Sales_Model_Quote $quote)
    {
        // Guard against unexpected recursion. Session flags should prevent
        // this but need to be sure this can't trigger infinite recursion.
        // If the lock is free (set to false), expect to not be within a recursive
        // collectTotals triggered by taxes.
        if (!self::$lockRecollectTotals) {
            // Acquire the lock prior to triggering the recursion. Prevents taxes
            // from being able to trigger further recursion.
            self::$lockRecollectTotals = true;
            $quote->collectTotals();
            // Free the lock once we're clear of the recursive collectTotals.
            self::$lockRecollectTotals = false;
        } else {
            // Do not expect further recursive attempts to occur. Something
            // would be potentially wrong with the session flags if it does.
            $this->logger->warning('Attempted to recollect totals for taxes during a recursive collection. Additional collection averted to prevent further recursion.', $this->logContext->getMetaData(__CLASS__));
        }
        return $quote;
    }
}
