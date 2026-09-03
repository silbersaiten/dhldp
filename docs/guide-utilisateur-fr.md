# DHL Deutschepost 3.2.9 — Guide utilisateur

## Commencez ici

DHL Deutschepost relie les commandes PrestaShop à l'expédition DHL Paket pour clients professionnels et à Deutsche Post INTERNETMARKE. Depuis le Back Office, le module crée les étiquettes, enregistre les numéros de suivi, traite des lots, prend en charge les manifestes et retours prévus et peut faciliter la saisie des adresses Packstation/Postfiliale au checkout.

Le module ne souscrit pas de contrat DHL, n'active aucun produit, ne calcule pas de tarifs en temps réel au checkout et ne détermine pas le contenu de votre contrat. Avant le mode Live, obtenez auprès de DHL les informations ci-dessous.

## Informations à obtenir avant la configuration

| Élément | Source | Utilisation |
| --- | --- | --- |
| Contrat DHL professionnel | Votre interlocuteur commercial DHL | Expéditions DHL Paket réelles |
| Identifiant et mot de passe GKP | Post & DHL Business Customer Portal | Authentification Live |
| EKP | Données du contrat dans GKP ou documents DHL | Identification du compte |
| Numéros de facturation (*Abrechnungsnummern*) | Positions du contrat dans GKP | Produit/procédure et participation |
| Activation DHL Retoure | Contrat DHL | Étiquettes de retour, si nécessaires |
| Compte Portokasse | Deutsche Post | INTERNETMARKE, si utilisée |

Si une information manque, demandez-la à la personne responsable du contrat. Le module ne peut ni découvrir ni activer ces numéros.

## Première connexion au portail DHL

1. Ouvrez le **Post & DHL Business Customer Portal (GKP)** : <https://geschaeftskunden.dhl.de/>.
2. Utilisez l'identifiant personnel reçu pour le contrat et le mot de passe initial ou le lien de réinitialisation.
3. Terminez l'activation ou le changement de mot de passe demandé.
4. Si aucun accès n'est arrivé, utilisez **Forgot password/Passwort vergessen** ou contactez l'administrateur du compte ou DHL. L'accès initial est normalement envoyé après le traitement de l'inscription professionnelle.
5. Pour une API en production, DHL recommande un **utilisateur système professionnel** dédié. Créez-le ou demandez-le une fois qu'un administrateur personnel accède à GKP.

Cet utilisateur système sert à l'API et ne peut pas se connecter à l'interface web GKP. Conservez au moins un administrateur personnel. Ne renseignez pas de client ID ou client secret du DHL Developer Portal : le module ne possède pas ces champs et utilise les identifiants GKP configurés pour les requêtes Live.

## Trouver l'EKP, le produit et la participation

Dans GKP, ouvrez la zone du contrat — généralement **Vertragsdaten > Vertragspositionen** — et repérez l'**Abrechnungsnummer** de chaque produit utilisé. Les libellés du portail peuvent évoluer ; la valeur déterminante est le numéro associé à la position du contrat.

Un numéro de facturation DHL Paket comporte 14 caractères :

`1234567890 01 01`

| Partie | Longueur | Exemple | Saisie dans le module |
| --- | --- | --- | --- |
| EKP | 10 caractères | `1234567890` | Champ **EKP** |
| Procédure/produit | 2 caractères | `01` | Choisir le **produit DHL** correspondant |
| Participation | 2 caractères | `01` | Champ **Participation** du produit |

Ne collez pas les 14 caractères dans Participation et n'y saisissez pas le code produit central.

| Procédure | Produit proposé par le module |
| --- | --- |
| `01` | DHL Paket |
| `53` | DHL Paket International |
| `54` | DHL Europaket |
| `62` | DHL Kleinpaket |
| `66` | Warenpost International |

Ajoutez uniquement les produits de votre contrat. Un même produit peut avoir plusieurs participations. DHL peut attribuer des valeurs numériques ou alphanumériques, mais le champ séparé **Return participation** de cette version exige exactement deux chiffres.

Pour les retours, copiez la participation de la position DHL Retoure concernée. `01` est fréquent, sans être garanti. L'ancienne documentation utilisait des identifiants Retoure Portal et un portal ID séparés ; ces champs et cette méthode n'existent plus dans la version actuelle.

## Exigences et compatibilité

- PrestaShop : le module déclare 1.6 comme minimum et la version en cours d'exécution comme maximum. Le CHANGELOG mentionne PrestaShop 8 et 9.
- PHP : aucune plage formelle n'est déclarée. Des corrections concernent PHP 7.2 et 8.4. Le code actuel n'est pas compatible avec PHP 8.5 à cause de la signature `SoapClient::__doRequest()` ; utilisez une version compatible telle que PHP 8.4 jusqu'à adaptation.
- Environnement : HTTPS sortant, cURL, JSON et mbstring sont utilisés. `logs`, `pdfs` et `data` doivent être accessibles en écriture.
- DHL : le pays expéditeur proposé est l'Allemagne (`DE`) et l'API d'expédition est fixée à `2.1`.
- Comptes : le mode Live nécessite GKP et les produits contractuels ; INTERNETMARKE nécessite Portokasse.

