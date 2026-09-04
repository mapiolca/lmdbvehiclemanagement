# Gestion des véhicules et engins pour Dolibarr

`lmdbvehiclemanagement` fournit un parc multientité intégré à Dolibarr. La version `0.15.0` couvre les véhicules routiers, utilitaires et engins, leurs affectations, kilométrages, consommations, assurances et dossiers documentaires de contrôles réglementaires.

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

## Fonctionnalités de la version 0.15.0

- fiche véhicule ou engin avec immatriculation facultative et cycle de vie `Brouillon` → `Validé` → `En service` / `Hors service` → `Cédé/Vendu` ;
- type de matériel, catégorie européenne, genre national, PTAC/PTRA, places, territoire réglementaire, date de construction, première immatriculation et mise en service ;
- questionnaire guidé de qualification réglementaire, avec profils routiers déduits des caractéristiques, réponses spécialisées historisées par véhicule et profils personnalisés ajoutables manuellement ; aucune échéance n’est inventée tant qu’une réponse ou une date indispensable manque ;
- catalogue français versionné couvrant contrôles routiers, pollution N1 et ses exemptions, poids lourds, transport en commun, catégorie L et son régime transitoire, taxi/VTC, sanitaire, auto-école, dépannage, transport public particulier, VGP à 3/6/12 mois, mise ou remise en service, tachygraphe, ADR et ATP ; les groupes d’obligations et priorités empêchent les règles concurrentes ;
- objet documentaire `Contrôle réglementaire` numéroté `CTLyyMM-NNNN`, avec organisme lié à un tiers, résultat simplifié, dates officielle/calculée/retenue, justificatif obligatoire avant validation et immutabilité du contrôle validé ;
- annulation motivée et contrôle de remplacement, archivage manuel limité aux contrôles annulés ou remplacés, contre-visite distincte, dérogation temporaire motivée, blocage configurable des nouvelles affectations et mises en service ;
- échéancier global, synthèse par matériel, listes Dolibarr natives, export des contrôles, registre de sécurité et import de brouillons ;
- événement Agenda planifié unique par exigence, avec code compatible Dolibarr v20, et travail planifié quotidien idempotent pour les recalculs et rappels par modèle d’email Dolibarr, avec option de rappel journalier après l’échéance, sujet UTF-8 et résolution traçable des adresses réelles ; les lancements manuels depuis les Travaux planifiés forcent explicitement les emails dus sans modifier la limite quotidienne des exécutions automatiques ;
- énergie sélectionnée dans un Select2 alimenté par un dictionnaire configurable, initialisé avec la [nomenclature réglementaire française P.3](https://www.legifrance.gouv.fr/loda/article_lc/LEGIARTI000049860492/2026-07-27) ;
- plusieurs conducteurs simultanés, avec une seule affectation principale sur une période donnée ;
- relevés kilométriques contrôlés, avec correction et remplacement de compteur explicitement qualifiés, et différence calculée entre deux relevés successifs ;
- événements véhicule (incident, panne, entretien ou autre événement opérationnel) ;
- chronologie consolidée sans table d'historique dupliquée ;
- chronologie triée du plus récent au plus ancien par défaut, incluant les pleins/recharges et proposant filtres SQL, recherche, tri et pagination par colonne ;
- documents natifs Dolibarr sur les véhicules et les événements ;
- journalisation Agenda native et traduite des créations, modifications, transitions et suppressions des sept objets métier, avec des titres formulés comme des actions compréhensibles et une configuration par entité respectant les choix administrateur ;
- codes Agenda compatibles avec la limite native de 50 caractères, y compris pour les attestations d’assurance ;
- modèles de numérotation distincts pour les véhicules et engins, événements, contrats d’assurance, consommations et contrôles réglementaires ;
- modèle de référence véhicule basé sur l’immatriculation, activable après précontrôle et migration transactionnelle des références, documents et index ECM ;
- droits granulaires distincts pour lire, créer/modifier, mettre en ou hors service, supprimer, exporter et importer, contrôlés directement avec la méthode native `$user->hasRight()` ;
- profils natifs Dolibarr d’import et d’export des véhicules, avec création des lignes importées par l’objet métier et sans effet Agenda pendant la simulation ;
- partage Multicompany configurable des véhicules, de leur numérotation et du dictionnaire des énergies.
- contrats d’assurance individuels ou de flotte, avec une couverture principale et des couvertures complémentaires ;
- résumé du contrat principal directement dans la fiche véhicule avec lien vers sa fiche native ; boutons natifs de liaison et de création lorsqu’aucun contrat n’est rattaché ;
- attestations communes ou propres à un véhicule, soumises à validation avant prise en compte ;
- justificatifs PDF, JPEG ou PNG contrôlés côté serveur, avec suppression des métadonnées EXIF/GPS des images ;
- référents assurance configurables par utilisateur ou groupe et personnels affectés éligibles selon leur type d’affectation ;
- relances quotidiennes configurables avant et après échéance au moyen des modèles d’emails et travaux planifiés natifs Dolibarr.
- menu haut dédié **Gestion des véhicules et engins**, avec des sections séparées pour le parc, les contrôles réglementaires, les consommations et les contrats d’assurance ;
- fiche autonome et liste native des contrats d’assurance ; l’ancien parcours modal redirige vers l’onglet `Attestations` de la fiche contrat.
- pictogramme véhicule natif dans le menu haut, déclaré aussi par la constante d’icône attendue par les thèmes Dolibarr afin d’éviter leur pictogramme générique de repli ;
- courtier filtré dynamiquement sur l’assureur et validation native des champs obligatoires du contrat.
- fiche contrat avec actions positionnées sous ses deux colonnes et statut affiché uniquement dans la bannière native.
- blocs transverses natifs « Fichiers joints », « Objets liés » et « Les X derniers événements » sous les actions de la fiche contrat.
- onglets natifs « Fiche », « Contacts/Adresses », « Notes », « Fichiers joints » et « Événements/Agenda » sur les contrats d’assurance, avec compteurs et bannière commune.
- boutons de transition du contrat rendus avec la classe d’action native Dolibarr, sans dimensionnement CSS spécifique.
- objet `Plein / Recharge` pour les carburants, recharges électriques, hydrogène et additifs, avec unités et devise figées historiquement ;
- prix facultatif pour les additifs : un montant vide reste inconnu et est exclu des moyennes, pics et courbes de prix, tandis qu’un zéro explicite est un prix connu ; toutes les quantités et fréquences restent comptées. Le prix moyen pondéré utilise uniquement les quantités valorisées, et le coût cumulé reste non défini si aucun prix n’est connu ;
- option par entité créant une opération diverse native au débit pour chaque plein ou recharge, à partir du compte bancaire, du mode de règlement et des comptes comptables configurés ;
- comptes général et auxiliaire saisis librement et facultatifs sans comptabilité ou en comptabilité simplifiée ; en partie double, compte général obligatoire du plan comptable actif et compte auxiliaire facultatif via les composants natifs ; les valeurs sont conservées lors d’un changement de module comptable et revalidées avant les prochaines créations, sans modification des OD historiques ;
- ticket PDF, JPEG ou PNG obligatoire lorsque l’option est active, stocké uniquement dans les documents natifs de l’opération diverse, avec contrôle MIME et suppression des métadonnées EXIF/GPS ;
- projet natif facultatif, synchronisation de la date, du libellé et du montant jusqu’au rapprochement ou au transfert comptable, puis verrouillage des données financières, du justificatif et de la suppression ;
- import CSV des pleins et recharges refusé lorsque la gestion des tickets est active, sans reprise automatique des consommations historiques ;
- ajout rapide natif `Plein / Recharge` dans le menu `+` de Dolibarr pour les utilisateurs disposant du droit de création ;
- tooltips Ajax natifs et traduits sur les liens `getNomUrl()` des véhicules, affectations, relevés kilométriques, événements, consommations, contrats et attestations d’assurance ;
- véhicules des tableaux de consommation affichés avec leur lien natif `getNomUrl()` et leur tooltip Ajax, sans requête SQL supplémentaire par ligne ;
- formulaires de création et d’édition adaptés aux écrans mobiles, avec champs éditables à 16 px pour éviter le zoom automatique d’iOS, contrôles fluides et actions tactiles, sans désactiver le zoom utilisateur ;
- résolution Ajax dédiée des véhicules conservant le droit général de lecture et le périmètre Multicompany natif ;
- affichage des badges, colonnes et filtres Environnement uniquement lorsqu’un partage donne réellement accès à plusieurs entités ;
- synchronisation transactionnelle entre chaque consommation et son relevé kilométrique source, y compris lors d’une modification, d’un changement de véhicule ou d’une suppression ;
- dictionnaire Multicompany des consommables et table normalisée de compatibilité avec les 46 codes énergie P.3 ;
- capacités configurables par véhicule et par consommable, autonomie WLTP et avertissement non bloquant au-delà de 100 % de capacité ;
- synthèses globale et par véhicule, séparées par consommable et unité, avec moyennes, coûts, distances, pics et graphiques natifs `DolGraph` ;
- liste native filtrable et paginée, export Dolibarr et import CSV sécurisé passant par l’objet métier.

## Installation

Copier le répertoire `lmdbvehiclemanagement` dans le répertoire des modules externes de Dolibarr, puis activer **Gestion des véhicules et engins** depuis la liste des modules. Une réactivation conservatrice initialise les dictionnaires et règles absents sans remplacer les réglages, choix Agenda, modèles, crons ou partages existants.

Les réglages, la compatibilité détectée et les métadonnées du module sont accessibles depuis l'unique roue dentée du module.

Après la mise à jour autorisant les prix d’additif facultatifs, désactiver puis réactiver le module pour appliquer la migration idempotente de `total_ttc` vers une colonne nullable. Les montants historiques, y compris les zéros, et les réglages sont conservés. Une ancienne recharge peut ensuite être corrigée en vidant son montant. Les imports gardent la colonne `total_ttc`, avec cellule vide autorisée pour les additifs ; les exports natifs distinguent une cellule vide d’un zéro. Les carburants et leurs OD conservent leurs règles existantes.

## Vérification locale

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

Le module constitue une aide documentaire de conformité : il ne réalise aucun contrôle et ne produit aucun rapport officiel. Les contrôles détaillés des extincteurs, appareils sous pression, accessoires de levage et fluides frigorigènes ne sont pas préconfigurés dans cette version. Quartix, les cartes grises, les sinistres, les primes et franchises, la gestion commerciale des factures d’achat et les contraventions restent hors périmètre. Les factures liées utilisent la gestion native Dolibarr.

## Licence

Copyright © 2026 Pierre Ardoin. Ce module est distribué sous licence GNU General Public License version 3 ou ultérieure.
