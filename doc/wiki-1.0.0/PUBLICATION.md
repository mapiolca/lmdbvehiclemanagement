# Publication de la notice wiki 1.0.0

Le fichier `../wiki-dolibarr-1.0.0.mediawiki` est la source de l'article. `notice.html` fournit un aperçu local illustré ; il ne reproduit pas le moteur de rendu exact de MediaWiki.

## Publication dans Dolibarr Wiki

1. Ouvrir ou créer la page **Module Gestion des véhicules et engins** avec un compte autorisé.
2. Importer les 19 fichiers JPEG ci-dessous dans le wiki, en conservant exactement leur nom (y compris la casse). Renseigner l'auteur, la provenance et la licence des captures selon les droits et les exigences du wiki ; la licence GPL du module ne doit pas être assimilée automatiquement à la licence de chaque média.
3. Coller la source MediaWiki dans l'éditeur source de l'article.
4. Utiliser l'aperçu du wiki pour vérifier les images, les légendes, le sommaire, les tableaux et les catégories.
5. Publier après cette vérification. Aucune publication ni aucun transfert vers le wiki n'a été réalisé pendant la préparation.

Les fichiers d'images sont des assets documentaires statiques du module. Ils ne contiennent ni URL à token, ni barre d'adresse, ni paramètres de connexion. Les images ont été recadrées avec le navigateur, sans retouche des textes ou des données affichés.

## Périmètre et vérification distante

