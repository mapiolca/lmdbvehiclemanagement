# Intégration QUARTIX QWS v2

Cette évolution de développement complète la version 1.0.0, sans publier de nouvelle version. Elle utilise le contrat du document **QWS V2.pdf** fourni pour cette intégration, avec l'adaptation du format d'authentification décrite ci-dessous.

## Mise en service

1. Déployer la branche, puis désactiver/réactiver le module pour installer les tables, les droits et les tâches. Les réglages sont conservés, y compris ceux des tâches déjà présentes.
2. Dans **Réglages → QUARTIX**, saisir le code client, le login, le mot de passe et le **Nom d’application (APPLICATION NAME)** fournis par QUARTIX pour l'environnement courant. Enregistrer avant de tester. Un mot de passe laissé vide conserve celui enregistré.
3. Confirmer avec QUARTIX le sens des horodatages et l'unité des durées. Le mode **Convention QWS** respecte un décalage explicite lorsqu’il est présent et lit les dates sans suffixe dans le fuseau du véhicule, conformément à la page 1 du document QWS. Les modes forcés historiques restent disponibles ; l'unité de TravelTime et IdlingTime n'est pas précisée. Aucune interprétation n'est choisie par défaut.
4. Tester la connexion pour charger le catalogue, puis associer explicitement les véhicules et confirmer leur fuseau IANA et la date/heure réelle d’installation du boîtier sur ce véhicule. Le début de journée provient de ShiftStartTime. Vérifier ces informations lors de l'association.
5. Activer la synchronisation dans les réglages, puis les quatre tâches dans les **Travaux planifiés** natifs. Utiliser un compte interne avec lecture du parc, synchronisation QUARTIX et gestion des relevés kilométriques. Les administrateurs disposent implicitement de ces droits.
6. Comparer les premières valeurs avec QUARTIX sur l'instance servant réellement ce code.

Chaque environnement possède sa connexion, ses jetons, ses associations et son état de synchronisation.
Le nom d'application est conservé dans une constante native par entité. Sa saisie est obligatoire : aucune valeur n'est inventée à partir du nom du module. Une installation existante doit compléter ce réglage ; tant qu'il manque, les tests de connexion et les travaux planifiés sont refusés localement avec un message explicite. La sauvegarde des identifiants ou du nom d'application invalide les jetons de cette seule entité pour renouveler l'authentification.
Un véhicule partagé reste synchronisé depuis son environnement propriétaire. Sa consultation utilise les données de ce propriétaire.
Les associations peuvent être suspendues et réactivées depuis l'interrupteur natif ON/OFF de leur ligne, avec contrôle CSRF, droits et entité propriétaire. Le bouton **Dissocier** ouvre une confirmation native avec deux choix : **Réaffectation du boîtier** conserve les imports sur l’ancien véhicule ; **Association erronée** supprime définitivement tous les trajets, synthèses et estimations QUARTIX de ce véhicule. Dans les deux cas, la dernière position est effacée et le boîtier devient disponible pour une nouvelle association. Les relevés manuels et ceux des pleins/recharges sont toujours conservés.
Une modification ultérieure du fuseau ou du début de journée dans QUARTIX nécessite également de revoir l'association avant de reprendre les imports.

La dissociation fonctionne aussi pour une association suspendue. Elle partage le verrou des tâches QUARTIX, utilise une transaction et contrôle l’identifiant de l’association confirmée pour refuser une ancienne confirmation après réassociation. La suppression d’estimations passe par l’objet de relevé et ses triggers CRUD ; le véhicule émet un UPDATE avec `trigger_reason=quartix_unlink` et `quartix_cleanup` indiquant une purge. Aucun événement ni email parallèle n’est créé.

Une nouvelle association impose une date d’installation via le datepicker natif. Les positions et kilométrages antérieurs sont refusés ; les synthèses commencent à la première journée QUARTIX complète après installation. Une date chevauchant des imports conservés sur ce véhicule est refusée. La rétention de douze mois des synthèses conserve son calendrier propre, même après dissociation.

