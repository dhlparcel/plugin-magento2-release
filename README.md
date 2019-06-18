# DHL Parcel for Magento 2
---------------------------

# Install

## Installation with composer
- Add the plugin to your composer with the command (recommended version range)  
`composer require dhlparcel/magento2-plugin:~1.0.0`

- Enable the DHL module by executing the following from the Magento root:  
`bin/magento module:enable DHLParcel_Shipping`

- Upgrade the database  
`bin/magento setup:upgrade`

- When running in production, complete the installation by recompiling  
`bin/magento setup:di:compile`

## Installation with zip extraction
- Go to the Magento 2 directory

- Extract the contents of the `magento2.zip` file in a new directory: `app/code/DHLParcel/Shipping`  
(If you're upgrading, remove the old files first)

- De plugin uses de Guzzle package to communicate with the API. Add Guzzle to the Magento root `composer.json`.  
`composer require guzzlehttp/guzzle`

- Enable the DHL module by executing the following from the Magento root:  
`bin/magento module:enable DHLParcel_Shipping`

- Upgrade the database  
`bin/magento setup:upgrade`

- When running in production, complete the installation by recompiling  
`bin/magento setup:di:compile`
