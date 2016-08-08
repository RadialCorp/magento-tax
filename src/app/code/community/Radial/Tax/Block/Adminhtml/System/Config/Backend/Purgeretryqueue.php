<?php

class Radial_Tax_Block_Adminhtml_System_Config_Backend_Purgeretryqueue extends Mage_Adminhtml_Block_System_Config_Form_Field
{
        protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
        {
                $this->setElement($element);
		$url = Mage::helper('adminhtml')->getUrl('radial_tax/adminhtml/purgeRetryQueue');

                $html = $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setType('button')
                    ->setClass('scalable')
                    ->setLabel('Purge Tax Message Retry Queue')
		    ->setOnClick("setLocation('$url')")
                    ->toHtml();

                return $html;
        }
}
