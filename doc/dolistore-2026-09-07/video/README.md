# Démonstration vidéo française

[Lire la vidéo MP4](demo-vehicules-engins-fr.mp4) · [Sous-titres SRT](sous-titres-fr.srt) · [Miniature extraite du montage](couverture-video-fr.jpg) · [Scénario modifiable](scenario-fr.json)

Durée : **1 min 09 s**. Format : **1920 × 1080, 25 images/s**, H.264 et audio AAC. La voix française de synthèse est **Microsoft Julie**, produite localement sous Windows. Les sous-titres sont incrustés pour rester visibles sans activation ; une piste française facultative et le fichier SRT sont également fournis. Pas de musique.

Le montage utilise les captures réelles anonymisées du kit : fiche véhicule, affectations, consommations, contrôles, assurance, intervention et facture, dossier PDF, utilisation et trajets QUARTIX. Les défilements, zooms légers et fondus sont des effets de montage sur des captures fixes. Il ne s’agit pas d’un enregistrement de clics en direct. Les images sources ne sont pas modifiées.

Les chiffres visibles sont des données de démonstration. La séquence QUARTIX concerne uniquement une archive contenant cette intégration ; elle précise qu’un accès et des équipements QUARTIX sont nécessaires. Les durées QUARTIX non confirmées restent indisponibles dans l’écran présenté. Le dossier PDF montré est un extrait ; le ZIP porte sur les pièces éligibles, pas sur tous les fichiers du véhicule sans distinction.

## Refaire le montage

Prérequis de production : Python 3, PowerShell 7 avec `System.Speech` et la voix Microsoft Julie, FFmpeg avec `libx264` et `libass`. Ces outils servent uniquement à produire le support marketing et ne sont pas des dépendances du module Dolibarr.

Depuis la racine du module, adapter le chemin de FFmpeg :

```powershell
python doc/dolistore-2026-09-07/video/build_video.py --ffmpeg "C:/chemin/ffmpeg.exe"
```

Le scénario JSON est la source unique des textes prononcés, des titres et des captures utilisées. Les sous-titres et les durées sont dérivés des fichiers audio, avec une courte pause entre les phrases. `--skip-voice` réutilise les voix déjà produites ; `--scene intro` permet de contrôler un seul plan. `--powershell` accepte un chemin explicite vers PowerShell 7.

Les intermédiaires restent dans `test/.dossier-test/video-render/`, ignoré par Git. Le binaire FFmpeg utilisé pour cette production vient du paquet `imageio-ffmpeg` 0.6.0 installé isolément dans `test/.dossier-test/video-tools/` ; il n’est pas inclus dans le kit ni ajouté aux dépendances du module.

Après modification, lancer `build_pack.py` dans le dossier du kit pour actualiser l’aperçu et l’archive complète. Le fichier `verification-video.json` consigne le format, les durées, les empreintes des sources et le contrôle de décodage. Les journaux techniques et les images de contrôle restent dans le dossier temporaire.

Références de production : [filtres FFmpeg, zoompan](https://ffmpeg.org/ffmpeg-filters.html#zoompan) et [rendu des sous-titres ASS](https://ffmpeg.org/ffmpeg-filters.html#ass).
