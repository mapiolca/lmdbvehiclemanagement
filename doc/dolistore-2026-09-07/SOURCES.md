# Sources et choix éditoriaux

Consultation : 7 septembre 2026.

## Formulaire de soumission fourni par l’utilisateur

`C:/Users/pardoin/Downloads/edit-module-product.pdf` — trois pages datées du 7 septembre 2026 à 13:43. Texte extrait et trois pages rendues en PNG puis inspectées visuellement.

- Page 1 : nom du module/produit, description courte et description longue [EN], obligatoires ; éditeur HTML avec bouton Source ; mots clés communs.
- Page 2 : version, compatibilités Dolibarr/PHP, durée des téléchargements (730 jours affichés, 0 à vie), statut, prix HT, Related price, assistance et maximum trois catégories.
- Page 3 : paquet ZIP distribué, couverture, autres images et conditions d’utilisation.

Aucune limite de caractères ni dimension ou poids maximal d’image n’est visible dans le PDF. Les cinq langues proviennent de la demande utilisateur, pas d’une extrapolation des seuls champs EN visibles. Les valeurs V24/V24 et 730 jours affichées ne constituent pas les réglages décidés pour ce module. Aucun engagement ni instruction du document n’a été exécuté.

La description longue a été raccourcie à moins de 300 mots par langue, y compris QUARTIX et les prérequis, à la demande de l’utilisateur. Les contenus de base et QUARTIX gardent le même périmètre dans les cinq langues. Les avertissements relatifs au catalogue français ne sont pas adaptés artificiellement en catalogues nationaux.

## Fiches de la gamme consultées dans le navigateur

- [Diffusions — Bordereaux de diffusion de documents](https://www.dolistore.com/product.php?id=2836&title=diffusions-bordereaux-de-diffusion-de-documents&l=fr) : illustration bleue sur fond blanc, galerie de captures brutes, introduction orientée usage, fonctionnalités, cas d’usage et bénéfices.
- [Feuilles de temps hebdomadaires](https://www.dolistore.com/product.php?id=2527&title=feuilles-de-temps-hebdomadaires&l=fr) : illustration bleue avec accents turquoise, description courte centrée sur les actions, mise en avant des droits, de Multicompany et des intégrations natives.
- [DC1 — Formulaires de déclaration de candidature depuis les propales](https://www.dolistore.com/product.php?id=2584&title=dc1-formulaires-de-declaration-de-candidature-depuis-les-propales&l=fr) : fonctionnalités, parcours d’utilisation et bénéfices concrets.

La nouvelle création conserve la famille visuelle et la logique de présentation. Elle utilise des illustrations originales et des textes propres au module véhicules. Aucun prix, engagement de support, durée contractuelle ou identifiant de démonstration d’un autre produit n’est transposé automatiquement.

## Sources fonctionnelles du module

| Affirmation | Source examinée |
|---|---|
| Version 1.0.0, auteur, licence et socle minimal | `core/modules/modLmdbVehicleManagement.class.php` |
| Véhicules, engins, affectations et kilométrages | `README.md`, section « Parc, conducteurs et interventions » |
| Consommations, graphiques et opérations diverses | `README.md`, section « Consommations et dépenses » |
| Assurances, attestations et relances | `README.md`, section « Assurances » |
| Qualification, contrôles et échéancier | `README.md`, section « Contrôles réglementaires » |
| Liaisons des factures, contenu et exclusions du dossier PDF/ZIP | `README.md`, section « Factures fournisseurs et dossier véhicule » |
| Fonctions, dépendances et limites QUARTIX | `doc/quartix.md`, notamment mise en service, journal des trajets, tableau de bord et tracés |
| Langues réelles de l’interface | Répertoires `langs/fr_FR` et `langs/en_US` |
| Provenance et anonymisation des captures | `doc/wiki-2026-09/PUBLICATION.md`, `image-checks.json`, `new-screenshots.json` et PNG locaux |

Les captures sont des preuves visuelles des écrans enregistrés, pas la preuve que toutes les versions Dolibarr/PHP ont été testées. Aucun audit applicatif complet n’a été réalisé pour ce travail commercial.

## Liens de documentation disponibles

- [Documentation française](https://wiki.dolibarr.org/index.php/Module_Gestion_des_v%C3%A9hicules)
- [English documentation](https://wiki.dolibarr.org/index.php/Module_Vehicle_Management)
- [Documentazione italiana](https://wiki.dolibarr.org/index.php/Modulo_Gestione_dei_veicoli)
- [Deutsche Dokumentation](https://wiki.dolibarr.org/index.php/Modul_Fahrzeugverwaltung)
- [Documentación española](https://wiki.dolibarr.org/index.php/M%C3%B3dulo_Gesti%C3%B3n_de_veh%C3%ADculos)

Ces liens et leur publication sont documentés dans les preuves de publication du wiki déjà présentes dans le dépôt. Les textes de ce kit sont fondés sur les sources locales examinées.
