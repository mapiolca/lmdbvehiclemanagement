"""Local browser regression fixture. No Dolibarr session, QWS call or real GPS data.

python test/run_quartix_browser.py <Dolibarr htdocs> [--revision HEAD]
Open the printed URL. --revision serves the selected Git version of the JS.
Only allowlisted module/Leaflet assets are served, on the loopback interface.
"""

import argparse
import html
import json
import subprocess
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlsplit

ROOT = Path(__file__).resolve().parents[1]
ENDPOINT = "/erp/modules/vehicles/vehicle_route.php"
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("core", type=Path)
parser.add_argument("--port", type=int, default=8765)
parser.add_argument("--revision")
args = parser.parse_args()
leaflet = args.core / "includes/leaflet"
if not (leaflet / "leaflet.js").is_file():
    parser.error("Native Dolibarr Leaflet is required")
source = (subprocess.check_output(["git", "show", args.revision + ":js/quartix_route.js"], cwd=ROOT)
          if args.revision else (ROOT / "js/quartix_route.js").read_bytes())
loaded = set()
requests = []


class Fixture(BaseHTTPRequestHandler):
    def log_message(self, *_):
        pass

    def reply(self, body, mime="text/html; charset=utf-8", status=200):
        body = body.encode() if isinstance(body, str) else body
        self.send_response(status)
        self.send_header("Content-Type", mime)
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_POST(self):
        body = parse_qs(self.rfile.read(int(self.headers.get("Content-Length", 0))).decode())
        if body.get("token") != ["fixture-only"] or body.get("action") != ["retrieve"]:
            self.reply('{"data":null,"error":"CSRF refused"}', "application/json", 403)
            return
        self.do_GET()

    def do_GET(self):
        url = urlsplit(self.path)
        query = parse_qs(url.query)
        day = query.get("day", ["1"])[0]
        if url.path == "/":
            links = " | ".join(f'<a href="{ENDPOINT}?day={i}">{label}</a>' for i, label in enumerate(
                ["Cached route", "Missing route / POST", "Privacy denial", "Expired session / HTML", "Tile failure", "Leaflet failure"], 1))
            self.reply('<h1>QUARTIX browser regression</h1><p>Synthetic data only.</p>' + links)
        elif url.path == "/script.js":
            self.reply(source, "application/javascript")
        elif url.path == "/style.css":
            self.reply((ROOT / "css/quartix_route.css").read_bytes(), "text/css")
        elif url.path in ("/leaflet.js", "/leaflet.css", "/images/marker-icon.png", "/images/marker-icon-2x.png", "/images/marker-shadow.png"):
            mime = "application/javascript" if url.path.endswith(".js") else "text/css" if url.path.endswith(".css") else "image/png"
            self.reply((leaflet / url.path.lstrip("/")).read_bytes(), mime)
        elif url.path.startswith("/tiles/"):
            self.reply('<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#eef3ee"/><path d="M0 128H256M128 0V256" stroke="#ccc"/><text x="20" y="30">Synthetic tile</text></svg>', "image/svg+xml")
        elif url.path == ENDPOINT and query.get("format") == ["json"]:
            requests.append(self.command + " " + url.path + " day=" + day)
            if day == "4":
                self.reply("<h1>Login required</h1>")
                return
            if day == "3":
                self.reply(json.dumps({"data": None, "error": "Access denied"}), "application/json", 403)
                return
            if self.command == "POST":
                loaded.add(day)
            missing = day == "2" and day not in loaded
            self.reply(json.dumps({"error": "", "data": {
                "state": "missing" if missing else "ready", "message": "Not loaded" if missing else "Route loaded",
                "points": [] if missing else [[48.1, 2.1], [48.12, 2.13], [48.2, 2.2]],
                "fetched_label": "" if missing else "Synthetic cached route", "in_progress": day == "2",
                "can_request": missing, "poll": False,
            }}), "application/json")
        elif url.path == ENDPOINT:
            tiles = f"http://127.0.0.1:{args.port}/" + ("bad-tiles" if day == "5" else "tiles") + "/{z}/{x}/{y}"
            options = {"tiles": tiles, "attribution": "Local fixture", "start": "Departure", "end": "Arrival",
                       "loading": "Loading", "failure": "Browser load/display failed", "tilesFailure": "Tiles failed; route retained"}
            self.reply(f'''<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/leaflet.css"><link rel="stylesheet" href="/style.css"><style>.hidden {{ display:none }}body {{font:16px sans-serif}}pre {{white-space:pre-wrap;overflow-wrap:anywhere}}</style>
{'' if day == '6' else '<script src="/leaflet.js"></script>'}<script src="/script.js"></script>
<h1>QUARTIX synthetic route — case {html.escape(day)}</h1><a href="/">Cases</a>
<form id="qx-route-form" method="POST" action="{ENDPOINT}">
<input type="hidden" name="token" value="fixture-only"><input type="hidden" name="action" value="retrieve">
<input type="hidden" name="day" value="{html.escape(day)}"><input type="hidden" name="trip" value="public-fixture">
<p id="qx-route-status" role="status"></p><p id="qx-route-fetched"></p><p id="qx-route-provisional" class="hidden">Provisional route</p>
<div id="qx-route-map" class="hidden" role="region" aria-label="Synthetic route"></div>
<button id="qx-route-load">Load route</button></form><script type="application/json" id="qx-route-options">{json.dumps(options)}</script>
<pre id="diagnostic"></pre><script>document.addEventListener('DOMContentLoaded', function () {{
document.getElementById('diagnostic').textContent = 'Native form.action = ' + String(document.getElementById('qx-route-form').action);
}});</script></html>''')
        elif url.path == "/requests":
            self.reply(html.escape("\n".join(requests)), "text/plain")
        else:
            if "HTMLInputElement" in url.path:
                requests.append(self.command + " " + url.path + " WRONG ENDPOINT")
            self.reply("Not found", status=404)


print(f"Open http://127.0.0.1:{args.port}/ (Ctrl+C stops the fixture)", flush=True)
ThreadingHTTPServer(("127.0.0.1", args.port), Fixture).serve_forever()
