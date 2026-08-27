# Gestion des véhicules pour Dolibarr

`lmdbvehiclemanagement` fournit un dossier véhicule multientité intégré à Dolibarr. La version `0.1.0` couvre les véhicules, leurs affectations, les relevés kilométriques et les événements métier.

## Compatibilité

- Dolibarr 20 ou supérieur
- PHP 8.0 ou supérieur
- MySQL ou MariaDB
- Module Multicompany facultatif
- Module Ressources facultatif pour la liaison `fk_resource`

## Fonctionnalités de la version 0.1.0

- fiche véhicule et statut de cycle de vie ;
- plusieurs conducteurs simultanés, avec une seule affectation principale sur une période donnée ;
- relevés kilométriques contrôlés, avec correction et remplacement de compteur explicitement qualifiés ;
- événements véhicule (incident, panne, entretien ou autre événement opérationnel) ;
- chronologie consolidée sans table d'historique dupliquée ;
- documents natifs Dolibarr sur les véhicules et les événements ;
- événements Agenda natifs liés au véhicule, conservés séparément des événements métier ;
- modèles de numérotation distincts pour les véhicules et les événements ;
- droits granulaires contrôlés directement avec la méthode native `$user->hasRight()` ;
- partage Multicompany configurable des véhicules et de leur numérotation.

## Installation

Copier le répertoire `lmdbvehiclemanagement` dans le répertoire des modules externes de Dolibarr, puis activer **Gestion des véhicules** depuis la liste des modules. Les écrans sont ajoutés sous le menu **Outils** ; aucun menu haut supplémentaire n'est créé.

Les réglages, la compatibilité détectée et les métadonnées du module sont accessibles depuis l'unique roue dentée du module.

## Vérification locale

Les règles indépendantes de la base peuvent être vérifiées avec la commande suivante :

    php test/run_business_rules.php

Une suite PHPUnit équivalente est fournie dans le répertoire test/phpunit. Les tests d'installation, de droits, de Multicompany et de documents nécessitent une instance Dolibarr configurée.

## Hors périmètre de cette version

Quartix, l'export ZIP, les cartes grises, les assurances, les contrôles techniques, les factures, le carburant et les contraventions seront ajoutés dans des lots ultérieurs. Aucun champ fournisseur n'est stocké dans le modèle métier courant.

## Licence

Copyright © 2026 Pierre Ardoin. Ce module est distribué sous licence GNU General Public License version 3 ou ultérieure.
