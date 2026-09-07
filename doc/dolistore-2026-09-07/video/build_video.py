"""Render an animated screenshot demo, without altering the source images.

Requires Windows System.Speech (Microsoft Julie) and FFmpeg with libass/libx264.
All intermediate files remain in the module's ignored test/.dossier-test folder.
"""
from pathlib import Path
import argparse
import hashlib
import json
import math
import re
import struct
import subprocess
import textwrap
import wave

ROOT = Path(__file__).resolve().parent
PACK = ROOT.parent
MODULE = PACK.parent.parent
WORK = MODULE / 'test/.dossier-test/video-render'
FPS = 25
WIDTH, HEIGHT = 1920, 1080
NAVY = '0x102447'
OUTPUT = ROOT / 'demo-vehicules-engins-fr.mp4'


def run(args, log=None):
    p = subprocess.run([str(a) for a in args], cwd=WORK, capture_output=True, text=True, encoding='utf-8', errors='replace')
    if log:
        Path(log).write_text(p.stderr, encoding='utf-8')
    if p.returncode:
        raise RuntimeError(p.stderr[-6000:])
    return p


def stamp(seconds, ass=False):
    factor = 100 if ass else 1000
    value = round(seconds * factor)
    hours, value = divmod(value, 3600 * factor)
    minutes, value = divmod(value, 60 * factor)
    secs, fraction = divmod(value, factor)
    return f'{hours}:{minutes:02}:{secs:02}.{fraction:02}' if ass else f'{hours:02}:{minutes:02}:{secs:02},{fraction:03}'


def escaped(text):
    return text.replace('\\', '').replace('{', '(').replace('}', ')').replace('\n', '\\N')


def wrapped(text, width=72):
    lines = textwrap.wrap(text, width=width, break_long_words=False, break_on_hyphens=False)
    assert len(lines) <= 2, text
    if len(lines) == 2:
        words = text.split()
        candidates = [(' '.join(words[:i]), ' '.join(words[i:])) for i in range(1, len(words))]
        candidates = [pair for pair in candidates if max(map(len, pair)) <= width]
        lines = min(candidates, key=lambda pair: abs(len(pair[0]) - len(pair[1])))
    return '\n'.join(lines)


ASS = '''[Script Info]
ScriptType: v4.00+
PlayResX: 1920
PlayResY: 1080
WrapStyle: 2
ScaledBorderAndShadow: yes
[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Base,Segoe UI,42,&H00FFFFFF,&H00FFFFFF,&H00472410,&H00472410,0,0,0,0,100,100,0,0,1,0,0,7,0,0,0,1
[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
'''


def ass_scene(scene, index, total):
    lines = [ASS]
    duration = scene['duration']

    def text(value, x, y, size, color='FFFFFF', bold=False, align=7, start=0, end=None, fade=True):
        tags = f'{{\\an{align}\\pos({x},{y})\\fs{size}\\c&H{color}&\\b{1 if bold else 0}'
        tags += '\\fad(220,180)' if fade else ''
        tags += '}'
        lines.append(f'Dialogue: 1,{stamp(start, True)},{stamp(duration if end is None else end, True)},Base,,0,0,0,,{tags}{escaped(value)}\n')

    text('LES MÉTIERS DU BÂTIMENT', 90, 48, 25, 'EAD6AF', True)
    text('GESTION DES VÉHICULES ET ENGINS  /  DOLIBARR', 1830, 52, 22, 'E5D2B7', align=9)
    if scene['layout'] == 'hero':
        text(scene['title'], 90, 215 if scene['id'] == 'intro' else 175, 76 if scene['id'] == 'intro' else 67, bold=True)
        text(scene['detail'], 95, 590, 35, 'F3DCB4')
        text('LE SUIVI DE PARC, DANS VOTRE ERP', 95, 750, 23, 'EAD6AF', True)
    elif scene['layout'] == 'document':
        text(scene['title'], 90, 220, 76, bold=True)
        text(scene['detail'], 95, 490, 42, 'F3DCB4')
        text('EXTRAIT DU DOSSIER GÉNÉRÉ', 1390, 897, 21, 'E5D2B7', align=8)
    else:
        text(scene['title'], 90, 118, 58, bold=True)
        text(scene['detail'], 94, 203, 25, 'F3DCB4')
        if scene['layout'] == 'pair':
            text('UTILISATION', 98, 265, 24, 'FFFFFF', True)
            text('JOURNAL DES TRAJETS', 997, 265, 24, 'FFFFFF', True)
            text('Accès et équipements QUARTIX requis · selon l’archive du module proposée', 960, 899, 24, 'F3DCB4', align=8)
    text(f'{index + 1:02} / {total:02}', 1825, 1050, 20, 'E5D2B7', align=9)
    text('Captures animées du module · données de démonstration · zones sensibles anonymisées', 90, 1050, 18, 'E5D2B7')
    for cue in scene['cues']:
        text(wrapped(cue['text']), 960, 982, 42, align=5, start=cue['start'], end=cue['end'], fade=False)
    return ''.join(lines)


