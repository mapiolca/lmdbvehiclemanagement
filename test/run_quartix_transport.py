#!/usr/bin/env python3
"""Check actual QWS request bytes locally; no account or internet access required."""

import argparse
from http.server import BaseHTTPRequestHandler, HTTPServer
import json
import os
from pathlib import Path
import ssl
import subprocess
import tempfile
import threading


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("htdocs", help="Read-only Dolibarr core checkout")
    parser.add_argument("--php", default="php")
    parser.add_argument("--openssl", default="openssl")
    args = parser.parse_args()
    errors = []
    requests = []
    auth = {"CustomerID": "test-company", "UserName": 'Test +é & "QWS"',
            "Password": 'fake +&="\\é/', "Application": "test-provider.app"}
    steps = [
        ("POST", "/v2/api/auth", auth, None, 200,
         {"AccessToken": "fake-access", "RefreshToken": "fake-refresh+&=quote\"\\/"}),
        ("GET", "/v2/api/vehicles?VehicleIDList=10%2C20", None, "fake-access", 401, None),
        ("POST", "/v2/api/auth/refresh", {"RefreshToken": "fake-refresh+&=quote\"\\/"}, None, 200,
         {"AccessToken": "fake-new-access", "RefreshToken": "fake-new-refresh"}),
        ("GET", "/v2/api/vehicles?VehicleIDList=10%2C20", None, "fake-new-access", 200,
         [{"VehicleId": 10}]),
    ]

    class Handler(BaseHTTPRequestHandler):
        def log_message(self, *_args):
            pass

        def respond(self):
            index = len(requests)
            requests.append(self.command)
            try:
                assert index < len(steps), "Unexpected additional request"
                method, path, payload, token, status, data = steps[index]
                assert (self.command, self.path) == (method, path), "Incorrect method, endpoint or encoded query"
                assert self.headers.get("Host") == "qws.quartix.net", "Canonical host changed"
                assert self.headers.get("Accept") == "application/json", "JSON response not requested"
                assert self.headers.get("AccessToken") == token, "Token missing or sent during authentication"
                body = self.rfile.read(int(self.headers.get("Content-Length", "0")))
                if payload is not None:
                    assert self.headers.get_content_type() == "application/json", "POST must use JSON, not form encoding"
                    assert json.loads(body) == payload, "JSON fields or special characters changed"
                else:
                    assert body == b"" and self.headers.get("Content-Type") is None, "GET unexpectedly has a request body or content type"
            except (AssertionError, ValueError) as error:
                # Fixed failure labels only; never print request content or credentials.
                errors.append(str(error) if isinstance(error, AssertionError) else "Invalid JSON body")
                status, data = 422, None
            response = json.dumps({"Meta": {"Code": 0}, "Data": data}).encode()
            self.send_response(status)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(response)))
            self.end_headers()
            self.wfile.write(response)

        do_POST = respond
        do_GET = respond

    test_dir = Path(__file__).resolve().parent
    with tempfile.TemporaryDirectory(prefix="qx-https-", dir=test_dir) as directory:
        certificate = Path(directory) / "fixture.pem"
        key = Path(directory) / "fixture.key"
        subprocess.run([args.openssl, "req", "-x509", "-newkey", "rsa:2048", "-nodes", "-days", "1",
                        "-subj", "/CN=qws.quartix.net", "-addext", "subjectAltName=DNS:qws.quartix.net",
                        "-keyout", str(key), "-out", str(certificate)],
                       check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, timeout=30)
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        context.load_cert_chain(certificate, key)
        with HTTPServer(("127.0.0.1", 0), Handler) as server:
            server.socket = context.wrap_socket(server.socket, server_side=True)
            thread = threading.Thread(target=server.serve_forever, daemon=True)
            thread.start()
            try:
                result = subprocess.run([args.php, str(test_dir / "run_quartix_transport.php"), args.htdocs,
                                         str(server.server_port), str(certificate)],
                                        env={**os.environ, "NO_PROXY": "*"}, timeout=60, check=False)
            finally:
                server.shutdown()
                thread.join(timeout=5)
    if errors or len(requests) != len(steps):
        raise SystemExit("HTTPS fixture failed: " + "; ".join(errors or ["Missing requests"]))
    if result.returncode:
        raise SystemExit(result.returncode)
    print("4 HTTPS requests verified: JSON auth/refresh, unchanged GET, tokens and special characters.")


if __name__ == "__main__":
    main()
