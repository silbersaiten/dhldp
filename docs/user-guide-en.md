# DHL Deutschepost 3.2.9 — User guide

## Start here

DHL Deutschepost connects PrestaShop orders to DHL Paket business customer shipping and Deutsche Post INTERNETMARKE. It creates shipping labels in the Back Office, stores tracking numbers, supports bulk label generation, manifests and supported return workflows, and can add Packstation/Postfiliale address helpers to checkout.

The module does not create a DHL contract, activate products, calculate live checkout rates or decide which services your contract contains. Before configuring Live mode, obtain the contract data described below from DHL.

## What you need before configuration

| Required item | Where it comes from | Used for |
| --- | --- | --- |
| DHL business customer contract | Your DHL sales contact | Live DHL Paket shipping |
| GKP user name and password | Post & DHL Business Customer Portal (GKP) | Authentication in Live mode |
| EKP | GKP contract data or DHL contract documents | Identifies the customer account |
| Billing numbers | GKP contract positions or DHL contract documents | Identify product/procedure and participation |
| DHL Retoure activation | DHL contract | Return labels, if required |
| Portokasse account | Deutsche Post | INTERNETMARKE labels, if required |

Ask the DHL contact responsible for your contract if an item is missing. The module cannot derive or activate contract numbers.

## First login to the DHL Business Customer Portal

1. Open the **Post & DHL Business Customer Portal** at <https://geschaeftskunden.dhl.de/>.
2. Use the personal user name supplied for the contract and the initial password or password-reset link.
3. Complete any password change or activation requested by the portal.
4. If no access data arrived, use **Forgot password** or contact the account administrator/DHL contact. DHL normally sends the initial portal access after the business-customer setup has been processed.
5. For a production API integration, DHL recommends a dedicated **business customer system user**. Create or request it after a personal administrator can access GKP.

A system user is intended for API authentication and cannot be used to sign in to the GKP web interface. Keep at least one personal administrator account for portal administration. Do not enter DHL Developer Portal client IDs or client secrets: this module has no fields for them and authenticates Live requests with the configured GKP credentials.

## Find EKP, product and participation numbers

In GKP, open the contract area—commonly **Contract data > Contract positions** (*Vertragsdaten > Vertragspositionen*)—and locate the **billing number** (*Abrechnungsnummer*) for every DHL product the shop will use. Portal wording can change; the decisive value is the billing number assigned to the contract position.

A DHL Paket billing number has 14 characters:

`1234567890 01 01`

| Part | Length | Example | Enter it in the module as |
| --- | --- | --- | --- |
| EKP | 10 characters | `1234567890` | **EKP** |
| Procedure/product | 2 characters | `01` | Select the matching **DHL Product** |
| Participation | 2 characters | `01` | Enter **Participation** beside that product |

Do not paste the complete 14-character billing number into the Participation field. Do not enter the middle product code there either.

Current product choices in the module are:

| Procedure | Module product |
| --- | --- |
| `01` | DHL Paket |
| `53` | DHL Paket International |
| `54` | DHL Europaket |
| `62` | DHL Kleinpaket |
| `66` | Warenpost International |

Add only products present in your contract. The same product can have more than one participation; create the combinations that are actually needed. Participation values can be numeric or alphanumeric where DHL assigns them, although the module's separate **Return participation** field currently accepts exactly two digits.

For returns, use the participation from the relevant DHL Retoure contract position. `01` is common but is not guaranteed. The old module documentation described separate Retoure Portal credentials and a portal ID; the current version has no such settings and does not use that legacy login method.

## Requirements and compatibility

- PrestaShop: the module declares version 1.6 as its minimum and the running PrestaShop version as its maximum. The changelog includes work for PrestaShop 8 and 9.
- PHP: no formal range is declared. The changelog records PHP 7.2 and 8.4 fixes. The current code is not compatible with PHP 8.5 because of the `SoapClient::__doRequest()` method signature; use a supported PHP version such as 8.4 until the module is adapted.
- PHP/runtime: outbound HTTPS, cURL, JSON and mbstring are used. The `logs`, `pdfs` and `data` module directories must be writable.
- DHL: current shipper country is Germany (`DE`) and the configured shipping API version is fixed to `2.1`.
- Accounts: Live DHL labels require GKP credentials and contracted products. INTERNETMARKE requires a Deutsche Post Portokasse account.

Before installation, back up files and database, test on staging, confirm outbound HTTPS access and decide which existing PrestaShop carriers correspond to which contract products. In multishop, select the intended shop context before saving credentials or mappings.

