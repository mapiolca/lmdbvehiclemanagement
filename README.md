# Gestion des véhicules et engins pour Dolibarr

`lmdbvehiclemanagement` fournit un parc multientité intégré à Dolibarr. La version `1.0.0` couvre les véhicules routiers, utilitaires et engins, leurs affectations, kilométrages, consommations, assurances et contrôles réglementaires, ainsi que les factures fournisseurs liées et le dossier véhicule PDF/ZIP.

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
- Événements d’entretien, panne et incident ; chronologie consolidée incluant contrôles et consommations, avec filtres SQL, tri et pagination.
- Références par modèles de numérotation, dont un modèle par immatriculation avec migration contrôlée des références et documents.

### Consommations et dépenses

- Pleins, recharges électriques, hydrogène et additifs ; dictionnaire des consommables compatible avec les énergies P.3, capacités par véhicule et avertissement de dépassement.
- Synchronisation transactionnelle avec les relevés kilométriques ; synthèses par véhicule, consommable et unité, coûts, distances et graphiques natifs DolGraph.
- Prix facultatif des additifs : une valeur absente est exclue des statistiques de prix, tandis qu’un zéro reste un prix connu. Les quantités et fréquences restent comptabilisées.
- Option par entité de création d’opérations diverses avec compte bancaire, règlement, projet et ticket PDF/JPEG/PNG obligatoire. Les images sont nettoyées de leurs métadonnées.
- Comptabilité facultative : comptes libres sans comptabilité ou en mode simplifié ; compte général du plan actif obligatoire en partie double. Données financières et ticket verrouillés après rapprochement ou transfert comptable.
- Import CSV et export natif ; import des consommations refusé lorsque l’option d’opérations diverses est active, sans création rétroactive pour l’historique.

### Assurances

- Contrats individuels ou de flotte, couverture principale et couvertures complémentaires, contacts de l’assureur et courtier, rattachements à plusieurs véhicules.
- Attestations communes ou propres à un véhicule, justificatifs contrôlés, validation, rejet et archivage ; référents utilisateurs/groupes et personnels affectés éligibles.
- Relances avant et après échéance par modèles d’emails et travaux planifiés natifs, avec prévention des doublons.

### Contrôles réglementaires

- Questionnaire guidé et historisé, profils réglementaires, catalogue français versionné, surcharges d’entité auditées et règles personnalisées.
- Contrôles routiers, pollution, catégorie L, usages spécialisés, VGP, mise ou remise en service, tachygraphe, ADR et ATP ; aucune échéance inventée lorsqu’une donnée indispensable manque.
- Contrôles numérotés avec organisme, résultat, dates et justificatif obligatoire ; validation immuable, annulation motivée, remplacement, contre-visite et archivage encadré.
- Échéancier, registre de sécurité, export et import en brouillon ; blocage configurable des nouvelles affectations et mises en service.
- Travail planifié quotidien de recalcul et de rappel, événement Agenda unique par exigence et rappels journaliers facultatifs après échéance. Un lancement manuel peut rejouer les emails dus sans consommer la limite automatique.

### Factures, documents et intégration native

- Création et liaison réciproque de factures fournisseurs aux événements et contrôles ; dossier véhicule PDF/ZIP décrit ci-dessus.
- Notes, contacts, fichiers joints, objets liés, Agenda CRUD traduit, modèles de numérotation, imports/exports et droits granulaires natifs.
- Listes avec colonnes personnalisables, filtres, badges de statut et tooltips Ajax ; formulaires adaptés aux écrans mobiles.
- Partages Multicompany configurables, documents dans l’entité propriétaire, filtres Environnement lorsqu’un partage est actif et conservation des réglages à la réactivation.

## Installation et mise à jour

Copier le répertoire `lmdbvehiclemanagement` dans le répertoire des modules externes de Dolibarr, puis activer **Gestion des véhicules et engins** depuis la liste des modules. Une réactivation conservatrice initialise les dictionnaires et règles absents sans remplacer les réglages, choix Agenda, modèles, crons ou partages existants.

