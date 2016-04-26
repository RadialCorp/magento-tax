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

/**
 * Configuration model to be registered with the eb2c core config helper.
 */
class EbayEnterprise_Tax_Model_Config extends EbayEnterprise_Eb2cCore_Model_Config_Abstract
{
    protected $_configPaths = array(
        'admin_origin_city' => 'ebayenterprise_tax/admin_origin/city',
        'admin_origin_country_code' => 'ebayenterprise_tax/admin_origin/country_code',
        'admin_origin_line1' => 'ebayenterprise_tax/admin_origin/line1',
        'admin_origin_line2' => 'ebayenterprise_tax/admin_origin/line2',
        'admin_origin_line3' => 'ebayenterprise_tax/admin_origin/line3',
        'admin_origin_line4' => 'ebayenterprise_tax/admin_origin/line4',
        'admin_origin_main_division' => 'ebayenterprise_tax/admin_origin/main_division',
        'admin_origin_postal_code' => 'ebayenterprise_tax/admin_origin/postal_code',
        'api_operation' => 'ebayenterprise_tax/api/operation',
        'api_service' => 'ebayenterprise_tax/api/service',
        'shipping_tax_class' => 'ebayenterprise_tax/tax_class/shipping',
        'tax_duty_rate_code' => 'ebayenterprise_tax/duty/rate_code',
        'vat_inclusive_pricing' => 'ebayenterprise_tax/pricing/vat_inclusive',
    );
}
