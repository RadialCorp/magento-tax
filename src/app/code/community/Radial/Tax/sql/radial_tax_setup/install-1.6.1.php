<?php

$installer = $this;
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');

$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'hts_codes', array(
    'type' => 'text',
    'group' => 'PTF',
    'label' => 'HTS Codes',
    'input' => 'textarea',
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
    'visible' => true,
    'required' => false,
    'user_defined' => false,
    'default' => '',
    'apply_to' => 'simple,configurable,virtual,bundle,downloadable,giftcard',
    'visible_on_front' => false,
    'used_in_product_listing' => true
));

$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'tax_code', array(
    'type' => 'varchar',
    'group' => 'Prices',
    'label' => 'Tax Code',
    'input' => 'text',
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
    'visible' => true,
    'required' => true,
    'user_defined' => false,
    'default' => '',
    'apply_to' => 'simple,virtual,bundle,downloadable,giftcard',
    'visible_on_front' => false,
    'used_in_product_listing' => true
));

$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'screen_size', array(
    'type' => 'text',
    'group' => 'PTF',
    'label' => 'Screen Size',
    'input' => 'textarea',
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
    'visible' => true,
    'required' => false,
    'user_defined' => false,
    'default' => '',
    'apply_to' => 'simple,configurable,virtual,bundle,downloadable,giftcard',
    'visible_on_front' => false,
    'used_in_product_listing' => true
));
