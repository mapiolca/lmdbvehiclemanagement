# Historique des versions

## À publier

- Intégration QUARTIX en lecture seule : connexion chiffrée par environnement, associations explicites, kilométrage estimé quotidien, dernière position sous droit GPS et synthèses d'utilisation quotidiennes/mensuelles sur douze mois.
- Trois travaux planifiés natifs avec lots reprenables, renouvellement des jetons, quotas et verrouillage. Les réglages des tâches sont conservés à la désactivation/réactivation. Authentification et renouvellement envoyés en JSON ; les refus 422 sont distingués des erreurs de service. Les erreurs de connexion indiquent l'étape et le statut HTTP ou le code réseau, sans exposer les secrets.
- Migration additive des relevés estimés : priorité aux relevés réels et aux pleins/recharges, anomalies visibles et imports sans doublon. Aucun GPS dans les documents ou pour les utilisateurs externes.
- Réactiver le module après déploiement, configurer QUARTIX, confirmer les unités/fuseaux, associer les véhicules puis activer les trois tâches. Voir [le guide QUARTIX](doc/quartix.md). Version du module inchangée.

## `1.0.0` — 2026-09-04

Première version stable. Cette entrée synthétise les versions de développement `0.1.0` à `0.15.0` et les évolutions précédemment « À publier ».

### Gestion du parc

- Gestion des véhicules, utilitaires et engins : caractéristiques, énergies P.3, capacités, affectations des conducteurs, cycle de vie et relevés kilométriques avec corrections et remplacements de compteur.
- Chronologie consolidée des événements, contrôles et consommations, avec filtres, tri, pagination, colonnes personnalisables, badges et liens natifs Dolibarr ; formulaires adaptés aux écrans mobiles.
- Numérotation dédiée aux objets, immatriculation facultative et modèle de référence par immatriculation avec migration transactionnelle des références, documents et index ECM.

### Consommations et dépenses

- Suivi des pleins, recharges et additifs, capacités compatibles avec l’énergie, synchronisation des kilométrages, imports/exports et statistiques par unité avec graphiques natifs. Les prix absents des additifs restent distincts des prix nuls et sont exclus des statistiques de prix.
- Création optionnelle, par entité, d’une opération diverse native au débit avec compte bancaire, règlement, projet et justificatif contrôlé. Synchronisation transactionnelle des données financières ; verrouillage après rapprochement bancaire ou transfert comptable.
- Création native de factures fournisseurs depuis les événements et contrôles, avec liaison transactionnelle et relations multiples réciproques dans les blocs Objets liés, sans modifier les données des contrôles validés.

### Assurances et contrôles réglementaires

- Contrats individuels ou de flotte, couvertures principales et complémentaires, contacts, attestations contrôlées et historisées, référents et relances configurables par modèles d’emails et travaux planifiés natifs.
- Qualification réglementaire guidée et historisée, catalogue français versionné : contrôles routiers, pollution, catégorie L, usages spécialisés, VGP, mise en service, tachygraphe, ADR et ATP. Règles natives, surcharges d’entité auditées et règles personnalisées.
- Contrôles numérotés avec justificatifs, validation immuable, annulation motivée, remplacement et archivage encadré ; échéancier, registre de sécurité, exports et imports en brouillon.
- Recalcul quotidien des échéances, événement Agenda unique par exigence et rappels configurables, avec prévention des doublons et traçabilité des destinataires.

### Documents et intégration Dolibarr

- Modèle natif Dossier véhicule : PDF récapitulatif multipage avec chronologie, contrôles, interventions, factures, synthèse des consommations et inventaire documentaire ; ZIP des originaux du véhicule, des interventions et des factures, hors justificatifs de consommation.
- En-têtes et pieds de page natifs, documents absents signalés, génération verrouillée et indexée dans l’entité propriétaire ; restauration du dossier précédent en cas d’échec. Aperçus et téléchargements soumis aux droits de lecture du véhicule et des factures, sans accès public.
- Intégration des notes, contacts, fichiers joints, objets liés, Agenda CRUD traduit, imports/exports, droits granulaires et réglages natifs. Partages Multicompany configurables et conservation des réglages par entité lors des réactivations.
- Compatibilité minimale Dolibarr v20, PHP 8.0 et MySQL/MariaDB ; dépendances conditionnelles présentées dans l’onglet Compatibilité. Contrôle des droits, des entités, des tokens et des fichiers ; nettoyage des métadonnées des images justificatives.

### Mise à jour depuis une version de développement

- Réactiver le module après déploiement pour appliquer les migrations idempotentes et enregistrer les hooks, dictionnaires, événements et modèles natifs ; les réglages existants sont conservés. Les migrations comprennent notamment les anciens profils réglementaires, les codes Agenda des attestations et les prix d’additifs facultatifs.
- Pour le dossier véhicule, activer le modèle dans les réglages Modèles de document ; le module Factures fournisseurs et l’extension PHP ZIP sont requis. Régénérer les dossiers existants pour bénéficier de la présentation finale.
- La création d’opérations diverses exige le module Banques et Caisses et une configuration par entité ; elle ne reprend pas les consommations historiques et interdit leur import CSV lorsqu’elle est active. La comptabilité reste facultative, avec compte général du plan actif obligatoire en partie double.
- Les nouveaux libellés Agenda s’appliquent aux événements futurs ; les historiques et contrôles validés ne sont pas réécrits. Le passage de version à `1.0.0` n’ajoute aucune migration métier supplémentaire.
