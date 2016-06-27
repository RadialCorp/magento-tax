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

class Radial_Tax_Test_Helper_DataTest extends EcomDev_PHPUnit_Test_Case
{
    /** @var Radial_Tax_Helper_Data */
    protected $helper;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $origLogContext;

    public function setUp()
    {
        $this->helper = Mage::helper('radial_tax');

        // Mock log context to prevent session interactions while getting log meta data.
        $logContext = $this->getHelperMock('ebayenterprise_magelog/context', ['getMetaData']);
        $logContext->method('getMetaData')->will($this->returnValue([]));

        // Swap out the log context used by the tax helper being tested, grabbing
        // a reference to the original value so it can be restored later.
        $this->origLogContext = EcomDev_Utils_Reflection::getRestrictedPropertyValue($this->helper, 'logContext');
        EcomDev_Utils_Reflection::setRestrictedPropertyValue($this->helper, 'logContext', $logContext);
    }

    protected function tearDown()
    {
        // Restore the original log context instance.
        EcomDev_Utils_Reflection::setRestrictedPropertyValue($this->helper, 'logContext', $this->origLogContext);
    }

    /**
     * Test getting the HTS code for a product in a given country.
     */
    public function testGetProductHtsCodeByCountry()
    {
        $product = Mage::getModel('catalog/product', ['hts_codes' => serialize([
            ['destination_country' => 'US', 'hts_code' => 'US-HTS-Code'],
            ['destination_country' => 'CA', 'hts_code' => 'CA-HTS-Code'],
        ])]);
        $this->assertSame(
            $this->helper->getProductHtsCodeByCountry($product, 'CA'),
            'CA-HTS-Code'
        );
    }

    /**
     * When a product has not HTS codes available, null should be returned
     * when attpemting to get an HTS code.
     */
    public function testGetProductHtsCodeByCountryNoHtsCodes()
    {
        $product = Mage::getModel('catalog/product');
        $this->assertNull($this->helper->getProductHtsCodeByCountry($product, 'US'));
    }

    /**
     * When a product has not HTS codes available, null should be returned
     * when attpemting to get an HTS code.
     */
    public function testGetProductHtsCodeByCountryNoMatchingHtsCode()
    {
        $product = Mage::getModel(
            'catalog/product',
            ['hts_codes' => serialize([['destination_country' => 'US', 'hst_code' => 'US-HTS-Code']])]
        );
        $this->assertNull($this->helper->getProductHtsCodeByCountry($product, 'CA'));
    }

    /**
     * Provide exceptions that can be thrown from the SDK and the exception
     * expected to be thrown after handling the SDK exception.
     *
     * @return array
     */
    public function provideSdkExceptions()
    {
        $invalidPayload = '\eBayEnterprise\RetailOrderManagement\Payload\Exception\InvalidPayload';
        $networkError = '\eBayEnterprise\RetailOrderManagement\Api\Exception\NetworkError';
        $unsupportedOperation = '\eBayEnterprise\RetailOrderManagement\Api\Exception\UnsupportedOperation';
        $unsupportedHttpAction = '\eBayEnterprise\RetailOrderManagement\Api\Exception\UnsupportedHttpAction';
        $baseException = 'Exception';
        $taxCollectionException = 'Radial_Tax_Exception_Collector_Exception';
        return [
            [$invalidPayload, $taxCollectionException],
            [$networkError, $taxCollectionException],
            [$unsupportedOperation, $taxCollectionException],
            [$unsupportedHttpAction, $taxCollectionException],
            [$baseException, $taxCollectionException],
        ];
    }

    /**
     * GIVEN An <api> that will thrown an <exception> of <exceptionType> when making a request.
     * WHEN A request is made.
     * THEN The <exception> will be caught.
     * AND An exception of <expectedExceptionType> will be thrown.
     *
     * @param string
     * @param string
     * @dataProvider provideSdkExceptions
     */
    public function testSdkExceptionHandling($exceptionType, $expectedExceptionType)
    {
        $exception = new $exceptionType(__METHOD__ . ': Test Exception');
        $api = $this->getMock('\eBayEnterprise\RetailOrderManagement\Api\IBidirectionalApi');
        $api->method('send')
            ->will($this->throwException($exception));

        $this->setExpectedException($expectedExceptionType);

        EcomDev_Utils_Reflection::invokeRestrictedMethod(
            $this->helper,
            '_sendApiRequest',
            [$api]
        );
    }
}
