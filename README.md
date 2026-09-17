# Gestion des véhicules et engins pour Dolibarr

`lmdbvehiclemanagement` fournit un parc multientité intégré à Dolibarr. La version `1.0.0` couvre les véhicules routiers, utilitaires et engins, leurs affectations, kilométrages, consommations, assurances et contrôles réglementaires, ainsi que les factures fournisseurs liées et le dossier véhicule PDF/ZIP.

La [notice utilisateur](https://wiki.dolibarr.org/index.php/Module_Gestion_des_v%C3%A9hicules) est disponible sur le wiki Dolibarr. Les sources wiki, captures, illustrations, descriptions Dolistore et vidéos sont centralisées dans [le dossier du module sur dolibarr_wiki](https://github.com/mapiolca/dolibarr_wiki/tree/main/lmdbvehiclemanagement/1.0.0). Les [guides techniques](https://github.com/mapiolca/dolibarr_wiki/blob/main/lmdbvehiclemanagement/1.0.0/guides/README.md) sont également maintenus dans ce dépôt documentaire.

## Compatibilité

- Dolibarr 20 ou supérieur
- PHP 8.0 ou supérieur
- MySQL ou MariaDB
- Module Multicompany facultatif
- Module Ressources facultatif pour la liaison `fk_resource`
- Modules Agenda et Travaux planifiés recommandés pour les échéances et relances automatiques
- Module Banques et Caisses requis lorsque la création d’opérations diverses pour les pleins / recharges est activée ; comptabilité facultative
- Module Factures fournisseurs requis pour les liaisons de factures et les dossiers véhicule ; extension PHP `zip` (`ZipArchive`) requise pour générer le couple PDF/ZIP

## Factures fournisseurs et dossier véhicule

Depuis une fiche événement ou contrôle, **Créer une facture fournisseur** ouvre le formulaire natif. Le fournisseur accessible de l’intervention est proposé lorsqu’il est renseigné. L’enregistrement crée une facture brouillon et sa liaison dans une même transaction ; une annulation ne crée rien et un échec de liaison annule la création. La sélection du fournisseur reste libre.

Une facture existante peut être sélectionnée sur chaque fiche ; depuis une facture fournisseur, utiliser **Lier à** pour sélectionner des événements ou contrôles. Les relations apparaissent réciproquement dans **Objets liés**, sans doublons et sans limite à une seule facture. Un contrôle validé conserve ses données réglementaires lors d’une liaison ou déliaison. Seule la relation est supprimée lors d’une déliaison.

Les liaisons exigent les droits de lecture du module, d’écriture de l’événement ou du contrôle, ainsi que de lecture et création des factures fournisseurs. Les deux objets doivent appartenir à l’entité courante. Les droits sont vérifiés directement par `hasRight()`, sans privilège administrateur implicite ; les formulaires et liens modificatifs utilisent les tokens Dolibarr.

Après déploiement, réactiver le module pour enregistrer ses nouveaux contextes de hooks, puis activer **Dossier véhicule** dans le bloc **Modèles de document** des réglages. Le modèle peut être choisi par défaut. Sur la fiche véhicule ou son onglet **Fichiers joints**, sélectionner ce modèle et cliquer sur **Générer**.

Le dossier contient :

- un PDF présentant en tableaux les caractéristiques, capacités, profils et champs complémentaires accessibles, la chronologie disponible, tous les événements et contrôles avec leurs statuts, leurs factures et l’inventaire de leurs documents, puis tous les pleins/recharges et l’inventaire des fichiers du véhicule ;
- un ZIP contenant ce PDF et les fichiers originaux dans des répertoires `vehicle`, `events`, `controls` et `invoices`. Les sous-répertoires d’origine sont conservés ; une facture commune à plusieurs interventions n’est embarquée qu’une fois.

Les brouillons, annulations et archives restent inclus. Le chargement s’effectue par lots de 200, sans dépendre de la pagination de l’écran. L’historique reflète les données enregistrées et ne reconstitue pas des modifications qui n’ont pas été historisées. Les montants affichés dans les autres sections conservent leurs valeurs et devises ; les montants des consommations ne figurent pas dans leur tableau de synthèse.

Les fiches détaillées utilisent des tableaux **Désignation / Valeur** ; la chronologie sépare date, source, type, description, état et kilométrage. Les titres, références et en-têtes de colonnes sont répétés sur les pages suivantes. Les lignes ordinaires restent entières ; les textes dépassant une page sont répartis sans empiéter sur les pieds de page natifs. Régénérer un dossier existant pour appliquer cette présentation.

Chaque page reprend la disposition d’en-tête des modèles natifs Dolibarr : logo de société à gauche, titre **Dossier véhicule**, référence du véhicule et date/heure de génération alignés à droite. Le modèle respecte les marges PDF et le choix de logo standard/grand format de Dolibarr ; sans logo lisible, le nom de la société émettrice est affiché. Les logos sont recherchés dans le répertoire société de l’entité propriétaire du véhicule, sans repli vers celui d’une autre entité. L’espace réservé sous l’en-tête s’adapte à la taille du logo et aux références longues.

Les pleins et recharges sont regroupés dans un seul tableau, avec une ligne par consommation : **Date du relevé**, **Kilométrage**, **Réf.**, **Consommable**, **Nature**, **Unité** et **Référence / type d’huile**. Ce tableau exclut conducteur, projet, quantité, total TTC, devise, description, note publique, état et date de création. Cette présentation ne modifie ni les données enregistrées ni les autres sections du dossier.

Les justificatifs des consommations et de leurs OD, temporaires, aperçus et dossiers précédents sont exclus. Une facture sans fichier reste mentionnée ; aucune génération de facture commerciale n’est déclenchée. Les fichiers indexés mais absents sont signalés dans le PDF. Une erreur de lecture, de compression ou d’indexation annule la publication et restaure le dossier précédent.

La génération exige la lecture et l’écriture du véhicule ainsi que la lecture des factures fournisseurs. La consultation, le téléchargement et l’aperçu exigent les seuls droits cumulés de lecture. Les utilisateurs externes et les liens publics ne peuvent pas accéder au dossier. Les noms réservés `lmdb-dossier-<id>.pdf` et `.zip` ne peuvent pas être renommés ou remplacés par un upload : utiliser **Générer** pour les mettre à jour. Ces restrictions permettent de maintenir les contrôles lors d’un accès direct et après un retrait de droits.

Les fichiers sont préparés dans le répertoire temporaire protégé du véhicule, vérifiés puis publiés et indexés dans l’entité propriétaire. Un verrou empêche deux générations simultanées. Les modèles actifs et le modèle par défaut sont conservés lors d’une réactivation. Aucune nouvelle table métier ni API n’est ajoutée : les relations utilisent `element_element`, les modèles `document_model` et les documents l’index ECM natif.

### Intégration et compatibilité du dossier

Le socle reste Dolibarr v20/PHP 8.0. Les contrats natifs ont été vérifiés dans les sources v20 à v24 : `add_object_linked()`, `BILL_SUPPLIER_CREATE` dans la transaction de création, `Form::selectForForms()`, `showLinkToObjectBlock`, résolution des objets externes, modèles documentaires, `FormFile::showdocuments()`, `getMultidirOutput()`, `indexFile()` et compression native. En v20, le formulaire natif de liaison sélectionne un objet par soumission ; à partir de v21 il accepte plusieurs identifiants. Les relations restent multiples dans toutes ces versions. Les variantes de templates d’objets externes sont fournies pour ces versions.

Les paramètres dédiés `lmdb_source_type` et `lmdb_source_id` sont conservés par `formObjectOptions` puis validés par `doActions` ; le paramètre natif `origin` n’est pas utilisé. Les changements réels de relation et de document produisent un seul trigger CRUD `UPDATE`, avec les contextes `supplier_invoice_link`, `supplier_invoice_unlink` ou `document_generation`. Agenda et Notifications conservent leur configuration native.

Les hooks `checkSecureAccess`, `downloadDocument`, `renameUploadedFile`, `moveUploadedFile` et les triggers ECM protègent les fichiers réservés. Le contexte natif `main` couvre aussi l’aperçu public par une garde limitée à `document.php` et `viewimage.php` dans `setContentSecurityPolicy`, sans modifier la politique CSP : le chemin public de l’aperçu contourne les deux premiers hooks. Aucune modification du core n’est nécessaire.

Le pied de page suit le cycle PDF natif, avec une mesure séparée pour les contenus HTML et les extensions `pdf_pagefoot`. Le texte libre facultatif utilise la constante par entité `LMDBVEHICLEMANAGEMENT_DOSSIER_FREE_TEXT`. L’onglet **Compatibilité** indique les dépendances absentes et la génération reste indisponible si `ZipArchive` manque.

## Fonctionnalités de la version 1.0.0

### Parc, conducteurs et interventions

- Véhicules routiers, utilitaires et engins avec immatriculation facultative, caractéristiques techniques, énergie, capacités et autonomie WLTP.
- Cycle de vie du brouillon à la cession, affectations simultanées avec une seule affectation principale par période, relevés kilométriques et gestion explicite des corrections ou remplacements de compteur.
- Affectations : conducteur choisi par le sélecteur natif Select2 parmi les utilisateurs actifs ayant accès à l’entité courante de saisie, y compris par les groupes Multicompany. Une date de début antérieure à aujourd’hui (jour du serveur) permet de consigner une affectation historique, même encore en cours, malgré un blocage réglementaire actuel. Cette saisie ne valide pas la conformité passée et ne modifie aucun contrôle ni l’état du véhicule. Les affectations actives débutant aujourd’hui ou plus tard restent soumises au blocage. Droits, accès au véhicule, période et chevauchement des affectations principales restent contrôlés en création comme en modification.
- Événements d’entretien, panne et incident ; chronologie consolidée incluant contrôles et consommations, avec filtres SQL, tri et pagination.
- Références par modèles de numérotation, dont un modèle par immatriculation avec migration contrôlée des références et documents.

### Consommations et dépenses

- Pleins, recharges électriques, hydrogène et additifs ; dictionnaire des consommables compatible avec les énergies P.3, capacités par véhicule et avertissement de dépassement.
- Synchronisation transactionnelle avec les relevés kilométriques ; synthèses par véhicule, consommable et unité, coûts, distances et graphiques natifs DolGraph.
- Prix facultatif des additifs : une valeur absente est exclue des statistiques de prix, tandis qu’un zéro reste un prix connu. Les quantités et fréquences restent comptabilisées.
- Option par entité de création d’opérations diverses avec compte bancaire, règlement, projet et ticket PDF/JPEG/PNG obligatoire. Les images sont nettoyées de leurs métadonnées.
- Un plein avec OD peut concerner un véhicule accessible par partage Multicompany : consommation, relevé kilométrique, OD et justificatif appartiennent à l’entité de saisie, avec ses réglages bancaires et comptables. Le véhicule conserve son propriétaire et son immatriculation figure dans le libellé de l’OD. Un véhicule inaccessible reste refusé. Le justificatif utilise exclusivement le répertoire bancaire de l’entité propriétaire de la consommation ; une configuration documentaire absente ou invalide bloque l’opération sans repli. Les protections des OD rapprochées, comptabilisées et consultées depuis une autre entité sont conservées. Ce correctif ne nécessite ni migration ni réactivation.
- Comptabilité facultative : comptes libres sans comptabilité ou en mode simplifié ; compte général du plan actif obligatoire en partie double. Données financières et ticket verrouillés après rapprochement ou transfert comptable.
- Import et export natifs ; reprise historique des consommations sans OD, même lorsque la gestion des OD est active.

### Assurances

- Contrats individuels ou de flotte, couverture principale et couvertures complémentaires, contacts de l’assureur et courtier, rattachements à plusieurs véhicules.
- Attestations communes ou propres à un véhicule, justificatifs contrôlés, validation, rejet et archivage ; référents utilisateurs/groupes et personnels affectés éligibles.
- Relances avant et après échéance par modèles d’emails et travaux planifiés natifs, avec prévention des doublons.

### Contrôles réglementaires

- Questionnaire guidé et historisé, profils réglementaires, catalogue français versionné, surcharges d’entité auditées et règles personnalisées.
- Une qualification confirmée est résumée par une ligne **État**, son badge et **Visualiser / éditer**. Le détail s’ouvre dans une modale jQuery UI native ; sans droit d’écriture, **Visualiser** ouvre les réponses en lecture seule. Un questionnaire incomplet reste affiché. La sauvegarde utilise le formulaire POST et le token natifs, conserve les saisies sur échec et recharge la fiche après succès. Sans JavaScript, le lien ouvre le détail dans la page.
- Contrôles routiers, pollution, catégorie L, usages spécialisés, VGP, mise ou remise en service, tachygraphe, ADR et ATP ; aucune échéance inventée lorsqu’une donnée indispensable manque.
- Contrôles numérotés avec organisme, résultat, dates et justificatif obligatoire ; validation immuable, annulation motivée, remplacement, contre-visite et archivage encadré.
- Échéancier, registre de sécurité, export et import en brouillon ; blocage configurable des nouvelles affectations et mises en service.
- Travail planifié quotidien de recalcul et de rappel, événement Agenda unique par exigence et rappels journaliers facultatifs après échéance. Un lancement manuel peut rejouer les emails dus sans consommer la limite automatique.

### Factures, documents et intégration native

- Création et liaison réciproque de factures fournisseurs aux événements et contrôles ; dossier véhicule PDF/ZIP décrit ci-dessus.
- Notes, contacts, fichiers joints, objets liés, Agenda CRUD traduit, modèles de numérotation, imports/exports et droits granulaires natifs.
- Listes avec colonnes personnalisables, filtres, badges de statut et tooltips Ajax ; formulaires adaptés aux écrans mobiles.
- Partages Multicompany configurables, documents dans l’entité propriétaire, filtres Environnement lorsqu’un partage est actif et conservation des réglages à la réactivation.

Le [guide des partages Multicompany](https://github.com/mapiolca/dolibarr_wiki/blob/main/lmdbvehiclemanagement/1.0.0/guides/multicompany-sharing.md) décrit le partage individuel des véhicules et les huit familles globales indépendantes, conditionnées par l’accès au véhicule. Les saisies interentités appartiennent à l’entité de saisie ; les historiques locaux restent consultables après retrait du véhicule. Le guide précise la migration et la reprise des échéances des véhicules déjà importés. Les sources QUARTIX appartiennent à l’entité collectrice et suivent leur propre partage global ; voir le [guide de propriété QUARTIX](https://github.com/mapiolca/dolibarr_wiki/blob/main/lmdbvehiclemanagement/1.0.0/guides/quartix-ownership.md).

## Installation et mise à jour

Copier le répertoire `lmdbvehiclemanagement` dans le répertoire des modules externes de Dolibarr, puis activer **Gestion des véhicules et engins** depuis la liste des modules. Une réactivation conservatrice initialise les dictionnaires et règles absents sans remplacer les réglages, choix Agenda, modèles, crons ou partages existants.

Les réglages, la compatibilité détectée et les métadonnées du module sont accessibles depuis l'unique roue dentée du module.

Le menu haut s’intitule **Véhicules | Engins** (**Vehicles | Equipment** en anglais). Le renommage utilise la clé de traduction du menu existant ; ces changements d’interface ne nécessitent pas de migration SQL ni de réactivation.

Pour une mise à jour depuis une version de développement, consulter [ChangeLog.md](ChangeLog.md), puis désactiver et réactiver le module pour appliquer la migration idempotente de `total_ttc` vers une colonne nullable. Les montants historiques, y compris les zéros, et les réglages sont conservés. Une ancienne recharge peut ensuite être corrigée en vidant son montant. Les imports gardent la colonne `total_ttc`, avec cellule vide autorisée pour les additifs ; les exports natifs distinguent une cellule vide d’un zéro. Les carburants et leurs OD conservent leurs règles existantes.

## Vérification locale

La qualification compacte et sa modale sont vérifiées sans instance ERP avec `php test/run_qualification_modal.php /chemin/vers/dolibarr/htdocs` et `node test/run_qualification_modal_browser.cjs /chemin/vers/dolibarr/htdocs` (Playwright/Chromium, ou `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH`). Sept scénarios utilisent le rendu PHP de la page, les composants natifs Form/Select2/calendrier et des réponses synthétiques : qualification confirmée, lecture seule, réponse inconnue, date requise absente, questionnaire vide, accès sans JavaScript et échec simulé de sauvegarde. Le navigateur vérifie aussi la soumission des dates, profils et token, la fermeture et les dimensions tablette/téléphone. Exécutés le 17 septembre 2026 sous PHP 8.4.22 avec les sources Dolibarr `25.0.0-alpha` (`5dedd84c6c1db18a8f864e5a7ac168a842afbc95`) ; ils ne constituent pas une validation sur une instance déployée.

L'intégration QUARTIX est documentée dans [le guide de configuration et de validation](https://github.com/mapiolca/dolibarr_wiki/blob/main/lmdbvehiclemanagement/1.0.0/guides/quartix.md) : connexion par environnement, kilométrage estimé quotidien, dernière position protégée par un droit GPS, utilisation par véhicule, journal des trajets, tracés en modale et tableau de bord QUARTIX du parc. Les associations peuvent être suspendues ou dissociées, avec suppression des imports en cas d'erreur ou conservation lors d'une réaffectation du boîtier. La date d'installation borne les nouveaux imports. Elle nécessite une réactivation du module après déploiement et l'activation des quatre travaux planifiés natifs (le nouveau journal est installé désactivé). La version reste 1.0.0 pendant ce développement.

Pour toute création ou modification de tableau, utiliser comme référence la [documentation native Dolibarr des tableaux avec filtres](https://develop.lesmetiersdubatiment.fr/admin/tools/ui/content/tables.php#tablesection-withfilters) : filtres dans le formulaire de liste, commandes natives, tris et alignements cohérents entre filtres, en-têtes et cellules. Vérifier aussi la conservation des filtres, la sélection des colonnes et la pagination sur le code déployé.

Ses tests hors ligne utilisent les objets Dolibarr et une base en mémoire, sans accès à QUARTIX :

    php test/run_quartix.php /chemin/vers/dolibarr/htdocs

Les durées QUARTIX se règlent dans l’entité collectrice, selon l’unité des valeurs reçues. Le cas signalé le 17 septembre 2026 a été confirmé par l’utilisateur et son rapport QUARTIX : `0,019120…` représente une fraction de journée, soit environ 27 min 32 s, et non une durée en heures. Après déploiement, sélectionner **Jours (1 = 24 h)** dans **Réglages → QUARTIX → Unité des durées** pour cette source. Les tableaux Utilisation et Trajets affichent alors **00:27** de conduite et **00:02** de ralenti au format `hh:mm`. Les secondes sont conservées pour les sommes, puis masquées à l’affichage ; un cumul de 60 heures reste **60:00**. Les graphiques gardent des valeurs numériques en heures avec la même conversion.

Les valeurs brutes en cache ne changent pas : aucun réimport, migration SQL ni changement automatique d’unité n’est effectué. La source partagée utilise toujours le réglage de son collecteur. L’unité vide ou inconnue et les durées absentes restent indisponibles ; un zéro connu s’affiche **00:00**. Cette confirmation porte sur l’échantillon fourni, pas sur tous les comptes QUARTIX.

Validation du 17 septembre 2026 : 480 contrôles QUARTIX hors ligne sous PHP 8.4.22 avec les sources Dolibarr `25.0.0-alpha` du checkout `5dedd84c6c1db18a8f864e5a7ac168a842afbc95`. La suite exécute les rendus PHP des deux tableaux et la préparation des données du graphique, les réglages par entité, les unités existantes, les valeurs absentes et les cumuls supérieurs à 24 h. Les helpers natifs `convertDurationtoHour()` et `convertSecondToTime()` ont aussi passé six cas isolés chacun avec les sources [Dolibarr 20.0.0](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/core/lib/date.lib.php) et [23.0.2](https://github.com/Dolibarr/dolibarr/blob/ccef1102e6850b7545be7bad91cf0cc4c74ac6ea/htdocs/core/lib/date.lib.php). Le code minute `i` est utilisé pour rester compatible avec v20. Aucun essai QWS authentifié ni validation du correctif déployé n’a été réalisé ; le navigateur de développement demandait une connexion.

Les accès individuels des véhicules, familles globales, parents, transactions et migrations sont vérifiés avec SQLite et des doubles des API natives :

    php test/run_sharing.php /chemin/vers/dolibarr/htdocs

Un second argument facultatif désignant une archive officielle Multicompany 21 ou 22 charge en lecture seule sa classe de rendu native pour vérifier le sélecteur. Il n’installe pas Multicompany et ne remplace pas une validation sur trois entités MySQL/MariaDB. La matrice de validation est détaillée dans le guide des partages Multicompany.

Les affectations disposent d’une suite ciblée utilisant les méthodes métier, le sélecteur `Form::select_dolusers()`, les dates Dolibarr et `DaoMulticompany::verifyRight()` natifs, avec une base SQLite en mémoire et des doubles pour le chargement utilisateur et la persistance :

    php -d extension=pdo_sqlite test/run_assignments.php /chemin/vers/dolibarr/htdocs /chemin/vers/multicompany
    node test/run_assignments_browser.cjs /chemin/vers/dolibarr/htdocs /chemin/vers/multicompany

Le second test nécessite Playwright, PHP dans le PATH et Chromium (ou `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH`). Il vérifie sur une page locale le Select2 natif, sa recherche, les utilisateurs autorisés, une liste vide et le repli sans JavaScript. Aucun accès à une instance ni effet métier externe. Le 17 septembre 2026, les 35 contrôles PHP et les 5 scénarios navigateur ont réussi avec les sources Dolibarr 20.0.0 / Multicompany 21.0.2 et Dolibarr 23.0.2 / Multicompany 22.0.1, sous PHP 8.4.22 et Edge sans interface. Il s’agit de tests simulés, pas d’une validation complète de ces couples en production ni d’un essai sous PHP 8.0. Après déploiement, vérifier sur l’instance le conducteur autorisé/refusé, l’affectation historique d’un véhicule partagé, le blocage d’une affectation démarrant aujourd’hui et la protection CSRF.

Les règles indépendantes de la base peuvent être vérifiées avec la commande suivante :

    php test/run_business_rules.php
    php test/run_agenda_contracts.php
    php test/run_regulatory_contracts.php
    php test/run_ui_contracts.php
    php test/run_consumption_od_contracts.php
    php -d extension=pdo_sqlite test/run_consumption_od_sharing.php
    php test/run_native_import.php
    php test/run_consumption_native_import.php
    php test/run_capacity_import_transactions.php

Le workflow GitHub Actions `PHP checks` exécute ces neuf suites autonomes et vérifie la syntaxe de tous les fichiers PHP versionnés sur PHP 8.0 et 8.5. La suite OD/partages utilise SQLite en mémoire ; l’extension `pdo_sqlite` est activée dans le workflow. Le module n’a pas de dépendances Composer propres : le workflow ne lance donc ni validation ni installation Composer. Ces contrôles n’installent pas une instance Dolibarr et ne remplacent pas les tests d’intégration ci-dessous. PHPStan n’est pas exécuté par ce workflow, en l’absence de configuration d’analyse du module.

Les tests comportementaux des réglages OD utilisent les classes natives depuis une installation ou un checkout Dolibarr, sans connexion à la base ni écriture métier :

    php -d extension=mysqli test/run_consumption_od_settings.php /chemin/vers/dolibarr/htdocs

Validation OD/partages du 17 septembre 2026 sous PHP 8.4.22 : 304 contrôles exécutent le service de règlement, les méthodes de consommation et les requêtes de partage réels, avec SQLite et des doubles explicites pour la persistance native, l’upload et l’index ECM. Ils couvrent les véhicules locaux et partagés, la propriété des données et du justificatif, les libellés, les refus de droits ou de partage, les annulations après échec et les verrouillages comptables. L’ancienne version reproduit le refus `CannotMoveObjectBetweenEntities` avec cette suite. Les 183 contrôles de réglages, verrouillages et résolution documentaire native passent séparément avec les classes Dolibarr 20.0.0, 21.0.0, 22.0.0, 23.0.2, 24.0.0 et le checkout 25.0.0-alpha `5dedd84c6c1db18a8f864e5a7ac168a842afbc95`. Il ne s’agit pas de tests d’instances complètes ni d’essais sous PHP 8.0 ; le chargement du pilote `mysqli` n’ouvre aucune connexion. Après déploiement, vérifier le formulaire avec un vrai ticket, le compte bancaire retenu et la consultation du justificatif. Aucun test navigateur du correctif déployé ni PHPStan n’a été réalisé.

Les prix facultatifs disposent de tests comportementaux utilisant les classes natives de normalisation, stockage et export CSV, ainsi que des doubles de base et de graphique sans écriture métier :

    php test/run_consumption_prices.php /chemin/vers/dolibarr/htdocs

Les factures liées et le dossier disposent de tests comportementaux utilisant les classes natives, un double transactionnel de base et les véritables moteurs PDF/ZIP :

    php -d extension=zip test/run_invoice_dossier.php /chemin/vers/dolibarr/htdocs

Définir `LMDB_DOSSIER_TEST_ROOT` vers un répertoire temporaire hors du code pour recevoir les fixtures (à défaut, le script historique utilise le répertoire ignoré `test/.dossier-test/`) : dossier de 621 entrées, tableau de 80 consommations, fichiers homonymes, PDF multipages, en-têtes avec/sans logo, référence longue, marges natives et variantes de pieds de page vide, court, HTML long, société et hook. Les tests couvrent les refus de droits/entité, les relations multiples, le contrôle validé, les comptes rendus sans fichier, les prix absents/zéro, les archives sans reçus et la restauration après échec. Ils ne remplacent pas un essai de la transaction native complète sur une base MariaDB/MySQL ni la vérification navigateur sur le code déployé.

Une suite PHPUnit équivalente est fournie dans le répertoire test/phpunit. Les tests d'installation, de droits, de Multicompany et de documents nécessitent une instance Dolibarr configurée.

## Hors périmètre de cette version

Le module constitue une aide documentaire de conformité : il ne réalise aucun contrôle et ne produit aucun rapport officiel. Les contrôles détaillés des extincteurs, appareils sous pression, accessoires de levage et fluides frigorigènes ne sont pas préconfigurés dans cette version. L’export des parcours GPS QUARTIX, l’écoconduite, modifications des affectations par QUARTIX, cartes grises, sinistres, primes et franchises, gestion commerciale des factures d’achat et contraventions restent hors périmètre. Les factures liées utilisent la gestion native Dolibarr.

## Licence

Copyright © 2026 Pierre Ardoin. Ce module est distribué sous licence GNU General Public License version 3 ou ultérieure.

### Mise à jour des véhicules par import

Dans l’assistant natif, sélectionner l’immatriculation ou le VIN comme clé de mise à jour. Si plusieurs clés sont choisies, elles doivent toutes correspondre au même véhicule de l’entité courante. Chaque clé doit être associée et renseignée. Sans correspondance, une création est tentée ; une correspondance ambiguë est refusée. Les cellules vides et champs non associés conservent les données existantes. Le statut existant est conservé. La simulation est annulée par la transaction native ; les bilans distinguent insertions et mises à jour.

Les capacités sont proposées dans le profil véhicules à partir des consommables actifs du dictionnaire accessible : carburants, électricité en kWh, hydrogène, gaz et fluides, y compris les consommables personnalisés associés à une énergie. Chaque colonne affiche son unité. Les identifiants de correspondance utilisent le code et l’unité du dictionnaire, par exemple `t.capacity_DIESEL_L` et `t.capacity_ELECTRICITY_KWH`, sans dépendre des identifiants numériques d’une installation. Ces champs sont enregistrés dans la table de capacités existante par le hook natif `ImportInsert` et les méthodes métier du véhicule.

Saisir uniquement un nombre positif, avec virgule ou point décimal, dans l’unité affichée. Une cellule vide ou non associée conserve la capacité ; **0 efface explicitement cette capacité**. Les autres champs du véhicule ne sont pas effaçables par cet import. Une capacité incompatible avec l’énergie effective du véhicule, un consommable indisponible ou une correspondance ambiguë provoquent une erreur de ligne avant écriture. Aucune conversion automatique entre kg et m³ n’est appliquée.

Le véhicule et ses capacités sont enregistrés dans la même transaction. La simulation ne déclenche aucun événement et annule les écritures. L’import final normal émet un seul événement CRUD après enregistrement des capacités ; le mode rapide natif, lorsqu’il est proposé par Dolibarr, conserve sa désactivation des actions automatiques. Après déploiement, relancer l’assistant ou télécharger un nouveau modèle pour disposer des colonnes de capacité et compléter la correspondance d’un ancien profil. Aucune migration SQL supplémentaire n’est nécessaire.


### Import natif des pleins et recharges

Depuis **Outils → Nouvel import**, choisir **Pleins / recharges — historique sans OD**. Seul cet accès est conservé : l’ancienne page CSV et son bouton sont retirés. Relancer l’assistant après déploiement ; aucune migration SQL spécifique n’est nécessaire.

Associer les colonnes avec les lecteurs CSV ou XLSX natifs. À la création : véhicule (référence ou immatriculation), code du consommable actif, date/heure, kilométrage et quantité. Le montant TTC suit les règles de la consommation ; un prix inconnu reste vide pour un additif. Dates texte : `YYYY-MM-DD HH:MM:SS` (date seule acceptée à minuit) ; une cellule Excel de type date conserve son heure. Les nombres acceptent le point ou la virgule décimale, sans unité. Conducteur : login ; son absence utilise l’auteur à la création. Description, référence d’huile, qualification et motif du relevé sont facultatifs sauf exigence métier.

En création seule, une consommation existante est refusée. En mise à jour, sélectionner **la référence seule**, ou **les trois clés véhicule + date/heure + consommable**. Les clés partielles et correspondances ambiguës sont refusées. Pour modifier une clé, sélectionner la référence seule. Une cellule vide ou non associée conserve la valeur existante ; zéro est une valeur, jamais une demande d’effacement. Les lignes identiques ne produisent ni écriture ni événement et ne sont pas comptées comme insertions ou mises à jour.

Cet import historique ne crée aucune OD, écriture bancaire ou pièce justificative et ne change aucun réglage global. Une consommation liée à une OD peut être reconnue comme identique mais ne peut pas être modifiée par import : utiliser sa fiche et le parcours de paiement existant. Le kilométrage n’est jamais estimé à partir de QUARTIX ou des lignes voisines.

Le droit général de lecture du module et le droit d’import des consommations sont requis ; le mode mise à jour exige aussi le droit de modification, y compris pour un administrateur. Véhicules et consommations doivent appartenir à l’entité courante. Les relations sont vérifiées dans leur périmètre accessible. Consommation et relevé sont enregistrés dans une transaction, avec verrouillage des véhicules dans un ordre stable. La simulation est annulée par Dolibarr sans triggers ; le mode rapide natif conserve la suppression des actions automatiques.

Validation autonome : `php test/run_consumption_native_import.php`. Le test `php test/run_consumption_prices.php <Dolibarr htdocs>` vérifie également la relation vers le consommable avec le véritable validateur natif et sa table de dictionnaire. En cas d’erreur « Fetch non appelable sur la classe », déployer la correction du dictionnaire puis relancer la simulation ; aucune modification du CSV ni migration SQL n’est nécessaire. La préparation du classeur utilisateur et l’import réel restent une étape ultérieure.
