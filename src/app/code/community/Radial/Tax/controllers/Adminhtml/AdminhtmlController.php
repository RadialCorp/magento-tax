<?php
 
class Radial_Tax_Adminhtml_AdminhtmlController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
	//Load Layout
	$this->loadLayout();

        //Render Layout
        $this->renderLayout();
    }

    /**
     * Reset Messages at Maximum Retries
     */ 
    public function messageResetAction()
    {
        Mage::getSingleton('adminhtml/session')->addSuccess("Successfully Reset Tax Messages at Maximum Transmission");
	$maxretries = Mage::getStoreConfig('radial_core/radial_tax_core/maxretries');

	$objectCollection= Mage::getResourceModel('sales/order_invoice_collection')
                        ->addFieldToFilter('radial_tax_transmit', array('eq' => $maxretries))
                        ->setPageSize(100);

        $pages = $objectCollection->getLastPageNumber();
        $currentPage = 1;

        do
        {
                $objectCollection->setCurPage($currentPage);
                $objectCollection->load();

                foreach($objectCollection as $object)
                {
                        $object->setRadialTaxTransmit(0);
                        $object->save();
                }

                $currentPage++;
                $objectCollection->clear();
        } while ($currentPage <= $pages);

	$objectCollection= Mage::getResourceModel('sales/order_creditmemo_collection')
                        ->addFieldToFilter('radial_tax_transmit', array('eq' => $maxretries))
                        ->setPageSize(100);

        $pages = $objectCollection->getLastPageNumber();
        $currentPage = 1;

        do
        {
                $objectCollection->setCurPage($currentPage);
                $objectCollection->load();

                foreach($objectCollection as $object)
                {
                        $object->setRadialTaxTransmit(0);
                        $object->save();
                }

                $currentPage++;
                $objectCollection->clear();
        } while ($currentPage <= $pages);

        $this->_redirect('adminhtml/system_config/edit/section/radial_core');
    }

    /**
     * Purge all messages in the retry queue
     */ 
    public function purgeRetryQueueAction()
    {
	Mage::getSingleton('adminhtml/session')->addSuccess("Successfully Marked All Tax Messages as Sent (PURGED QUEUE)");
        $maxretries = Mage::getStoreConfig('radial_core/radial_tax_core/maxretries');

        $objectCollection= Mage::getResourceModel('sales/order_invoice_collection')
                        ->addFieldToFilter('radial_tax_transmit', array('neq' => -1))
                        ->setPageSize(100);

        $pages = $objectCollection->getLastPageNumber();
        $currentPage = 1;

        do
        {
                $objectCollection->setCurPage($currentPage);
                $objectCollection->load();

                foreach($objectCollection as $object)
                {
                        $object->setRadialTaxTransmit(-1);
                        $object->save();
                }

                $currentPage++;
                $objectCollection->clear();
        } while ($currentPage <= $pages);

        $objectCollection= Mage::getResourceModel('sales/order_creditmemo_collection')
                        ->addFieldToFilter('radial_tax_transmit', array('neq' => -1))
                        ->setPageSize(100);

        $pages = $objectCollection->getLastPageNumber();
        $currentPage = 1;

        do
        {
                $objectCollection->setCurPage($currentPage);
                $objectCollection->load();

                foreach($objectCollection as $object)
                {
                        $object->setRadialTaxTransmit(-1);
                        $object->save();
                }

                $currentPage++;
                $objectCollection->clear();
        } while ($currentPage <= $pages);

	$this->_redirect('adminhtml/system_config/edit/section/radial_core');
    }
}
