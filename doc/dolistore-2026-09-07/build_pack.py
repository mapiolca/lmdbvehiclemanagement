"""Build the local Dolistore editorial pack. Standard library only; no image editing.

Screenshots are copied byte-for-byte from the reviewed, anonymised wiki assets.
The script only writes inside this publication pack directory.
"""
from pathlib import Path
import hashlib
import html
import json
import re
import shutil
import struct
import zipfile

ROOT = Path(__file__).resolve().parent
MODULE = ROOT.parent.parent
DATA = json.loads((ROOT / 'content.json').read_text(encoding='utf-8'))
SCREEN_SOURCE = MODULE / 'doc/wiki-2026-09/images'

# Source suffix, destination slug, title FR, title EN, caption FR, caption EN, optional QUARTIX.
SCREENS = [
    ('02-fiche-anonymise', 'fiche-vehicule', 'Votre véhicule, ses informations essentielles', 'Your vehicle at a glance', 'Caractéristiques, énergie, capacités et assurance réunies sur la fiche véhicule.', 'Technical specifications, energy, capacities and insurance on the vehicle record.', False),
    ('08-echeancier-anonymise', 'echeancier-controles', 'Les échéances à préparer, visibles ensemble', 'See upcoming inspection deadlines together', 'Filtrez les contrôles et identifiez les échéances et les statuts à suivre.', 'Filter inspections and review the deadlines and statuses that need attention.', False),
    ('07-synthese-anonymise', 'synthese-consommations', 'Vos consommations deviennent lisibles', 'Make sense of consumption records', 'Synthèses par consommable, coûts renseignés et graphiques. Valeurs de démonstration non représentatives d’un coût ou d’une consommation réels.', 'Summaries by consumable, recorded costs and charts. Demonstration values do not represent real operating costs or consumption.', False),
    ('13-intervention-facture-anonymise', 'intervention-facture', 'De l’intervention à sa facture', 'From maintenance event to supplier invoice', 'Retrouvez l’événement, l’immobilisation renseignée et les liens vers les factures fournisseurs Dolibarr.', 'Review the event, recorded downtime and links to Dolibarr supplier invoices.', False),
    ('11-assurance-anonymise', 'contrat-assurance', 'Gardez les couvertures à portée de main', 'Keep insurance details within reach', 'Contrat, période de couverture, véhicules liés et accès aux attestations dans un même parcours.', 'Insurance contract, coverage period, linked vehicles and access to certificates in one workflow.', False),
    ('04-affectations-anonymise', 'affectations', 'Sachez à qui le véhicule est affecté', 'Know who the vehicle is assigned to', 'Consultez les utilisateurs affectés, leurs périodes et l’affectation principale.', 'Review assigned users, their assignment periods and the primary assignment.', False),
    ('05-kilometrage-anonymise', 'kilometrages', 'Conservez le fil des kilomètres', 'Keep a traceable mileage history', 'Relevés datés, sources et écarts consultables dans une liste native Dolibarr.', 'Dated readings, sources and changes in a native Dolibarr list.', False),
    ('17-historique-anonymise', 'chronologie', 'L’histoire du véhicule se retrouve ici', 'Follow the vehicle’s recorded history', 'Parcourez les affectations, consommations, relevés, interventions et assurances enregistrés.', 'Browse recorded assignments, consumption, mileage, events and insurance activity.', False),
    ('19-apercu-pdf-anonymise', 'dossier-pdf', 'Un dossier véhicule prêt à consulter', 'A vehicle dossier ready to review', 'Extrait de la première page du dossier PDF généré par le module. Le ZIP associé regroupe les pièces éligibles.', 'Extract from the first page of the generated PDF vehicle dossier. The associated ZIP collects eligible files.', False),
    ('quartix-utilisation', 'quartix-utilisation', 'QUARTIX : rapprochez l’utilisation du suivi de parc', 'QUARTIX: connect usage data to fleet records', 'Distances et trajets sur les journées synchronisées. Les durées attendent la confirmation de leur unité.', 'Distances and trips for synchronised days. Durations remain unavailable until their unit is confirmed.', True),
    ('quartix-trajets-anonymise', 'quartix-trajets', 'QUARTIX : retrouvez le journal des trajets', 'QUARTIX: review trip records', 'Journal filtrable et paginé. Les lieux sont anonymisés dans cette illustration.', 'Filterable, paginated trip records. Locations are anonymised in this illustration.', True),
    ('quartix-route-anonymise', 'quartix-trace', 'QUARTIX : consultez le tracé autorisé', 'QUARTIX: view authorised route traces', 'Modale cartographique native. Le fond de carte et la référence sont floutés pour protéger les lieux ; les trajets privés ne sont pas cartographiés.', 'Native map dialog. Map and reference are blurred to protect locations; private trips are not mapped.', True),
]

