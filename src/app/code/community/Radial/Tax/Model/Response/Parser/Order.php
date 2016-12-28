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

use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteReply;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedShipGroup;

class Radial_Tax_Model_Response_Parser_Order extends Radial_Tax_Model_Response_Parser_Abstract
{
    /** @var ITaxDutyFeeQuoteReply */
    protected $_taxResponse;
    /** @var Mage_Sales_Model_Order */
    protected $_order;
    /** @var Radial_Tax_Helper_Factory */
    protected $_taxFactory;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $_logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $_logContext;

    /**
     * @param array $args Must contain key/value for:
     *                         - tax_response => eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteReply
     *                         - order => Mage_Sales_Model_Order
     *                         May contain key/value for:
     *                         - tax_factory => Radial_Tax_Helper_Factory
     *                         - logger => EbayEnterprise_MageLog_Helper_Data
     *                         - log_context => EbayEnterprise_MageLog_Helper_Context
     */
    public function __construct(array $args)
    {
        list(
            $this->_order,
            $this->_taxResponse,
            $this->_taxFactory,
            $this->_logger,
            $this->_logContext
        ) = $this->_checkTypes(
            $args['order'],
            $args['tax_response'],
            $this->_nullCoalesce($args, 'tax_factory', Mage::helper('radial_tax/factory')),
            $this->_nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog/data')),
            $this->_nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context'))
        );
    }

    /**
     * Enforce type checks on constructor args array.
     *
     * @param Mage_Sales_Model_Order
     * @param ITaxDutyFeeQuoteReply
     * @param Radial_Tax_Helper_Factory
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @return array
     */
    protected function _checkTypes(
        Mage_Sales_Model_Order $order,
        ITaxDutyFeeQuoteReply $taxResponse,
        Radial_Tax_Helper_Factory $taxFactory,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext
    ) {
        return [$order, $taxResponse, $taxFactory, $logger, $logContext];
    }

    /**
     * Extract tax data from the tax response payload and store tax records,
     * duties and fees.
     *
     * Extracts all three sets of tax data as each set of data can be retrieved
     * from the same address parser. Extracting all three sets at once prevents
     * nearly identical steps from being repeated for each ship group for each
     * type of tax data.
     *
     * @return self
     */
    protected function _extractTaxData()
    {
        // ship group - e.g. $taxRecords = [[$recordA, $recordB], [$recordC, $recordD]].
        $taxRecords = [];
        $duties = [];
        $fees = [];
        foreach ($this->_taxResponse->getShipGroups() as $shipGroup) {
            $address = $this->_getQuoteAddressForShipGroup($shipGroup);
            if ($address) {
                $addressParser = $this->_taxFactory->createResponseAddressParser($shipGroup, $address);
                $taxRecords[] = $addressParser->getTaxRecords();
                $duties[] = $addressParser->getTaxDuties();
                $fees[] = $addressParser->getTaxFees();
            } else {
                $this->_logger->warning(
                    'Tax response ship group does not relate to any known address.',
                    $this->_logContext->getMetaData(__CLASS__, ['rom_response_body' => $shipGroup->serialize()])
                );
            }
        }

        // Flatten each nested array of tax data - allows for a single array_merge
        // instead of iteratively calling array_merge on each pass when extracting
        // tax data for each ship group.
        $this->_taxRecords = $this->_flattenArray($taxRecords);
        $this->_taxDuties = $this->_flattenArray($duties);
        $this->_taxFees = $this->_flattenArray($fees);
        return $this;
    }

    /**
     * Get the order address the ship group is the destination for.
     *
     * @param ITaxedShipGroup
     * @return Mage_Sales_Model_Quote_Address|null
     */
    protected function _getQuoteAddressForShipGroup(ITaxedShipGroup $shipGroup)
    {
        // If the destination id can be guaranteed to match between request and
        // response, the id generated for the request could be captured with the
        // address to link the destination payload back up with the order address.
        return $this->_order->getAddressesCollection()->getItemByColumnValue('destination_id', $shipGroup->getDestination()->getId());
    }
}
