# Historique des versions

## `0.7.0` — 2026-08-31

- Ajout du modèle natif de référence véhicule par immatriculation, avec migration transactionnelle réversible des références, documents et index ECM.
- Remplacement du parcours modal des assurances par les fiches et onglets natifs, ajout des actions `Lier` et `Créer`, et déplacement du cycle de vie des attestations dans l’onglet dédié.
- Ajout de l’objet `Plein / Recharge`, de ses droits, de sa numérotation, de son dictionnaire de consommables compatible avec les 46 codes P.3 et de la synchronisation transactionnelle avec les relevés kilométriques.
- Ajout des capacités par véhicule et consommable, de l’autonomie WLTP, des listes/imports/exports et des synthèses séparées par unité avec graphiques `DolGraph`.
- Ajout de la navigation Consommation, de l’onglet véhicule et des fiches natives avec Notes, Fichiers joints, Événements/Agenda et blocs transverses.

## `0.6.0` — 2026-08-31

- Ajout des onglets natifs « Fiche », « Contacts/Adresses », « Notes », « Fichiers joints » et « Événements/Agenda » à la fiche contrat d’assurance.
- Ajout des notes publiques et privées, des rôles de contacts internes/externes et de leur migration idempotente sur les installations existantes.
- Ajout des compteurs d’onglets, du filtrage Agenda selon les droits et de la bannière commune sur chaque onglet.
- Uniformisation des boutons de transition au format d’action natif Dolibarr, tout en conservant les mutations POST et les tokens CSRF.

## `0.5.0` — 2026-08-31

- Ajout sous les actions de la fiche contrat des blocs natifs Dolibarr « Fichiers joints », « Objets liés » et « Les X derniers événements ».
- Respect du répertoire documentaire de l’entité propriétaire et des droits natifs Agenda lors de l’affichage de ces blocs.

## `0.4.3` — 2026-08-31

- Repositionnement des boutons d’action de la fiche contrat sous les deux colonnes selon la disposition native Dolibarr.
- Suppression de la ligne d’état redondante dans les informations du contrat ; le badge de statut reste visible dans la bannière native.

## `0.4.2` — 2026-08-31

- Déclaration de l’icône Font Awesome du module dans la constante native utilisée par les thèmes Dolibarr, afin que le menu haut affiche le véhicule au lieu du pictogramme générique de repli.

## `0.4.1` — 2026-08-28

- Correction du pictogramme du menu haut avec l’icône Font Awesome rendue par le helper natif Dolibarr.
- Filtrage du champ Courtier sur les seuls contacts du tiers assureur sélectionné, au moyen du rafraîchissement Ajax natif Dolibarr.
- Alignement des champs obligatoires et des couleurs de saisie sur le thème natif, sans classe d’erreur appliquée aux contrôles.
- Remplacement du contrôle obligatoire spécifique par la validation de champs fournie par `CommonObject`.

## `0.4.0` — 2026-08-27

- Ajout d’un menu haut dédié « Gestion des véhicules », structuré en sections Véhicules et Contrats d’assurance avec leurs accès de création et de liste.
- Ajout d’une fiche autonome et d’une liste native Dolibarr pour les contrats, avec filtres SQL, tri, pagination, statuts, rattachements et environnement Multicompany.
- Correction des transactions de création de contrats et d’attestations afin qu’un trigger natif retournant `0` valide correctement le commit.
- Mutualisation du formulaire d’assurance entre la fiche et la modale, avec validation serveur, styles obligatoires natifs et conservation des saisies en erreur.
- Remplacement des alertes JavaScript génériques de la modale par des messages d’erreur au rendu Dolibarr et un marquage des contrôles concernés.

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
