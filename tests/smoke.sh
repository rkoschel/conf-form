#!/usr/bin/env bash
# Smoke-Test gegen die deployte Installation (siehe SPEC §11).
# Prüft Erreichbarkeit, Zugriffssperren und Header von außen.
#
#   tests/smoke.sh https://christen-in-hamm.de/konferenz
#
# Nur für den Server gedacht: lokal fehlen Basic Auth und echte 404
# (php -S liefert für unbekannte Pfade die index.php aus).

set -uo pipefail

base="${1:-}"
[[ -n "$base" ]] || { echo "Aufruf: $0 <basis-url>" >&2; exit 2; }
base="${base%/}"
fail=0

check() { # check <pfad> <erwartete Codes als Regex>
    local got
    got="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$base$1")"
    if [[ "$got" =~ ^($2)$ ]]; then
        printf "  ok      %-42s %s\n" "$1" "$got"
    else
        printf "  FEHLER  %-42s %s (erwartet %s)\n" "$1" "$got" "$2"
        fail=1
    fi
}

assert() { # assert <beschreibung> <befehl...>
    local desc="$1"; shift
    if "$@"; then
        printf "  ok      %s\n" "$desc"
    else
        printf "  FEHLER  %s\n" "$desc"
        fail=1
    fi
}

echo "Smoke-Test: $base"

echo "Öffentlich erreichbar:"
for p in / /register/ /cancel/ /assets/app.css /assets/vendor/bootstrap/bootstrap.min.css \
         /assets/vendor/bootstrap/bootstrap.bundle.min.js; do
    check "$p" 200
done

echo "Admin nur mit Login:"
check /admin/ 401
check /admin/events.php 401

echo "Gesperrt:"
for p in /app/ /app/schema.sql /app/bootstrap.php /app/db.php /app/templates/layout.php \
         /app/lib/PHPMailer/PHPMailer.php /.htaccess /app/.htaccess /admin/.htaccess \
         /assets/ /assets/vendor/; do
    check "$p" '403|404'
done

echo "Nicht deployt:"
for p in /config.example.php /config.prod.php /config.local.php /deploy.env /deploy.sh \
         /test.sh /README.md /SPEC.md /.git/HEAD /dev/router.php /tests/smoke.sh /tools/install.sh; do
    check "$p" 404
done

echo "Inhalt und Header:"
headers="$(curl -sI --max-time 15 "$base/")"
body="$(curl -s --max-time 15 "$base/")"
assert "Infoseite rendert (<main> vorhanden)" grep -q '<main' <<< "$body"
assert "keine PHP-Fehlermeldung" bash -c '! grep -qiE "fatal error|warning:|notice:|deprecated:|Konfiguration nicht gefunden|app_secret" <<< "$1"' _ "$body"
assert "kein X-Powered-By" bash -c '! grep -qi "^x-powered-by:" <<< "$1"' _ "$headers"
assert "X-Content-Type-Options: nosniff" grep -qi '^x-content-type-options: nosniff' <<< "$headers"
assert "X-Frame-Options: DENY" grep -qi '^x-frame-options: deny' <<< "$headers"
assert "Referrer-Policy: same-origin" grep -qi '^referrer-policy: same-origin' <<< "$headers"
assert "Infoseite setzt kein Cookie" bash -c '! grep -qi "^set-cookie:" <<< "$1"' _ "$headers"

if [[ "$base" == https://* ]]; then
    echo "HTTPS:"
    redirect="$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 15 "http://${base#https://}/")"
    assert "HTTP leitet auf HTTPS um ($redirect)" [ "$redirect" = "301 $base/" ]
fi

echo
if (( fail )); then
    echo "Smoke-Test FEHLGESCHLAGEN"
    exit 1
fi
echo "Smoke-Test ok"