def size(path):
    data = path.read_bytes()
    assert data[:8] == b'\x89PNG\r\n\x1a\n'
    return struct.unpack('>II', data[16:24])


def compose_filter(scene, ass_file):
    duration = scene['duration']
    filters = []
    layout = scene['layout']
    sources = [PACK / p for p in scene['images']]
    if layout == 'screen':
        # Fit wide images; scroll tall source screens within a dedicated viewport.
        w, h = size(sources[0])
        scaled_h = math.ceil(h * 1740 / w / 2) * 2
        if scaled_h > 640:
            dy = scaled_h - 640
            # Show the lower documentary area sooner on intervention records.
            start = 0.70 if scene['id'] == 'interventions' else 0
            travel = duration * .60 if scene['id'] == 'interventions' else duration - .5
            expr = f'{dy}*({start}+(1-{start})*min(t/{max(1,travel):.3f},1))'
            filters.append(f"[1:v]scale=1740:{scaled_h},crop=1740:640:0:'{expr}'[shot]")
        else:
            filters.append(f'[1:v]scale=1740:{scaled_h},pad=1740:640:0:(oh-ih)/2:white[shot]')
        filters.extend(['[0:v]drawbox=x=82:y=268:w=1756:h=656:color=0x6F93B8:t=fill[frame]', '[frame][shot]overlay=90:276[v]'])
    elif layout == 'pair':
        filters.extend([
            '[1:v]scale=770:-2,pad=846:560:(ow-iw)/2:(oh-ih)/2:white[a]',
            '[2:v]scale=770:-2,pad=846:560:(ow-iw)/2:(oh-ih)/2:white[b]',
            '[0:v][a]overlay=90:310[left]', '[left][b]overlay=984:310[v]'
        ])
    elif layout == 'document':
        filters.extend(['[1:v]scale=-2:760[doc]', '[0:v][doc]overlay=1040:112[v]'])
    else:
        filters.extend([
            "[1:v]scale=760:760,zoompan=z='1+0.018*on/" + str(round(duration * FPS)) + "':x='iw/2-iw/zoom/2':y='ih/2-ih/zoom/2':d=1:s=760x760:fps=25[hero]",
            '[0:v][hero]overlay=1070:130[v]'
        ])
    filters.append(f"[v]drawbox=x=0:y=0:w=1920:h=7:color=0x47BED7:t=fill,ass={ass_file.name},fade=t=in:st=0:d=0.20:color={NAVY},fade=t=out:st={duration-.2:.3f}:d=0.20:color={NAVY},format=yuv420p[out]")
    return ';\n'.join(filters)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--ffmpeg', required=True, type=Path)
    parser.add_argument('--skip-voice', action='store_true')
    parser.add_argument('--powershell', default='pwsh.exe', help='PowerShell 7 executable with access to Windows voices')
    parser.add_argument('--scene', help='Render one scene for visual review; no final assembly.')
    args = parser.parse_args()
    ffmpeg = args.ffmpeg.resolve()
    WORK.mkdir(parents=True, exist_ok=True)
    spec = json.loads((ROOT / 'scenario-fr.json').read_text(encoding='utf-8'))
    if not args.skip_voice:
        run([args.powershell, '-NoProfile', '-File', ROOT / 'synthese-vocale.ps1', '-Scenario', ROOT / 'scenario-fr.json', '-OutputDirectory', WORK])
    offset = 0
    captions = []
    audio_format = None
    for scene in spec['scenes']:
        frames = []
        scene['cues'] = []
        position = .40
        for i, phrase in enumerate(scene['speech']):
            with wave.open(str(WORK / f"{scene['id']}-{i}.wav"), 'rb') as wav:
                fmt = (wav.getnchannels(), wav.getsampwidth(), wav.getframerate())
                assert audio_format is None or audio_format == fmt
                audio_format = fmt
                raw = wav.readframes(wav.getnframes())
                duration = wav.getnframes() / wav.getframerate()
            scene['cues'].append({'text': phrase, 'start': position, 'end': position + duration})
            captions.append({'text': phrase, 'start': offset + position, 'end': offset + position + duration})
            frames.append((position, raw))
            position += duration + .16
        duration = math.ceil((position + .38) * FPS) / FPS
        scene['duration'] = duration
        scene['start'] = offset
        offset = round(offset + duration, 6)
        channels, sample_width, sample_rate = audio_format
        audio = bytearray(round(duration * sample_rate) * channels * sample_width)
        for when, raw in frames:
            begin = round(when * sample_rate) * channels * sample_width
            audio[begin:begin + len(raw)] = raw
        with wave.open(str(WORK / f"{scene['id']}-audio.wav"), 'wb') as wav:
            wav.setnchannels(channels)
            wav.setsampwidth(sample_width)
            wav.setframerate(sample_rate)
            wav.writeframes(bytes(audio))
    print(f'Timeline: {offset:.2f} s; {len(captions)} subtitle cues', flush=True)
    (ROOT / 'sous-titres-fr.srt').write_text('\n\n'.join(f"{i}\n{stamp(c['start'])} --> {stamp(c['end'])}\n{wrapped(c['text'])}" for i, c in enumerate(captions, 1)) + '\n', encoding='utf-8')
    (WORK / 'timeline.json').write_text(json.dumps(spec, ensure_ascii=False, indent=2), encoding='utf-8')
    for index, scene in enumerate(spec['scenes']):
        if args.scene and scene['id'] != args.scene:
            continue
        print(f"Rendering {scene['id']}: {scene['duration']:.2f}s", flush=True)
        ass_path = WORK / f"{scene['id']}.ass"
        ass_path.write_text(ass_scene(scene, index, len(spec['scenes'])), encoding='utf-8-sig')
        filter_path = WORK / f"{scene['id']}.filters.txt"
        filter_path.write_text(compose_filter(scene, ass_path), encoding='utf-8')
        command = [ffmpeg, '-y', '-hide_banner', '-loglevel', 'warning', '-filter_complex_threads', '2', '-f', 'lavfi', '-i', f'color=c={NAVY}:s={WIDTH}x{HEIGHT}:r={FPS}']
        for path in scene['images']:
            command.extend(['-loop', '1', '-framerate', str(FPS), '-i', PACK / path])
        command.extend(['-i', WORK / f"{scene['id']}-audio.wav", '-filter_complex_script', filter_path, '-map', '[out]', '-map', f"{len(scene['images'])+1}:a", '-t', str(scene['duration']), '-c:v', 'libx264', '-preset', 'fast', '-crf', '19', '-pix_fmt', 'yuv420p', '-threads', '4', '-c:a', 'pcm_s16le', '-ar', '48000', '-ac', '1', WORK / f"{scene['id']}.mkv"])
        run(command, WORK / f"{scene['id']}-render.log")
    if args.scene:
        return
    (WORK / 'concat.txt').write_text(''.join(f"file '{s['id']}.mkv'\n" for s in spec['scenes']), encoding='utf-8')
    # Normalize the entire narration once, without pumping between individual shots.
    measured = run([ffmpeg, '-hide_banner', '-f', 'concat', '-safe', '0', '-i', WORK / 'concat.txt', '-vn', '-af', 'loudnorm=I=-16:TP=-1.5:LRA=11:print_format=json', '-f', 'null', '-'], WORK / 'loudness-measurement.log')
    values = json.loads(re.search(r'\{\s*"input_i".*?\}', measured.stderr, re.S).group(0))
    normalizer = 'loudnorm=I=-16:TP=-1.5:LRA=11:linear=true:' + ':'.join(f'{k}={values[v]}' for k, v in [('measured_I','input_i'),('measured_TP','input_tp'),('measured_LRA','input_lra'),('measured_thresh','input_thresh'),('offset','target_offset')])
    run([ffmpeg, '-y', '-hide_banner', '-f', 'concat', '-safe', '0', '-i', WORK / 'concat.txt', '-i', ROOT / 'sous-titres-fr.srt', '-map', '0:v', '-map', '0:a', '-map', '1:0', '-c:v', 'copy', '-af', normalizer, '-c:a', 'aac', '-b:a', '160k', '-ar', '48000', '-c:s', 'mov_text', '-metadata:s:a:0', 'language=fra', '-metadata:s:s:0', 'language=fra', '-metadata:s:s:0', 'title=Français (également incrustés)', '-disposition:s:0', '0', '-metadata', 'title=Gestion des véhicules et engins — Démonstration', '-metadata', 'comment=Captures réelles animées et anonymisées ; données de démonstration ; voix de synthèse Microsoft Julie.', '-movflags', '+faststart', OUTPUT], WORK / 'assembly.log')
    decode = run([ffmpeg, '-v', 'error', '-i', OUTPUT, '-f', 'null', '-'], WORK / 'decode.log')
    metadata = run([ffmpeg, '-hide_banner', '-i', OUTPUT, '-af', 'ebur128=peak=true', '-f', 'null', '-'], WORK / 'validation.log')
    run([ffmpeg, '-y', '-loglevel', 'error', '-ss', '0.32', '-i', OUTPUT, '-frames:v', '1', '-q:v', '2', ROOT / 'couverture-video-fr.jpg'])
    for i, scene in enumerate(spec['scenes']):
        second = scene['start'] + min(scene['duration'] / 2, scene['cues'][0]['end'] - .10)
        run([ffmpeg, '-y', '-loglevel', 'error', '-ss', str(second), '-i', OUTPUT, '-frames:v', '1', WORK / f'qa-{i:02}.png'])
    qa = {'duration_seconds': offset, 'resolution': [WIDTH, HEIGHT], 'fps': FPS, 'voice': spec['voice'], 'voice_type': 'Windows local speech synthesis', 'audio': 'AAC mono 48 kHz; integrated loudness target -16 LUFS', 'burned_in_subtitles': True, 'embedded_optional_subtitles': 'fra / mov_text / not default', 'subtitle_cues': len(captions), 'source_type': 'Animated genuine screenshots; no live screen recording', 'full_decode_errors': decode.stderr.strip(), 'bytes': OUTPUT.stat().st_size, 'sha256': hashlib.sha256(OUTPUT.read_bytes()).hexdigest(), 'sources': [{'path': p, 'sha256': hashlib.sha256((PACK/p).read_bytes()).hexdigest()} for p in sorted({p for s in spec['scenes'] for p in s['images']})], 'scenes': [{'id': s['id'], 'start': s['start'], 'duration': s['duration']} for s in spec['scenes']]}
    assert all(0 <= c['start'] < c['end'] <= offset for c in captions)
    assert all(a['end'] <= b['start'] for a,b in zip(captions, captions[1:]))
    (ROOT / 'verification-video.json').write_text(json.dumps(qa, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(f'Done: {OUTPUT} ({OUTPUT.stat().st_size / 1024**2:.1f} MiB)', flush=True)


if __name__ == '__main__':
    main()
