<?php
class Radial_Tax_Block_Adminhtml_Sales_Order_View_Info_Block extends Mage_Core_Block_Template
{    
    protected $order;
    
    public function getOrder() {
        if (is_null($this->order)) {
            if (Mage::registry('current_order')) {
                $order = Mage::registry('current_order');
            }
            elseif (Mage::registry('order')) {
                $order = Mage::registry('order');
            }
            else {
                $order = new Varien_Object();
            }
            $this->order = $order;
        }
        return $this->order;
    }

    public function getNoTaxErrorMessage()
    {
        return Mage::getStoreConfig('radial_core/radial_tax_core/notaxcalcerror', $this->order->getStoreId());
    }
}
