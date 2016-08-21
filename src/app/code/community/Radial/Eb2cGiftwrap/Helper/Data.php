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
 * @copyright   Copyright (c) 2013-2014 eBay Enterprise, Inc. (http://www.radial.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Radial_Eb2cGiftwrap_Helper_Data extends Mage_Core_Helper_Abstract implements Radial_Eb2cCore_Helper_Interface
{
    /**
     * @var array boilerplate for initializing a new gift wrapping with limited information.
     */
    protected $_giftWrapTplt;

    /**
     * @return array the static defaults for a new gift wrapping
     */
    protected function _getGiftWrapTplt()
    {
        if (!$this->_giftWrapTplt) {
            $cfg = $this->getConfigModel();
            $this->_giftWrapTplt = array(
                'eb2c_tax_class' => $cfg->dummyTaxClass,
                'base_price' => (float) $cfg->dummyBasePrice,
                'image' => null,
                'status' => (int) $cfg->dummyStatus,
            );
        }
        return $this->_giftWrapTplt;
    }

    /**
     * @see Radial_Eb2cCore_Helper_Interface::getConfigModel
     * Get giftwrap config instantiated object.
     * @param mixed $store
     * @return Radial_Eb2cCore_Model_Config_Registry
     */
    public function getConfigModel($store = null)
    {
        return Mage::getModel('eb2ccore/config_registry')
            ->setStore($store)
            ->addConfigModel(Mage::getSingleton('eb2cgiftwrap/config'));
    }

    /**
     * instantiate new gift wrapping object and apply dummy data to it
     * @param  string $sku
     * @param  array $additionalData optional
     * @return Enterprise_GiftWrapping_Model_Wrapping
     */
    public function createNewGiftwrapping($sku, array $additionalData = array())
    {
        /** @var Enterprise_GiftWrapping_Model_Wrapping $giftWrapping */
        $giftWrapping = Mage::getModel('enterprise_giftwrapping/wrapping');
        return $this->_applyDummyData($giftWrapping, $sku, $additionalData);
    }

    /**
     * Fill a gift wrapping model with dummy data so that it can be saved and edited later.
     * @see http://www.magentocommerce.com/boards/viewthread/289906/
     * @param Enterprise_GiftWrapping_Model_Wrapping $wrap gift wrapping model to be autofilled
     * @param string $sku the new gift wrapping's sku
     * @param array $additionalData optional
     * @return Enterprise_GiftWrapping_Model_Wrapping
     */
    protected function _applyDummyData(Enterprise_GiftWrapping_Model_Wrapping $wrap, $sku, array $additionalData = array())
    {
        $wrapData = array_merge($this->_getGiftWrapTplt(), $additionalData);
        $design = isset($wrapData['design']) ? $wrapData['design'] : null;
        $wrapData['design'] = $design ?: "Incomplete gift wrapping: $sku";
        $wrapData['eb2c_sku'] = $sku;
        return $wrap->addData($wrapData);
    }

    /**
     * Calculating the order item gift wrapping row total when the passed in object is
     * a concrete 'sales/order_item' instance otherwise simply return the gift wrap price.
     * @param  Varien_Object $item
     * @return float
     */
    public function calculateGwItemRowTotal(Varien_Object $item)
    {
        $qty = ($item instanceof Mage_Sales_Model_Order_Item) ? $item->getQtyOrdered() : 1;
        return Mage::app()->getStore()->roundPrice($qty * $item->getGwPrice() + $item->getGwCardPrice());
    }
}
