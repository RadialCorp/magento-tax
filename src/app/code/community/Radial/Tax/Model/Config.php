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

/**
 * Configuration model to be registered with the eb2c core config helper.
 */
class Radial_Tax_Model_Config extends Radial_Core_Model_Config_Abstract
{
    protected $_configPaths = array(
        'admin_origin_city' => 'radial_tax/admin_origin/city',
        'admin_origin_country_code' => 'radial_tax/admin_origin/country_code',
        'admin_origin_line1' => 'radial_tax/admin_origin/line1',
        'admin_origin_line2' => 'radial_tax/admin_origin/line2',
        'admin_origin_line3' => 'radial_tax/admin_origin/line3',
        'admin_origin_line4' => 'radial_tax/admin_origin/line4',
        'admin_origin_main_division' => 'radial_tax/admin_origin/main_division',
        'admin_origin_postal_code' => 'radial_tax/admin_origin/postal_code',
        'api_operation' => 'radial_tax/api/operation',
        'api_service' => 'radial_tax/api/service',
        'shipping_tax_class' => 'radial_tax/tax_class/shipping',
        'tax_duty_rate_code' => 'radial_tax/duty/rate_code',
        'vat_inclusive_pricing' => 'radial_tax/pricing/vat_inclusive',
	'enabled' => 'radial_core/radial_tax_core/enabledmod',
	'effectivefrom' => 'radial_core/radial_tax_core/effectivefrom',
	'effectiveto' => 'radial_core/radial_tax_core/effectiveto',
    );
}
