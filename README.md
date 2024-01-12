# DHL eCommerce for Magento 2
---------------------------
DHL offers a convenient plug-in for Magento 2 online stores. This plug-in allows you to add multiple online delivery options and to print shipping labels directly in your online store, which makes shipping packages significantly easier and a lot more fun. Please note that this plug-in is only available for online stores that ship orders from the Benelux region.

# Install / Update
## Update instructions
- If you've installed a previous version with zip extraction, please remove the files found in `app/code/DHLParcel/Shipping` and proceed with the installation instructions (can be either composer or zip extraction).

- If you're installed a previous version with composer with the recommended version range, just run the following commands to complete the update  
`composer update dhlparcel/magento2-plugin:~1.0.0`  
`php bin/magento setup:upgrade`  
`php bin/magento setup:di:compile (only for production environments)`

## Installation with composer
- Add the plugin to your composer with the command (recommended version range)  
`composer require dhlparcel/magento2-plugin:~1.0.0`

- Enable the DHL module by executing the following from the Magento root:  
`php bin/magento module:enable DHLParcel_Shipping`

- Upgrade the database  
`php bin/magento setup:upgrade`

- When running in production, complete the installation by recompiling  
`php bin/magento setup:di:compile`

## Installation with zip extraction
- Go to the Magento 2 directory

- Extract the contents of the `magento2.zip` file in a new directory: `app/code/DHLParcel/Shipping`  
(If you're upgrading, remove the old files first)

- De plugin uses de Guzzle package to communicate with the API. Add Guzzle to the Magento root `composer.json`.  
`composer require guzzlehttp/guzzle`

- De plugin uses fpdi-fpdf for merging pdf's. Add fpdi-fpdf to the Magento root `composer.json`.  
`composer require setasign/fpdi-fpdf`

- Enable the DHL module by executing the following from the Magento root:  
`php bin/magento module:enable DHLParcel_Shipping`

- Upgrade the database  
`php bin/magento setup:upgrade`

- When running in production, complete the installation by recompiling  
`php bin/magento setup:di:compile`

## When updating with zip with a version before 1.0.10 to current

- De plugin uses fpdi-fpdf for merging pdf's. Add fpdi-fpdf to the Magento root `composer.json`.  
`composer require setasign/fpdi-fpdf`