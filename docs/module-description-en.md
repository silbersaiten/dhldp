# DHL Deutschepost 3.2.9 — Module description

## Short description

Create DHL Paket and Deutsche Post INTERNETMARKE shipping labels from PrestaShop orders, store tracking data, and manage supported delivery, customs, manifest and return workflows from the Back Office.

## Full description

DHL Deutschepost links selected PrestaShop carriers with contracted DHL and Deutsche Post products. Shop staff can prepare shipment data on an order, create and download supported labels, write the tracking number back to the order carrier, and optionally change order status or send the included transit email. DHL products can use configured package defaults, additional services, export data and return-label functions. The Deutsche Post section provides INTERNETMARKE product, label, page-position, manifest and shipping-list controls.

The module addresses the repetitive work between an order and the carrier portal. It does not replace a DHL/Deutsche Post contract, calculate live checkout prices, or make unavailable services eligible.

## Core features

- DHL Parcel Shipping API v2.1 label creation for the module's Germany shipper configuration.
- Deutsche Post INTERNETMARKE REST label creation in PDF or PNG.
- Mapping between PrestaShop carriers and DHL/Deutsche Post products.
- Single-order and supported bulk DHL label workflows.
- Tracking-number storage and a secure cron entry point for tracking refresh.
- Optional order-status update and transit email after label creation.
- Product customs tariff number and country-of-origin fields.
- Export-document data and selectable DHL label formats.
- Supported DHL additional services and defaults, including age check, routing and sustainability options.
- Packstation and Postfiliale address assistance, with an optional Google map.
- DHL return labels, enclosed returns and integration with supported PrestaShop RMA flows.
- DHL manifests and Deutsche Post manifest/shipping-list options where the API/product permits.
- Context-aware configuration and activation controls for multishop.
- Local documentation in six languages, available without an internet connection.

## Typical uses

The module fits shops that fulfil contracted DHL Paket shipments from Germany, use Deutsche Post INTERNETMARKE for suitable mail products, need labels and tracking in the order workflow, or require customs and return-label data alongside PrestaShop orders.

## Typical workflow

1. Select the shop context and configure account and sender data.
2. Add contracted products and map PrestaShop carriers.
3. Open an eligible order and review shipment, package and optional-service data.
4. Create and inspect the label.
5. The module stores label/tracking metadata and applies configured status/email behaviour.
6. Use manifest, return or tracking functions when required and supported.

## Benefits for the merchant

- Reduces re-entry of order and address data in separate carrier portals.
- Keeps label references and tracking numbers connected to the PrestaShop order.
- Gives warehouse staff controlled defaults for products, dimensions, formats and services.
- Supports both DHL Paket and Deutsche Post workflows in one module interface.
- Provides explicit controls for customer contact-data consent, logs and output choices.
- Supplies offline user and product documentation in the module package.

## Administration

Configuration is divided into **DHL settings**, **DHL DP settings**, **Information**, and **DHL Manifest**. DHL settings cover account authentication, products/carriers, label behaviour, additional services, returns, sender address and COD bank data. Deutsche Post settings cover INTERNETMARKE credentials, products/carriers, output format/page placement and sender address. A Quick start panel links to the local guide, local description, Silbersaiten's module catalogue, paid support and support email.

## Multishop

The settings controllers accept all-shop, group and individual-shop contexts, and the module can be enabled or disabled for the selected context. Operational reads frequently use the order's shop ID. Some resources remain global or shared: Deutsche Post page formats and PPL version, downloaded products, product customs, and custom tables without a direct shop column. Manifest and Information screens require a single shop when multishop is active. Merchants should verify every shop separately and avoid describing the module as fully data-isolated per shop.

## Privacy and stored data

Shipment requests can transmit sender/recipient identity, addresses, shipment content and values, tracking references, email and phone to DHL or Deutsche Post as required by the chosen service. When the optional customer-confirmation setting is off, email and phone are sent to DHL by default. The merchant remains responsible for lawful processing, notices and retention.

The module stores configuration credentials, shipment/label/package/tracking metadata, RMA consent, customs data, Deutsche Post products/prices, generated files and optional logs. Logs are masked by module logic but must still be treated as sensitive. Uninstall does not automatically erase these tables, settings, logs or generated files.

## External services

Runtime functions can contact DHL Parcel Shipping, Returns, token, Location Finder and tracking services; Deutsche Post INTERNETMARKE and tracking; Silbersaiten infrastructure for the PPL CSV; Google Maps when explicitly enabled; and the shop mail system/HP ePrint address when configured. The included documentation itself is fully local and uses no CDN.

## Compatibility

The module declares PrestaShop `1.6` as minimum and the currently executing PrestaShop version as maximum. Its changelog records compatibility work for PrestaShop 8 and 9. No formal PHP version range is declared; the changelog records fixes for PHP 7.2 and 8.4. The current code is not compatible with PHP 8.5 because of its `SoapClient::__doRequest()` method signature. The exact environment and contracted carrier products must be tested before production.

## Important limitations

- DHL sender country is Germany only in the current API definition.
- DHL API selection is fixed to version 2.1.
- Live accounts and eligible contract products are required.
- There is no live checkout-rate engine, background queue or automatic log/file retention cleanup.
- External availability, prices, delivery performance and service acceptance are controlled by DHL/Deutsche Post, not guaranteed by the module.
- Multishop configuration is supported, but not every custom data store is physically separated by shop.

## Main advantages

- Integrated label and tracking workflow inside PrestaShop.
- DHL Paket and Deutsche Post INTERNETMARKE in one module.
- Carrier/product mapping and practical warehouse defaults.
- Customs, additional-service, manifest and supported return controls.
- Multishop-aware settings with clearly documented shared data.
- Six-language offline documentation and direct support links.

## Very short store-card texts

1. Create DHL Paket and Deutsche Post INTERNETMARKE labels directly from PrestaShop orders.
2. Connect PrestaShop carriers with DHL/Deutsche Post labels, tracking, customs and returns.
3. Manage contracted DHL and Deutsche Post shipping workflows from your PrestaShop Back Office.
