# Intégration QUARTIX QWS v2

Cette évolution de développement complète la version 1.0.0, sans publier de nouvelle version. Elle utilise le contrat du document **QWS V2.pdf** fourni pour cette intégration, avec l'adaptation du format d'authentification décrite ci-dessous.

## Mise en service

1. Déployer la branche, puis désactiver/réactiver le module pour installer les tables, les droits et les tâches. Les réglages sont conservés, y compris ceux des tâches déjà présentes.
2. Dans **Réglages → QUARTIX**, saisir le code client, le login et le mot de passe du compte de l'environnement courant. Un mot de passe laissé vide conserve celui enregistré.
3. Confirmer avec QUARTIX le sens des horodatages et l'unité des durées. Le texte QWS décrit des heures locales alors que ses exemples portent Z ; l'unité de TravelTime et IdlingTime n'est pas précisée. Aucune interprétation n'est choisie par défaut.
4. Tester la connexion pour charger le catalogue, puis associer explicitement les véhicules et confirmer leur fuseau IANA. Le début de journée provient de ShiftStartTime. Vérifier ces informations lors de l'association.
5. Activer la synchronisation dans les réglages, puis les trois tâches dans les **Travaux planifiés** natifs. Utiliser un compte interne avec lecture du parc, synchronisation QUARTIX et gestion des relevés kilométriques. Les administrateurs disposent implicitement de ces droits.
6. Comparer les premières valeurs avec QUARTIX sur l'instance servant réellement ce code.

Chaque environnement possède sa connexion, ses jetons, ses associations et son état de synchronisation.
Un véhicule partagé reste synchronisé depuis son environnement propriétaire. Sa consultation utilise les données de ce propriétaire.
Les associations peuvent être suspendues ; leur remplacement ou le changement de code client après association nécessite une migration contrôlée, afin de ne pas attribuer l'historique d'un autre véhicule.
Une modification ultérieure du fuseau ou du début de journée dans QUARTIX nécessite également de revoir l'association avant de reprendre les imports.

## Données et source faisant autorité

| Donnée | Source QWS | Règle de conservation / conflit |
|---|---|---|
| Kilométrage estimé | /vehicles/odometer, OdoEstimateKm, EstimateDateTime | Une observation par véhicule, source et jour local. Rejeu sans doublon ; une observation plus ancienne ne remplace pas une plus récente du même jour. Historique des relevés conservé. |
| Dernière position | /vehicles/live | Une seule ligne par véhicule : coordonnées, lieu, date d'événement, date de réception et état du suivi. Une réponse plus ancienne ne remplace pas la position connue. |
| Utilisation | /vehicles/tripsummary, GroupBy=day | Distance en km, nombre de trajets, conduite et ralenti. Conservation glissante de douze mois et agrégation mensuelle à la lecture. |

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
Les données de trajets détaillés, conducteurs, écoconduite et distances privées ne sont pas importées.

## Travaux planifiés

Les trois tâches sont créées **désactivées**, avec un réveil toutes les quinze minutes. L'administrateur conserve la maîtrise de leur fréquence et de leur activation.

| Tâche | Traitement à chaque passage |
|---|---|
| Dernières positions | Un appel groupé pour au plus 100 véhicules, puis reprise au véhicule suivant. |
| Kilométrage quotidien | Jusqu'à 100 véhicules qui n'ont pas encore été synchronisés aujourd'hui (jour UTC). L'unicité de l'observation utilise le jour local de la donnée. |
| Utilisation et reprise historique | Jusqu'à 20 véhicules et environ 45 secondes de traitement, par fenêtres de sept jours. Relecture des sept dernières journées terminées après chaque clôture de journée QUARTIX, puis reprise progressive vers le passé. |

Une grande flotte peut nécessiter plusieurs passages ; l'intervalle réel dépend de sa taille, du temps de réponse et des quotas du compte.
Les bornes métier sont les débuts de journée QUARTIX dans le fuseau du véhicule. La journée en cours n'est pas agrégée.
Après une interruption de plus de sept journées, la reprise historique revisite la fenêtre conservée pour combler les trous.
La purge est bornée ; elle inclut les associations suspendues tant que la synchronisation globale et la tâche d'utilisation sont actives. Si les tâches sont arrêtées, aucune purge ne s'exécute.

Un verrou MySQL nommé, isolé par préfixe de base et environnement, sérialise les trois tâches et les actions de configuration.
Les positions/kilométrages utilisent des requêtes QWS groupées ; les synthèses sont demandées par véhicule pour vérifier strictement chaque réponse.
Les périodes d'utilisation et leurs curseurs sont enregistrés dans une même transaction. Une erreur sur un véhicule n'annule pas les autres.
Les erreurs visibles et les logs emploient des codes contrôlés, sans réponse API brute, secret ou coordonnées GPS.
Un test de connexion en échec précise maintenant l'endpoint concerné (/auth, /auth/refresh ou /vehicles) et le statut HTTP reçu ; une erreur de transport fournit son numéro cURL lorsqu'il est disponible. Ces seules métadonnées sont également journalisées. Aucun corps de réponse, paramètre, en-tête d'authentification ou message réseau brut n'est enregistré.
Le message « erreur de service » seul ne permet pas de conclure que le mot de passe est incorrect : il peut correspondre à une redirection, une requête refusée ou une indisponibilité distante. Le statut 422 est distingué comme un refus du contenu de la requête. Il interrompt le lot sans avancer le curseur et applique le délai de reprise ; aucun nouvel encodage n'est essayé automatiquement.
Les quotas 429 et Retry-After suspendent les appels de l'environnement, y compris les tests manuels de connexion.
Les erreurs d'authentification/réseau des tâches utilisent un délai de reprise ; un 401 provoque un renouvellement de jeton, puis une nouvelle authentification si le jeton de renouvellement est refusé.

