# AGENT.md — lmdbvehiclemanagement

## Version du référentiel

| Information | Valeur |
|---|---|
| Version | `2026.08.26.1` |
| Date | `2026-08-26` |

## Historique

### `2026.08.26.1` — 2026-08-26

- Création du référentiel local du module.
- Compatibilité minimale Dolibarr v20 et PHP 8.0.
- Périmètre de modification limité à la racine du module `lmdbvehiclemanagement/`.

## Règles locales

- Ne jamais modifier le core Dolibarr.
- Le véhicule est la source de vérité métier ; `fk_resource` reste une liaison facultative.
- Les déclencheurs personnalisés sont limités à `CREATE`, `UPDATE` et `DELETE`.
- Toute table métier et toute requête doivent respecter l'entité Dolibarr.
- Les fichiers utilisateurs sont stockés dans les répertoires documentaires Dolibarr, jamais dans le code du module.
- Ne jamais introduire une dépendance Quartix dans les objets du premier lot.