def write(relative, text):
    path = ROOT / relative
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text.rstrip() + '\n', encoding='utf-8', newline='\n')

def inline(text):
    return re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', html.escape(text))

def paragraph(text):
    return '<p>' + inline(text) + '</p>'

def bullets(items):
    return '<ul>' + ''.join('<li>' + inline(x) + '</li>' for x in items) + '</ul>'

def section(title, content):
    return '<h3>' + html.escape(title) + '</h3>' + content

def quartix_html(c):
    q = c['quartix']
    return section(q['title'], paragraph(q['paragraph']) + paragraph(q['note']))

def body_html(c, include_quartix):
    result = '<h2>' + html.escape(c['hero']) + '</h2>'
    result += ''.join(paragraph(p) for p in c['intro'])
    result += section(c['features_title'], bullets(c['features']))
    result += paragraph(c['audience'])
    if include_quartix:
        result += quartix_html(c)
    result += paragraph(c['closing'])
    result += section(c['requirements_title'], bullets(c['requirements']))
    return result

def body_md(c, include_quartix):
    parts = ['# ' + c['hero'], *c['intro']]
    parts.extend(['## ' + c['features_title'], '\n'.join('- ' + x for x in c['features']), c['audience']])
    if include_quartix:
        q = c['quartix']
        parts.extend(['## ' + q['title'], q['paragraph'], q['note']])
    parts.extend([c['closing'], '## ' + c['requirements_title'], '\n'.join('- ' + x for x in c['requirements'])])
    return '\n\n'.join(parts)

def word_count(c, include_quartix):
    plain = re.sub(r'</?(?:h[23]|p|li|ul)\b[^>]*>', ' ', body_html(c, include_quartix))
    plain = html.unescape(re.sub('<[^>]+>', '', plain))
    return len(plain.split())

