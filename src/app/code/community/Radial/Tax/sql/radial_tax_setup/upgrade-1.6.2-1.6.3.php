<?php

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$optionsN = array(
	'type' => Varien_Db_Ddl_Table::TYPE_VARCHAR,
	'visible' => false,
	'required' => false
);

$installer->addAttribute('order', 'radial_tax_transmit', $optionsN);
$installer->addAttribute('quote', 'radial_tax_transmit', $optionsN);

$installer->endSetup();
