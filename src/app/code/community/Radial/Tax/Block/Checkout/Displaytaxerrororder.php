<?php
class Radial_Tax_Block_Checkout_Displaytaxerrororder extends Mage_Core_Block_Template
{
    public function getNoTaxErrorMessage()
    {
        return Mage::getStoreConfig('radial_core/radial_tax_core/notaxcalcerror', Mage::app()->getStore()->getStoreId()); 
    }

    protected function _toHtml()
    {
	$order = false;	
	$orderTransmit = false;

	$orderId = $this->getRequest()->getParam('order_id');
	$order = Mage::getModel('sales/order')->load($orderId);
	$orderTransmit = $order->getData('radial_tax_transmit');

	$enabled = Mage::getStoreConfig('radial_core/radial_tax_core/enabledmod', $order->getStoreId());
        $effectiveFrom = Mage::getStoreConfig('radial_core/radial_tax_core/effectivefrom', $order->getStoreId());
        $effectiveTo = Mage::getStoreConfig('radial_core/radial_tax_core/effectiveto', $order->getStoreId());
        $orderCreateTime = $order->getCreatedAt();

        $dtEffectiveFrom = new DateTime($effectiveFrom);
        $dtEffectiveTo = new DateTime($effectiveTo);
        $dtOrderCreateTime = new DateTime($orderCreateTime);

	if( $orderTransmit && $orderTransmit == -1 )
	{
		return '';
	}

	if( $orderTransmit && $orderTransmit != -1 )
	{
		$taxRecords = unserialize($order->getData('radial_tax_taxrecords'));

		if( $effectiveFrom && $dtEffectiveFrom > $dtOrderCreateTime)
        	{
                	return parent::_toHtml();
        	}

        	if( $effectiveTo && $dtEffectiveTo < $dtOrderCreateTime)
        	{
                	return parent::_toHtml();
        	}

        	if( !$enabled )
        	{
                	return parent::_toHtml();
        	}

        	if( !$taxRecords )
        	{
                	return parent::_toHtml();
        	}
	}

	if( !$orderTransmit )
	{
		return parent::_toHtml();
	}

	return '';
    }
}
