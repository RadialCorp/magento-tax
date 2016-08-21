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
class Radial_Eb2cGiftwrap_Overrides_Model_Wrapping extends Enterprise_GiftWrapping_Model_Wrapping
{
    /**
     * Overriding the magic method get sku because the import process always call getSku
     * and since the wrapping only knows about getEb2cSku we need to make sure the right method get call
     * @return string
     */
    public function getSku()
    {
        return $this->getData('eb2c_sku');
    }
    /**
     * Overriding the magic method setSku to reference the setEb2cSku instead
     * @param string $value
     * @return self
     */
    public function setSku($value)
    {
        $this->setData('eb2c_sku', $value);
        return $this;
    }
}
