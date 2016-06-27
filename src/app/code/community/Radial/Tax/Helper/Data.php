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

use eBayEnterprise\RetailOrderManagement\Api\Exception\NetworkError;
use eBayEnterprise\RetailOrderManagement\Api\Exception\UnsupportedHttpAction;
use eBayEnterprise\RetailOrderManagement\Api\Exception\UnsupportedOperation;
use eBayEnterprise\RetailOrderManagement\Api\IBidirectionalApi;
use eBayEnterprise\RetailOrderManagement\Payload\Exception\InvalidPayload;

class Radial_Tax_Helper_Data extends Mage_Core_Helper_Abstract implements Radial_Core_Helper_Interface
{
    const TAX_FAILED_MESSAGE = 'Radial_Tax_Request_Failed';

    /** @var Radial_Core_Helper_Data */
    protected $coreHelper;
    /** @var Radial_Tax_Helper_Factory */
    protected $taxFactory;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $logContext;

    /**
     * @param array $args May contain key/value for:
     * - core_helper    => Radial_Core_Helper_Data
     * - tax_factory    => Radial_Tax_Helper_Factory
     * - logger         => EbayEnterprise_MageLog_Helper_Data
     * - log_context    => EbayEnterprise_MageLog_Helper_Context
     */
    public function __construct(array $args = [])
    {
        list(
            $this->coreHelper,
            $this->taxFactory,
            $this->logger,
            $this->logContext
        ) = $this->checkTypes(
            $this->nullCoalesce($args, 'core_helper', Mage::helper('radial_core')),
            $this->nullCoalesce($args, 'tax_factory', Mage::helper('radial_tax/factory')),
            $this->nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context'))
        );
    }

    /**
     * Enforce type checks on constructor init params.
     *
     * @param Radial_Core_Helper_Data
     * @param Radial_Tax_Helper_Factory
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @return array
     */
    protected function checkTypes(
        Radial_Core_Helper_Data $coreHelper,
        Radial_Tax_Helper_Factory $taxFactory,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext
    ) {
        return func_get_args();
    }

    /**
     * Fill in default values.
     *
     * @param array
     * @param string
     * @param mixed
     * @return mixed
     */
    protected function nullCoalesce(array $arr, $key, $default)
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /**
     * @see Radial_Core_Helper_Interface::getConfigModel
     * @param mixed
     * @return Radial_Core_Model_Config_Registry
     */
    public function getConfigModel($store = null)
    {
        return Mage::getModel('radial_core/config_registry')
            ->setStore($store)
            ->addConfigModel(Mage::getSingleton('radial_tax/config'));
    }

    /**
     * Get the HTS code for a product in a given country.
     *
     * @param Mage_Catalog_Model_Product
     * @param string $countryCode The two letter code for a country (US, CA, DE, etc...)
     * @return string|null The HTS Code for the product/country combination. Null if no HTS code is available.
     */
    public function getProductHtsCodeByCountry(Mage_Catalog_Model_Product $product, $countryCode)
    {
        $htsCodes = unserialize($product->getHtsCodes());
        if (is_array($htsCodes)) {
            foreach ($htsCodes as $htsCode) {
                if ($countryCode === $htsCode['destination_country']) {
                    return $htsCode['hts_code'];
                }
            }
        }

        return null;
    }

    /**
     * Make an API request to the TDF service for the quote and return any
     * tax records in the response.
     *
     * @param Mage_Sales_Model_Order_Quote
     * @return Radial_Tax_Model_Result
     * @throws Radial_Tax_Exception_Collector_Exception If tax records could not be collected.
     */
    public function requestTaxesForQuote(Mage_Sales_Model_Quote $quote)
    {
        $api = $this->getSdkApi();
        return $this->_prepareRequest($api, $quote)
            ->_sendApiRequest($api)
            ->_extractResponseResults($api, $quote);
    }

    /**
     * Get an API object for the SDK to make the TDF request.
     *
     * @return IBidirectionalApi
     */
    protected function getSdkApi()
    {
        $taxConfig = $this->getConfigModel();

        return $this->coreHelper->getSdkApi(
            $taxConfig->apiService,
            $taxConfig->apiOperation
        );
    }

