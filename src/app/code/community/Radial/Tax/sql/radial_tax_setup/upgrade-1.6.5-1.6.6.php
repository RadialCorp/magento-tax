<?php

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$optionsN = array(
        'type' => Varien_Db_Ddl_Table::TYPE_VARCHAR,
        'visible' => false,
        'required' => false
);

$installer->addAttribute('order', 'radial_gw_printed_card_sku', $optionsN);
$installer->addAttribute('order', 'radial_gw_printed_card_tax_class', $optionsN);

$installer->endSetup();
