<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$entities = array(
	'quote',
	'order'
);

$options = array(
	'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
	'visible' => false,
	'required' => false
);

foreach ($entities as $entity) {
	$installer->addAttribute($entity, "radial_tax_taxrecords", $options);
	$installer->addAttribute($entity, "radial_tax_duties", $options);
	$installer->addAttribute($entity, "radial_tax_fees", $options);
}

$installer->endSetup();
