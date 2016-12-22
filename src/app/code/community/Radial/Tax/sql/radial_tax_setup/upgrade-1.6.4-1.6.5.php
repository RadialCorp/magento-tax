<?php

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$installer->run("
ALTER TABLE sales_flat_quote_item
  ADD COLUMN `radial_hts_code` VARCHAR(255) NULL default ''
");

$installer->run("
ALTER TABLE sales_flat_order_item
  ADD COLUMN `radial_hts_code` VARCHAR(255) NULL default ''
");

$installer->run("
ALTER TABLE sales_flat_quote_item
  ADD COLUMN `radial_screen_size` VARCHAR(10) NULL default ''
");

$installer->run("
ALTER TABLE sales_flat_order_item
  ADD COLUMN `radial_screen_size` VARCHAR(10) NULL default ''
");

$installer->run("
ALTER TABLE sales_flat_quote_item
  ADD COLUMN `radial_manufacturing_country_code` VARCHAR(10) NULL default ''
");

$installer->run("
ALTER TABLE sales_flat_order_item
  ADD COLUMN `radial_manufacturing_country_code` VARCHAR(10) NULL default ''
");

$installer->run("
ALTER TABLE sales_flat_quote_item
  ADD COLUMN `radial_tax_code` VARCHAR(10) NULL default ''
");

$installer->run("
ALTER TABLE sales_flat_order_item
  ADD COLUMN `radial_tax_code` VARCHAR(10) NULL default ''
");

$installer->endSetup();
