<?php
/**
 * Copyright (c) 2013-2016 Radial, Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright   Copyright (c) 2013-2016 Radial, Inc. (http://www.radial.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Radial_Tax_Model_Adminhtml_System_Config_Backend_Totalnumretry extends Mage_Core_Model_Config_Data
{
    public function _afterLoad()
    {
	$maxretries = Mage::getStoreConfig('radial_core/radial_tax_core/maxretries');

        $pendingCreditMemoSize = Mage::getResourceModel('sales/order_creditmemo_collection') 
			->addFieldToFilter('radial_tax_transmit', array('lt' => $maxretries))
                        ->addFieldToFilter('radial_tax_transmit', array('neq' => -1))->getSize(); 

        $pendingInvoicesSize = Mage::getResourceModel('sales/order_invoice_collection')
			->addFieldToFilter('radial_tax_transmit', array('lt' => $maxretries))
			->addFieldToFilter('radial_tax_transmit', array('neq' => -1))->getSize();

	$pendingInvoiceQuoteSize = Mage::getResourceModel('sales/order_collection')
                        ->addFieldToFilter('radial_tax_transmit', array('lt' => $maxretries))
                        ->addFieldToFilter('radial_tax_transmit', array('neq' => -1))->getSize();

        $objectCollectionSize = $pendingCreditMemoSize + $pendingInvoicesSize + $pendingInvoiceQuoteSize;
	
	$publicDisplay = '# of Tax Messages Waiting For Transmission Retry: '. $objectCollectionSize;

        $this->setValue($publicDisplay);
        return $this;
    }

    /**
     * Simply turn of data saving. This is a display-only field in admin dependent upon presence of
     *  files in the filesystem, not any configuration data.
     * @return self
     * @codeCoverageIgnore Magento promises to not save anything if _dataSaveAllowed is false.
     */
    public function _beforeSave()
    {
        $this->_dataSaveAllowed = false;
        return $this;
    }
}

