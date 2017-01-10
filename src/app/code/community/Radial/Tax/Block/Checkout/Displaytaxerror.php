<?php
class Radial_Tax_Block_Checkout_Displaytaxerror extends Mage_Core_Block_Template
{
    public function getNoTaxErrorMessage()
    {
        return Mage::getStoreConfig('radial_core/radial_tax_core/notaxcalcerror', Mage::app()->getStore()->getStoreId()); 
    }

    protected function _toHtml()
    {
	$effectiveFrom = Mage::getStoreConfig('radial_core/radial_tax_core/effectivefrom');
        $effectiveTo = Mage::getStoreConfig('radial_core/radial_tax_core/effectiveto');
        $currentTime = Mage::getModel('core/date')->date('Y-m-d H:i:s');

        $dtEffectiveFrom = new DateTime($effectiveFrom);
        $dtEffictiveTo = new DateTime($effectiveTo);
        $dtCurrentTime = new DateTime($currentTime);

	$quote = Mage::getSingleton('checkout/session')->getQuote();
	$taxRecords = unserialize($quote->getData('radial_tax_taxrecords'));

        if( $effectiveFrom && $dtEffectiveFrom > $dtCurrentTime)
        {
		return parent::_toHtml();
        }

        if( $effectiveTo && $dtEffectiveTo < $dtCurrentTime)
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

	return '';
    }
}
