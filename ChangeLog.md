# Historique des versions

## `0.1.1` — 2026-08-27

- Masquage de l’état dans le formulaire de création, le statut `Brouillon` restant imposé côté serveur selon le parcours natif Dolibarr.

## `0.1.0` — 2026-08-27

- Initialisation du module externe `lmdbvehiclemanagement` compatible Dolibarr v20+ et PHP 8.0+.
- Ajout des dossiers véhicule avec les états `Brouillon`, `Validé`, `En service`, `Hors service` et `Cédé/Vendu`, ainsi qu’une migration conservatrice des anciens états et des énergies libres.
- Ajout de la chronologie consolidée, des documents et événements Agenda natifs, des droits granulaires et des modèles de numérotation protégés contre les créations concurrentes.
- Ajout du dictionnaire d'énergies Multicompany préchargé avec la nomenclature P.3 et exploité par un Select2 natif.
- Ajout des profils natifs d'import/export, des permissions dédiées contrôlées avec `$user->hasRight()` et du partage Multicompany configurable.
- Ajout des réglages, de la page de compatibilité et de l'onglet À propos, avec masquage du champ technique de modèle PDF sur la fiche véhicule.
