# Historique des versions

## `0.3.0` — 2026-08-27

- Ajout des contrats d’assurance individuels et de flotte, avec contrat principal, couvertures complémentaires et rattachements multivéhicules normalisés.
- Ajout du bloc récapitulatif HalfRight et d’une gestion complète en modale native avec repli en page complète.
- Ajout du dépôt, du contrôle, du rejet et de l’archivage des attestations PDF ou image, avec nettoyage des métadonnées et historique synthétique.
- Ajout des droits assurance, des référents utilisateurs/groupes et des personnels affectés éligibles selon les réglages par entité.
- Ajout des modèles d’emails natifs et du travail planifié quotidien de relance, avec seuils configurables et journal d’idempotence.

## `0.2.0` — 2026-08-27

- Repositionnement des boutons d’action sous la fiche avec les conteneurs et helpers natifs Dolibarr.
- Suppression de la ligne d’état redondante dans les informations du véhicule ; le badge de statut reste affiché dans la bannière native.
- Prise en charge de l’éditeur WYSIWYG natif pour la description, avec repli automatique vers un textarea.
- Uniformisation de la bannière native sur tous les onglets du véhicule, y compris le badge d’environnement Multicompany.
- Réorganisation des onglets selon le parcours véhicule : fiche, affectation, kilométrage, historique, notes, fichiers joints, puis événements Agenda.

## `0.1.2` — 2026-08-27

- Alignement du formulaire véhicule sur la validation native Dolibarr : contrôle des champs obligatoires côté serveur et annulation possible sans compléter le formulaire.

## `0.1.1` — 2026-08-27

- Masquage de l’état dans le formulaire de création, le statut `Brouillon` restant imposé côté serveur selon le parcours natif Dolibarr.

## `0.1.0` — 2026-08-27

- Initialisation du module externe `lmdbvehiclemanagement` compatible Dolibarr v20+ et PHP 8.0+.
- Ajout des dossiers véhicule avec les états `Brouillon`, `Validé`, `En service`, `Hors service` et `Cédé/Vendu`, ainsi qu’une migration conservatrice des anciens états et des énergies libres.
- Ajout de la chronologie consolidée, des documents et événements Agenda natifs, des droits granulaires et des modèles de numérotation protégés contre les créations concurrentes.
- Ajout du dictionnaire d'énergies Multicompany préchargé avec la nomenclature P.3 et exploité par un Select2 natif.
- Ajout des profils natifs d'import/export, des permissions dédiées contrôlées avec `$user->hasRight()` et du partage Multicompany configurable.
- Ajout des réglages, de la page de compatibilité et de l'onglet À propos, avec masquage du champ technique de modèle PDF sur la fiche véhicule.
