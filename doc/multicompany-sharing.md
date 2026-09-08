# Partage individuel Multicompany

## Configuration et utilisation

Le module reste compatible Dolibarr 20+ et PHP 8.0+. Le partage individuel nécessite Multicompany 21+ et ses API natives de formulaire et de persistance. L’onglet **Compatibilité** indique leur disponibilité. Aucun identifiant de module, droit, objet ou modèle de numérotation n’est modifié.

1. Déployer le module, puis le désactiver et le réactiver avec Multicompany actif. La migration complète les périmètres absents à partir de leur ancien partage. Elle conserve les valeurs désactivées, les listes vides et les réglages existants. Elle n’active pas le mode individuel. La nouvelle famille QUARTIX ne reprend aucun ancien périmètre ni aucune autorisation.
2. Dans les réglages natifs Multicompany, activer les partages et leur gestion par élément. Pour chaque entité bénéficiaire, sélectionner les entités sources autorisées pour les familles du module.
3. Dans les réglages du module véhicules, onglet **Partage individuel**, le super-administrateur choisit les familles gérées individuellement. Les interrupteurs utilisent les constantes et le traitement AJAX natifs Multicompany. Cet onglet complète la page native, dont les interrupteurs de granularité des versions 21/22 sont limités aux familles core.
4. Depuis l’entité propriétaire, un administrateur ouvre la fiche et renseigne **Partagé avec**. Pour les attestations, affectations et relevés, le lien globe de la ligne ouvre ce réglage dans leur écran existant. Les destinations proposées sont actives, accessibles à cet administrateur et configurées pour recevoir cette famille depuis le propriétaire.
5. Partager d’abord les parents requis. Enregistrer le partage d’un enfant ne partage jamais son parent. Un refus annule toute l’opération.

Chaque famille possède son propre périmètre :

| Famille | Élément natif de partage | Accès supplémentaire requis |
|---|---|---|
| Véhicule | `lmdbvehicle` | Aucun parent |
| Contrat d’assurance | `lmdbinsurancecontract` | Aucun parent |
| Attestation | `lmdbinsurancecertificate` | Contrat et véhicule si l’attestation est individuelle |
| Contrôle réglementaire | `lmdbvehicleregulatorycontrol` | Véhicule |
| Consommation | `lmdbvehicleconsumption` | Véhicule |
| Événement métier | `lmdbvehicleevent` | Véhicule |
| Affectation | `lmdbvehicleassignment` | Véhicule |
| Données QUARTIX | `lmdbvehiclequartix` | Véhicule pour les bénéficiaires ; historique conservé chez le collecteur |
| Relevé kilométrique | `lmdbvehicleodometerreading` | Véhicule |

Pour QUARTIX, seul le mode **sélection** est utilisé ; aucune exclusion ni valeur « Tout partager par défaut » ne peut élargir les accès. Les kilométrages QUARTIX suivent exclusivement cette famille. Pour les autres familles, en mode **sélection**, les associations stockées désignent les bénéficiaires. En mode **Tout partager par défaut**, elles désignent les exclusions. Le sélecteur affiche dans les deux cas les bénéficiaires effectifs. Changer ce mode inverse l’interprétation des associations existantes : choisir le mode avant de saisir les partages et contrôler les accès après tout changement. Désactiver puis réactiver une famille conserve ses associations.

## Consultation, droits et documents

Le partage ne donne aucun droit fonctionnel supplémentaire à un utilisateur standard. Le droit de lecture du module reste nécessaire ; les droits de modification, suppression, génération et consultation GPS restent distincts. Aucune élévation administrateur n’accorde de permission fonctionnelle : les droits sont exigés pour tous les profils, puis les accès aux objets et parents sont contrôlés. Les comptes externes n’accèdent pas à ces objets.

