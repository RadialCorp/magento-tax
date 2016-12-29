<?php

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$installer->run("
   ALTER TABLE sales_flat_quote_address ADD COLUMN `radial_destination_id` VARCHAR(30) DEFAULT NULL
");

$installer->run("
   ALTER TABLE sales_flat_order_address ADD COLUMN `radial_destination_id` VARCHAR(30) DEFAULT NULL
");

$installer->endSetup();