**Mise à jour d’une installation existante :** désactiver/réactiver le module via l’administration native pour ajouter la colonne nullable `qx_link.sync_from`. Les réglages sont conservés. La migration est additive et rejouable. Les anciennes associations conservent une date vide et leur reprise historique existante : aucune date d’installation n’est inventée. Les nouvelles associations renseignent obligatoirement cette borne.

## Données et source faisant autorité

| Donnée | Source QWS | Règle de conservation / conflit |
|---|---|---|
| Kilométrage estimé | /vehicles/odometer, OdoEstimateKm, EstimateDateTime | Une observation par véhicule, source et jour local. Rejeu sans doublon ; une observation plus ancienne ne remplace pas une plus récente du même jour. Historique des relevés conservé. |
| Dernière position | /vehicles/live | Une seule ligne par véhicule : coordonnées, lieu, date d'événement, date de réception et état du suivi. Une réponse plus ancienne ne remplace pas la position connue. |
| Utilisation | /vehicles/tripsummary, GroupBy=vehicle, un véhicule et un jour par requête | Distance en km, nombre de trajets, conduite et ralenti. Conservation glissante de douze mois et agrégation mensuelle à la lecture. |

Les relevés réels, y compris ceux des pleins et recharges, restent les seuls points de référence des contrôles de progression.
Les estimations QUARTIX ne bloquent jamais leur création ou correction. Une estimation contredisant les relevés réels voisins reste consultable avec le badge **Estimation QUARTIX contradictoire**.
Les écarts affichés entre relevés réels ignorent les estimations. Les statistiques de consommation conservent leur liaison au relevé réel de chaque consommation.
La liste des relevés est paginée par SQL. Les calculs d'écart et d'anomalie prennent aussi en compte les relevés réels immédiatement extérieurs à la page affichée.

Les imports utilisent les méthodes objet et les triggers CRUD existants de kilométrage, avec trigger_reason = quartix_estimate.
L'Agenda et les Notifications restent pilotés par leur configuration native ; aucun événement ni email parallèle n'est créé.
La chronologie et le dossier identifient les estimations comme telles. Les positions ne sont incluses ni dans la chronologie, ni dans les documents, ni dans un export ou une API du module.

Un jour absent de la réponse QWS est enregistré comme **sans donnée**, jamais comme un zéro.
Les totaux et graphiques portent uniquement sur les jours renseignés ; la couverture indique les jours renseignés sur la période demandée.
Les durées brutes sont conservées et converties en heures seulement après confirmation de l'unité.
Les heures locales inexistantes ou répétées lors du changement d'heure sont refusées. Les dates futures incohérentes et les nombres invalides sont rejetés.
Les conducteurs, parcours GPS détaillés et données d’écoconduite ne sont pas importés. Le journal applique la protection des trajets privés décrite ci-dessous.

## Travaux planifiés

Les quatre tâches sont créées **désactivées**, avec un réveil toutes les quinze minutes. L'administrateur conserve la maîtrise de leur fréquence et de leur activation.

| Tâche | Traitement à chaque passage |
|---|---|
| Dernières positions | Un appel groupé pour au plus 100 véhicules, puis reprise au véhicule suivant. |
| Kilométrage quotidien | Jusqu'à 100 véhicules qui n'ont pas encore été synchronisés aujourd'hui (jour UTC). L'unicité de l'observation utilise le jour local de la donnée. |
| Utilisation et reprise historique | Jusqu'à 20 véhicules et environ 45 secondes de traitement, par fenêtres de sept jours. Relecture des sept dernières journées terminées après chaque clôture de journée QUARTIX, puis reprise progressive vers le passé. |

Une grande flotte peut nécessiter plusieurs passages ; l'intervalle réel dépend de sa taille, du temps de réponse et des quotas du compte.
Les bornes métier sont les débuts de journée QUARTIX dans le fuseau du véhicule. La journée en cours n'est pas agrégée.
Après une interruption de plus de sept journées, la reprise historique revisite la fenêtre conservée pour combler les trous.
La purge est bornée ; elle inclut les associations suspendues tant que la synchronisation globale et la tâche d'utilisation sont actives. Si les tâches sont arrêtées, aucune purge ne s'exécute.

