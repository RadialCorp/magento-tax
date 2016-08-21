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

/**
 * @codeCoverageIgnore
 */
class Radial_Eb2cGiftwrap_Model_Resource_Wrapping_Collection extends Enterprise_GiftWrapping_Model_Resource_Wrapping_Collection
{
    /**
     * Substitute the giftwrapping sku for entity_id as all giftwrappings processed from the
     * feeds will have a sku. This makes looking up a giftwrapping by Eb2c SKU more
     * reasonable and allows for newly created items to be looked up after being
     * added to the collection but before the collection has been saved.
     * @param  Varien_Object $item
     * @return string
     */
    protected function _getItemId(Varien_Object $item)
    {
        return $item->getEb2cSku();
    }
}
