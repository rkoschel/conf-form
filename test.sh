#!/usr/bin/env bash
# Lint, statische Analyse und Tests (siehe SPEC §11).
#
#   ./test.sh                       alles
#   ./test.sh --testsuite unit      Argumente gehen an PHPUnit

set -euo pipefail
cd "$(dirname "$0")"

if [[ ! -f tools/phpunit.phar || ! -f tools/phpstan.phar ]]; then
    echo "Fehler: Tools fehlen – tools/install.sh ausführen" >&2
    exit 1
fi

echo "== php -l"
fail=0
while IFS= read -r -d '' file; do
    out="$(php -l "$file" 2>&1)" || { echo "$out"; fail=1; }
done < <(find web dev tests -name '*.php' -not -path 'web/app/lib/*' -print0)
(( fail == 0 )) || exit 1
echo "ok"

echo "== PHPStan"
php -d memory_limit=1G tools/phpstan.phar analyse --no-progress

echo "== PHPUnit"
php tools/phpunit.phar "$@"