Un verrou MySQL nommé, isolé par préfixe de base et environnement, sérialise les quatre tâches et les actions de configuration.
Les positions/kilométrages utilisent des requêtes QWS groupées ; les synthèses sont demandées par véhicule pour vérifier strictement chaque réponse.
Chaque journée d’utilisation est enregistrée dans une transaction ; le curseur de période avance après tous les jours attendus. Une erreur sur un véhicule n'annule pas les autres.
Les erreurs visibles et les logs emploient des codes contrôlés, sans réponse API brute, secret ou coordonnées GPS.
Un test de connexion en échec précise maintenant l'endpoint concerné (/auth, /auth/refresh ou /vehicles) et le statut HTTP reçu ; une erreur de transport fournit son numéro cURL lorsqu'il est disponible. Ces seules métadonnées sont également journalisées. Aucun corps de réponse, paramètre, en-tête d'authentification ou message réseau brut n'est enregistré.
Le message « erreur de service » seul ne permet pas de conclure que le mot de passe est incorrect : il peut correspondre à une redirection, une requête refusée ou une indisponibilité distante. Le statut 422 est distingué comme un refus du contenu de la requête. Il interrompt le lot sans avancer le curseur et applique le délai de reprise ; aucun nouvel encodage n'est essayé automatiquement.
Les quotas 429 et Retry-After suspendent les appels de l'environnement, y compris les tests manuels de connexion.
Les erreurs d'authentification/réseau des tâches utilisent un délai de reprise ; un 401 provoque un renouvellement de jeton, puis une nouvelle authentification si le jeton de renouvellement est refusé.

## Format des requêtes d'authentification

Les POST `/auth` et `/auth/refresh` transmettent désormais un objet JSON avec `Content-Type: application/json`. Les noms des champs restent `CustomerID`, `UserName`, `Password`, `Application` et, pour le renouvellement, `RefreshToken`. Les GET conservent leurs paramètres d'URL et l'en-tête `AccessToken` ; les secrets ne passent jamais dans l'URL.

Le PDF historique décrit les paramètres en `formData`. Après le refus HTTP 422 observé sur `/auth` avec cet encodage, le choix JSON s'appuie sur un [exemple QWS publié le 7 juillet 2026 par son auteur comme fonctionnel](https://community.fabric.microsoft.com/t5/Power-Query/Convert-Dynamic-Data-source/td-p/5276109). Cet exemple d'intégration n'est pas une spécification officielle QUARTIX. Le 5 septembre 2026, l'authentification JSON a été validée sur l'instance Dolibarr de développement après saisie du nom d'application fourni par QUARTIX : le catalogue a répondu HTTP 200.

Le client utilisait initialement la clé du module comme champ `Application`. Il transmet maintenant exactement la valeur **APPLICATION NAME** fournie par QUARTIX et enregistrée dans l'entité. Pour une installation déjà initialisée, déployer les fichiers puis compléter ce nouveau champ et enregistrer avant de relancer **Tester la connexion et charger les véhicules**. Aucune migration ni réactivation n'est nécessaire ; le mot de passe peut rester vide pour conserver celui enregistré. Si le refus persiste, conserver l'étape et le statut affichés pour le diagnostic, sans transmettre les identifiants ou les réponses API brutes.

Le catalogue réel renvoie le champ `VehicleId`, alors que le PDF décrit `VehicleID`. Le client normalise cette seule variante vers `VehicleID` dès la lecture des réponses, pour tous les consommateurs du module. Les identifiants doivent rester des entiers positifs ; deux variantes présentes avec des valeurs différentes sont refusées. Les noms des paramètres envoyés à QWS restent ceux du contrat, notamment `VehicleIDList`. Le champ réel des positions `LastEventDateTime` est également normalisé vers `LastEventDatetime`, avec refus de deux valeurs contradictoires. Pour les synthèses, le regroupement `day` renvoie `VehicleID=null` et des nombres de trajets nuls malgré une activité réelle. Le module demande donc `GroupBy=vehicle` avec un seul véhicule et `StartDay=EndDay`. QWS renvoie alors les totaux du jour avec l’identifiant du véhicule ; sa date de total `0001-01-01` est remplacée uniquement par cette unique journée explicitement demandée. Une requête multi-jours, un filtre multiple, un champ absent ou un identifiant contradictoire reste refusé à l’import. Ces comportements ont été vérifiés sur QWS le 5 septembre 2026. Chaque journée est enregistrée séparément ; la date de synchronisation permet de reprendre une semaine interrompue sans recommencer les jours déjà relus pendant la journée locale courante. Le curseur hebdomadaire avance seulement après la fin du lot.

