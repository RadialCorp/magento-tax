<?php

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$installer->run("
   ALTER TABLE sales_flat_order MODIFY `radial_tax_taxrecords` VARBINARY DEFAULT NULL
");

$installer->run("
   ALTER TABLE sales_flat_order MODIFY `radial_tax_duties` VARBINARY DEFAULT NULL
");

$installer->run("
   ALTER TABLE sales_flat_order MODIFY `radial_tax_fees` VARBINARY DEFAULT NULL
");

$installer->run("
   ALTER TABLE sales_flat_order MODIFY `radial_tax_transaction_id` VARBINARY DEFAULT NULL
");

$installer->run("
   ALTER TABLE sales_flat_quote MODIFY `radial_tax_taxrecords` VARBINARY DEFAULT NULL
");

$installer->run("
   ALTER TABLE sales_flat_quote MODIFY `radial_tax_duties` VARBINARY DEFAULT NULL
");

$installer->run("
   ALTER TABLE sales_flat_quote MODIFY `radial_tax_fees` VARBINARY DEFAULT NULL
");

$installer->run("
   ALTER TABLE sales_flat_quote MODIFY `radial_tax_transaction_id` VARBINARY DEFAULT NULL
");

$installer->endSetup();