Sauvegardez fichiers et base, testez en préproduction, vérifiez HTTPS et définissez les transporteurs PrestaShop correspondant aux produits. En multiboutique, choisissez le bon contexte avant d'enregistrer.

## Installation et mise à jour

### Nouvelle installation

1. Dans **Modules > Gestionnaire de modules**, choisissez **Installer un module** et sélectionnez le ZIP de release.
2. Installez **DHL Deutschepost**, puis ouvrez **Configurer**.
3. Suivez la première connexion ci-dessous.

### Mise à jour sans perdre les réglages

Téléversez le nouveau ZIP ou déployez le dossier `dhldp` complet par-dessus l'existant. Ne désinstallez pas d'abord. Exécutez la mise à niveau du module proposée par PrestaShop. Le chemin d'upgrade conserve clés et données.

Ne videz les caches PrestaShop et navigateur que si d'anciens modèles, traductions, CSS ou JavaScript restent visibles.

## Première connexion à DHL

1. Ouvrez **DHL settings**.
2. Choisissez **Sandbox** pour apprendre sans contrat réel ; le module utilise ses identifiants sandbox intégrés. Réservez **Live** à la production.
3. En Live, saisissez l'utilisateur GKP/API, son mot de passe et l'EKP de dix caractères. L'enregistrement vérifie le compte. Les astérisques affichés ensuite signifient seulement qu'un mot de passe est stocké.
4. Dans **DHL Products**, ajoutez chaque produit du contrat : les deux caractères centraux de l'Abrechnungsnummer déterminent le produit et les deux derniers la Participation.
5. Dans **Carriers**, associez chaque transporteur PrestaShop à la bonne combinaison. Les actions DHL n'apparaissent que sur une commande associée.
6. Renseignez l'expéditeur en séparant rue et numéro. Vous pouvez aussi copier exactement une référence d'expéditeur GKP.
7. Choisissez le format d'étiquette de l'imprimante. Pour le premier test, laissez désactivés changements d'état automatiques, acceptation des avertissements, retours immédiats et services supplémentaires.
8. Enregistrez et créez une étiquette pour une commande de test.

La sandbox valide le fonctionnement du module, pas le contenu du contrat Live. Effectuez aussi un test Live contrôlé.

## Créer la première étiquette DHL

1. Ouvrez une commande de test dont le transporteur est associé à DHL.
2. Dans la zone DHL, choisissez **Generate label**.
3. Vérifiez rue et numéro du destinataire, code postal, pays, poids et dimensions. Utilisez **Update delivery address** avant de réessayer.
4. Pour l'export, vérifiez description, valeur, pays d'origine et code tarifaire de chaque position.
5. Sélectionnez uniquement des services prévus par le produit et le contrat, puis envoyez.
6. Ouvrez le PDF et contrôlez expéditeur, destinataire, produit, format et numéro d'envoi.
7. Vérifiez le suivi et que l'état ou l'e-mail configuré n'a été déclenché qu'une fois.

Séparez toujours le numéro de la rue dans son champ. Une adresse combinée est une cause fréquente de rejet.

## Créer un lot d'étiquettes

1. Dans la liste des commandes, sélectionnez celles dont le transporteur DHL est correctement associé.
2. Lancez l'action groupée **Generate DHL labels**.
3. Analysez chaque échec : une adresse, un poids ou un service incorrect peut ne concerner qu'une commande.
4. Utilisez **Print last labels** pour récupérer le dernier lot si nécessaire.

Commencez par un petit lot. Le traitement groupé nécessite toujours des adresses, poids et données douanières valides.

## Problèmes fréquents lors du premier essai

| Symptôme | Cause probable | Vérification |
| --- | --- | --- |
| Compte rejeté à l'enregistrement | Utilisateur, mot de passe ou EKP Live incorrect ; compte verrouillé | Vérifier le compte personnel GKP, l'utilisateur API séparément, réinitialiser son mot de passe et comparer l'EKP au contrat. |
| GKP fonctionne mais pas le module | Confusion entre utilisateur personnel et système, ou mot de passe expiré | Employer les identifiants API ; l'utilisateur système ne se connecte pas à l'interface GKP. |
| Aucune action DHL | Transporteur non associé dans cette boutique | Refaire l'association dans le contexte de la commande. |
| Produit/participation refusé | Mauvaise partie du numéro | Sur 14 caractères : positions 11–12 = produit, 13–14 = participation. |
| Adresse refusée | Numéro dans la rue, code postal ou pays incorrect | Séparer rue/numéro et contrôler le format du pays. |
| Poids/dimensions refusés | Valeur vide, nulle, conversion erronée ou limite dépassée | Contrôler poids et emballage ; conversion `1` pour kg ou `0.001` pour grammes. |
| Export en échec | Description, valeur, origine ou code tarifaire manquant | Compléter chaque position ; le module valide 6, 8 ou 10 chiffres. |
| Service indisponible | Produit, destination ou contrat non compatible | Retirer le service ou demander confirmation à DHL. |
| PDF absent | `pdfs` non inscriptible ou erreur API | Contrôler droits/espace et activer temporairement le journal masqué. |

