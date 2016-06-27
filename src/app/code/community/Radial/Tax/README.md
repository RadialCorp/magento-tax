![ebay logo](/docs/static/logo-vert.png)

**Magento Retail Order Management Extension**
# Taxes, Duties & Fees

The intended audience for this guide is Magento merchants, business users and system integrators.

## Contents

- [Introduction](#introduction)
- [Dependencies](#dependencies)

## Introduction

The Magento Retail Order Management Extension uses the Retail Order Management tax service to handle calculations for VAT, sales and use taxes, import/export duties, and fees.

| Important |
|:----------|
| The Retail Order Management tax service requires the full shipping address to calculate taxes. A request to calculate taxes without the full shipping address will not be fulfilled. It is recommended to remove the "Estimate Shipping and Tax" section from the Magento cart as tax will not be estimated for guest customers at that time. |

The tax service also requires the shipping origin to properly estimate taxes. To provide an earlier estimate, once the customer has entered their shipping address, the "Tax Admin Origin" will be used as the shipping origin. This address is configurable at **System > Configuration > eBay Enterprise > Retail Order Management > Tax Admin Origin**. Once the customer selects their shipping method, the extension will call the inventory service to provide inventory details including the proper shipping origin. The extension will then use the shipping origin returned by the inventory service for a final call to the tax servic

## Dependencies

### Magento Modules

- Radial_Core
- EbayEnterprise_MageLog
- Mage_Catalog
- Mage_Customer
- Mage_Sales

### Other Dependencies

- [RetailOrderManagement-SDK](https://github.com/eBayEnterprise/RetailOrderManagement-SDK)

### Test Dependencies

- EcomDev_PHPUnit

- - -
Copyright © 2014 eBay Enterprise, Inc.
