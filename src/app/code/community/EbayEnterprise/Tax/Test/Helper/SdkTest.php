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

use eBayEnterprise\RetailOrderManagement\Api\Exception\NetworkError;
use eBayEnterprise\RetailOrderManagement\Api\Exception\UnsupportedHttpAction;
use eBayEnterprise\RetailOrderManagement\Api\Exception\UnsupportedOperation;
use eBayEnterprise\RetailOrderManagement\Payload\Exception\InvalidPayload;

/**
 * The class this test originally tested has been refactored into a different
 * class. This test no longer tests EbayEnterprise_Tax_Helper_Sdk, which no
 * longer exists, but instead test SDK related method on
 * EbayEnterprise_Tax_Helper_Data
 */
class EbayEnterprise_Tax_Test_Helper_SdkTest extends EbayEnterprise_Eb2cCore_Test_Base
{
    /** @var eBayEnterprise\RetailOrderManagement\Api\IBidirectionalApi */
    protected $_api;
    /** @var eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteRequest */
    protected $_emptyRequestBody;
    /** @var eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteRequest */
    protected $_completeRequestBody;
    /** @var eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteReply */
    protected $_responseBody;
    /** @var EbayEnterprise_Eb2cCore_Helper_Data */
    protected $_coreHelper;
    /** @var EbayEnterprise_Tax_Helper_Data */
    protected $_taxHelper;
    /** @var EbayEnterprise_Eb2cCore_Model_Config_Registry */
    protected $_taxConfig;
    /** @var string Name of the API service for taxes. Mocked config value. */
    protected $_apiService = 'taxes';
    /** @var string Name of the API operation for taxes. Mocked config value. */
    protected $_apiOperation = 'quote';
    /** @var EbayEnterprise_Tax_Helper_Factory */
    protected $_taxFactory;
    /** @var EbayEnterprise_Tax_Model_Request_Builder_Quote */
    protected $_quoteRequestBuilder;
    /** @var EbayEnterprise_Tax_Model_Response_Parser_Quote */
    protected $_quoteResponseParser;
    /** @var Mage_Sales_Model_Quote */
    protected $_quote;
    /** @var string General exception thrown by the SDK helper when exceptions from the SDK are encountered. */
    protected $_taxCollectorException = 'EbayEnterprise_Tax_Exception_Collector_Exception';

    public function setUp()
    {
        $logContext = $this->getHelperMock('ebayenterprise_magelog/context', ['getMetaData']);
        $logContext->expects($this->any())
            ->method('getMetaData')
            ->will($this->returnValue([]));

        $this->_api = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Api\IBidirectionalApi'
        );