Les réponses observées contiennent un décalage explicite pour les positions et aucun suffixe pour les estimations kilométriques. Le mode **Convention QWS** permet de traiter ces deux formes sans ignorer un décalage présent. Il doit être sélectionné et enregistré dans l’environnement concerné ; aucune migration ne remplace un choix existant. Les heures locales ambiguës au changement d’heure restent refusées.

## Sécurité et compatibilité

- Socle : Dolibarr 20+, PHP 8.0+, MySQL/MariaDB, cURL, OpenSSL et clé d'instance Dolibarr.
- Mot de passe et jetons chiffrés par dolEncrypt() ; le repli natif en clair est refusé. Aucun mot de passe stocké n'est renvoyé dans le formulaire.
- Transport HTTPS vers https://qws.quartix.net/v2/api, vérification TLS, sans redirection ni cookies, réponse bornée et délais limités. L'authentification QWS utilise l'en-tête AccessToken, sans secret dans l'URL.
- Exception documentée au helper HTTP : getURLContent() de Dolibarr 20 journalise les paramètres POST et l'en-tête QWS AccessToken. Le transport cURL dédié évite cette divulgation et reprend les réglages proxy natifs.
- Permission GPS indépendante de la lecture du parc ; tous les accès QUARTIX sont refusés aux utilisateurs externes. La synchronisation n'accorde pas le droit de consulter le GPS.
- Réglages et associations réservés aux administrateurs de l'environnement propriétaire. Formulaires protégés par le CSRF natif et redirection après traitement.
- Disponibilité centralisée dans LmdbVehicleQuartixConfig, exposée dans l'onglet **Compatibilité**. Les données d'utilisation restent lisibles dans le cache lorsque la synchronisation est suspendue.
- Tables complémentaires liées par fk_vehicle, index courts, aucune recopie des données métier Dolibarr. Migration additive is_estimate / provider_key / sync_from, rejouable sans reclassement des relevés historiques.
- Identifiant du module conservé : **450026**. Nouveaux droits aux offsets **24** (GPS) et **25** (synchronisation), sans renumérotation des droits existants.

## Validation

    php test/run_quartix.php /chemin/vers/dolibarr/htdocs

La suite utilise les vrais objets Dolibarr et une base SQLite en mémoire avec adaptation de la syntaxe MySQL à la frontière de test.
Elle couvre le chiffrement natif, les erreurs API, les renouvellements, les quotas, les dates et unités ambiguës, les conflits kilométriques, les relevés de consommation, les rejouements, le rollback, deux entités, les véhicules partagés, les droits GPS, la reprise des tâches, la rétention et la migration additive.
Les appels réseau sont simulés. Cette suite ne constitue pas un test du moteur MySQL, de l'authentification réelle QWS ou du navigateur.

Pour vérifier aussi le contenu réellement envoyé par cURL (Python 3 et OpenSSL en ligne de commande requis) :

    python3 test/run_quartix_transport.py /chemin/vers/dolibarr/htdocs

Les options `--php` et `--openssl` permettent de choisir les exécutables. Le script relance la suite QUARTIX puis démarre une fixture HTTPS sur l'interface locale avec un certificat temporaire supprimé en fin de test. Le nom d'hôte et la vérification TLS du client restent actifs ; seuls le routage vers localhost et la confiance dans le certificat de test sont adaptés dans une sous-classe de test.
Il vérifie les POST JSON d'authentification et de renouvellement après un 401, les espaces, accents et caractères spéciaux, les GET et les en-têtes, ainsi que le refus d'un JSON impossible à encoder avant toute connexion. Aucun compte réel ni accès réseau à QUARTIX n'est utilisé. Le test valide la sérialisation du client, pas l'acceptation par le service QUARTIX.

### Résultats locaux du 5 septembre 2026

Contrôles exécutés avec PHP 8.5.7, le core Dolibarr 20.0.4 et le checkout de développement 25.0.0-alpha lorsque la suite charge le core :

