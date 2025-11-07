# DHL Deutschepost

## Changelog
#### 3.0.5 (20.10.2025)
* Fixed tracking number in the email

#### 3.0.4 (17.10.2025)
* Fixed return label
* Fixed countries list for labels generating
* Separate the auth settings

#### 3.0.3(22.09.2025)
* PHP 8.4 -> 7.2 fix
* Translations DE

#### 3.0.2(03.09.2025)
* PHP 8.4 compatibility (classes/fpdi/fpdi_pdf_parser.php fix)

#### 3.0.1(08.08.2025)
* PS 9.0 compatibility

#### 3.0.0(07.08.2025)
* Transition from SOAP to REST API

## Default Settings Creation
* Added a Default Settings section in the module configuration.
* Moved "Enable 'Parcel outlet routing' service by default" to this section.
* Added new option: "Choose premium service for 'Warenpost International'".

## Bulk Label Creation
* Added per-order DHL warning handling and selective label
* creation in bulk label generation
* for improved flexibility and user control.

# Improving UX
* Added scroll button to DHL block in order admin panel.
* The customs number is now optional.
* Updated product name to DHL Kleinpaket

# API Logging Security Enhancement
## Changes Made
* Implemented centralized and secure API logging system
* Added masking of sensitive data in API requests and responses
* Standardized logging format across all API operations
### Security Improvements
Data Protection:
* Masking sensitive personal information (emails, phones)
* Hiding authentication credentials (passwords, tokens, client secrets)
* Protecting API endpoints and routing codes
* Securing shipment tracking information

#### 2.0.7(24.04.2025)
* Added a new setting that disables manifest creation from orders by default

#### 2.0.6(23.01.2025)
* fixed compatibility with earlier PHP versions
* product prices update for DP: all current product prices are removed, and the new data is added afterward.

#### 2.0.5(21.01.2025)
* added "Enable Order Status Update" option in Deutschepost settings

#### 2.0.4(08.01.2025)
* change of maximum height for DHL Kleinpaket from 5 to 8
* fix for error when updating Deutsche Post product list
* in the module settings, a Clear API log file button has been added to clear the API log of DHL and DP

#### 2.0.3(04.01.2025)
* The product Warenpost will be replaced by DHL Kleinpaket

## Changelog
#### 2.0.2(16.09.2024)
* Fixed safety
* authentication data has been moved to the .env file
* SSL verification enabled
* hidden value of password field in settings
* when reformatting the label, errors with a warning status are displayed
* updated login and password in the DHL sandbox 
* fixed bug with permission to transfer email and phone

#### 2.0.1(06.03.2024) 
* Fixed the DHL_PFPS_MAP parameter

#### 2.0.0(28.11.2023) 
* using rest api instead of soap api for locator
* added new option for disabling/enabling google map for searching packstations and postfilials
* updated form for searching packstations and postfilials on frontend


#### 1.1.6(02.11.2023)
* removed extra setting of module

#### 1.1.5(08.09.2023)
* removed ShipmentDetails.Notification

#### 1.1.4(19.07.2023)
* DHL API 3.5
* added Signed For By Recipient in additional services

#### 1.1.3(25.05.2023)
* fixed update status

#### 1.1.2(05.05.2023)
* fixed saving pdf label of dhl retour portal
* fixed creating labels after repost page

#### 1.1.1(04.01.2023)
* removed tracking api settings for updating 'delivered' status of order

#### 1.1.0(10.11.2022)
* compatibility ps 8.0
* added note 2 for bank data and for COD of shipment order
* added new option in module settings: E-mail address of HP ePrint printer
* updated list of label formats
* DHL API 3.4
* removed possibility to search postoffice and postfiliale on order page
* added new option in module settings: Enable searching DHL Postfiliales and DHL Packstations in front end


#### 1.0.17(16.09.2022)
* fixed displaying block of updating address in batch mode

#### 1.0.16(04.04.2022)
* fixed state on update address

#### 1.0.15(27.01.2022)
* added Warenpost international

#### 1.0.14(05.01.2022)
* added possibility to use shipper address by Shipper reference. Company logo on label

#### 1.0.13(20.09.2021)
* fixed custom value of product in export document
* modified address parsing
* fixed link of AdminOrders controller for  ps 1.6.x

#### 1.0.12(08.09.2021):
* removed button for mass-production of Deutschepost labels
* modified logic of parsing address of receiver(finding house number in address2 field)

#### 1.0.11(20.08.2021):
* added recepient email address for notification in additional services
* renamed label formats


#### 1.0.10(22.07.2021):
* fixed 'generate labels' url for some ps1.7.7.x installations

#### 1.0.9(15.07.2021):
* added PS 1.6.x compatibility

#### 1.0.8(15.02.2021):
* fixed PS 1.7.7 compatibility

#### 1.0.7(12.02.2021):
* fixed manifest secure token

#### 1.0.6(10.02.2021):
* fixed PS 1.7.7 compatibility
* fixed manifest
* updated list of dhl products
* added export documents for GB

#### 1.0.5(30.01.2021):
* added PS 1.7.7 compatibility

#### 1.0.4(21.01.2021):
* fix dp autorization

#### 1.0.3(16.11.2020):
* change sending mails according dhlcarrieraddress v.1.0.3

#### 1.0.2(22.08.2020):
* added DHL Retoure API
* added Deutschepost
* added PostNumber for Postfiliale

#### 1.0.1(15.07.2020):
* fixed minor bug

#### 1.0.0(23.06.2020):
* init 

