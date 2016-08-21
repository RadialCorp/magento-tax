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

$installer = $this;
$installer->startSetup();
// enterprise_giftwrapping
$tableName = $this->getTable('enterprise_giftwrapping/wrapping');
$conn = $installer->getConnection();
$conn->addColumn($tableName, 'eb2c_sku', 'varchar(64) DEFAULT NULL');
$conn->addColumn($tableName, 'eb2c_tax_class', 'varchar(255) DEFAULT NULL');
$conn->addKey($tableName, 'IDX_eb2c_sku', 'eb2c_sku');
$conn->addKey($tableName, 'IDX_eb2c_tax_class', 'eb2c_tax_class');
$installer->endSetup();