Un contrat flotte partagé reste consultable dans son intégralité, avec ses documents de contrat, même lorsque certains véhicules couverts sont privés. Cette règle n’ouvre pas leurs fiches. Les attestations possèdent leur partage propre, y compris lorsqu’elles sont stockées dans le répertoire du contrat.

Les filtres SQL s’appliquent avant pagination et calcul des totaux. Un relevé privé ne révèle pas sa date ou son kilométrage à travers une consommation partagée. Les indicateurs qui en ont besoin excluent cette source. Les historiques, échéanciers et dossiers signalent une vue potentiellement partielle sans indiquer le nombre d’enregistrements masqués. L’Agenda natif conserve ses propres droits et règles de partage.

Les fichiers restent dans le répertoire documentaire du propriétaire. La lecture de l’objet suffit pour ses documents existants. Une ancienne URL ou un téléchargement direct est réautorisé à chaque requête, y compris les aperçus natifs.

Un dossier composé PDF/ZIP doit encore être autorisé pour chacun des objets et factures qu’il contient. À sa génération, un fichier privé `.sharing.meta` accompagne le dossier et conserve leurs identifiants ainsi que les empreintes des fichiers. Ce fichier n’est ni un stockage de partages ni un document public. Un retrait d’accès à une source invalide le téléchargement du dossier existant ; une nouvelle génération peut produire un dossier limité aux sources restantes. Les anciens dossiers dépourvus de cette information doivent être régénérés pour une consultation inter-entités ou en mode individuel. Cette règle ne peut pas retirer une copie déjà téléchargée.

QUARTIX utilise les associations, paramètres et caches de l’entité collectrice, qui peut différer du propriétaire du véhicule. Les données sont privées par défaut et le propriétaire conserve son historique après retrait du véhicule. Voir le [guide dédié](quartix-ownership.md). Une entité bénéficiaire consulte les données disponibles mais ne modifie pas les associations, ne lance pas de synchronisation et ne demande pas de nouveaux tracés.

## Intégration native et rôle du filtre métier

La définition des familles est publiée par `ActionsLmdbVehicleManagement::getMulticompanySharingDefinition()` dans les hooks Multicompany existants. `init()` fusionne cette définition ; `remove()` conserve la configuration. Aucune table métier ni fichier core n’est ajouté ou modifié.

`getEntity()` reste la source du périmètre d’entités. `DaoMulticompany::setSharingsByElement()` écrit dans `entity_element_sharing`. Le formulaire et la présélection proviennent de `ActionsMulticompany`. Les modifications passent par une méthode objet, avec contrôle administrateur/propriétaire/destinations/parents, POST, token CSRF, transaction et redirection. Un changement effectif déclenche un seul trigger CRUD `UPDATE`, avec `context['trigger_reason'] = 'sharing_change'` et `changed_fields = ['sharing_entities']`. Une suppression métier retire aussi les associations natives dans la transaction.

L’onglet de réglages charge `/multicompany/core/js/lib_head.js` via `llxHeader()` lorsque les interrupteurs sont disponibles. `ajax_mcconstantonoff()` produit leur rendu et leurs appels, mais ne charge pas les fonctions JavaScript `setMulticompanyConstant()` et `delMulticompanyConstant()` : sans ce script, les clics restent sans effet même si `MULTICOMPANY_SHARING_BYELEMENT_ENABLED` vaut déjà `1`. Le chargement suit `multicompany/admin/granularity.php`, vérifié le 2026-09-08 dans les archives locales `module_multicompany-21.0.zip` (descripteur 21.0.2) et `module_multicompany-22.0.zip` (descripteur 22.0.1). Les réglages restent globaux (`entity = 0`), réservés au super-administrateur dans cet onglet, et enregistrés par le traitement natif avec token CSRF. Cette vérification des sources ne constitue pas un test de l’instance distante.

