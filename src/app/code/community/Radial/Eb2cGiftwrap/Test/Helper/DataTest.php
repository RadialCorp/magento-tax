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

class Radial_Eb2cGiftwrap_Test_Helper_DataTest extends Radial_Eb2cCore_Test_Base
{
    /**
     * Test that the method Radial_Eb2cGiftwrap_Helper_Data::createNewGiftwrapping
     * will be invoked and will return new Mage_Catalog_Model_Giftwrap object with dummy data
     */
    public function testCreateNewGiftwrapping()
    {
        $sku = '52-ABC-3832';
        $result = array(
            'eb2c_tax_class' => null,
            'base_price' => 0.0,
            'image' => null,
            'status' => 1,
            'design' => "Incomplete gift wrapping: $sku",
            'eb2c_sku' => $sku
        );

        $this->assertSame($result, Mage::helper('eb2cgiftwrap')->createNewGiftwrapping($sku)->getData());
    }

    /**
     * Test the helper method 'Radial_Order_Helper_Data::calculateGwItemRowTotal' using a data provider
     * that will test various scenarios and the expects the return value to be the same as known result. The first
     * scenario will instantiate a 'sales/order_item' object and initialize it with known gift wrapping price and
     * quantity data and then expects it to be the same as the product of quantity time the gift wrapping price.
     * The second scenario will instantiate a 'sales/order' object and initialize it with a known quantity, a known
     * gift wrapping price and expects only the gift wrapping price to be returned.
     * @param string $class
     * @param array $data
     * @param float $expected
     * @dataProvider dataProvider
     */
    public function testCalculateGwItemRowTotal($class, array $data, $expected)
    {
        $this->assertSame($expected, Mage::helper('eb2cgiftwrap')->calculateGwItemRowTotal(Mage::getModel($class, $data)));
    }
}