- Source fonctionnelle : version stable 1.0.0, documentation et descripteur de `main` au commit `094fc89`.
- Modèle de structure : [notice du module Diffusion](https://wiki.dolibarr.org/index.php/Module_Diffusion), adaptée aux parcours réellement observés.
- Captures : 5 septembre 2026, `develop.lesmetiersdubatiment.fr`, Dolibarr 24.0.0 avec Multicompany, environnement TEST 1.
- L'instance distante sert une évolution comprenant déjà l'intégration Quartix ; elle n'est donc pas une preuve du déploiement exact du commit 1.0.0. Les captures et l'article excluent cette intégration. Les portions correspondantes des onglets ont été laissées hors du cadre.
- Parcours consultés : listes, formulaires non soumis, fiches de démonstration, réglages, attestations, échéancier, historique et aperçu du dossier PDF existant.
- Aucun formulaire métier soumis, aucun réglage modifié, aucune tâche exécutée, aucun email envoyé, aucun PDF régénéré.
- L'aperçu du PDF existant fonctionne depuis la fiche principale. La présence du PDF et du ZIP est vérifiée ; une génération de bout en bout n'a pas été testée.
- Syntaxe documentaire : références des 19 images contrôlées, JPEG lisibles, absence de Quartix dans la source publique, contrôle visuel des cadrages.
- PHPStan et tests PHP/SQL : non applicables, aucun code applicatif modifié pour cette notice.

## Anomalies relevées, hors notice publique

1. L'onglet véhicule `vehicle_document.php?id=1` échoue avec `Class "LmdbVehicleManagementCompatibility" not found`, ligne 68. La notice illustre la génération depuis le bloc de la fiche principale, qui affiche les fichiers existants. À corriger et retester avant de présenter l'onglet comme validé sur cette instance.
2. Quelques libellés non traduits sont visibles dans les formulaires : `UnitKg` et `NotApplicable`. Les captures conservent le rendu réel ; aucun texte n'a été falsifié.
3. Un ancien événement Agenda présente des entités HTML doublement encodées. Aucun nettoyage de données n'a été effectué.

## Images à importer

- [LMDBVehicleManagement-1.0.0-15-modeles.jpg](LMDBVehicleManagement-1.0.0-15-modeles.jpg) — Réglages des modèles documentaires et de la numérotation. Le dossier est activé ; le choix du modèle par défaut est distinct.
- [LMDBVehicleManagement-1.0.0-16-relances-assurance.jpg](LMDBVehicleManagement-1.0.0-16-relances-assurance.jpg) — Choix des référents, seuils de relance, modèles d'emails et accès au travail planifié d'assurance.
- [LMDBVehicleManagement-1.0.0-01-liste.jpg](LMDBVehicleManagement-1.0.0-01-liste.jpg) — Accès aux rubriques du module et recherche dans le parc de démonstration.
- [LMDBVehicleManagement-1.0.0-03-creation.jpg](LMDBVehicleManagement-1.0.0-03-creation.jpg) — Partie supérieure du formulaire de création : identification, caractéristiques techniques et dates.
- [LMDBVehicleManagement-1.0.0-02-fiche.jpg](LMDBVehicleManagement-1.0.0-02-fiche.jpg) — Extrait de la fiche d'un véhicule en service : caractéristiques, capacités et résumé d'assurance.
- [LMDBVehicleManagement-1.0.0-04-affectations.jpg](LMDBVehicleManagement-1.0.0-04-affectations.jpg) — Liste des affectations : conducteur, période, type, caractère principal et état.
- [LMDBVehicleManagement-1.0.0-05-kilometrage.jpg](LMDBVehicleManagement-1.0.0-05-kilometrage.jpg) — Relevés kilométriques avec date, différence et source. Les lignes issues des consommations sont gérées depuis leur fiche d'origine.
- [LMDBVehicleManagement-1.0.0-06-plein.jpg](LMDBVehicleManagement-1.0.0-06-plein.jpg) — Formulaire de plein ou recharge avec ticket de caisse et règlement, lorsque les opérations diverses sont activées.
- [LMDBVehicleManagement-1.0.0-07-synthese.jpg](LMDBVehicleManagement-1.0.0-07-synthese.jpg) — Synthèse filtrable et premiers graphiques : les valeurs affichées proviennent d'un jeu de données de test.
- [LMDBVehicleManagement-1.0.0-13-intervention-facture.jpg](LMDBVehicleManagement-1.0.0-13-intervention-facture.jpg) — Fiche d'entretien et actions de création ou de liaison d'une facture fournisseur ; la relation existante apparaît dans Objets liés.
- [LMDBVehicleManagement-1.0.0-18-creation-assurance.jpg](LMDBVehicleManagement-1.0.0-18-creation-assurance.jpg) — Extrait du formulaire de contrat : assureur, police, période et rattachement des véhicules.
- [LMDBVehicleManagement-1.0.0-11-assurance.jpg](LMDBVehicleManagement-1.0.0-11-assurance.jpg) — Contrat d'assurance enregistré et véhicule couvert, avec type et période de couverture.
- [LMDBVehicleManagement-1.0.0-12-attestations.jpg](LMDBVehicleManagement-1.0.0-12-attestations.jpg) — Attestation validée et formulaire de dépôt : enregistrement provisoire et soumission au contrôle sont deux actions distinctes.
- [LMDBVehicleManagement-1.0.0-09-qualification.jpg](LMDBVehicleManagement-1.0.0-09-qualification.jpg) — Extrait du questionnaire de qualification, à compléter selon les caractéristiques et l'usage du matériel.
- [LMDBVehicleManagement-1.0.0-08-echeancier.jpg](LMDBVehicleManagement-1.0.0-08-echeancier.jpg) — Échéancier du parc de démonstration, avec dates retenues et états des exigences.
- [LMDBVehicleManagement-1.0.0-10-controle.jpg](LMDBVehicleManagement-1.0.0-10-controle.jpg) — Création d'un contrôle depuis une exigence : organisme, résultat, référence du procès-verbal et dates.
- [LMDBVehicleManagement-1.0.0-14-dossier.jpg](LMDBVehicleManagement-1.0.0-14-dossier.jpg) — Bloc documentaire de la fiche : sélection du modèle, génération, aperçu du PDF et téléchargement de l'archive.
- [LMDBVehicleManagement-1.0.0-19-apercu-pdf.jpg](LMDBVehicleManagement-1.0.0-19-apercu-pdf.jpg) — Extrait de la première page d'un dossier PDF existant : identification et caractéristiques du véhicule.
- [LMDBVehicleManagement-1.0.0-17-historique.jpg](LMDBVehicleManagement-1.0.0-17-historique.jpg) — Chronologie consolidée : interventions, relevés, consommations, assurances et affectations.
