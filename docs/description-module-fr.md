# DHL Deutschepost 3.2.9 — Description du module

## Description courte

Créez des étiquettes DHL Paket et Deutsche Post INTERNETMARKE depuis les commandes PrestaShop et gérez suivi, douane, manifestes et retours compatibles dans le Back Office.

## Description complète

DHL Deutschepost relie les transporteurs PrestaShop aux produits DHL et Deutsche Post souscrits. L'équipe prépare l'expédition dans la commande, crée/télécharge l'étiquette et enregistre le numéro de suivi. En option, le module change le statut ou envoie l'e-mail de transit inclus. DHL propose des valeurs colis, services supplémentaires, export et retours; Deutsche Post contrôle INTERNETMARKE, PDF/PNG, position, manifeste et bordereau.

Le module réduit la ressaisie dans le portail du transporteur. Il ne remplace pas le contrat, ne calcule pas les tarifs live du checkout et ne rend pas admissibles des services absents du compte.

## Fonctions principales

- DHL Parcel Shipping API 2.1 pour un expéditeur allemand.
- Deutsche Post INTERNETMARKE REST en PDF ou PNG.
- Association carrier–produit–participation.
- Étiquettes DHL unitaires et lots compatibles.
- Tracking dans la commande et cron protégé.
- Statut/e-mail optionnels, douane produit et export.
- Formats, dimensions et services DHL par défaut.
- Packstation/Postfiliale avec Google Maps facultatif.
- Retours DHL et intégration RMA compatible.
- Manifestes/bordereaux lorsque produit/API le permettent.
- Configuration/activation multiboutique contextuelle.
- Documentation locale en six langues.

## Usage et déroulement type

Le module convient aux boutiques expédiant sous contrat DHL Paket depuis l'Allemagne ou utilisant INTERNETMARKE. L'administrateur sélectionne le contexte et configure compte, expéditeur, produits et transporteurs. L'entrepôt vérifie l'envoi, crée l'étiquette; le module stocke le suivi et applique seulement les actions choisies. Manifeste, retour et suivi sont utilisés lorsqu'ils sont nécessaires et compatibles.

## Avantages marchand

- Moins de ressaisie de commandes et d'adresses.
- Étiquettes et suivi liés à la commande.
- Valeurs contrôlées pour entrepôt, produits, dimensions et formats.
- DHL Paket et INTERNETMARKE dans une interface.
- Contrôles explicites du consentement, des logs et de la sortie.
- Guide/description offline en six langues.

## Administration et multiboutique

**DHL settings**, **DHL DP settings**, **Information** et **DHL Manifest** structurent compte, produits/carriers, étiquettes, services, retours, expéditeur, COD et INTERNETMARKE. Démarrage rapide relie documentation locale, catalogue Silbersaiten et support.

Les réglages acceptent toutes les boutiques, un groupe ou une boutique; l'activation suit le contexte. Certaines données restent globales/partagées: formats/version PPL, produits téléchargés, douane produit et tables sans colonne boutique. Manifest/Information exigent une boutique. L'isolation physique complète par boutique n'est donc pas garantie.

## Confidentialité, services et compatibilité

Les requêtes peuvent transmettre identité, adresses, contenu/valeurs, références, e-mail et téléphone. Si le consentement est désactivé, e-mail et téléphone sont envoyés à DHL par défaut. Sont stockés comptes, étiquettes, colis, tracking, RMA, douane, produits/prix, fichiers et logs facultatifs; la désinstallation ne les efface pas automatiquement.

Le module peut contacter DHL Shipping/Returns/Token/Location Finder/Tracking, Deutsche Post INTERNETMARKE/Tracking, Silbersaiten pour PPL, Google Maps facultatif et messagerie/HP ePrint. La documentation est locale.

Le code déclare PrestaShop de `1.6` à la version exécutée; le CHANGELOG cite PrestaShop 8 et 9. Il ne déclare pas de plage PHP, malgré des corrections consignées pour 7.2 et 8.4. Le code actuel n'est pas compatible avec PHP 8.5 en raison de la signature de `SoapClient::__doRequest()`. L'environnement exact doit être testé.

## Limites importantes

- Expéditeur DHL uniquement en Allemagne et API 2.1.
- Comptes Live et produits souscrits nécessaires.
- Pas de moteur tarifaire checkout, file ni nettoyage automatique.
- Disponibilité, prix, délais et acceptation dépendent du transporteur.
- Multiboutique configurable, mais tous les stockages ne sont pas séparés par boutique.

## Avantages essentiels

- Étiquettes et suivi intégrés à PrestaShop.
- DHL Paket et INTERNETMARKE ensemble.
- Association produits et valeurs pratiques d'entrepôt.
- Douane, services, manifestes et retours compatibles.
- Multiboutique documentée sans promesse excessive.
- Six langues offline et accès direct au support.

## Trois textes très courts pour la fiche

1. Créez des étiquettes DHL Paket et INTERNETMARKE directement depuis les commandes PrestaShop.
2. Reliez les transporteurs PrestaShop à DHL/Deutsche Post, au suivi, à la douane et aux retours.
3. Gérez les expéditions DHL et Deutsche Post sous contrat depuis le Back Office PrestaShop.