Les réglages, la compatibilité détectée et les métadonnées du module sont accessibles depuis l'unique roue dentée du module.

Pour une mise à jour depuis une version de développement, consulter [ChangeLog.md](ChangeLog.md), puis désactiver et réactiver le module pour appliquer la migration idempotente de `total_ttc` vers une colonne nullable. Les montants historiques, y compris les zéros, et les réglages sont conservés. Une ancienne recharge peut ensuite être corrigée en vidant son montant. Les imports gardent la colonne `total_ttc`, avec cellule vide autorisée pour les additifs ; les exports natifs distinguent une cellule vide d’un zéro. Les carburants et leurs OD conservent leurs règles existantes.

## Vérification locale

L'intégration QUARTIX est documentée dans [le guide de configuration et de validation](doc/quartix.md) : connexion par environnement, kilométrage estimé quotidien, dernière position protégée par un droit GPS et utilisation par véhicule. Elle nécessite une réactivation du module après déploiement et l'activation des trois travaux planifiés natifs. La version reste 1.0.0 pendant ce développement.

Ses tests hors ligne utilisent les objets Dolibarr et une base en mémoire, sans accès à QUARTIX :

    php test/run_quartix.php /chemin/vers/dolibarr/htdocs

Les règles indépendantes de la base peuvent être vérifiées avec la commande suivante :

    php test/run_business_rules.php
    php test/run_agenda_contracts.php
    php test/run_regulatory_contracts.php
    php test/run_ui_contracts.php
    php test/run_consumption_od_contracts.php

Les tests comportementaux des réglages OD utilisent les classes natives depuis une installation ou un checkout Dolibarr, sans connexion à la base ni écriture métier :

    php test/run_consumption_od_settings.php /chemin/vers/dolibarr/htdocs

Les prix facultatifs disposent de tests comportementaux utilisant les classes natives de normalisation, stockage et export CSV, ainsi que des doubles de base et de graphique sans écriture métier :

    php test/run_consumption_prices.php /chemin/vers/dolibarr/htdocs

Les factures liées et le dossier disposent de tests comportementaux utilisant les classes natives, un double transactionnel de base et les véritables moteurs PDF/ZIP :

    php -d extension=zip test/run_invoice_dossier.php /chemin/vers/dolibarr/htdocs

Définir `LMDB_DOSSIER_TEST_ROOT` vers un répertoire temporaire hors du code pour recevoir les fixtures (à défaut, le script historique utilise le répertoire ignoré `test/.dossier-test/`) : dossier de 621 entrées, tableau de 80 consommations, fichiers homonymes, PDF multipages, en-têtes avec/sans logo, référence longue, marges natives et variantes de pieds de page vide, court, HTML long, société et hook. Les tests couvrent les refus de droits/entité, les relations multiples, le contrôle validé, les comptes rendus sans fichier, les prix absents/zéro, les archives sans reçus et la restauration après échec. Ils ne remplacent pas un essai de la transaction native complète sur une base MariaDB/MySQL ni la vérification navigateur sur le code déployé.

Une suite PHPUnit équivalente est fournie dans le répertoire test/phpunit. Les tests d'installation, de droits, de Multicompany et de documents nécessitent une instance Dolibarr configurée.

## Hors périmètre de cette version

Le module constitue une aide documentaire de conformité : il ne réalise aucun contrôle et ne produit aucun rapport officiel. Les contrôles détaillés des extincteurs, appareils sous pression, accessoires de levage et fluides frigorigènes ne sont pas préconfigurés dans cette version. Les trajets détaillés QUARTIX, cartes intégrées, écoconduite, modifications des affectations par QUARTIX, cartes grises, sinistres, primes et franchises, gestion commerciale des factures d’achat et contraventions restent hors périmètre. Les factures liées utilisent la gestion native Dolibarr.

## Licence

Copyright © 2026 Pierre Ardoin. Ce module est distribué sous licence GNU General Public License version 3 ou ultérieure.
