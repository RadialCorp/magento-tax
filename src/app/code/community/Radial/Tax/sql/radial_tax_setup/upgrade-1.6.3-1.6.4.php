<?php

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$currentDateTime = Mage::getModel('core/date')->date('Y-m-d H:i:s');
Mage::getConfig()->saveConfig('radial_core/radial_tax_core/effectivefrom', $currentDateTime, 'default', 0);

$installer->endSetup();
