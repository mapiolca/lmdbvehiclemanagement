# Historique des versions

## `0.14.0` — 2026-09-02

- Extension du parc aux véhicules, utilitaires et engins, avec immatriculation facultative, référence de repli `MATyyMM-NNNN` et caractéristiques de qualification réglementaire.
- Ajout des profils réglementaires cumulables, des exigences matérialisées et du catalogue français versionné pour les contrôles routiers, la pollution, les VGP, la mise en service, le tachygraphe, l’ADR et l’ATP, avec règles natives en lecture seule, surcharges d’entité auditées et règles personnalisées.
- Ajout de l’objet documentaire `Contrôle réglementaire` numéroté `CTLyyMM-NNNN`, de son workflow brouillon/validation/annulation/archivage, de ses justificatifs et de ses événements Agenda CRUD natifs.
- Ajout de l’échéancier, des listes et exports natifs, du registre de sécurité, des imports en brouillon, des droits dédiés et de la prise en charge Multicompany ; les sélecteurs de colonnes restent au premier plan, y compris hors des échéanciers courts sans défilement vertical imbriqué, et les échéances dépassées utilisent le badge d’erreur natif.
- Ajout d’un travail planifié quotidien idempotent pour recalculer les échéances, synchroniser leur événement Agenda avec résolution explicite du type natif et repli `AC_OTH_AUTO`, puis envoyer les rappels configurés au moyen d’un modèle d’email Dolibarr, avec option de rappel journalier après l’échéance, objets UTF-8 et destinataires utilisateurs traçables sans doublon d’adresse.

## `0.13.3` — 2026-09-02

- Correction des libellés non traduits des actions « Enregistrer le brouillon », « Rejeter » et « Archiver » dans l’onglet des attestations d’assurance.
- Ajout de clés métier françaises et anglaises stables, indépendantes des variations des catalogues génériques entre versions de Dolibarr.
- Ajout d’un contrôle empêchant la réapparition des clés brutes dans le rendu de l’onglet.

## `0.13.2` — 2026-09-02

- Raccourcissement des codes CRUD des attestations pour respecter la limite native de 50 caractères de `actioncomm.code` après ajout du préfixe `AC_` par Dolibarr.
- Correction de l’échec de création d’une attestation lorsque sa journalisation Agenda automatique est activée.
- Migration idempotente des déclarations `c_action_trigger` vers les nouveaux codes sans créer d’événement supplémentaire.
- Conservation par entité des choix Agenda existants, y compris une désactivation volontaire configurée à `0`.
- Ajout d’un contrôle couvrant la longueur des 21 codes d’événements Agenda générés.

## `0.13.1` — 2026-09-01

- Remplacement des titres Agenda techniques par des phrases métier traduites et spécifiques à chaque objet.
- Distinction des principales transitions : cycle de vie du véhicule, activation ou résiliation d’un contrat et traitement des attestations d’assurance.
- Enrichissement des descriptions avec les véhicules, conducteurs, kilométrages, quantités, périodes et champs modifiés disponibles.
- Conservation de l’auto-création Agenda native, des 21 triggers CRUD et des titres éventuellement fournis explicitement par un traitement métier.
- Application des nouveaux libellés aux futurs événements uniquement, sans réécriture de l’historique Agenda existant.

## `0.13.0` — 2026-09-01

- Déclaration idempotente des 21 événements CRUD des véhicules, affectations, relevés kilométriques, consommations, événements véhicule, contrats et attestations d’assurance dans l’Agenda natif.
- Activation conservatrice des événements Agenda dans chaque entité : les constantes absentes sont activées par défaut, sans modifier un choix administrateur existant, y compris la valeur `0`.
- Ajout de titres et descriptions traduits aux événements, avec référence, motif métier et champs modifiés, afin de conserver un historique intelligible après suppression.
- Passage de l’import natif des véhicules par `LmdbVehicle::create()`, sans trigger pendant la simulation et avec une erreur rapportée par ligne lors de l’import réel.
- Exclusion des migrations techniques de références de la journalisation utilisateur, sans insertion manuelle d’`ActionComm` ni doublon avec le mécanisme Agenda de Dolibarr.

## `0.12.5` — 2026-09-01

- Limitation à deux décimales des quantités de plein, recharge ou additif affichées dans la chronologie véhicule.
- Utilisation du formateur numérique natif Dolibarr sans modifier la précision stockée ni les calculs métier.

## `0.12.4` — 2026-08-31

- Optimisation responsive des formulaires de création et d’édition des véhicules, événements, affectations, kilométrages, consommations, assurances et attestations.
- Utilisation d’une taille minimale de 16 px sur les champs éditables mobiles pour éviter le zoom automatique d’iOS, sans désactiver le zoom utilisateur.
- Adaptation des largeurs Select2, éditeurs, tableaux de champs et boutons d’action aux écrans tactiles étroits.

## `0.12.3` — 2026-08-31

- Affichage des véhicules de la synthèse et de la liste des consommations avec leur lien natif `getNomUrl()` et leur tooltip Ajax.
- Réutilisation des données déjà chargées par les requêtes de liste afin de ne pas ajouter de requête SQL par ligne.

## `0.12.2` — 2026-08-31

- Masquage du badge Environnement sur les fiches lorsque Multicompany est désactivé ou qu’aucune autre entité ne partage l’objet courant.
- Application de la même règle aux colonnes et filtres Environnement des listes de véhicules, événements, consommations et contrats d’assurance.

## `0.12.1` — 2026-08-31

- Traduction des valeurs techniques `fuel` et `additive` dans le champ Nature des tooltips Ajax des pleins et recharges.
- Chargement explicite des catalogues Dolibarr et du module nécessaires aux tooltips génériques et aux contrats d’assurance.

