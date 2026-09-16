"""Serve synthetic sharing forms with real module PHP/JS and native MC/jQuery assets.

python3 test/run_sharing_browser.py <Dolibarr htdocs> <Multicompany zip>
No real ERP session or data. POST replies show submitted fields without persistence.
"""
import argparse
import html
import os
import subprocess
import tempfile
import zipfile
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlsplit

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('core', type=Path)
parser.add_argument('multicompany', type=Path)
parser.add_argument('--port', type=int, default=8766)
args = parser.parse_args()
assets = {'/jquery.js': 'js/jquery.min.js', '/jquery-ui.js': 'js/jquery-ui.min.js', '/jquery-ui.css': 'css/base/jquery-ui.min.css'}
archive = zipfile.ZipFile(args.multicompany)
work = ROOT / 'test/.dossier-test'
work.mkdir(exist_ok=True)
with tempfile.TemporaryDirectory(dir=work) as temporary:
    fixture = Path(temporary) / 'sharing.html'
    subprocess.run(['php', '-d', 'error_reporting=24575', str(ROOT / 'test/run_sharing.php'), str(args.core), str(args.multicompany)], env={**os.environ, 'LMDB_SHARING_FIXTURE': str(fixture)}, check=True)
    content = fixture.read_bytes()

class Fixture(BaseHTTPRequestHandler):
    def log_message(self, *_):
        pass

    def reply(self, body, mime='text/html; charset=utf-8', status=200):
        body = body.encode() if isinstance(body, str) else body
        self.send_response(status)
        self.send_header('Content-Type', mime)
        self.send_header('Cache-Control', 'no-store')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        path = urlsplit(self.path).path
        if path == '/':
            self.reply(content)
        elif path in assets:
            self.reply((args.core / 'includes/jquery' / assets[path]).read_bytes(), 'text/css' if path.endswith('.css') else 'application/javascript')
        elif path.startswith('/multicompany/inc/multiselect/') and path[1:] in archive.namelist():
            self.reply(archive.read(path[1:]), 'text/css' if path.endswith('.css') else 'application/javascript')
        elif path == '/lmdbvehiclemanagement/js/quartix_sharing.js':
            self.reply((ROOT / 'js/quartix_sharing.js').read_bytes(), 'application/javascript')
        else:
            self.reply('Not found', status=404)

    def do_POST(self):
        data = parse_qs(self.rfile.read(int(self.headers.get('Content-Length', 0))).decode())
        self.reply('<pre>'+html.escape(str(data))+'</pre>', status=200 if data.get('token') == ['fixture'] else 403)

print(f'Open http://127.0.0.1:{args.port}/ (Ctrl+C to stop)', flush=True)
ThreadingHTTPServer(('127.0.0.1', args.port), Fixture).serve_forever()
