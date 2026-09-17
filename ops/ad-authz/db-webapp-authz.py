#!/usr/bin/env python3
import http.server
import subprocess
import urllib.parse

HOST = "127.0.0.1"
PORT = 8090
DOMAIN = "topsnet-ads.tops.net"
ALLOWED_GROUPS = {
    "db-webapp-users",
    "db-webapp-rechnungstool",
}


def normalize_user(value: str) -> str:
    value = (value or "").strip()
    if "\\" in value:
        _, value = value.rsplit("\\", 1)
    if "@" not in value:
        value = f"{value}@{DOMAIN}"
    return value.lower()


class Handler(http.server.BaseHTTPRequestHandler):
    server_version = "db-webapp-authz/1.0"

    def do_GET(self):
        parsed = urllib.parse.urlparse(self.path)
        if parsed.path != "/check":
            self.send_error(404)
            return

        params = urllib.parse.parse_qs(parsed.query)
        required_groups = [
            group.strip().lower()
            for group in params.get("group", [])
            if group.strip()
        ]
        if not required_groups or any(group not in ALLOWED_GROUPS for group in required_groups):
            self.send_error(400)
            return

        user = normalize_user(self.headers.get("X-Remote-User", ""))
        if not user or user == f"@{DOMAIN}":
            self.send_error(401)
            return

        try:
            result = subprocess.run(
                ["/usr/bin/id", "-nG", user],
                check=False,
                capture_output=True,
                text=True,
                timeout=3,
            )
        except (OSError, subprocess.TimeoutExpired):
            self.send_error(503)
            return

        if result.returncode != 0:
            self.send_error(403)
            return

        groups = {item.lower() for item in result.stdout.split()}

        for group in required_groups:
            expected = f"{group}@{DOMAIN}"
            if expected not in groups and group not in groups:
                self.send_error(403)
                return

        invoice_group = "db-webapp-rechnungstool"
        invoice_expected = f"{invoice_group}@{DOMAIN}"

        self.send_response(204)
        if invoice_group in groups or invoice_expected in groups:
            self.send_header("X-DB-Webapp-Rechnungstool", "1")
        self.end_headers()

    def log_message(self, fmt, *args):
        return


if __name__ == "__main__":
    http.server.ThreadingHTTPServer((HOST, PORT), Handler).serve_forever()
