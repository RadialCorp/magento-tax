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
 * @copyright   Copyright (c) 2013-2014 eBay Enterprise, Inc. (http://www.ebayenterprise.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Session storage container for ROM tax records.
 *
 * @method EbayEnterprise_Tax_Model_Record[] getTaxRecords()
 * @method self setTaxRecords(EbayEnterprise_Tax_Model_Record[])
 * @method bool getTaxRequestSuccess()
 * @method self setTaxRequestSuccess(bool)
 */
class EbayEnterprise_Tax_Model_Session extends Mage_Core_Model_Session_Abstract
{
    /**
     * Initialize the session.
     */
    protected function _construct()
    {
        $this->init('ebayenterprise_tax');
    }
}
