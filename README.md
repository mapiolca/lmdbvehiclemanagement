# Gestion des véhicules et engins pour Dolibarr

`lmdbvehiclemanagement` fournit un parc multientité intégré à Dolibarr. La version `0.14.1` couvre les véhicules routiers, utilitaires et engins, leurs affectations, kilométrages, consommations, assurances et dossiers documentaires de contrôles réglementaires.

## Compatibilité

- Dolibarr 20 ou supérieur
- PHP 8.0 ou supérieur
- MySQL ou MariaDB
- Module Multicompany facultatif
- Module Ressources facultatif pour la liaison `fk_resource`
- Modules Agenda et Travaux planifiés recommandés pour les échéances et relances automatiques

## Fonctionnalités de la version 0.14.1

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
- droits granulaires distincts pour lire, créer/modifier, mettre en ou hors service, supprimer, exporter et importer, avec contrôle métier centralisé : élévation des administrateurs, périmètre des administrateurs Multicompany et recours aux droits natifs `$user->hasRight()` pour les utilisateurs standards ;
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

## Vérification locale

Les règles indépendantes de la base peuvent être vérifiées avec la commande suivante :

    php test/run_business_rules.php
    php test/run_agenda_contracts.php
    php test/run_regulatory_contracts.php
    php test/run_ui_contracts.php

Une suite PHPUnit équivalente est fournie dans le répertoire test/phpunit. Les tests d'installation, de droits, de Multicompany et de documents nécessitent une instance Dolibarr configurée.

## Hors périmètre de cette version

Le module constitue une aide documentaire de conformité : il ne réalise aucun contrôle et ne produit aucun rapport officiel. Les contrôles détaillés des extincteurs, appareils sous pression, accessoires de levage et fluides frigorigènes ne sont pas préconfigurés dans cette version. Quartix, l’export ZIP, les cartes grises, les sinistres, les primes et franchises, les factures d’achat et les contraventions restent hors périmètre.

## Licence

Copyright © 2026 Pierre Ardoin. Ce module est distribué sous licence GNU General Public License version 3 ou ultérieure.
