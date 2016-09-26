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

$attrList = array(
	'radial_tax_taxrecords', 'radial_tax_duties', 'radial_tax_fees', 'radial_tax_transaction_id'
);

foreach ($entities as $entity) {
	$model = 'sales/' . $entity;

	foreach( $attrList as $attrN )
	{
		$installer->addAttribute($entity, $attrN, $options);
	}
}

$optionsN = array(
	'type' => Varien_Db_Ddl_Table::TYPE_VARCHAR,
	'visible' => false,
	'required' => false
);

$installer->addAttribute('order', 'radial_tax_transmit', $optionsN);
$installer->addAttribute('quote', 'radial_tax_transmit', $optionsN);
$installer->addAttribute('invoice', 'radial_tax_transmit', $optionsN);
$installer->addAttribute('creditmemo', 'radial_tax_transmit', $optionsN);

$installerA = Mage::getResourceModel('catalog/setup', 'catalog_setup');

$entity = 'catalog_product';
$code = 'hts_codes';
$attr = Mage::getResourceModel('catalog/eav_attribute')
    ->loadByCode($entity,$code);

if( !$attr->getId())
{
	$installerA->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'hts_codes', array(
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
}

$entity = 'catalog_product';
$code = 'tax_code';
$attr = Mage::getResourceModel('catalog/eav_attribute')
    ->loadByCode($entity,$code);

if( !$attr->getId())
{
	$installerA->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'tax_code', array(
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
}

$entity = 'catalog_product';
$code = 'screen_size';
$attr = Mage::getResourceModel('catalog/eav_attribute')
    ->loadByCode($entity,$code);

if( !$attr->getId())
{
	$installerA->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'screen_size', array(
	    'type' => 'varchar',
	    'group' => 'PTF',
	    'label' => 'Screen Size',
	    'input' => 'text',
	    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
	    'visible' => true,
	    'required' => false,
	    'user_defined' => false,
	    'default' => '',
	    'apply_to' => 'simple,configurable,virtual,bundle,downloadable,giftcard',
	    'visible_on_front' => false,
	    'used_in_product_listing' => true
	));
}

$installer->endSetup();