`LmdbVehicleSharing::sql()` compose ces règles pour les requêtes propres au module. Il ne remplace pas une API native équivalente : `getEntity()` renvoie des entités, et le DAO de Multicompany 21/22 n’expose pas de constructeur générique de prédicat SQL pour les objets externes et leurs parents. Filtrer après lecture fausserait pagination et totaux. La méthode utilise les lignes natives de partage en sélection ou exclusion et ajoute les conditions métier de parent. Il n’existe aucun second registre de partage.

Le hook `hookGetEntity` traduit uniquement les noms de tables/alias du module vers leurs éléments. Un traitement `restrictedArea` est limité au GET des infobulles AJAX du module, dont le core infère parfois une sous-permission de lecture absente ; il exige au préalable l’autorisation complète de l’objet et de ses parents. Il ne modifie pas les accès aux autres pages ou modules.

Dans Multicompany 21/22, le retrait dans le widget d’un objet existant appelle un endpoint qui cherche sa classe dans le core (`htdocs/<element>/class/<element>.class.php`). Cet endpoint ne sait pas charger les classes externes. L’adaptation du formulaire conserve le widget, ses scripts, sa présélection et le DAO natifs, et confie le retrait à la méthode métier protégée du module. Aucun sélecteur ni endpoint de persistance parallèle n’est créé.

## Validation

Suites locales :

```sh
php test/run_sharing.php /chemin/dolibarr/htdocs
php test/run_sharing.php /chemin/dolibarr/htdocs /chemin/module_multicompany-21.0.zip
php test/run_sharing.php /chemin/dolibarr/htdocs /chemin/module_multicompany-22.0.zip
php test/run_ui_contracts.php
php test/run_regulatory_contracts.php
php test/run_agenda_contracts.php
php test/run_quartix.php /chemin/dolibarr/htdocs
php test/run_invoice_dossier.php /chemin/dolibarr/htdocs
php test/run_consumption_prices.php /chemin/dolibarr/htdocs
php test/run_consumption_od_settings.php /chemin/dolibarr/htdocs
```

Les tests de partage exécutent les prédicats SQL et les méthodes du module dans SQLite avec des doubles du DAO. Les archives facultatives chargent la véritable classe de formulaire Multicompany en lecture seule. Les adaptations SQLite restent dans les tests ; les requêtes de production restent MySQL/MariaDB. La suite dossiers emploie les moteurs PDF/ZIP et contrôle les refus de téléchargement après retrait d’une source.

Validation à compléter sur une instance servant effectivement ces fichiers :

- Trois entités A/B/C ; pour chaque famille, deux objets dans A, un seul partagé avec B. Contrôler listes, filtres, totaux, navigation, sélecteurs, AJAX, documents et exports dans B et C, puis après retrait.
- Parent partagé/enfant privé, puis enfant partagé/parent privé ; contrat flotte partagé avec véhicules privés, attestations privées et attestation véhicule sans accès à son véhicule.
- Modes sélection/exclusion ; réactivation conservant les réglages, y compris `0`, chaîne vide et liste vide. Vérifier la migration native sur MySQL/MariaDB et sa reprise après échec.
- Super-administrateur, administrateur d’entité, administrateur Multicompany, lecteur, droits partiels et absence de droits ; destinations falsifiées, token absent/invalide, POST rejoué et échec transactionnel.
- Depuis B, consultation QUARTIX sans association/synchronisation/demande de tracé ; depuis A, opérations autorisées avec les droits existants.
- Aperçus, téléchargements directs, fichiers de contrat et attestations, anciens dossiers et dossier généré avant retrait d’un partage. Confirmer les répertoires du propriétaire et l’absence de résultat périmé.
- Dolibarr 20/PHP 8.0 et version du parc. PHPStan avec l’outillage du projet lorsqu’il est disponible.

Les suites hors ligne ne valident pas une instance distante non déployée, les transactions réelles MySQL/MariaDB ou une session Multicompany complète. La navigation v24 utilise le filtre USF natif avec les identifiants autorisés ; son volume est à contrôler sur un parc très important.