| Suite | Résultat |
|---|---|
| QUARTIX | 86 contrôles initiaux réussis sur chacun des deux cores ; 215 contrôles réussis sur le core 25.0.0-alpha avec nom d'application par entité, conservation du mot de passe, invalidation des jetons et variantes VehicleId/VehicleID, dates QWS et dissociation (conservation, purge, restauration sur erreur, ancienne confirmation et réaffectation bornée) ; totaux par véhicule et journée, nombre de trajets, reprise d’une semaine interrompue et budget d’exécution ; diagnostic HTTP, refus 422, chiffrement, transport simulé, stockage, droits, rendu GPS et graphiques natifs inclus |
| Transport QUARTIX HTTPS local | 4 contrôles supplémentaires et 4 requêtes HTTPS vérifiés avec le vrai cURL : authentification JSON, lecture expirée, renouvellement JSON et lecture réussie ; données fictives uniquement |
| Règles métier | 50 contrôles réussis |
| Contrats Agenda | 400 contrôles réussis |
| Contrats d'interface | 158 contrôles réussis |
| Contrats réglementaires | 80 contrôles réussis |
| Contrats de consommation / OD | 17 contrôles réussis |
| Prix des consommations | 95 contrôles réussis sur chacun des deux cores |
| Réglages des OD | 168 contrôles réussis sur chacun des deux cores |
| Syntaxe PHP et diff | 21 fichiers PHP contrôlés sans erreur de syntaxe ; contrôle des espaces Git réussi |

Le core historique et certains tests existants émettent des avertissements de dépréciation sous PHP 8.5, sans échec des assertions.
PHPStan n'est pas installé dans l'environnement disponible : aucune analyse PHPStan n'a été exécutée.
Le runtime PHP 8.0 n’est pas disponible. Les suites locales utilisent SQLite et des réponses simulées ; les validations authentifiées sur le serveur de développement sont décrites séparément ci-dessous.
Les essais de concurrence utilisent un refus de verrou simulé ; une exécution concurrente réelle reste à vérifier sur MySQL/MariaDB.
Les documents, catégories et modèles de numérotation ne changent pas de fonctionnement ; aucune nouvelle génération documentaire ou modification de leurs modèles n'est introduite.

### Validation déployée du 5 septembre 2026

Le commit `af6ee3a0cb99ec6eb50f5b7a577676ccd44059c9` de `codex/quartix-integration` a été poussé puis chargé depuis **Update from Remote** dans cPanel. Le dépôt est directement le répertoire servi par l’instance de développement. Le HEAD cPanel et les comportements observés confirment que le code corrigé est exécuté sous Dolibarr 24.0.0 / PHP 8.3.33.

- Authentification JSON réussie et catalogue de quatre véhicules chargé ; aucune valeur secrète exposée.
- Désactivation/réactivation native réussie sur la base du serveur : colonne `sync_from` ajoutée, constantes, associations, fréquences et états des tâches conservés, vérifiés avant/après.
- Trois travaux planifiés avec un dernier code de retour `0`. L’exécution manuelle de l’utilisation a traité un véhicule sans erreur ; les tâches de kilométrage et positions ont également terminé avec succès.
- Sept journées importées : somme des distances et nombre de trajets identiques aux totaux QWS du même véhicule et de la même période. Kilométrage, date d’estimation, coordonnées et date de dernière position également comparés avec les réponses authentifiées et concordants.
- Consultation de l’utilisation et des relevés : tableaux et graphiques natifs visibles ; l’estimation contradictoire est signalée, les relevés de pleins/recharges restent prioritaires.
- Interrupteur natif vérifié et confirmation de dissociation affichée avec les deux modes, puis annulée sans modifier l’association. La purge destructive est validée par les tests locaux uniquement.

L’unité de `TravelTime` et `IdlingTime` n’est pas confirmée par l’utilisateur. Le réglage de l’instance a été remis sur **À confirmer auprès de QUARTIX** : les durées restent indisponibles, même si les valeurs brutes sont conservées. Aucune unité n’est déduite des distances ou de l’ordre de grandeur des réponses.