## Install or update

### New installation

1. In **Modules > Module Manager**, choose **Upload a module** and select the release ZIP.
2. Install **DHL Deutschepost** and open **Configure**.
3. Follow the first-time connection below.

### Update without losing settings

Upload the new ZIP through Module Manager or deploy the complete new `dhldp` directory over the existing one. Do not uninstall first. Run the PrestaShop module upgrade when offered. Existing configuration keys and data are retained by the normal upgrade path.

After an update, clear the PrestaShop and browser cache only if old templates, translations, CSS or JavaScript remain visible.

## Connect DHL for the first time

1. Open **DHL settings** in the module configuration.
2. Select **Sandbox** to learn the workflow without a live contract. The module uses its bundled DHL sandbox authentication data. Select **Live** only for production.
3. In Live mode, enter the GKP/API user name, its password and the 10-character EKP, then save. Saving performs an account check. A row of asterisks shown later means a password is stored; it is not the password itself.
4. Under **DHL Products**, add each contracted product. Select the product using the middle two characters of its billing number and enter the final two characters as Participation.
5. Under **Carriers**, assign every relevant PrestaShop carrier to the correct DHL product/participation combination. An order receives DHL actions only when its carrier is mapped.
6. Enter the sender address. Keep street and house number in their separate fields. Alternatively select a GKP shipper reference and copy that reference exactly.
7. Choose the label format for the installed printer. Leave automatic status changes, warning acceptance, immediate returns and extra services off for the first test.
8. Save and create a label for a test order.

Sandbox proves the module workflow, not the contents of your live contract. Always repeat one controlled test in Live mode before normal shipping.

## Create the first DHL label

1. Create or select a test order whose carrier is mapped in **DHL settings**.
2. Open the order and choose **Generate label** in the DHL area.
3. Check recipient street and house number, postcode, country, weight and dimensions. Correct an address using **Update delivery address** before retrying.
4. For an international shipment, check description, value, origin country and customs tariff number for every customs position.
5. Select only services supported by the product and contract, then submit.
6. Open the generated PDF and verify sender, recipient, product, format and shipment number.
7. Confirm that the tracking number appears on the order and that any configured order-status or email action occurred exactly once.

If the recipient address arrives as one combined street line, split the house number into its dedicated field. This is a frequent reason for label rejection.

## Generate labels in bulk

1. Open the PrestaShop order list.
2. Select orders whose carriers have valid DHL mappings.
3. Choose the bulk action **Generate DHL labels**.
4. Review failures individually; one invalid address, missing weight or unsupported service may affect only that order.
5. Use **Print last labels** to retrieve the most recently generated batch when appropriate.

Start with a small batch. Bulk processing does not remove the need for valid order addresses, weights and customs data.

## Common first-label problems

| Symptom | Likely cause | What to check |
| --- | --- | --- |
| Account data is rejected on save | Wrong Live user/password/EKP, locked user or unavailable product | Sign in with the personal GKP account, verify the API/system user separately, reset its password if needed and compare EKP with the contract data. |
| Authentication works in the portal but not in the module | A personal portal user and API system user were confused, or the password expired | Use the credentials assigned for API use. Remember that a system user cannot log into the portal UI. |
| No DHL action appears on an order | Its PrestaShop carrier is not mapped in the current shop context | Recreate the carrier mapping for the order's carrier and shop. |
| Product or participation is rejected | Wrong part of the billing number was entered or the product is not contracted | Re-read the 14-character billing number: select characters 11–12 as product and enter characters 13–14 as participation. |
| Address is rejected | House number is missing/combined, postcode or country is invalid | Separate street and house number and compare the address with the destination format. |
| Weight/dimensions are rejected | Empty, zero, wrongly converted or beyond product limits | Check product weights, packaging weight and conversion rate (`1` for kg, `0.001` for grams). |
| Export shipment fails | Customs description, value, origin or tariff number is missing | Complete every customs position; the module validates tariff numbers as 6, 8 or 10 digits. |
| A service is unavailable | It is not supported by product, destination or contract | Remove the service or ask DHL to confirm the contract position. |
| PDF is not stored | `pdfs` is not writable or the API request failed | Check permissions, disk space and temporarily enable the masked API log. |
| Old settings are still shown | Wrong multishop context or stale cache | Select the correct context, save again and clear PrestaShop/browser cache. |

## Settings that require a deliberate choice

The remaining settings are best understood by their effect, not configured one by one without a shipping policy:

- **Weight:** enable product-weight calculation only when product weights are maintained. Use conversion `1` for kilograms or `0.001` for grams, and add realistic packaging weight.
- **Printer:** choose a format matching the printer. `100x70mm` is intended only for DHL Kleinpaket and Warenpost International.
- **Order status and mail:** begin with no automatic status change and no transit mail. Enable them after checking that PrestaShop does not send duplicate messages.
- **Warnings:** leave automatic acceptance off initially so staff see DHL warnings.
- **Additional services:** Parcel outlet routing, GoGreen, GoGreen Plus, age check, Premium and return options depend on product, destination and contract and may cost extra.
- **Privacy consent:** if consent is disabled, the module sends customer email and phone to DHL by default. Decide the lawful basis and customer information with the shop's privacy adviser.
- **Packstation/Postfiliale:** the checkout helpers can be enabled without a map. Google Maps is optional and requires a separately restricted Google Maps API key.
- **Returns:** enable extended return management only together with PrestaShop merchandise returns and after testing allowed return countries. Immediate return-label sending is off by default.
- **Cash on delivery:** enter account owner and correct IBAN/BIC only when the contract uses COD.
- **Logging:** enable DHL/DP logs only for diagnosis and disable them afterwards; logs and PDFs are not automatically purged.

## Deutsche Post INTERNETMARKE first setup

This is a separate workflow from DHL Paket.

1. Register or sign in to **Portokasse** at <https://portokasse.deutschepost.de/portokasse/>. A new registration may require an activation code sent by post.
2. Open **DHL DP settings** and enter the Portokasse/INTERNETMARKE user name and password.
3. On the first connection, Portokasse may request permanent approval for the business application. Review it under **My data > Business applications** (*Meine Daten > Geschäftsanwendungen*).
4. Retrieve page formats and update the PPL product list if required.
5. Assign only the PrestaShop carriers that should use Deutsche Post, choose a default product and output format (`pdf` or `png`), and enter the sender address.
6. For PDF sheets, select page format, start page, row and column and print one test page before bulk use.

If login fails, test the Portokasse login directly and use its password-reset function. A Deutsche Post product being visible in the module does not guarantee account eligibility or the current price.

## Multishop

The settings pages accept all-shops, shop-group and single-shop contexts and use normal PrestaShop configuration inheritance. Save broad defaults in all-shops context, group values only when truly shared, and credentials, senders and carrier mappings in each individual shop where they differ.

Operational settings are generally read using the order's shop. However, the six custom module tables have no direct `id_shop`; product customs data and the Deutsche Post product list are shared, and the PPL version/page-format list is global. Manifest and Information controllers require a single-shop context when multishop is active. Test one label in every operational shop.

## Stored data, privacy and external services

The module stores credentials and settings in PrestaShop configuration; label, package, order-consent, customs and INTERNETMARKE records in six `dhldp_*` tables; generated files in `pdfs`; optional logs in `logs`; and the Deutsche Post product list in `data/ppl.csv`.

Shipment creation can transmit sender and recipient names, addresses, contents, values, references, email and phone to DHL or Deutsche Post as required by the chosen service. Optional integrations are DHL Location Finder, Google Maps for the map view, `prestamodule.silberserver.de` for the PPL CSV update, shop email transport and an HP ePrint address when configured. The local documentation itself has no external runtime dependency.

## Limits, cache and uninstallation

- Current DHL shipper-country support is Germany only; API version is fixed to 2.1.
- There is no live checkout-rate calculation, background queue or worker. Most API calls run in the web request.
- Product/service availability and prices are controlled by external accounts and APIs.
- Tracking is updated by `cron_track.php` using the module secure key; there is no console command. Protect the cron URL.
- The old guide's Austrian sender workflow, separate Retoure Portal login and manual legacy tracking URL do not describe this version.

Normal label creation requires no cache clearing. Clear cache after an update only when old assets or translations remain.

Uninstall removes module tabs and custom hooks but deliberately does not delete the six tables, configuration values, labels, logs or product list. Back up and remove those items manually only when complete erasure is intended.

## Recommended first-production checklist

- Live account check succeeds with the intended API user and EKP.
- Every mapped carrier points to a contracted product and correct participation.
- Sender, street/house-number split, weight conversion and printer format are verified.
- One domestic label, one relevant export label and any return workflow have been tested separately.
- Status changes, customer mail and privacy consent match shop policy.
- Each multishop context has its own controlled test.
- Diagnostic logging is off after acceptance testing.

