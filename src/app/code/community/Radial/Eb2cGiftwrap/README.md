![ebay logo](../../../../../../docs/static/logo-vert.png)

**Magento Retail Order Management Extension**
# Gift Wrap and Messaging

The intended audience for this guide is Magento merchants, business users and system integrators.

## Contents

- [Introduction](#introduction)
- [Gift Messaging](#gift-messaging)
- [Gift Wrapping](#gift-wrapping)
- [Exporting Gift Wrapping](#exporting-gift-wrapping)
- [Importing Gift Wrapping](#importing-gift-wrapping)

## Introduction

The Retail Order Management Extension enables merchants to offer gift wrapping and messaging services to customers.

## Gift Messaging

The Magento Retail Order Management Extension includes support for gift messages that closely matches the functionality available in Magento. The availability of gift messages for items and orders is dependent upon specific agreements between the merchant and eBay Enterprise.

When available, gift messages may be added for individual items or for all items shipping to a single address. The "Add Printed Card" option is used to determine if the gift messages are to be included on the pack slip or as separate cards.

| Important |
|:----------|
| The Retail Order Management Extension does not currently support including a price for adding a printed card. To avoid issues, Magento should not be configured with a price for printed cards. |

## Gift Wrapping

The Magento Retail Order Management Extension includes limited support for gift wrapping. The availability of gift wrapping is dependent upon specific agreements between the merchant and eBay Enterprise.

When available, gift wrapping may be added for individual items or for all items shipping to a single address. Gift wrapping may be added with or without a gift message. If the customer selects to include a printed card, however, any items that include a gift message will also be gift wrapped. Additional rules regarding gift wrapping may be determined as part of the merchant agreement with eBay Enterprise.

| Important |
|:----------|
| When making gift options available to customers, it is advised to limit the user interface to make only the options supported by this extension and agreements with eBay Enterprise available. |

To support gift wrapping via Retail Order Management, the extension adds two new attributes to each gift wrap item in Magento, "SKU" and "Tax Class." You can view these fields in the Magento Admin at **Sales > Gift Wrapping > Edit > Item Information**.

## Exporting Gift Wrapping

If you use Magento to manage product information, you will need to send product information for each gift wrap item to the Product Hub. The extension will not export Magento Gift Wrapping items. You will need to ensure that each gift wrapping item is also managed as a product in Magento. Ensure the gift wrapping product is disabled in Magento to prevent selling it individually.

## Importing Gift Wrapping

You can indicate that a product is gift wrapping in the Item Master feed by specifying `giftwrap` as the ItemType for that item node. The extension will import items of type `gift wrap` as a Magento gift wrapping item, rather than a Magento product.

```xml
<Item>
	<ItemId>
		<ClientItemId>GW-TST-0001</ClientItemId>
	</ItemId>
	<BaseAttributes>
		<ItemDescription>Gift Wrap product 1</ItemDescription>
		<ItemType>GiftWrap</ItemType>
		<TaxCode>17</TaxCode>
	</BaseAttributes>
	<ExtendedAttributes>
		<Price>10.95</Price>
	</ExtendedAttributes>
</Item>
```

| Important |
|:----------|
| The Magento Retail Order Management Extension does not support importing product images, including gift wrap items. Gift wrap images will require manual upload by a Magento business user or importation by a Magento system integrator. |

### Gift Wrapping Attribute Mappings

| XPath | Magento Gift Wrapping Attribute | Description | Language Support |
|:------|:--------------------------------|:------------|:-----------------|
| ItemId/ClientItemId | SKU | New attribute added by the extension | No |
| BaseAttributes/ItemDescription | Gift Wrapping Design | This is the descriptive name that appears during checkout when Gift Wrapping is selected. | No |
| BaseAttributes/TaxCode | Tax Class | New attribute added by the extension | No |
| ExtendedAttributes/Price | Price | Price of this gift wrapping item. This attribute will only update via the Item Master, and will not update via the Pricing feed. | No |

*All XPath expressions are relative to `/ItemMaster/Item[]/` in the Item Master.*

All other XML nodes will be ignored, unless custom mapped, when importing gift wrapping items into Magento.

- - -
Copyright © 2014 eBay Enterprise, Inc.
