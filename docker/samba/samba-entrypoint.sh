#!/bin/sh
set -eu

if [ -z "${SAMBA_PASSWORD:-}" ]; then
    echo "SAMBA_PASSWORD is not set" >&2
    exit 1
fi

printf '%s\n%s\n' "$SAMBA_PASSWORD" "$SAMBA_PASSWORD" | smbpasswd -s -a rechnungen >/dev/null

exec smbd --foreground --no-process-group --debug-stdout