## Format des requêtes d'authentification

Les POST `/auth` et `/auth/refresh` transmettent désormais un objet JSON avec `Content-Type: application/json`. Les noms des champs restent `CustomerID`, `UserName`, `Password`, `Application` et, pour le renouvellement, `RefreshToken`. Les GET conservent leurs paramètres d'URL et l'en-tête `AccessToken` ; les secrets ne passent jamais dans l'URL.

Le PDF historique décrit les paramètres en `formData`. Après le refus HTTP 422 observé sur `/auth` avec cet encodage, le choix JSON s'appuie sur un [exemple QWS publié le 7 juillet 2026 par son auteur comme fonctionnel](https://community.fabric.microsoft.com/t5/Power-Query/Convert-Dynamic-Data-source/td-p/5276109). Cet exemple d'intégration n'est pas une spécification officielle QUARTIX ; la réussite avec le compte concerné reste à confirmer après déploiement.

Pour une installation QUARTIX déjà initialisée, ce correctif ne nécessite ni migration, ni réactivation, ni nouvelle saisie du mot de passe. Déployer les fichiers modifiés puis relancer **Tester la connexion et charger les véhicules**. Si le refus persiste, conserver l'étape et le statut affichés pour le diagnostic, sans transmettre les identifiants ou les réponses API brutes.

## Sécurité et compatibilité

- Socle : Dolibarr 20+, PHP 8.0+, MySQL/MariaDB, cURL, OpenSSL et clé d'instance Dolibarr.
- Mot de passe et jetons chiffrés par dolEncrypt() ; le repli natif en clair est refusé. Aucun mot de passe stocké n'est renvoyé dans le formulaire.
- Transport HTTPS vers https://qws.quartix.net/v2/api, vérification TLS, sans redirection ni cookies, réponse bornée et délais limités. L'authentification QWS utilise l'en-tête AccessToken, sans secret dans l'URL.
- Exception documentée au helper HTTP : getURLContent() de Dolibarr 20 journalise les paramètres POST et l'en-tête QWS AccessToken. Le transport cURL dédié évite cette divulgation et reprend les réglages proxy natifs.
- Permission GPS indépendante de la lecture du parc ; tous les accès QUARTIX sont refusés aux utilisateurs externes. La synchronisation n'accorde pas le droit de consulter le GPS.
- Réglages et associations réservés aux administrateurs de l'environnement propriétaire. Formulaires protégés par le CSRF natif et redirection après traitement.
- Disponibilité centralisée dans LmdbVehicleQuartixConfig, exposée dans l'onglet **Compatibilité**. Les données d'utilisation restent lisibles dans le cache lorsque la synchronisation est suspendue.
- Tables complémentaires liées par fk_vehicle, index courts, aucune recopie des données métier Dolibarr. Migration additive is_estimate / provider_key, rejouable sans reclassement des relevés historiques.
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
| QUARTIX | 86 contrôles initiaux réussis sur chacun des deux cores ; 127 contrôles réussis sur le core 25.0.0-alpha avec diagnostic HTTP et refus 422 ; chiffrement, transport simulé, stockage, reprises, droits, rendu GPS et graphiques natifs inclus |
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
Le runtime PHP 8.0, un serveur MySQL/MariaDB de recette, les identifiants QUARTIX et une instance navigateur servant cette branche ne sont pas disponibles pour cette validation locale.
Les essais de concurrence utilisent un refus de verrou simulé ; une exécution concurrente réelle reste à vérifier sur MySQL/MariaDB.
Les documents, catégories et modèles de numérotation ne changent pas de fonctionnement ; aucune nouvelle génération documentaire ou modification de leurs modèles n'est introduite.

À vérifier sur l'instance de recette après déploiement :

- activation/réactivation sur MySQL/MariaDB avec conservation des constantes (0 et chaîne vide), fréquences, états des tâches et réglages Multicompany ;
- trois tâches visibles et exécutables, utilisateur standard de synchronisation, lecture seule, GPS, administrateur d'entité et utilisateur externe ;
- association correcte de deux véhicules dans deux entités ; absence de fuite et écriture uniquement dans l'entité propriétaire pour un véhicule partagé ;
- concordance du kilométrage, de la position, des journées, trajets et durées avec QUARTIX, après confirmation des unités et fuseaux ;
- consultation bureau/mobile, datepickers, pagination, colonnes, graphiques natifs, données absentes, position ancienne et suivi interrompu ;
- refus des POST sans token, succès avec token, erreurs partielles et reprise après interruption/quota ;
- relevé manuel et plein/recharge en contradiction avec une estimation, badge d'anomalie et statistiques inchangées ;
- absence de GPS dans le dossier et la chronologie ; libellé d'estimation explicite.

Les vérifications locales ne nécessitent aucun compte QUARTIX. PHPStan doit être lancé avec l'outillage du projet lorsqu'il est disponible ; aucun baseline ni niveau affaibli n'est ajouté par cette évolution.
