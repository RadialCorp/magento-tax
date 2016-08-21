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

/**
 * @codeCoverageIgnore
 */
class Radial_Eb2cGiftwrap_Overrides_Block_Adminhtml_Giftwrapping_Edit_Form extends Enterprise_GiftWrapping_Block_Adminhtml_Giftwrapping_Edit_Form
{
    /**
     * Overriding the Giftwrapping Edit Form block in order to add the SKU and Tax Class Fields.
     * Prepare edit form
     * @return Enterprise_GiftWrapping_Block_Adminhtml_Giftwrapping_Edit_Form
     */
    protected function _prepareForm()
    {
        parent::_prepareForm();
        $model = Mage::registry('current_giftwrapping_model');
        $form = $this->getForm();
        $fieldset = $form->addFieldset('item_fieldset', array(
            'legend' => Mage::helper('enterprise_giftwrapping')->__('Item Information')
        ));
        $fieldset->addField('eb2c_sku', 'text', array(
            'label'    => Mage::helper('enterprise_giftwrapping')->__('SKU'),
            'name'     => 'eb2c_sku',
            'required' => true,
            'value'    => $model->getEb2cSku(),
            'scope'    => 'store'
        ));

        $fieldset->addField('eb2c_tax_class', 'text', array(
            'label'    => Mage::helper('enterprise_giftwrapping')->__('Tax Class'),
            'name'     => 'eb2c_tax_class',
            'required' => true,
            'value'    => $model->getEb2cTaxClass(),
            'scope'    => 'store'
        ));
        $this->setForm($form);
        return $this;
    }
}