    /**
     * Prepare the API request with data from the quote - fill out and set
     * the request payload.
     *
     * @param IBidirectionalApi
     * @param Mage_Sales_Model_Order_Quote
     * @return self
     */
    protected function _prepareRequest(IBidirectionalApi $api, Mage_Sales_Model_Quote $quote)
    {
        try {
            $requestBody = $api->getRequestBody();
        } catch (UnsupportedOperation $e) {
            // If the SDK cannot handle sending requests to the tax/quote
            // service operation but is expected to, the SDK is likely broken.
            // As this would fall into the "human intervention required"
            // category of errors, log crit the exception.
            $this->logger->critical(
                'Tax quote service request unsupported by SDK.',
                $this->logContext->getMetaData(__CLASS__, [], $e)
            );
            // Throw a more generic, expected exception to prevent
            // this from being a blocking failure.
            throw $this->_failTaxCollection();
        }
        $taxRequest = $this->taxFactory
            ->createRequestBuilderQuote($requestBody, $quote)
            ->getTaxRequest();
        $api->setRequestBody($taxRequest);
        return $this;
    }

    /**
     * Send the request for the TDF service and handle any responses or exceptions.
     *
     * @param IBidirectionalApi
     * @return self
     */
    protected function _sendApiRequest(IBidirectionalApi $api)
    {
        $logger = $this->logger;
        $logContext = $this->logContext;
        try {
            $api->send();
            // Generally, these catch statements will all add a log message for the
            // exception and throw a more generic exception that can be handled
            // (by Magento or the Tax module) in such a way as to not block checkout.
        } catch (InvalidPayload $e) {
            $logger->warning(
                'Invalid payload for tax quote. See exception log for more details.',
                $logContext->getMetaData(__CLASS__, ['exception_message' => $e->getMessage()])
            );
            $logger->logException($e, $logContext->getMetaData(__CLASS__, [], $e));
            throw $this->_failTaxCollection();
        } catch (NetworkError $e) {
            $logger->warning(
                'Caught network error sending tax quote request. See exception log for more details.',
                $logContext->getMetaData(__CLASS__, ['exception_message' => $e->getMessage()])
            );
            $logger->logException($e, $logContext->getMetaData(__CLASS__, [], $e));
            throw $this->_failTaxCollection();
        } catch (UnsupportedOperation $e) {
            $logger->critical(
                'The tax quote service operation is unsupported in the current configuration. See exception log for more details.',
                $logContext->getMetaData(__CLASS__, ['exception_message' => $e->getMessage()])
            );
            $logger->logException($e, $logContext->getMetaData(__CLASS__, [], $e));
            throw $this->_failTaxCollection();
        } catch (UnsupportedHttpAction $e) {
            $logger->critical(
                'The tax quote operation is configured with an unsupported HTTP action. See exception log for more details.',
                $logContext->getMetaData(__CLASS__, ['exception_message' => $e->getMessage()])
            );
            $logger->logException($e, $logContext->getMetaData(__CLASS__, [], $e));
            throw $this->_failTaxCollection();
        } catch (Exception $e) {
            $logger->warning(
                'Encountered unexpected exception from tax quote operation. See exception log for more details.',
                $logContext->getMetaData(__CLASS__, ['exception_message' => $e->getMessage()])
            );
            $logger->logException($e, $logContext->getMetaData(__CLASS__, [], $e));
            throw $this->_failTaxCollection();
        }
        return $this;
    }

    /**
     * Extract tax records from the API response body for the quote.
     *
     * @param IBidirectionalApi
     * @param Mage_Sales_Model_Order_Quote
     * @return Radial_Tax_Model_Result
     */
    protected function _extractResponseResults(IBidirectionalApi $api, Mage_Sales_Model_Quote $quote)
    {
        try {
            $responseBody = $api->getResponseBody();
        } catch (UnsupportedOperation $e) {
            // This exception handling is probably not necessary but
            // is technically possible. If the sdk flow of
            // getRequest->setRequest->send->getResponse is followed,
            // which is is by the one public method of this class, this
            // exception should never be thrown in this instance. If it
            // were to be thrown at all by the SDK, it would have already
            // happened during the "send" step.
            $this->logger->critical(
                'Tax quote service response unsupported by SDK.',
                $this->logContext->getMetaData(__CLASS__, [], $e)
            );
            throw $this->_failTaxCollection();
        }
        $responseParser = $this->taxFactory
            ->createResponseQuoteParser($responseBody, $quote);
        return $this->taxFactory->createTaxResults(
            $responseParser->getTaxRecords(),
            $responseParser->getTaxDuties(),
            $responseParser->getTaxFees()
        );
    }

    /**
     * Create a fairly generic exception for the tax module indicating
     * that tax collection via the SDK has failed.
     *
     * @return Radial_Tax_Exception_Collector_Exception
     */
    protected function _failTaxCollection()
    {
        return Mage::exception('Radial_Tax_Exception_Collector', $this->__(self::TAX_FAILED_MESSAGE));
    }
}
