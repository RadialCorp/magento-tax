<?php
class Radial_Tax_Block_Adminhtml_Sales_Order_Create_Totals_Grandtotal extends Mage_Adminhtml_Block_Sales_Order_Create_Totals_Grandtotal {
    /**
     * Get grandtotal exclude tax
     * The Grand Total w/o Tax Should Exclude the Radial_Tax_Amount as well - Krule
     *
     * @return float
     */
    public function getTotalExclTax()
    {
        $excl = $this->getTotal()->getAddress()->getGrandTotal()-$this->getTotal()->getAddress()->getTaxAmount()-$this->getTotal()->getAddress()->getRadialTaxAmount();
        $excl = max($excl, 0);
        return $excl;
    }
}