def image_info(path):
    data = path.read_bytes()
    if data[:8] != b'\x89PNG\r\n\x1a\n':
        raise ValueError(f'Not a PNG: {path.name}')
    w, h = struct.unpack('>II', data[16:24])
    chunks = []
    offset = 8
    while offset < len(data):
        length = struct.unpack('>I', data[offset:offset + 4])[0]
        chunks.append(data[offset + 4:offset + 8].decode('ascii'))
        offset += 12 + length
    return {'width': w, 'height': h, 'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest(), 'metadata_chunks': [x for x in chunks if x in ('eXIf', 'tEXt', 'zTXt', 'iTXt')]}

manifest = []
for i, item in enumerate(SCREENS, 1):
    src, slug, fr, en, caption_fr, caption_en, optional = item
    source = SCREEN_SOURCE / ('LMDBVehicleManagement-2026-09-' + src + '.png')
    destination = ROOT / 'captures' / f'{i:02d}-{slug}.png'
    shutil.copyfile(source, destination)
    info = image_info(destination)
    assert info['sha256'] == hashlib.sha256(source.read_bytes()).hexdigest()
    manifest.append({'order': i, 'file': destination.relative_to(ROOT).as_posix(), 'source': source.relative_to(MODULE).as_posix(), 'title_fr': fr, 'title_en': en, 'caption_fr': caption_fr, 'caption_en': caption_en, 'alt_fr': fr + '. ' + caption_fr, 'alt_en': en + '. ' + caption_en, 'requires_quartix': optional, 'source_identical': True, **info})

illustrations = []
for p in sorted((ROOT / 'illustrations').glob('*.png')):
    illustrations.append({'file': p.relative_to(ROOT).as_posix(), **image_info(p)})
write('manifest.json', json.dumps({'prepared': DATA['prepared'], 'source_commit': DATA['source_commit'], 'illustrations': illustrations, 'screenshots': manifest}, ensure_ascii=False, indent=2))

panels = []
language_names = {'en': 'English', 'fr': 'Français', 'de': 'Deutsch', 'es': 'Español', 'it': 'Italiano'}
submission_index = ['# Textes de soumission Dolistore — EN, FR, DE, ES, IT',
    'Chaque langue contient le nom du module, une description courte et une description longue concise. Le HTML est destiné au bouton « Source » de l’éditeur du formulaire. Choisir la variante QUARTIX uniquement si elle est incluse dans le paquet vendu.',
    'Le PDF fourni montre ces trois champs obligatoires en anglais. Les quatre autres langues sont livrées à la demande de l’utilisateur. Aucune limite de caractères n’est visible dans le PDF ; les comptages ci-dessous sont des mesures éditoriales.',
    '| Langue | Tous les champs | Courte avec QUARTIX | Longue avec QUARTIX | Longue sans QUARTIX |',
    '|---|---|---:|---:|---:|']
for lang, c in DATA['languages'].items():
    fields = {k: c[k] for k in ['name', 'title', 'short', 'short_quartix', 'seo_title', 'seo_description', 'keywords']}
    fields['lengths'] = {k: len(c[k]) for k in ['title', 'short', 'short_quartix', 'seo_title', 'seo_description']}
    write(f'textes/champs-{lang}.json', json.dumps(fields, ensure_ascii=False, indent=2))
    write(f'textes/nom-module-{lang}.txt', c['name'])
    write(f'textes/description-courte-{lang}.txt', c['short'])
    write(f'textes/description-courte-quartix-{lang}.txt', c['short_quartix'])
    write(f'textes/bloc-quartix-{lang}.html', quartix_html(c))
    for q in (False, True):
        tag = '-avec-quartix' if q else ''
        write(f'textes/description-longue{tag}-{lang}.html', body_html(c, q))
        write(f'textes/description-longue{tag}-{lang}.md', body_md(c, q))
        short = c['short_quartix'] if q else c['short']
        submission = {'language': lang, 'includes_quartix': q, 'module_name': c['name'], 'short_description': short, 'long_description_html': body_html(c, q), 'counts': {'name_characters': len(c['name']), 'short_characters': len(short), 'long_words': word_count(c, q)}}
        write(f'textes/soumission{tag}-{lang}.json', json.dumps(submission, ensure_ascii=False, indent=2))
        write(f'textes/soumission{tag}-{lang}.md', '\n\n'.join(['# Champs Dolistore — ' + language_names[lang], '## Nom du module/produit', c['name'], '## Description courte', short, '## Description longue', body_md(c, q)]))
    if lang in ('fr', 'en'):
        guide = ['# Captures et légendes — ' + lang.upper(), 'Les fichiers sont des copies exactes des images anonymisées du wiki. Les écrans sont en français.', '| Ordre | Fichier | Titre | Légende |', '|---|---|---|---|']
        for s in manifest:
            guide.append(f"| {s['order']:02d} | [{Path(s['file']).name}](../{s['file']}) | {s['title_' + lang]} | {s['caption_' + lang]} |")
        write(f'textes/legendes-{lang}.md', '\n\n'.join(guide[:2]) + '\n\n' + '\n'.join(guide[2:]))
    panels.append(f'<article class="copy-panel" lang="{lang}" data-lang="{lang}" {"hidden" if lang != "fr" else ""}><p class="eyebrow">DESCRIPTION LONGUE · {lang.upper()}</p><div class="long-copy">{body_html(c, True)}</div></article>')
    submission_index.append(f"| {language_names[lang]} | [Avec QUARTIX](textes/soumission-avec-quartix-{lang}.md) · [Sans](textes/soumission-{lang}.md) | {len(c['short_quartix'])} caractères | [HTML](textes/description-longue-avec-quartix-{lang}.html) · {word_count(c, True)} mots | [HTML](textes/description-longue-{lang}.html) · {word_count(c, False)} mots |")
write('SOUMISSION-5-LANGUES.md', '\n\n'.join(submission_index[:3]) + '\n\n' + '\n'.join(submission_index[3:]) + '\n\nLes titres publicitaires et métadonnées SEO restent des variantes facultatives dans `champs-xx.json` ; aucun champ SEO dédié n’apparaît dans le PDF. Utiliser le nom court pour « Nom du module/produit » et le fichier de description correspondant pour chaque zone du formulaire.\n\nLes textes commerciaux existent dans cinq langues. L’interface du module reste disponible en français et en anglais. Voir [le guide de publication](GUIDE-PUBLICATION.md) pour les autres champs du formulaire.')

language_buttons = ''.join(f'<button type="button" data-language="{lang}" class="{"secondary" if lang != "fr" else ""}" aria-pressed="{str(lang == "fr").lower()}">{label}</button>' for lang, label in language_names.items())
language_files = ''.join(f'<a href="textes/soumission-avec-quartix-{lang}.md">{label} · Nom et descriptions</a><a href="textes/description-longue-avec-quartix-{lang}.html">{label} · HTML avec QUARTIX</a><a href="textes/description-longue-{lang}.html">{label} · HTML sans QUARTIX</a>' for lang, label in language_names.items())

gallery = []
for s in manifest:
    badge = 'QUARTIX · option' if s['requires_quartix'] else 'MODULE'
    gallery.append(f'<figure><a href="{s["file"]}" target="_blank"><img src="{s["file"]}" alt="{html.escape(s["alt_fr"])}" loading="lazy"></a><figcaption><span class="eyebrow">{s["order"]:02d} / {badge}</span><h3>{html.escape(s["title_fr"])}</h3><p>{html.escape(s["caption_fr"])}</p><a href="{s["file"]}" download>Télécharger le PNG ↗</a></figcaption></figure>')

page = r'''<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Kit Dolistore — Gestion des véhicules et engins</title>
<style>
:root{--ink:#102447;--blue:#1455a5;--muted:#526581;--line:#dce5ef;--paper:#f5f8fc}*{box-sizing:border-box}body{margin:0;font-family:Segoe UI,Arial,sans-serif;color:var(--ink);background:var(--paper);line-height:1.65}a{color:var(--blue);text-underline-offset:4px}header{background:#fff;border-bottom:1px solid var(--line);padding:20px 5vw;display:flex;justify-content:space-between;gap:24px;align-items:center}header strong{letter-spacing:-.3px}nav{display:flex;gap:22px;flex-wrap:wrap;font-size:14px}main{max-width:1160px;margin:auto;padding:44px 28px 80px}.eyebrow{font-size:11px;font-weight:700;letter-spacing:1.6px;color:var(--blue);text-transform:uppercase}h1{font-size:clamp(32px,4vw,51px);line-height:1.13;letter-spacing:-1.5px;margin:14px 0 20px}h2{font-size:32px;line-height:1.2;letter-spacing:-.6px}h3{line-height:1.35}p{margin:12px 0 20px}.intro{display:grid;grid-template-columns:1.2fr .85fr;gap:44px;align-items:center}.intro img{width:100%;display:block}.intro .lead{font-size:18px;color:var(--muted)}.tags{display:flex;gap:8px;flex-wrap:wrap}.tags span{font-size:12px;background:#e5edf8;padding:5px 10px;border-radius:5px}.button,button{display:inline-block;border:0;background:var(--blue);color:white;padding:12px 18px;border-radius:5px;text-decoration:none;cursor:pointer;font:600 14px Segoe UI,Arial,sans-serif}.secondary{background:#e5edf8;color:var(--ink)}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.hero-banner{margin:48px 0}.hero-banner img{width:100%;height:auto;display:block;background:white}.note{font-size:13px;color:var(--muted);border-left:3px solid #88b5dc;padding-left:14px}.section-title{margin-top:64px}.gallery{display:grid;grid-template-columns:1fr 1fr;gap:24px}figure{margin:0;background:white;border:1px solid var(--line);border-radius:8px;overflow:hidden}figure>a{display:flex;min-height:215px;height:265px;align-items:center;padding:14px;background:#fff;border-bottom:1px solid var(--line)}figure img{width:100%;max-height:100%;object-fit:contain}figcaption{padding:22px}figcaption h3{margin:10px 0;font-size:20px}figcaption p{color:var(--muted);font-size:14px}figcaption a{font-size:13px}.toolbar{display:flex;gap:12px;flex-wrap:wrap;margin:20px 0}.copy-panel{background:white;border:1px solid var(--line);padding:40px 48px;border-radius:8px;max-width:900px}.copy-panel h2{font-size:36px}.copy-panel section{margin-top:36px}.copy-panel h3{font-size:23px}.copy-panel li{margin:9px 0}.copy-panel p{max-width:75ch}.copy-panel section:last-child{font-size:14px;color:var(--muted);border-top:1px solid var(--line);padding-top:18px}.files{display:grid;grid-template-columns:1fr 1fr;gap:24px}.files>div{background:white;border:1px solid var(--line);border-radius:8px;padding:24px}.files a{display:block;margin:10px 0}textarea{width:100%;height:150px;font-size:16px;padding:12px;border:1px solid var(--line);font-family:inherit}.copy-status{min-height:24px;font-size:13px}.foot{margin-top:64px;font-size:13px;color:var(--muted)}[hidden]{display:none!important}@media(max-width:700px){header{align-items:flex-start;flex-direction:column}main{padding:28px 18px}.intro,.gallery,.files{grid-template-columns:1fr}.intro{gap:18px}.intro img{max-width:360px;margin:auto}.copy-panel{padding:26px 22px}.copy-panel h2{font-size:29px}figure>a{height:220px}.section-title{margin-top:45px}nav{gap:16px}}
.field-preview{max-width:900px;background:#fff;border:1px solid var(--line);border-radius:8px;padding:24px;margin-bottom:20px}.field-preview label{display:block;font-weight:600;margin-bottom:6px}.field-preview input,.field-preview select{width:100%;padding:10px;font:inherit;color:var(--ink);background:white;border:1px solid var(--line);border-radius:4px}.field-preview textarea{height:auto;min-height:112px;line-height:1.5;color:var(--ink)}.field-preview .field{margin-bottom:20px}.field-preview .field:last-child{margin-bottom:0}.field-meta{font-size:12px;color:var(--muted);margin:6px 0}.copy-panel h3{margin-top:28px}.long-copy>ul:last-child{font-size:14px;color:var(--muted)}
</style></head><body>
<header><strong>Les Métiers du Bâtiment</strong><nav><a href="#visuels">Visuels</a><a href="#video">Vidéo</a><a href="#captures">Captures</a><a href="#description">Description</a><a href="#fichiers">Fichiers</a></nav></header>
<main><section class="intro" id="visuels"><div><p class="eyebrow">KIT DE PRÉSENTATION · DOLISTORE</p><h1>Votre parc roule.<br>Vous gardez<br>le contrôle.</h1><p class="lead">Gestion des véhicules et engins pour Dolibarr.<br>Le suivi opérationnel, administratif et documentaire de votre parc, au même endroit.</p><div class="tags"><span>Véhicules & engins</span><span>Multicompany</span><span>PDF / ZIP</span><span>QUARTIX en option</span></div><div class="actions"><a class="button" href="kit-dolistore-vehicules-engins.zip" download>Télécharger le kit complet</a><a class="button secondary" href="#description">Lire la description</a></div></div><a href="illustrations/01-couverture-vehicules-engins-titre-fr.png"><img src="illustrations/01-couverture-vehicules-engins-titre-fr.png" alt="Gestion des véhicules et engins : utilitaire et engin bleus, calendrier, entretien et assurance"></a></section>
<div class="hero-banner"><a href="illustrations/02-banniere-votre-parc-roule-fr.png"><img src="illustrations/02-banniere-votre-parc-roule-fr.png" alt="Votre parc roule. Vous gardez le contrôle. Affectations, consommations, échéances : tout se retrouve dans Dolibarr."></a></div>
<p class="note">Aperçu local du kit de publication. Les illustrations présentent le produit ; les captures ci-dessous proviennent réellement du module. Les zones sensibles restent anonymisées.</p>
<h2 class="section-title" id="video">Le module en 1 min 09 s.</h2><p>Une démonstration en français, avec voix de synthèse et sous-titres. Les captures réelles sont animées pour montrer les fonctionnalités.</p><video controls preload="metadata" aria-label="Démonstration française de Gestion des véhicules et engins" style="display:block;width:100%;aspect-ratio:16/9;background:#102447;border-radius:8px"><source src="video/demo-vehicules-engins-fr.mp4" type="video/mp4">Votre navigateur ne peut pas lire la vidéo. <a href="video/demo-vehicules-engins-fr.mp4">Télécharger le MP4</a>.</video><div class="actions"><a class="button" href="video/demo-vehicules-engins-fr.mp4" download>Télécharger la vidéo · MP4 1080p</a><a class="button secondary" href="video/sous-titres-fr.srt" download>Sous-titres · FR</a><a href="video/README.md">Scénario et sources du montage</a></div><p class="note">Données de démonstration. QUARTIX : intégration optionnelle, accès et équipements requis ; à présenter uniquement si l’archive vendue contient cette intégration.</p>
<h2 class="section-title" id="captures">Les écrans qui donnent corps à la promesse.</h2><p>Une sélection ordonnée par bénéfice. Ouvrez une image pour la consulter à sa taille originale.</p><div class="gallery">__GALLERY__</div>
<h2 class="section-title" id="description">Cinq langues. Trois champs. Prêts à copier.</h2><p>Choisissez la langue et la variante correspondant au paquet vendu. Une accroche, six bénéfices et les prérequis utiles.</p><div class="toolbar" aria-label="Langue des descriptions">__LANGUAGE_BUTTONS__</div>
<div class="field-preview"><div class="field"><label for="copy-variant">Contenu du paquet commercialisé</label><select id="copy-variant"><option value="with">Avec l’intégration QUARTIX</option><option value="without">Sans l’intégration QUARTIX</option></select></div><div class="field"><label for="module-name">Nom du module/produit</label><input id="module-name" readonly><p class="field-meta" id="name-count"></p><button type="button" id="copy-name" class="secondary">Copier le nom</button></div><div class="field"><label for="short-description">Description courte</label><textarea id="short-description" readonly rows="4"></textarea><p class="field-meta" id="short-count"></p><button type="button" id="copy-short" class="secondary">Copier la description courte</button></div><div class="field"><p class="field-meta" id="long-count"></p><button type="button" id="copy-html">Copier le HTML de la description longue</button><p class="field-meta">À coller via le bouton « Source » de l’éditeur Dolistore. Les comptages ci-dessus sont indicatifs : aucune limite n’apparaît dans le PDF fourni.</p></div></div><p id="copy-status" class="copy-status" role="status"></p><textarea id="copy-fallback" hidden aria-label="Texte à copier manuellement"></textarea>__PANELS__
<h2 class="section-title" id="fichiers">Vos fichiers de publication.</h2><div class="files"><div><h3>Illustrations et guide</h3><a href="illustrations/01-couverture-vehicules-engins-titre-fr.png" download>Couverture avec le nom du module · PNG</a><a href="illustrations/01-couverture-vehicules-engins.png" download>Variante sans texte · PNG</a><a href="illustrations/02-banniere-votre-parc-roule-fr.png" download>Bannière commerciale française · PNG</a><a href="manifest.json">Dimensions, poids, provenance et empreintes</a><a href="SOUMISSION-5-LANGUES.md">Accès aux champs des cinq langues</a><a href="GUIDE-PUBLICATION.md">Guide des autres champs de soumission</a><a href="textes/legendes-fr.md">Légendes des captures · FR</a><a href="textes/legendes-en.md">Légendes des captures · EN</a></div><div><h3>Textes EN · FR · DE · ES · IT</h3>__LANGUAGE_FILES__</div></div>
<p class="foot">Préparé le 7 septembre 2026 · Module 1.0.0 · Contenu fondé sur la branche de développement QUARTIX et les captures du wiki. Le guide de publication précise le choix de l’archive, les prérequis et les champs commerciaux à renseigner.</p></main>
<script>
const descriptions=__DATA__,longDescriptions=__HTML__;let currentLanguage='fr';
function currentCopy(){return longDescriptions[currentLanguage][document.getElementById('copy-variant').value]}
function renderCopy(){const c=descriptions[currentLanguage],copy=currentCopy();document.querySelectorAll('.copy-panel').forEach(p=>{p.hidden=p.dataset.lang!==currentLanguage;if(!p.hidden)p.querySelector('.long-copy').innerHTML=copy.html});document.querySelectorAll('[data-language]').forEach(b=>{b.classList.toggle('secondary',b.dataset.language!==currentLanguage);b.setAttribute('aria-pressed',String(b.dataset.language===currentLanguage))});const name=document.getElementById('module-name'),short=document.getElementById('short-description');name.value=c.name;short.value=copy.short;name.lang=currentLanguage;short.lang=currentLanguage;document.getElementById('name-count').textContent=Array.from(c.name).length+' caractères';document.getElementById('short-count').textContent=Array.from(copy.short).length+' caractères';document.getElementById('long-count').textContent='Description longue : '+copy.words+' mots';document.getElementById('copy-status').textContent='';document.getElementById('copy-fallback').hidden=true}
document.querySelectorAll('[data-language]').forEach(button=>button.addEventListener('click',()=>{currentLanguage=button.dataset.language;renderCopy()}));document.getElementById('copy-variant').addEventListener('change',renderCopy);
function fitShortText(){const field=document.getElementById('short-description');field.style.height='auto';field.style.height=(field.scrollHeight+2)+'px'}document.querySelectorAll('[data-language]').forEach(button=>button.addEventListener('click',fitShortText));document.getElementById('copy-variant').addEventListener('change',fitShortText);window.addEventListener('resize',fitShortText);
async function copyText(text){const status=document.getElementById('copy-status');const fallback=document.getElementById('copy-fallback');fallback.hidden=false;fallback.value=text;fallback.focus();fallback.select();status.textContent='Texte sélectionné : utilisez Ctrl+C si la copie automatique est bloquée.';try{await navigator.clipboard.writeText(text);status.textContent='Copié. Vous pouvez coller ce contenu dans votre fiche.';fallback.hidden=true}catch(error){status.textContent='Le navigateur bloque la copie automatique. Le texte est sélectionné : utilisez Ctrl+C.'}}
document.getElementById('copy-name').addEventListener('click',()=>copyText(descriptions[currentLanguage].name));
document.getElementById('copy-short').addEventListener('click',()=>copyText(currentCopy().short));
document.getElementById('copy-html').addEventListener('click',()=>copyText(currentCopy().html));renderCopy();fitShortText();
</script></body></html>'''
page = page.replace('__GALLERY__', ''.join(gallery)).replace('__PANELS__', ''.join(panels)).replace('__DATA__', json.dumps(DATA['languages'], ensure_ascii=False).replace('<', '\\u003c'))
copy_variants = {lang: {key: {'html': body_html(c, q), 'words': word_count(c, q), 'short': c['short_quartix'] if q else c['short']} for key, q in [('with', True), ('without', False)]} for lang, c in DATA['languages'].items()}
page = page.replace('__LANGUAGE_BUTTONS__', language_buttons).replace('__LANGUAGE_FILES__', language_files).replace('__HTML__', json.dumps(copy_variants, ensure_ascii=False).replace('<', '\\u003c'))
page = page.replace('<video controls preload="metadata"', '<video controls preload="metadata" poster="video/couverture-video-fr.jpg"')
write('index.html', page)

checks = {'prepared': DATA['prepared'], 'screenshots': len(manifest), 'illustrations': len(illustrations), 'source_copies_identical': all(s['source_identical'] for s in manifest), 'screenshots_without_text_or_exif_metadata': all(not s['metadata_chunks'] for s in manifest), 'php_modified': False, 'source_commit': DATA['source_commit']}
for lang, c in DATA['languages'].items():
    checks[lang] = {'name_chars': len(c['name']), 'short_chars': len(c['short']), 'short_quartix_chars': len(c['short_quartix']), 'long_words': word_count(c, False), 'long_quartix_words': word_count(c, True), 'seo_title_chars': len(c['seo_title']), 'seo_description_chars': len(c['seo_description'])}
write('checks.json', json.dumps(checks, ensure_ascii=False, indent=2))

archive = ROOT / 'kit-dolistore-vehicules-engins.zip'
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as out:
    for path in sorted(ROOT.rglob('*')):
        if path.is_file() and path != archive and '__pycache__' not in path.parts:
            out.write(path, 'kit-dolistore-vehicules-engins/' + path.relative_to(ROOT).as_posix())
with zipfile.ZipFile(archive) as verified:
    assert verified.testzip() is None
print(json.dumps({**checks, 'archive_bytes': archive.stat().st_size}, ensure_ascii=False, indent=2))
