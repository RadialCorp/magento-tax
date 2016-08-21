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
class Radial_Eb2cGiftwrap_Overrides_Block_Adminhtml_Giftwrapping_Grid extends Enterprise_GiftWrapping_Block_Adminhtml_Giftwrapping_Grid
{
    /**
     * Overriding the Giftwrapping grid block in order to add SKU and Tax Class to the grid listing
     * page
     * Prepare edit form
     * @return Enterprise_GiftWrapping_Block_Adminhtml_Giftwrapping_Edit_Form
     */
    protected function _prepareColumns()
    {
        $this->addColumnAfter('eb2c_sku', array(
            'header' => Mage::helper('enterprise_giftwrapping')->__('SKU'),
            'index' => 'eb2c_sku'
        ), 'design');
        $this->addColumnAfter('eb2c_tax_class', array(
            'header' => Mage::helper('enterprise_giftwrapping')->__('Tax Class'),
            'index'  => 'eb2c_tax_class'
        ), 'eb2c_sku');
        return parent::_prepareColumns();
    }
}