## `0.12.0` — 2026-08-31

- Ajout d’une colonne `Différence` dans l’onglet Kilométrage afin d’afficher la distance entre deux relevés chronologiquement successifs.
- Calcul à la volée depuis les relevés chargés, sans donnée redondante ni migration SQL.
- Affichage des écarts positifs en vert avec un signe `+`, des écarts négatifs en rouge et du premier relevé sans antécédent avec un tiret neutre.

## `0.11.3` — 2026-08-31

- Correction de l’accès au tooltip Ajax des véhicules lorsque le sous-espace de droits `lmdbvehicle` ne contient volontairement que les droits d’écriture et de suppression.
- Conservation du droit général de lecture du module et du périmètre Multicompany du véhicule dans la résolution du tooltip natif.

## `0.11.2` — 2026-08-31

- Interprétation des options vides des filtres de consommation comme une absence de filtre, y compris lorsque les sélecteurs natifs transmettent `-1`.
- Affichage réellement vide par défaut des sélecteurs véhicule, conducteur et consommable.
- Construction de la requête de synthèse uniquement avec les critères explicitement renseignés, tout en conservant la date courante comme borne métier supérieure par défaut.

## `0.11.1` — 2026-08-31

- Conservation de la validation native du navigateur sur les champs obligatoires des événements véhicule.
- Exclusion du bouton `Annuler` de cette validation grâce à l’attribut HTML natif `formnovalidate`.

## `0.11.0` — 2026-08-31

- Ajout des pleins, recharges et additifs à la chronologie consolidée du véhicule.
- Tri par défaut du plus récent au plus ancien avec tri natif sur chaque colonne pertinente.
- Ajout des filtres SQL par période, source, type, libellé, kilométrage, état et présence de documents.
- Conservation des filtres, du tri, de la pagination et de la limite dans les liens natifs de la liste.

## `0.10.1` — 2026-08-31

- Correction de l’URL de l’ajout rapide `Plein / Recharge` avec le helper natif compatible Dolibarr v20+.
- Suppression de la dépendance à la constante inexistante `DOL_URL_ROOT_ALT` qui provoquait une erreur fatale dans le menu haut.

## `0.10.0` — 2026-08-31

- Ajout des tooltips Ajax natifs sur les liens des objets du module.
- Affichage des caractéristiques principales, du statut et du pictogramme propres à chaque objet.
- Résolution native des affectations et relevés kilométriques par leur type d’élément Dolibarr.

## `0.9.0` — 2026-08-31

- Ajout de l’entrée native `Plein / Recharge` au menu d’ajout rapide de Dolibarr.
- Affichage limité aux utilisateurs disposant du droit de création des consommations.

## `0.8.7` — 2026-08-31

- Période de la synthèse des consommations laissée vide par défaut avec ouverture du sélecteur natif sur la date du jour.
- Interprétation des bornes vides comme la première consommation disponible pour le début et la date courante pour la fin.

## `0.8.6` — 2026-08-31

- Vérification et sécurisation des filtres de la synthèse des consommations, notamment des périodes incomplètes et de la remise à zéro native.
- Utilisation de l’utilisateur ayant créé le plein ou la recharge comme conducteur lorsqu’aucun autre conducteur n’est explicitement sélectionné.
- Application du conducteur effectif aux filtres, listes, statistiques et exports, y compris pour les anciennes consommations sans conducteur enregistré.

## `0.8.5` — 2026-08-31

- Encadrement de chaque graphique de consommation dans un tableau Dolibarr distinct et de largeur stable.
- Déplacement du titre dans la ligne d’en-tête native du tableau.
- Conservation du tableau et de son titre en l’absence de données, avec affichage du message natif d’absence d’enregistrement.

## `0.8.4` — 2026-08-31

- Suppression du pictogramme redondant placé devant le titre du bloc d’assurance de la fiche véhicule.
- Espacement du lien vers le contrat avec une classe native Dolibarr.
- Ajout du tooltip Ajax natif au `getNomUrl()` des contrats avec référence, libellé, assureur, police, formule, période, statut et assistance.

## `0.8.3` — 2026-08-31

- Rétablissement de l’actualisation native des listes à la fermeture du sélecteur de colonnes.
- Ajout du marqueur `formfilteraction` attendu par Dolibarr pour mémoriser et appliquer les colonnes sélectionnées.
- Suppression du besoin de tout gestionnaire JavaScript spécifique pour ce comportement.

## `0.8.2` — 2026-08-31

- Positionnement du sélecteur de colonnes avec le rendu natif `getTitleFieldOfList()`.
- Prise en charge du réglage Dolibarr plaçant la colonne d’actions à gauche ou à droite.
- Alignement des filtres, du sélecteur et des cellules d’action sur le comportement des listes du core.

## `0.8.1` — 2026-08-31

- Actualisation JavaScript immédiate des champs de capacité lors d’un changement d’énergie.
- Prise en charge du sélecteur natif Dolibarr et des événements Select2, en création comme en modification.
- Rétablissement de la hauteur native des tableaux sur les listes principales.
- Correction de la mémorisation et de l’application des colonnes visibles avec le contexte de page natif Dolibarr.

## `0.8.0` — 2026-08-31

- Filtrage dynamique des capacités selon l’énergie P.3 sélectionnée sur le véhicule.
- Ajout des compatibilités d’additifs : AdBlue pour diesel/B100, huile pour motorisations thermiques et fluides transverses pour toutes les énergies.
- Suppression transactionnelle des capacités devenues incompatibles après un changement d’énergie.

## `0.7.1` — 2026-08-31

- Correction du rendu des accents et caractères spéciaux dans les libellés des capacités de consommables.
- Affichage des unités après chaque champ de capacité, selon le même rendu que l’autonomie WLTP.

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