        // Create two request bodies - one to serve as the "empty" request
        // body that needs to be populated with data and one to serve as
        // the "complete" payload that has been populated with data.
        $this->_emptyRequestBody = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteRequest'
        );
        $this->_completeRequestBody = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteRequest'
        );

        $this->_responseBody = $this->getMockForAbstractClass(
            'eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxDutyFeeQuoteReply'
        );

        $this->_coreHelper = $this->getHelperMock('ebayenterprise_eb2ccore', ['getSdkApi']);

        $this->_taxConfig = $this->buildCoreConfigRegistry([
            'apiService' => $this->_apiService,
            'apiOperation' => $this->_apiOperation,
        ]);

        $this->_taxHelper = $this->getHelperMock('ebayenterprise_tax/data', ['__']);
        $this->_taxHelper->expects($this->any())
            ->method('__')
            ->will($this->returnArgument(0));

        $this->_taxFactory = $this->getHelperMock(
            'ebayenterprise_tax/factory',
            ['createRequestBuilderQuote', 'createResponseQuoteParser', 'createTaxResults']
        );

        $this->_quoteRequestBuilder = $this->getModelMockBuilder('ebayenterprise_tax/request_builder_quote')
            ->disableOriginalConstructor()
            ->setMethods(['getTaxRequest'])
            ->getMock();

        $this->_quoteResponseParser = $this->getModelMockBuilder('ebayenterprise_tax/response_parser_quote')
            ->disableOriginalConstructor()
            ->setMethods(['getTaxRecords', 'getTaxDuties', 'getTaxFees'])
            ->getMock();

        $this->_quote = $this->getModelMock('sales/quote');

        $this->_taxHelper = $this->getHelperMock('ebayenterprise_tax', ['getConfigModel']);
        $this->_taxHelper->method('getConfigModel')->willReturn($this->_taxConfig);

        // As helpers do not support constructor injection, inject
        // dependencies by directly setting the class properties.
        EcomDev_Utils_Reflection::setRestrictedPropertyValues(
            $this->_taxHelper,
            [
                'coreHelper' => $this->_coreHelper,
                'taxFactory' => $this->_taxFactory,
                'logContext' => $logContext,
            ]
        );
    }

    /**
     * Test that a properly configured SDK API is created for making
     * the tax request.
     */
    public function testGetSdkApi()
    {
        // Allow the core helper to provide the correct API object
        // if given the appropriate configuration values for the
        // API service and operation.
        $this->_coreHelper->expects($this->any())
            ->method('getSdkApi')
            ->with($this->identicalTo($this->_apiService), $this->identicalTo($this->_apiOperation))
            ->will($this->returnValue($this->_api));

        $this->assertSame(
            $this->_api,
            EcomDev_Utils_Reflection::invokeRestrictedMethod($this->_taxHelper, 'getSdkApi')
        );
    }

    /**
     * Test that the API gets populated with the apporpriate request
     * payload when preparing to make the tax request.
     */
    public function testPrepareRequestSuccess()
    {
        // Expect that the API will provide the empty request body
        // that will need to be populated to make the request.
        $this->_api->expects($this->any())
            ->method('getRequestBody')
            ->will($this->returnValue($this->_emptyRequestBody));
        // The api then needs to be given the complete request body.
        $this->_api->expects($this->once())
            ->method('setRequestBody')
            ->with($this->identicalTo($this->_completeRequestBody))
            ->will($this->returnSelf());
        // Not exactly require that the tax factory be used to get the request
        // builder, but if it is used, it must be given the apporpriate payload
        // and quote object.
        $this->_taxFactory->expects($this->any())
            ->method('createRequestBuilderQuote')
            ->with($this->identicalTo($this->_emptyRequestBody), $this->identicalTo($this->_quote))
            ->will($this->returnValue($this->_quoteRequestBuilder));
        // Allow the quote request builder to be used to provide the
        // response object needed by the API.
        $this->_quoteRequestBuilder->expects($this->any())
            ->method('getTaxRequest')
            ->will($this->returnValue($this->_completeRequestBody));

        // Mostly superfluous to ensure the method returns "self" (easy
        // enough to prove just be looking at the method) but easy enough
        // to test the it provides the apporpriate return value to test it anyway.
        $this->assertSame(
            $this->_taxHelper,
            EcomDev_Utils_Reflection::invokeRestrictedMethod(
                $this->_taxHelper,
                '_prepareRequest',
                [$this->_api, $this->_quote]
            )
        );
    }

    /**
     * If the request payload cannot be created - due to the tax/quote operation
     * not being supported by the SDK, the SDK exception should be caught
     * and a more general exception thrown instead.
     */
    public function testPrepareRequestFailure()
    {
        $this->_api->expects($this->any())
            ->method('getRequestBody')
            ->will($this->throwException(new UnsupportedOperation));
        $this->setExpectedException($this->_taxCollectorException);
        EcomDev_Utils_Reflection::invokeRestrictedMethod(
            $this->_taxHelper,
            '_prepareRequest',
            [$this->_api, $this->_quote]
        );
    }

    /**
     * When sending a request, the SDK API should be used to send the
     * tax request.
     */
    public function testSendApiRequestSuccess()
    {
        // Mock request and response bodies for the SDK. Inconsequential
        // to the test but may be necessary to prevent errors (currently
        // serialized version of both are logged so must simply exist to
        // allow the code to run).
        $this->_api->expects($this->any())
            ->method('getRequestBody')
            ->will($this->returnValue($this->_emptyRequestBody));
        $this->_api->expects($this->any())
            ->method('getResponseBody')
            ->will($this->returnValue($this->_responseBody));
        // Really just need to ensure that the request is sent
        // via the SDK API. In the success case, not much else
        // happens here.
        $this->_api->expects($this->once())
            ->method('send')
            ->will($this->returnSelf());

        $this->assertSame(
            $this->_taxHelper,
            EcomDev_Utils_Reflection::invokeRestrictedMethod(
                $this->_taxHelper,
                '_sendApiRequest',
                [$this->_api]
            )
        );
    }

    /**
     * Provide exceptions to be thrown by the SDK API when sending
     * tax requests.
     */
    public function provideSdkSendExceptions()
    {
        return [
            [new NetworkError],
            [new InvalidPayload],
            [new UnsupportedOperation],
            [new UnsupportedHttpAction],
            [new Exception],
        ];
    }

    /**
     * When the API SDK request fails, exceptions from the SDK should
     * be caught and more general, expected exceptions should be thrown
     * for Magento/Tax module to handle appropriately.
     *
     * @param Exception $sdkException Exception to be thrown by the api.
     * @dataProvider provideSdkSendExceptions
     */
    public function testSendApiRequestErrorHandling(Exception $sdkException)
    {
        // Mock request and response bodies for the SDK. Inconsequential
        // to the test but may be necessary to prevent errors (currently
        // serialized version of both are logged so must simply exist to
        // allow the code to run).
        $this->_api->expects($this->any())
            ->method('getRequestBody')
            ->will($this->returnValue($this->_emptyRequestBody));
        $this->_api->expects($this->any())
            ->method('getResponseBody')
            ->will($this->returnValue($this->_responseBody));
        $this->_api->expects($this->once())
            ->method('send')
            ->will($this->throwException($sdkException));

        $this->setExpectedException($this->_taxCollectorException);
        EcomDev_Utils_Reflection::invokeRestrictedMethod(
            $this->_taxHelper,
            '_sendApiRequest',
            [$this->_api]
        );
    }

    /**
     * When extracting results from the SDK response, a tax
     * result model with the tax data extracted from the
     * response should be returned.
     */
    public function testExtractResponseResultsSuccess()
    {
        // Create an expected result, should be returned when successfully
        // extracting results from a response.
        $sdkResult = $this->getModelMockBuilder('ebayenterprise_tax/result')->disableOriginalConstructor()->getMock();

        // Mock the API to return an expected response body as the
        // reply to the SDK request.
        $this->_api->expects($this->any())
            ->method('getResponseBody')
            ->will($this->returnValue($this->_responseBody));

        // Mock up some SDK results - doesn't really matter what these
        // are, just that they can be recognized as the "right" results.
        $taxRecords = [$this->getModelMockBuilder('ebayenteprirse_tax/record')->disableOriginalConstructor()->getMock()];
        $taxDuties = [$this->getModelMockBuilder('ebayenteprirse_tax/duty')->disableOriginalConstructor()->getMock()];
        $taxFees = [$this->getModelMockBuilder('ebayenteprirse_tax/fee')->disableOriginalConstructor()->getMock()];

        // Create a quote response parser capable of returning the
        // proper results from the tax response.
        $this->_quoteResponseParser->expects($this->any())
            ->method('getTaxRecords')
            ->will($this->returnValue($taxRecords));
        $this->_quoteResponseParser->expects($this->any())
            ->method('getTaxDuties')
            ->will($this->returnValue($taxDuties));
        $this->_quoteResponseParser->expects($this->any())
            ->method('getTaxFees')
            ->will($this->returnValue($taxFees));

        // Setup the tax factory to be able to provide the proper quote
        // response parser if given the correct response payload and quote.
        $this->_taxFactory->expects($this->any())
            ->method('createResponseQuoteParser')
            ->with($this->identicalTo($this->_responseBody), $this->identicalTo($this->_quote))
            ->will($this->returnValue($this->_quoteResponseParser));
        // Setup the tax factory to be able to provide the proper SDK result
        // if given the expected tax data.
        $this->_taxFactory->expects($this->any())
            ->method('createTaxResults')
            ->with($this->identicalTo($taxRecords), $this->identicalTo($taxDuties), $this->identicalTo($taxFees))
            ->will($this->returnValue($sdkResult));

        // Ensure that the correct SDK result is returned when extracting
        // results from the SDK. For now, this will return the exact same
        // instance as is expected. In the future this may not be the case and
        // test could be expected to simply ensure the results available in
        // the returned result model match the expected results.
        $this->assertSame(
            $sdkResult,
            EcomDev_Utils_Reflection::invokeRestrictedMethod(
                $this->_taxHelper,
                '_extractResponseResults',
                [$this->_api, $this->_quote]
            )
        );
    }

    /**
     * When the SDK throws an exception while getting the response body,
     * the SDK exception should be caught and a more generic exception
     * that can be expected to be caught (by Magento or Tax module)
     * should be thrown.
     */
    public function testExtractResponseResultsFailure()
    {
        $this->_api->expects($this->any())
            ->method('getResponseBody')
            ->will($this->throwException(new UnsupportedOperation));

        $this->setExpectedException($this->_taxCollectorException);
        EcomDev_Utils_Reflection::invokeRestrictedMethod(
            $this->_taxHelper,
            '_extractResponseResults',
            [$this->_api, $this->_quote]
        );
    }
}