## Réglages qui nécessitent une décision

- **Poids :** calculer depuis les produits seulement si les poids sont tenus à jour ; utiliser `1` pour kg ou `0.001` pour grammes et ajouter l'emballage.
- **Imprimante :** choisir son format ; `100x70mm` est réservé à DHL Kleinpaket et Warenpost International.
- **État/e-mail :** commencer sans automatisme, puis vérifier l'absence de messages en double.
- **Avertissements :** ne pas les accepter automatiquement pendant les premiers tests.
- **Services :** routing, GoGreen, GoGreen Plus, âge, Premium et retours dépendent du contrat et peuvent être facturés.
- **Confidentialité :** si la confirmation est désactivée, e-mail et téléphone sont envoyés à DHL par défaut. Définir la base légale et l'information client.
- **Packstation/Postfiliale :** les aides fonctionnent sans carte ; Google Maps est facultatif et demande une clé restreinte.
- **Retours :** activer la gestion étendue avec les retours PrestaShop et après test des pays. L'envoi immédiat est désactivé par défaut.
- **Journaux :** uniquement pour le diagnostic ; journaux et PDF ne sont pas purgés automatiquement.

## Première configuration INTERNETMARKE

1. Inscrivez-vous ou connectez-vous à **Portokasse** : <https://portokasse.deutschepost.de/portokasse/>. Une nouvelle inscription peut demander un code d'activation envoyé par courrier.
2. Ouvrez **DHL DP settings** et saisissez les identifiants Portokasse/INTERNETMARKE.
3. À la première connexion, Portokasse peut demander une autorisation permanente de l'application professionnelle. Vérifiez **Meine Daten > Geschäftsanwendungen**.
4. Récupérez les formats de page et actualisez la liste PPL si nécessaire.
5. Associez seulement les transporteurs Deutsche Post, choisissez le produit, la sortie `pdf` ou `png` et l'expéditeur.
6. Pour les planches PDF, réglez page, ligne et colonne et imprimez un essai.

En cas d'échec, testez directement Portokasse et réinitialisez le mot de passe. Un produit visible dans le module ne garantit ni l'éligibilité du compte ni son prix actuel.

## Multiboutique

Les pages prennent en charge tous les shops, groupe et boutique individuelle avec l'héritage PrestaShop. Enregistrez les valeurs générales dans tous les shops et les identifiants, expéditeurs et associations différents dans chaque boutique.

Les réglages opérationnels sont généralement lus pour la boutique de la commande. Cependant, les six tables n'ont pas d'`id_shop` direct ; douanes produit et liste Deutsche Post sont partagées, version PPL et formats sont globaux. Manifest et Information exigent une boutique unique. Testez une étiquette dans chaque boutique active.

## Données, confidentialité, services et limites

Les identifiants et options sont stockés dans la configuration PrestaShop ; étiquettes, colis, consentements, douanes et INTERNETMARKE dans six tables `dhldp_*` ; fichiers dans `pdfs` ; journaux dans `logs` ; produits Deutsche Post dans `data/ppl.csv`.

Selon le service, noms, adresses, contenu, valeurs, références, e-mail et téléphone peuvent être transmis à DHL ou Deutsche Post. Intégrations facultatives : DHL Location Finder, Google Maps, `prestamodule.silberserver.de` pour PPL, e-mail du shop et HP ePrint. La documentation locale n'a aucune dépendance externe.

Le pays expéditeur DHL est actuellement limité à l'Allemagne, l'API est fixée à 2.1 et il n'existe ni tarification checkout ni worker. `cron_track.php` actualise le suivi avec la clé secrète : protégez son URL. L'ancien expéditeur autrichien, la connexion Retoure Portal séparée et l'ancienne URL manuelle de suivi ne concernent pas cette version.

La désinstallation retire onglets et hooks mais conserve tables, configuration, étiquettes, journaux et liste produit. Ne les supprimez manuellement qu'après sauvegarde et si l'effacement total est voulu.

## Contrôle avant mise en production

- Le compte Live est validé avec l'utilisateur API et l'EKP corrects.
- Chaque transporteur correspond à un produit et une participation du contrat.
- Expéditeur, séparation rue/numéro, conversion du poids et format d'impression sont contrôlés.
- Une étiquette nationale, un export nécessaire et les retours ont été testés séparément.
- États, e-mails et consentement correspondent à la politique du shop.
- Chaque contexte multiboutique a son propre test.
- Le journal de diagnostic est désactivé.

