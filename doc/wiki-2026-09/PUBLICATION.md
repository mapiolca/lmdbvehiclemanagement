# Publication du wiki — 7 septembre 2026

Mise à jour de la notice française et création de quatre notices complètes incluant QUARTIX QWS v2. Publication réalisée sur le wiki Dolibarr avec le compte Mapiolca, à la demande de Pierre Ardoin.

## Articles publiés

| Langue | Article | Révision |
|---|---|---|
| FR | [Module Gestion des véhicules](https://wiki.dolibarr.org/index.php/Module_Gestion_des_v%C3%A9hicules) | 67223 |
| EN | [Module Vehicle Management](https://wiki.dolibarr.org/index.php/Module_Vehicle_Management) | 67224 |
| IT | [Modulo Gestione dei veicoli](https://wiki.dolibarr.org/index.php/Modulo_Gestione_dei_veicoli) | 67225 |
| DE | [Modul Fahrzeugverwaltung](https://wiki.dolibarr.org/index.php/Modul_Fahrzeugverwaltung) | 67226 |
| ES | [Módulo Gestión de vehículos](https://wiki.dolibarr.org/index.php/M%C3%B3dulo_Gesti%C3%B3n_de_veh%C3%ADculos) | 67227 |

La notice française antérieure (révision 67173 du 5 septembre 2026) a été sauvegardée dans `fr.source.mediawiki`. Son contenu a été comparé avec le texte de l’éditeur avant mise à jour. Les fichiers `fr.mediawiki`, `en.mediawiki`, `it.mediawiki`, `de.mediawiki` et `es.mediawiki` correspondent aux textes publiés, à la normalisation des fins de ligne et blancs de fin près.

## Illustrations et confidentialité

Chaque article affiche 22 illustrations :

- 13 anciennes captures remplacées dans les articles par des PNG où toutes les immatriculations visibles, y compris les références de véhicules et l’aperçu PDF, sont floutées ;
- 3 nouvelles captures QUARTIX : utilisation, journal des trajets et modale du tracé ;
- 6 anciennes illustrations réutilisées après contrôle, sans adresse ni immatriculation visible.

Les adresses du journal et l’immatriculation de la modale sont floutées. Le fond de carte est également flouté pour protéger les lieux, avec conservation des commandes de zoom et de l’attribution Leaflet / OpenStreetMap. La capture d’utilisation est recadrée et ne contient ni adresse ni immatriculation.

Les métadonnées sont supprimées des 16 nouveaux PNG. Le script local `anonymize.py`, autorisé explicitement par l’utilisateur, contrôle que les pixels hors zones de floutage restent identiques aux images sources décodées. Les originaux QUARTIX et les planches de contrôle sont conservés uniquement dans le dossier local ignoré `test/.dossier-test/wiki-2026-09/` ; ils ne font pas partie de la publication. Ce dossier local est nécessaire pour régénérer les trois nouvelles captures avec `anonymize.py`.

Les anciens fichiers et les anciennes révisions du wiki n’ont pas été supprimés. L’anonymisation porte sur les illustrations employées par les versions courantes des cinq articles.

## Contrôles réalisés

- Prévisualisation des cinq articles : 22 images chargées, aucune image cassée, six tableaux rendus pour chaque langue.
- Lecture publique via l’API MediaWiki : existence des cinq pages, correspondance des textes, 22 fichiers accessibles et quatre liens réciproques de langue par article.
- Empreintes SHA-1 des 16 fichiers téléversés identiques aux fichiers PNG locaux contrôlés.
- Contrôle visuel des zones floutées et suppression des métadonnées ; chaque fichier respecte la limite de 2 Mio.
- `git diff --check` sans erreur de contenu.
- Aucun code PHP, schéma SQL ou réglage applicatif modifié dans ce travail documentaire. PHPStan et tests applicatifs non applicables.

Les preuves sont dans `image-checks.json`, `published-images-verification.json` et `publication-verification.json`. Les noms et descriptions d’import sont dans `uploads.json`.

## Périmètre fonctionnel documenté

Connexion et test QWS v2, configuration par entité, unités et fuseaux, association des véhicules et gestion des boîtiers, droits GPS et synchronisation, quatre travaux planifiés natifs, kilométrage estimé, synthèses d’utilisation, trajets, cartes, confidentialité, conservation et diagnostic. Le guide précise que les anciennes archives 1.0.0 peuvent ne pas contenir QUARTIX et que les traductions documentaires n’étendent pas les langues du logiciel ni le catalogue réglementaire français.

## Proposition de commit

Titre : `docs: documenter QUARTIX sur le wiki en cinq langues`

Description : compléter la notice française, fournir les notices EN/IT/DE/ES, publier les illustrations anonymisées et conserver les preuves de correspondance des pages et des fichiers. Aucun commit ni push GitHub réalisé dans cette tâche.