Après une exécution manuelle réussie, le cron natif Dolibarr 24.0.0 produit encore une erreur de fermeture `mysqli object is already closed` dans son gestionnaire de fin de requête (`cronjob.class.php:1364`). Le traitement QUARTIX et ses écritures sont terminés avec succès. Le même défaut est décrit dans le [ticket officiel Dolibarr #39801](https://github.com/Dolibarr/dolibarr/issues/39801). Aucun fichier core n’a été modifié ; la correction de ce défaut relève de la maintenance Dolibarr.

### Vérifications restant à effectuer

- exécution sur le socle PHP 8.0 / Dolibarr 20 avec l’ensemble des derniers contrôles ;
- parcours authentifiés avec utilisateur standard de synchronisation, lecture seule, GPS, administrateur d’entité et utilisateur externe ;
- association correcte de deux véhicules dans deux entités ; absence de fuite et écriture uniquement dans l'entité propriétaire pour un véhicule partagé ;
- concordance des durées avec QUARTIX après confirmation de leur unité ;
- consultation bureau/mobile, datepickers, pagination, colonnes, graphiques natifs, données absentes, position ancienne et suivi interrompu ;
- refus des POST sans token, succès avec token, erreurs partielles et reprise après interruption/quota ;
- relevé manuel et plein/recharge en contradiction avec une estimation, badge d'anomalie et statistiques inchangées ;
- absence de GPS dans le dossier et la chronologie ; libellé d'estimation explicite.

Les vérifications locales ne nécessitent aucun compte QUARTIX. PHPStan doit être lancé avec l'outillage du projet lorsqu'il est disponible ; aucun baseline ni niveau affaibli n'est ajouté par cette évolution.


## Journal des trajets et tableau de bord du parc

L’onglet **Trajets** suit les onglets métier du véhicule et utilise le droit GPS existant. Il propose les sept derniers jours avec dates natives, état, tri, colonnes personnalisables et pagination SQL. Une arrivée provisoire retournée par QWS n’est jamais affichée comme définitive. Les horaires suivent le fuseau de la session Dolibarr ; le jour de départ conserve le découpage QUARTIX du véhicule. Les jours synchronisés sans trajet comptent dans la couverture ; les jours jamais importés restent inconnus.

Le client lit `GET /vehicles/trips` pour **un véhicule et une journée QUARTIX**. Le schéma a été vérifié par une requête authentifiée le 5 septembre 2026 : réponse HTTP 200, 13 lignes, dates `StartDateTime`/`EndDateTime` avec décalage explicite, `InProgress` et `IsPrivate` booléens. `StartDateTimeLocal`/`EndDateTimeLocal` sont ignorés. Le contrat retourne aussi des trajets terminant dans la journée : seuls ceux dont le départ appartient à la journée demandée sont conservés. Les doublons identiques sont retirés ; des départs contradictoires au même instant font refuser la réponse complète.

Deux tables sont ajoutées : `qx_tripday` identifie la journée, son propriétaire, le véhicule, le fuseau, le début de journée, la provenance historique de l’association et la dernière synchronisation ; `qx_trip` contient ses trajets. L’identifiant de provenance reste historique après suppression de l’association. Aucun identifiant stable de trajet n’est présumé : validation complète en mémoire, puis remplacement transactionnel d’une journée. Réponse invalide, transport interrompu ou erreur SQL : le cache précédent est conservé. Les lignes n’ont pas de référence métier ni d’édition manuelle, de document ou d’API publique.

Dès que `IsPrivate` est vrai **ou** que `PrivacyDistance` est positive, seuls le jour et les distances sont conservés dans le résumé. Les lieux, coordonnées, horaires précis, durées et statut précis du trajet sont absents. Si les deux indications de confidentialité manquent, la même protection s’applique. L’état technique de la journée indique seulement si elle doit encore être relue. Aucun conducteur, parcours GPS, export GPS, document ou événement de chronologie n’est alimenté. Les durées publiques restent brutes en cache et indisponibles à l’affichage tant que leur unité n’est pas confirmée ; l’unité du compte de test reste non confirmée.

Le réglage **Conservation des trajets (jours)** est un entier strictement positif, propre à l’environnement. La constante native `LMDBVEHICLEMANAGEMENT_QX_TRIP_RETENTION_DAYS` reçoit 30 **uniquement si absente**. Le calendrier UTC borne la conservation (journée courante comprise), comme les autres purges de l’intégration. Une réduction filtre immédiatement les vues et purge les journées expirées par lots de 100 au prochain passage ; une augmentation reprend les jours antérieurs disponibles. Une longue conservation consomme davantage de stockage, d’appels API et de temps de reprise. La page indique le nombre de trajets stockés, y compris ceux en attente de purge.

Le quatrième travail natif **QUARTIX — journal des trajets**, désactivé à l’installation, se réveille toutes les quinze minutes. Il traite en priorité le jour courant et les journées encore ouvertes, puis relit quotidiennement les sept journées terminées et reprend les journées historiques manquantes. Budget de lot d’environ 45 secondes, au plus 10 appels par véhicule et curseur entre véhicules ; chaque journée réussie constitue son propre point de reprise. Le verrou d’entité, les jetons, quotas et délais de reprise sont communs aux quatre travaux. La purge précède les appels QWS et reste active lorsque la synchronisation est suspendue, si le module et ce travail restent actifs. Une désactivation du module ou du travail arrête la purge ; l’interface le signale.

Une réaffectation conserve le journal de l’ancien véhicule ; une association erronée le supprime dans la transaction de dissociation. Une nouvelle association ne peut couvrir une journée possédant déjà une autre provenance. Choisir une journée d’installation ultérieure évite d’écraser cet historique. La suppression autorisée d’un véhicule nettoie son cache de trajets, après les contrôles existants sur les autres données métier.

Le menu **Tableau de bord** du parc est accessible avec le droit de lecture des véhicules. Il présente par défaut les trente journées terminées précédant le jour UTC courant : véhicules associés, suspendus et non associés, kilomètres, trajets, jours actifs et jours renseignés/demandés. Un jour actif a une distance ou un nombre de trajets positif. Les totaux restent limités aux jours réellement renseignés. La comparaison graphique affiche les vingt distances les plus élevées de la sélection ; l’évolution quotidienne et les totaux couvrent toute la sélection, indépendamment de la pagination.

Les lieux, dates de position et liens vers le journal sont exclus des requêtes et du rendu sans droit GPS. Les travaux et leurs erreurs sont présentés par environnement, séparément de l’ancienneté des positions. Une position ancienne ne permet pas de conclure à une panne ou à une immobilisation. Les véhicules partagés se lisent dans leur environnement propriétaire, avec badges et filtre Multicompany natifs ; l’import, la configuration et la purge appartiennent exclusivement au propriétaire. Les utilisateurs externes sont refusés. L’affichage ne déclenche aucun appel QWS.

Les composants utilisés existent dans Dolibarr v20 : `Form::selectDate`, sélection de colonnes et pagination natives, Select2/multiselect2, `DolGraph` avec moteur `jflot`, droits et travaux planifiés. Le module reste en version 1.0.0, compatible PHP 8.0. Les réglages, associations et trois travaux précédents doivent rester identiques après réactivation.

### Vérification locale de cette extension

- 297 contrôles QUARTIX sur le core de développement 25.0.0-alpha ; 296 contrôles sur Dolibarr 20.0.4 avant l’ajout du contrôle explicite du droit GPS d’un utilisateur standard. Les tests incluent les snapshots ouverts/terminés, doublons, journées chevauchantes, DST, confidentialité, transactions, réaffectation, purge bornée, suppression du véhicule, quota, reprise, droits et agrégats multientités.
- Suites existantes : 50 règles métier, 400 contrats Agenda, 80 réglementaires, 158 d’interface, 17 contrats OD, 168 réglages OD et 95 prix de consommation ; transport HTTPS local vérifié avec le véritable cURL.
- Syntaxe des fichiers PHP modifiés vérifiée avec PHP 8.5.7. PHP 8.0 n’est pas installé et PHPStan n’est pas disponible ; aucune nouvelle dépendance ni règle d’exclusion d’analyse n’est ajoutée.
- Les migrations MySQL et les écrans authentifiés restent à vérifier sur le code déployé. Les trajets privés réels et ouverts ne figuraient pas dans l’échantillon QWS du 4 septembre ; leurs cas sont testés avec des données synthétiques reprenant les types observés.
