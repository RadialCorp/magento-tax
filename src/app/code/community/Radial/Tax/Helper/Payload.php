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

use eBayEnterprise\RetailOrderManagement\Payload\Order\IFee as IOrderFee;
use eBayEnterprise\RetailOrderManagement\Payload\Order\ITax;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IGifting;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IMailingAddress;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IPhysicalAddress;

/**
 * Methods for converting Magento types into corresponding ROM SDK payload
 * types. While this could include all sorts of type translations, it should
 * mostly be limited to types for which there is an obvious translation between
 * the Magneto and ROM SDK types.
 */
class Radial_Tax_Helper_Payload
{
    /**
     * Transfer data from a Magento customer address model to a ROM SDK
     * MailingAddress payload.
     *
     * @param Mage_Customer_Model_Address_Abstract
     * @param IMailingAddress
     * @return IMailingAddress
     */
    public function customerAddressToMailingAddressPayload(
        Mage_Customer_Model_Address_Abstract $address,
        IMailingAddress $payload
    ) {
        return $this->customerAddressToPhysicalAddressPayload($address, $payload)
            ->setFirstName($address->getFirstname())
            ->setLastName($address->getLastname())
            ->setMiddleName($address->getMiddlename())
            ->setHonorificName($address->getSuffix());
    }

    /**
     * Transfer data from a Magento customer address to a ROM SDK
     * PhysicalAddress payload.
     *
     * @param Mage_Customer_Model_Address_Abstract
     * @param IPhysicalAddress
     * @return IPhysicalAddress
     */
    public function customerAddressToPhysicalAddressPayload(
        Mage_Customer_Model_Address_Abstract $address,
        IPhysicalAddress $payload
    ) {
        return $payload
            ->setLines($address->getStreetFull())
            ->setCity($address->getCity())
            ->setMainDivision($this->getRegion($address))
            ->setCountryCode($address->getCountry())
            ->setPostalCode($address->getPostcode());
    }

    /**
     * If the country for the Address is US then get the 2 character ISO region code;
     * otherwise, for any other country get the fully qualified region name.
     *
     * @param  Mage_Customer_Model_Address_Abstract
     * @return string
     */
    protected function getRegion(Mage_Customer_Model_Address_Abstract $address)
    {
        return $address->getCountry() === 'US'
            ? $address->getRegionCode()
            : $address->getRegion();
    }

    /**
     * Trasfer data from an "item" with gifting options to a Gifting payload.
     * The "item" may be a quote item or quote address, as either may have
     * gift options data, retrievable in the same way.
     *
     * @param Varien_Object
     * @param IGfiting
     * @return IGifting
     */
    public function giftingItemToGiftingPayload(
        Varien_Object $giftItem,
        IGifting $giftingPayload
    ) {
        $giftPricing = $giftingPayload->getEmptyGiftPriceGroup();
        $giftWrap = Mage::getModel('enterprise_giftwrapping/wrapping')->load($giftItem->getGwId());
        if ($giftWrap->getId()) {
            // For quote items (which will have a quantity), gift wrapping price
            // on the item will be the price for a single item to be wrapped,
            // total will be for cost for all items to be wrapped (qty * amount).
            // For addresses (which will have no quantity), gift wrapping price
            // on the address will be the price for wrapping all items for that
            // address, so total is just amount (1 * amount).
            $giftQty = $giftItem->getQty() ?: 1;
            // Add pricing data for gift wrapping - does not include discounts
            // as Magento does not support applying discounts to gift wrapping
            // out-of-the-box.
            $giftPricing->setUnitPrice($giftWrap->getBasePrice())
                ->setAmount($giftItem->getGwPrice() * $giftQty)
                ->setTaxClass($giftWrap->getEb2cTaxClass());
            $giftingPayload
                ->setGiftItemId($giftWrap->getEb2cSku())
                ->setGiftDescription($giftWrap->getDesign())
                ->setGiftPricing($giftPricing);
        }
        return $giftingPayload;
    }

    /**
     * Tnransfer data from a tax record model to a tax payload.
     *
     * @param Radial_Tax_Model_Records
     * @param ITax
     * @return ITax
     */
    public function taxRecordToTaxPayload(
        Radial_Tax_Model_Record $taxRecord,
        ITax $taxPayload
    ) {
        return $taxPayload
            ->setType($taxRecord->getType())
            ->setTaxability($taxRecord->getTaxability())
            ->setSitus($taxRecord->getSitus())
            ->setJurisdiction($taxRecord->getJurisdiction())
            ->setJurisdictionLevel($taxRecord->getJurisdictionLevel())
            ->setJurisdictionId($taxRecord->getJurisdictionId())
            ->setImposition($taxRecord->getImposition())
            ->setImpositionType($taxRecord->getImpositionType())
            ->setEffectiveRate($taxRecord->getEffectiveRate())
            ->setTaxableAmount($taxRecord->getTaxableAmount())
            ->setCalculatedTax($taxRecord->getCalculatedTax())
            ->setSellerRegistrationId($taxRecord->getSellerRegistrationId());
    }

    /**
     * Transfer data from a tax fee record to a fee payload.
     *
     * @param Radial_Tax_Model_Fee
     * @param IOrderFee
     * @param Radial_Tax_Model_Record[]
     * @return IOrderFee
     */
    public function taxFeeToOrderFeePayload(
        Radial_Tax_Model_Fee $fee,
        IOrderFee $orderFee,
        array $taxRecords = []
    ) {
        $taxIterable = $orderFee->getTaxes();
        foreach ($taxRecords as $taxRecord) {
            $taxPayload = $this->taxRecordToTaxPayload($taxRecord, $taxIterable->getEmptyTax());
            $taxIterable[$taxPayload] = null;
        }
        return $orderFee->setType($fee->getType())
            ->setDescription($fee->getDescription())
            ->setAmount($fee->getAmount())
            ->setItemId($fee->getItemId())
            ->setTaxes($taxIterable);
    }
}
