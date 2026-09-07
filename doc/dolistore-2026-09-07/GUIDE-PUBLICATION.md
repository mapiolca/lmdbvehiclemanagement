# Kit Dolistore — Gestion des véhicules et engins

Préparé le 7 septembre 2026 pour le module `lmdbvehiclemanagement`, version déclarée `1.0.0`.
État de référence : branche `codex/quartix-integration`, commit `9dd954cee19bd91ae3ddbcfac17d883b5e3b3997`. Le dépôt était propre avant ce travail.

## Commencer ici

Ouvrir [l’aperçu du kit](index.html#description), puis utiliser [l’archive complète](kit-dolistore-vehicules-engins.zip) pour retrouver tous les fichiers au même endroit. L’aperçu fonctionne en local, sans service tiers, et propose les cinq langues EN, FR, DE, ES et IT, le choix avec/sans QUARTIX et la copie séparée des trois champs. Après extraction, conserver les sous-répertoires à côté de `index.html`.

Le [récapitulatif des cinq langues](SOUMISSION-5-LANGUES.md) donne accès aux textes et aux comptages. Chaque description longue reste sous 300 mots, prérequis et bloc QUARTIX compris. L’accroche, les six bénéfices en puces et les prérequis sont volontairement courts, selon la demande de l’utilisateur.

## Contenu livré

- Une couverture carrée avec « Gestion des véhicules et engins », ajoutée à la demande de l’utilisateur.
- La variante de couverture sans texte, utilisable notamment avec la fiche anglaise.
- Une bannière française « Votre parc roule. Vous gardez le contrôle. ».
- Douze captures authentiques du module, copiées à l’identique depuis les illustrations anonymisées du wiki : neuf générales et trois QUARTIX.
- Une [vidéo française de 1 min 09 s](video/demo-vehicules-engins-fr.mp4), avec voix de synthèse, sous-titres et captures animées. Le [scénario et les sources de montage](video/README.md) sont fournis ; la séquence QUARTIX exige une archive incluant cette intégration.
- Noms de module, descriptions courtes et longues, titres publicitaires, métadonnées SEO et mots-clés en anglais, français, allemand, espagnol et italien. Les légendes des captures restent en français et anglais ; les écrans et les visuels titrés restent en français.
- Versions longues en Markdown pour la relecture et en HTML simple pour l’éditeur de description Dolistore.
- Une variante avec QUARTIX, une variante sans QUARTIX et un bloc QUARTIX séparé pour chaque langue.
- Prompts des illustrations, sources, manifeste des dimensions/poids/empreintes et contrôles du pack.

Les dimensions réelles des PNG sont indiquées dans [manifest.json](manifest.json). Elles décrivent les fichiers livrés, sans prétendre constituer une prescription officielle de Dolistore. Les captures restent à leur définition native ; elles n’ont pas été agrandies artificiellement.

## Choix de la description

La description principale de l’aperçu inclut QUARTIX, car l’intégration existe dans la branche examinée. La version affichée par le descripteur reste 1.0.0 pendant ce développement ; le numéro seul ne permet donc pas de savoir si une ancienne archive contient QUARTIX.

- Archive contenant l’intégration : utiliser `description-courte-quartix-fr.txt` et `description-longue-avec-quartix-fr.html`.
- Archive sans l’intégration : utiliser `description-courte-fr.txt` et `description-longue-fr.html`, sans les captures 10 à 12.
- Même logique pour les fichiers `en`, `de`, `es` et `it`.

Vérifier le contenu de l’archive réellement mise en vente avant de retenir la variante. Ce kit est un support de présentation : il ne crée pas d’archive installable du module et ne publie pas de fiche.

Les fragments HTML sont destinés au mode source de l’éditeur de description. Les fichiers HTML individuels n’embarquent pas le style de l’aperçu local ; Dolistore applique sa propre présentation. Le rendu de l’éditeur vendeur n’a pas été testé dans un compte authentifié.

## Correspondance avec le formulaire fourni

Source examinée : `C:/Users/pardoin/Downloads/edit-module-product.pdf`, trois pages, capture du 7 septembre 2026 à 13:43. Les trois pages ont été extraites et inspectées visuellement.

| Champ visible dans le PDF | Contenu à utiliser |
|---|---|
| Nom du module/produit [EN] | `textes/nom-module-en.txt` ; nom court, sans ajouter le titre publicitaire |
| Description courte [EN] | `textes/description-courte-quartix-en.txt` ou la variante sans QUARTIX |
| Description longue [EN] | `textes/description-longue-avec-quartix-en.html` ou la variante sans QUARTIX ; coller avec le bouton **Source** |
| Déclinaisons FR, DE, ES, IT demandées | Mêmes fichiers avec le suffixe de langue correspondant |
| Mots clés | Liste `keywords` du fichier `textes/champs-xx.json` ; un seul champ commun est visible, sans suffixe de langue |

Le PDF montre seulement les trois champs anglais, obligatoires. Il ne prouve pas que les cinq langues sont exigées par la plateforme ni comment son interface affiche les autres langues. Les cinq versions répondent à la demande de l’utilisateur.

Aucune longueur maximale, dimension imposée aux images ou taille maximale des pièces jointes n’est visible dans ce PDF. Les comptages du kit sont informatifs. Les titres publicitaires et champs SEO sont des ressources facultatives : aucun champ SEO dédié n’est visible dans le formulaire.

## Ordre conseillé dans la galerie Dolistore

1. Couverture avec le nom du module.
2. Fiche véhicule.
3. Échéancier des contrôles.
4. Synthèse des consommations.
5. Intervention et facture fournisseur.
6. Contrat d’assurance.
7. Dossier PDF.
8. QUARTIX : utilisation, si inclus dans l’archive.
9. QUARTIX : journal des trajets, si inclus.
10. QUARTIX : tracé, si inclus.

Les affectations, kilométrages et chronologie complètent cette sélection si davantage d’images sont souhaitées. La bannière peut servir de visuel secondaire ou de support promotionnel. Le manifeste numérote les fichiers par groupe de parcours ; l’ordre commercial ci-dessus est une recommandation de sélection.

Les légendes bilingues se trouvent dans `textes/legendes-fr.md` et `textes/legendes-en.md`. Les textes alternatifs sont également fournis dans `manifest.json`.

## Positionnement commercial

Promesse centrale : **Votre parc roule. Vous gardez le contrôle.**

Le texte met en avant six bénéfices démontrables : centraliser les informations, suivre les affectations, comprendre les consommations, préparer les échéances, relier les interventions aux factures et rassembler le dossier véhicule.

Il ne promet ni économies chiffrées, ni suppression de toutes les pannes, ni conformité juridique garantie, ni suivi GPS instantané. Les contrôles sont présentés comme une aide documentaire fondée sur le catalogue français. QUARTIX est présenté avec ses prérequis et la notion de dernière synchronisation disponible.

## Champs commerciaux à renseigner lors de la création de la fiche

| Champ | Valeur ou traitement |
|---|---|
| Nom | Gestion des véhicules et engins |
| Auteur | Les Métiers du Bâtiment — Pierre Ardoin |
| Version | 1.0.0, à aligner avec l’archive commercialisée |
| Référence technique du module | lmdbvehiclemanagement ; la référence produit Dolistore sera celle attribuée à la fiche |
| Licence | GPL-3.0-or-later, d’après le descripteur |
| Socle minimal déclaré | Dolibarr 20 ; PHP 8.0 ; MySQL/MariaDB |
| Langues de l’interface | Français et anglais |
| Langues des descriptions commerciales | EN, FR, DE, ES, IT ; cela ne traduit pas l’interface du module |
| Langues de documentation | Français, anglais, italien, allemand et espagnol sur le wiki |
| Catégories proposées (maximum 3) | Modules/Plugins → Autres ; Interfaçage externes si QUARTIX est inclus ; GED - Gestion de documents comme troisième catégorie facultative pour le dossier PDF/ZIP |
| Prix HT | À décider ; aucun prix déduit des autres modules |
| Date de sortie | Date de publication effective, à renseigner |
| Durée d’accès aux mises à jour et téléchargements | Le formulaire affiche 730 jours ; 0 signifie validité à vie. Valeur à décider pour ce produit, sans déduire un engagement de cette valeur affichée |
| Statut | Le PDF montre « Désactivé » sélectionné ; « Soumettre pour approbation » est disponible. Aucun statut n’a été modifié |
| Related price | QUARTIX est facultatif pour le suivi de parc. Ses équipements et services sont séparés. Si l’intégration est annoncée, renseigner le coût minimal applicable au contrat QUARTIX proposé ; ce montant n’est pas fourni et n’a pas été inventé |
| Support | `support@lesmetiersdubatiment.fr` est utilisé par Diffusions et Feuilles de temps ; `developpeur@lesmetiersdubatiment.fr` par DC1. Retenir l’adresse souhaitée pour ce produit |
| Démonstration | Aucun lien de démo annoncé : l’activation de ce module et l’accès public n’ont pas été vérifiés |
| Compatibilités maximales à cocher | À aligner sur la matrice réellement validée pour l’archive de vente ; aucune certification de toutes les versions n’est ajoutée par ce kit |
| Compatibilités présélectionnées | Le PDF affiche V24 en minimum et maximum ; ces sélections du formulaire ne changent pas le socle déclaré du module (Dolibarr 20 / PHP 8.0) |
| Paquet distribué | Archive ZIP installable du module, distincte du ZIP commercial de ce kit ; le kit ne doit pas être téléversé dans ce champ |
| Image de couverture | `illustrations/01-couverture-vehicules-engins-titre-fr.png` ; variante sans texte disponible pour une couverture commune aux langues |
| Autres images | Sélection des captures et éventuellement de la bannière ci-dessus |

Le PDF présente aussi les conditions d’utilisation et le souhait facultatif d’une inclusion au core. Ces cases n’ont pas été cochées : le document sert à comprendre les champs, pas à autoriser un engagement ou une soumission.

## Contrôles et limites

- Relecture des textes par rapport au README, au descripteur et au guide QUARTIX.
- Inspection visuelle des douze captures retenues ; réutilisation sans retouche des versions déjà anonymisées.
- Les valeurs de démonstration des consommations ne représentent pas des résultats réels. La légende le précise.
- Les captures QUARTIX conservent les messages d’indisponibilité des durées tant que l’unité n’est pas confirmée ; le fond de carte reste flouté. Ces éléments n’ont pas été remplacés par de fausses données.
- Certaines captures générales datent des 4–5 septembre ; les captures QUARTIX datent du 7 septembre. Ce travail ne constitue pas une nouvelle capture de l’instance ni une validation du déploiement courant.
- Contrôle des dimensions, tailles, empreintes et identité binaire avec les sources ; suppression antérieure des métadonnées des captures vérifiée par inspection des blocs PNG.
- Vérification visuelle de l’aperçu local sur bureau et écran étroit ; liens et changements de langue contrôlés.
- Aucun code PHP, SQL, descripteur, permission, paramètre Dolibarr ou version du module modifié. PHPStan et suites métier non applicables à ces ajouts documentaires.
- Aucune publication Dolistore, aucun commit, aucun push, aucun déploiement réalisé.

## Régénération

Depuis la racine du module :

```powershell
python doc/dolistore-2026-09-07/build_pack.py
```

Le script utilise seulement la bibliothèque standard Python. Il lit `content.json`, copie les captures anonymisées, génère les textes/aperçu/manifestes et reconstruit le ZIP. Il ne retouche aucune image et n’écrit que dans ce répertoire. Les illustrations sont générées séparément avec l’outil image_gen intégré à Codex ; leurs prompts sont conservés.

## Proposition de commit

Titre : `docs: préparer le kit commercial Dolistore du module véhicules`

Description : ajouter les illustrations et douze captures anonymisées ; fournir les noms et descriptions commerciales concises EN/FR/DE/ES/IT avec variantes QUARTIX. Aligner le guide sur le formulaire de soumission fourni, avec copie séparée du nom, du résumé et du HTML dans l’aperçu local. Vérifier les langues, les longueurs, les liens et le ZIP. Code et mécanismes natifs Dolibarr inchangés ; PHPStan non applicable.
