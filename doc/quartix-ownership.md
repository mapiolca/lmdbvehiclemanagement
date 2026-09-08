# Propriété des données QUARTIX

## Utilisation

Une entité peut connecter son compte QUARTIX à un véhicule qu’elle possède ou auquel elle accède par partage. Le collecteur possède l’association et toutes ses observations. Exemple : A partage son véhicule avec B ; B utilise son compte QUARTIX. A ne voit aucune observation de B tant que B ne les lui partage pas explicitement.

Dans Multicompany, activer le partage et le partage par élément, autoriser les entités sources pour **Données QUARTIX**, puis activer cette famille dans les réglages du parc. Dans l’onglet **Utilisation QUARTIX**, le propriétaire clique sur **Gérer le partage des données QUARTIX** : une modale contient le sélecteur natif et les boutons **Enregistrer** et **Annuler**. Fermer ou annuler abandonne les changements ; un refus d’enregistrement conserve la sélection pour correction. Le bouton concerne la source sélectionnée, également depuis son historique après retrait du véhicule, et n’est pas proposé aux bénéficiaires. Cette famille utilise exclusivement la sélection : les lignes natives désignent des bénéficiaires, jamais des exclusions. Chaque bénéficiaire doit aussi accéder au véhicule. Le partage ordinaire du véhicule ou des relevés kilométriques ne partage pas QUARTIX.

Le sélecteur **Propriétaire des données QUARTIX** présente une source à la fois. Sans choix explicite, la source locale est sélectionnée, sinon l’unique source autorisée ; plusieurs sources distantes nécessitent un choix. Les liens, filtres et demandes de tracé conservent `quartix_id`. Le tableau de bord sélectionne une entité collectrice à la fois. La liste générale des relevés conserve les relevés ordinaires autorisés et les seules estimations de la source sélectionnée.

Les bénéficiaires consultent les caches uniquement. Ils ne peuvent pas administrer l’association, appeler le compte source ou repartager les données. Tous les profils doivent avoir le droit de lecture du parc ; positions, journal et tracés exigent le droit GPS. Les imports et demandes de nouveaux tracés exigent le droit de synchronisation. L’import de kilométrages conserve aussi le droit de gestion des relevés. L’administration du compte, des associations, des partages et du nettoyage exige séparément le rôle administrateur de l’entité concernée. Aucun rôle administrateur ne remplace un droit fonctionnel.

## Retrait, dissociation et conservation

Lorsque le véhicule est retiré du partage, le collecteur accède encore à ses données depuis son tableau de bord QUARTIX, avec les liens **Utilisation**, **Journal** et **Relevés kilométriques QUARTIX**. Seule l’identification historique enregistrée pendant l’accès au véhicule est affichée. Sa fiche actuelle, ses documents et ses champs privés ne sont pas chargés.

Les quatre travaux planifiés excluent alors cette association avant les appels et les écritures. Les demandes de tracés devenues inéligibles sont suspendues. Le retour du véhicule dans le partage permet la reprise d’une association restée active ; une suspension manuelle reste respectée.

Une dissociation conserve l’objet QUARTIX et ses autorisations. La réaffectation conserve les historiques, mais retire la dernière position et les demandes en attente. Le nettoyage d’une association erronée supprime les seules observations du collecteur. Après dissociation, **Effacer l’historique QUARTIX dissocié** reste disponible pour son propriétaire, avec confirmation native et token. Les autres sources du véhicule sont conservées.

Les durées existantes restent applicables : synthèses sur douze mois, journal et tracés selon la durée configurée, kilométrages conservés selon le comportement existant. La purge planifiée dépend toujours de l’exécution des travaux natifs. « Historique consultable » ne signifie pas conservation illimitée.

## Installation et migration

Déployer les fichiers puis désactiver/réactiver le module depuis l’administration native. Cette opération installe la structure et les nouvelles déclarations. Elle conserve les comptes, paramètres, tâches planifiées, choix Agenda et partages existants. Aucune nouvelle API REST ni aucun nouveau menu haut n’est ajouté. L’identifiant de module et les identifiants de droits restent inchangés.

La table `qx_dataset` contient une ligne unique par `(entity, fk_vehicle)`. Les associations, positions, synthèses, journées de trajets et relevés importés sont liés par `fk_quartix` ; trajets et tracés restent liés à leur journée. `snapshot_vehicle_label` fige uniquement la référence du véhicule pour son identification après retrait. La migration ne lit une référence vivante que lorsque le véhicule appartient déjà à l’entité du jeu historique ; une référence absente reste vide.

Le remplissage des liaisons est transactionnel et rejouable, y compris pour des historiques sans association ni véhicule existant. Entités, identifiants, mesures, dates et réglages sont conservés. Une liaison existante incohérente arrête la migration au lieu de réaffecter silencieusement les données. **Aucune autorisation de partage n’est créée**, même pour les données déjà présentes. Les partages QUARTIX souhaités doivent donc être configurés après migration.

