#!/usr/bin/env bash
# Lädt die Entwicklungs-Tools (nicht im Repo, nicht deployt) mit fester
# Version und prüft die SHA-256-Prüfsumme.
#
# Neue Version: URL und Prüfsumme anpassen (sha256sum tools/<name>.phar).

set -euo pipefail
cd "$(dirname "$0")"

tools=(
    "phpunit.phar https://phar.phpunit.de/phpunit-11.5.56.phar 915fa161f496dc04a45cd6032855879bca0bab644048cd0516982dffe678e9f1"
    "phpstan.phar https://github.com/phpstan/phpstan/releases/download/2.2.16/phpstan.phar 1a2fb5460c142502d3cd06272529c18b19fda004ada974bd2581cb9fd6c0a53b"
)

for entry in "${tools[@]}"; do
    read -r name url sha <<< "$entry"
    if [[ -f "$name" ]] && echo "$sha  $name" | sha256sum -c --quiet 2>/dev/null; then
        echo "ok     $name"
        continue
    fi
    echo "lade   $name"
    curl -sfL -o "$name.tmp" "$url"
    if ! echo "$sha  $name.tmp" | sha256sum -c --quiet; then
        rm -f "$name.tmp"
        echo "Fehler: Prüfsumme von $name stimmt nicht" >&2
        exit 1
    fi
    mv "$name.tmp" "$name"
done
