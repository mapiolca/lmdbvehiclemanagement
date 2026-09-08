"""Local browser regression fixture. No Dolibarr session, QWS call or real GPS data.

python test/run_quartix_browser.py <Dolibarr htdocs> [--revision HEAD]
Open the printed URL. --revision serves the selected Git version of the JS.
Renders the production PHP template with the native translator and jQuery UI.
Only allowlisted assets are served, on the loopback interface.
"""

import argparse
import html
import json
import subprocess
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlsplit

ROOT = Path(__file__).resolve().parents[1]
ENDPOINT = "/erp/modules/lmdbvehiclemanagement/vehicle_route.php"
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("core", type=Path)
parser.add_argument("--port", type=int, default=8765)
parser.add_argument("--revision")
args = parser.parse_args()
leaflet = args.core / "includes/leaflet"
if not (leaflet / "leaflet.js").is_file():
    parser.error("Native Dolibarr Leaflet is required")
source = subprocess.check_output(["git", "show", args.revision + ":js/quartix_route.js"], cwd=ROOT) if args.revision else None
loaded = set()
requests = []
ui_images = {"/images/" + path.name: path for path in (args.core / "includes/jquery/css/base/images").glob("*.png")}


def page(day, dialog):
    tiles = f"http://127.0.0.1:{args.port}/" + ("bad-tiles" if day == "5" else "tiles") + "/{z}/{x}/{y}"
    content = subprocess.check_output([
        "php", str(ROOT / "test/render_quartix_route.php"), str(args.core),
        "dialog" if dialog else "direct", day, tiles,
    ]).decode("utf-8")
    links = " | ".join(f'<a class="qx-route-open" href="{ENDPOINT}?day={i}&amp;trip=public-fixture&amp;quartix_id=35">{label}</a>'
                       for i, label in enumerate(["Cached route", "Missing route / POST", "Privacy denial",
                           "Expired session / HTML", "Tile failure", "Leaflet failure", "Slow response",
                           "Privacy revoked", "Trip completed on refresh"], 1))
    return f'''<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/jquery-ui.css"><link rel="stylesheet" href="/leaflet.css"><link rel="stylesheet" href="/style.css">
<style>body {{font:14px sans-serif}}div.hidden,span.hidden {{display:none}}
div.info,div.warning,div.error {{padding:16px;margin:1em 0;border-radius:5px;border-left:5px solid}}
div.info {{background:#eff8fc;border-color:#87bfc2;color:#558}}div.warning {{background:#fcf8e3;border-color:#f2cf87}}
div.error {{background:#efcfcf;border-color:#f28787}}</style>
<script src="/jquery.js"></script><script src="/jquery-ui.js"></script>
{'' if day == '6' else '<script src="/leaflet.js"></script>'}<script src="/script.js"></script>
<h1>QUARTIX synthetic journal</h1><nav id="navigation-history">Parent navigation history</nav>
<p>Synthetic data only. Minimal Eldy alert/hidden rules, native jQuery UI and Leaflet.</p>
{links if dialog else '<a href="/">Cases</a>'}{content}
<footer id="debugbar">Parent debug bar</footer></html>'''


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
        try:
            self.wfile.write(body)
        except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError):
            pass  # Closing the dialog aborts an in-flight request.

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
            self.reply(page(day, True))
        elif url.path == "/script.js":
            self.reply(source if source is not None else (ROOT / "js/quartix_route.js").read_bytes(), "application/javascript")
        elif url.path == "/style.css":
            self.reply((ROOT / "css/quartix_route.css").read_bytes(), "text/css")
        elif url.path in ("/jquery.js", "/jquery-ui.js", "/jquery-ui.css"):
            asset = {"/jquery.js": "js/jquery.min.js", "/jquery-ui.js": "js/jquery-ui.min.js",
                     "/jquery-ui.css": "css/base/jquery-ui.min.css"}[url.path]
            self.reply((args.core / "includes/jquery" / asset).read_bytes(), "text/css" if url.path.endswith(".css") else "application/javascript")
        elif url.path in ui_images:
            self.reply(ui_images[url.path].read_bytes(), "image/png")
        elif url.path in ("/leaflet.js", "/leaflet.css", "/images/marker-icon.png", "/images/marker-icon-2x.png", "/images/marker-shadow.png"):
            mime = "application/javascript" if url.path.endswith(".js") else "text/css" if url.path.endswith(".css") else "image/png"
            self.reply((leaflet / url.path.lstrip("/")).read_bytes(), mime)
        elif url.path.startswith("/tiles/"):
            self.reply('<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#eef3ee"/><path d="M0 128H256M128 0V256" stroke="#ccc"/><text x="20" y="30">Synthetic tile</text></svg>', "image/svg+xml")
        elif url.path == ENDPOINT and query.get("format") == ["json"]:
            if query.get("quartix_id") != ["35"]:
                self.reply(json.dumps({"data": None, "error": "QUARTIX source missing"}), "application/json", 403)
                return
            requests.append(self.command + " " + url.path + " day=" + day)
            visits = sum(entry.endswith(" day=" + day) for entry in requests)
            if day == "7":
                time.sleep(3)
            if day == "4":
                self.reply("<h1>Login required</h1>")
                return
            if day == "3" or (day == "8" and visits > 1):
                self.reply(json.dumps({"data": None, "error": "Access denied"}), "application/json", 403)
                return
            if self.command == "POST":
                loaded.add(day)
            missing = day == "2" and day not in loaded
            self.reply(json.dumps({"error": "", "data": {
                "state": "missing" if missing else "ready", "message": "Not loaded" if missing else "Route loaded",
                "message_level": "warning" if missing else "info",
                "points": [] if missing else [[48.1, 2.1], [48.12, 2.13], [48.2, 2.2]],
                "fetched_label": "" if missing else "Synthetic cached route " + day,
                "in_progress": day == "2" or (day == "9" and visits == 1),
                "can_request": missing, "poll": False,
            }}), "application/json")
        elif url.path == ENDPOINT:
            self.reply(page(day, False))
        elif url.path == "/requests":
            self.reply(html.escape("\n".join(requests)), "text/plain")
        else:
            if "HTMLInputElement" in url.path:
                requests.append(self.command + " " + url.path + " WRONG ENDPOINT")
            self.reply("Not found", status=404)


print(f"Open http://127.0.0.1:{args.port}/ (Ctrl+C stops the fixture)", flush=True)
ThreadingHTTPServer(("127.0.0.1", args.port), Fixture).serve_forever()