Les méthodes métier émettent `LMDBVEHICLEMANAGEMENT_QUARTIX_CREATE` et `LMDBVEHICLEMANAGEMENT_QUARTIX_UPDATE`, avec un contexte distinguant association, partage, import et nettoyage. Ces événements sont déclarés pour l’Agenda et les Notifications natifs. L’Agenda QUARTIX reste désactivé tant que l’administrateur ne le configure pas. Aucun événement QUARTIX n’est déclenché sur le véhicule d’une autre entité. L’objet source persiste ; aucune suppression de source n’est exposée.

## Validation

Socle annoncé : Dolibarr 20+/PHP 8.0+, MySQL/MariaDB. Partage individuel : Multicompany 21+ avec ses formulaires et son DAO natifs ; l’onglet Compatibilité indique leur disponibilité. La consultation locale indépendante reste disponible sans Multicompany.

Les suites `run_quartix.php` et `run_sharing.php` couvrent la collecte B sur A, les sources simultanées, le partage retour, le refus de C, le retrait et le rétablissement du véhicule, les anciennes URLs, l’absence d’appels après retrait, les historiques, nettoyages, permissions partielles, erreurs transactionnelles et rejeux de migration. La suite dossiers vérifie la révocation de chaque source contenue dans les documents générés ; les relevés QUARTIX utilisent le même prédicat d’accès. Les adaptateurs SQL en mémoire ne valident pas le comportement d’un moteur MySQL/MariaDB réel ni la concurrence entre processus.

Contrôles exécutés le 2026-09-08 sous PHP 8.5.7, avec le core local Dolibarr `25.0.0-alpha` (commit `f0eeff2`) en lecture seule :

- QUARTIX : 446 assertions, dont la migration additive et son rejeu, sur adaptateur SQL en mémoire.
- Partage : 437 assertions pour chacun des formulaires natifs chargés depuis les archives Multicompany 21 et 22 ; DAO et base simulés.
- Agenda : 416 assertions ; interface : 168 ; règles métier : 50 ; factures/dossiers : 196, avec génération PDF native.
- Transport : quatre requêtes HTTPS locales vérifiées avec le transport cURL réel et des identifiants fictifs.
- Navigateur partage : ouverture de la modale, ajout de bénéficiaires fictifs, annulation et réouverture avec restauration, POST contenant la sélection entière et la source ; formulaire véhicule avec envoi commun des champs et des partages. Fixture `test/run_sharing_browser.py` utilisant le code modifié et les composants natifs Multicompany 21/jQuery UI, sans données ni session ERP réelles. Validation sur l’instance distante non réalisée faute de déploiement de ce correctif.
- Navigateur tracés : tracé en cache, récupération automatique d’un tracé manquant avec `quartix_id`, puis refus d’accès sans carte résiduelle, sur la fixture locale servant le JavaScript et le template modifiés. Cette fixture n’est pas une session Dolibarr complète.

PHPStan est absent de l’environnement. Les bibliothèques tierces du core/Multicompany émettent des avertissements de dépréciation sous PHP 8.5 ; leurs fichiers n’ont pas été modifiés. Les moteurs MySQL/MariaDB, Dolibarr 20/PHP 8.0 et l’instance du parc n’ont pas été exécutés pour cette évolution.

Avant déploiement métier, effectuer la recette A/B/C sur une instance servant ce code : migration puis réactivation, source locale et sources partagées, sélecteurs, pagination, droits partiels, CSRF, révocation pendant une synchronisation, dossier PDF/ZIP et aperçu après révocation. Vérifier MySQL et MariaDB, le socle Dolibarr 20/PHP 8.0 et la version du parc. Les recettes historiques d’autres versions figurant dans `quartix.md` ne constituent pas une validation de cette migration.

## English guide

Each collecting entity owns its QUARTIX connection and observations, even when another entity owns the vehicle. Data is private by default, including migrated history. Explicit native Multicompany sharing uses the `lmdbvehiclequartix` family in selection mode only. Recipients also need vehicle access and can only read cached data; they cannot manage the source, synchronize it or share it onward.

In the usage tab, **Manage QUARTIX data sharing** opens a dedicated modal for the selected source. Only its owning administrator can manage it. Cancel or close discards changes; failed saves preserve the selection.

Choose a data owner before viewing usage, mileage, trips or routes. The local source is the default; otherwise the sole authorized source is selected, or a choice is required. The dashboard reports one collecting entity at a time. Read, GPS and synchronization permissions are checked separately, without an implicit administrator bypass.

After vehicle access is withdrawn, collection stops and the owner can still read retained history from its QUARTIX dashboard. Only the saved historical vehicle reference is used. Existing retention rules remain in effect. Restored vehicle access resumes active associations, but never overrides manual suspension. Unlinking or deleting a vehicle in one entity does not remove another entity’s observations.

Deploy the code and reactivate the module to install the persistent dataset and ownership links. Migration preserves entities, identifiers, observations and settings, and grants no sharing rights. Configure intended QUARTIX sharing afterward. Native Agenda/Notification events target the QUARTIX source; automatic Agenda creation is opt-in. No new REST API or top-level menu is introduced. Validate the migration on MySQL/MariaDB and the complete A/B/C workflow on an instance running the updated code before operational deployment.
