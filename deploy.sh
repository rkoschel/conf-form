#!/usr/bin/env bash
# Deployment per lftp über FTPS (siehe SPEC §2.1).
#
#   ./deploy.sh              web/ nach FTP_APP_DIR spiegeln
#   ./deploy.sh --dry-run    nur anzeigen, was passieren würde
#   ./deploy.sh setup        config.prod.php und .htpasswd nach FTP_PRIVATE_DIR
#   --force                  Git-Prüfungen (Branch, sauberer Stand) überspringen

set -euo pipefail
cd "$(dirname "$0")"

die() { echo "Fehler: $*" >&2; exit 1; }

usage() {
    sed -n '2,8p' "$0" | sed 's/^# \{0,1\}//'
}

mode=deploy
dry_run=0
force=0
for arg in "$@"; do
    case "$arg" in
        setup) mode=setup ;;
        -n|--dry-run) dry_run=1 ;;
        --force) force=1 ;;
        -h|--help) usage; exit 0 ;;
        *) usage >&2; die "Unbekanntes Argument: $arg" ;;
    esac
done

# --- Konfiguration ---------------------------------------------------------

[[ -f deploy.env ]] || die "deploy.env fehlt (Vorlage: deploy.env.example)"
# shellcheck source=/dev/null
source deploy.env

: "${FTP_HOST:?FTP_HOST fehlt in deploy.env}"
: "${FTP_USER:?FTP_USER fehlt in deploy.env}"
: "${FTP_PASSWORD:?FTP_PASSWORD fehlt in deploy.env}"
: "${FTP_APP_DIR:?FTP_APP_DIR fehlt in deploy.env}"
: "${FTP_PRIVATE_DIR:?FTP_PRIVATE_DIR fehlt in deploy.env}"
FTP_VERIFY_CERT="${FTP_VERIFY_CERT:-true}"

FTP_APP_DIR="${FTP_APP_DIR%/}"
FTP_PRIVATE_DIR="${FTP_PRIVATE_DIR%/}"

# Schutz der Landingpage im selben Webroot: nur in .../konferenz schreiben
[[ "$FTP_APP_DIR" == */konferenz ]] \
    || die "FTP_APP_DIR muss auf /konferenz enden (ist: $FTP_APP_DIR)"

# Config und .htpasswd dürfen nie im Webroot landen
webroot="$(dirname "$FTP_APP_DIR")"
[[ "$FTP_PRIVATE_DIR/" != "$webroot/"* ]] \
    || die "FTP_PRIVATE_DIR liegt im Webroot $webroot (ist: $FTP_PRIVATE_DIR)"

command -v lftp >/dev/null || die "lftp nicht installiert (sudo apt install lftp)"

# --- lftp ------------------------------------------------------------------

# Passwort per Umgebungsvariable, damit es nicht in der Prozessliste steht
export LFTP_PASSWORD="$FTP_PASSWORD"

run_lftp() {
    lftp <<EOF
set cmd:fail-exit true
set ftp:ssl-force true
set ftp:ssl-protect-data true
set ssl:verify-certificate $FTP_VERIFY_CERT
set ftp:passive-mode true
set ftp:list-options -a
set net:timeout 20
set net:max-retries 2
open -u "$FTP_USER" --env-password "$FTP_HOST"
$1
EOF
}

# --- Deploy ----------------------------------------------------------------

deploy() {
    if (( ! force )); then
        branch="$(git rev-parse --abbrev-ref HEAD)"
        [[ "$branch" == main ]] || die "Nicht auf main (sondern $branch); --force zum Übergehen"
        # Geänderte, neue oder ignorierte Dateien in web/ würden mit hochgeladen
        dirty="$(git status --porcelain --ignored -- web/)"
        [[ -z "$dirty" ]] || die "web/ enthält nicht committete oder ignorierte Dateien; --force zum Übergehen:
$dirty"
    fi

    local flags="--reverse --delete --only-newer --no-perms --parallel=4 --verbose"
    (( dry_run )) && flags="$flags --dry-run"

    echo "Deploy: web/ → ftp://$FTP_HOST$FTP_APP_DIR (Commit $(git rev-parse --short HEAD))"
    (( dry_run )) && echo "(Dry-Run – es wird nichts verändert)"
    run_lftp "mirror $flags web/ \"$FTP_APP_DIR\""
}

# --- Setup -----------------------------------------------------------------

setup() {
    [[ -f config.prod.php ]] || die "config.prod.php fehlt (Vorlage: config.example.php)"
    [[ -f .htpasswd ]] || die ".htpasswd fehlt (htpasswd -c -B .htpasswd admin)"
    php -l config.prod.php >/dev/null || die "config.prod.php hat Syntaxfehler"
    php -r '$c = require "config.prod.php"; exit(strlen($c["app_secret"] ?? "") >= 32 ? 0 : 1);' \
        || die "app_secret in config.prod.php fehlt oder ist zu kurz"

    echo "Setup: config.prod.php, .htpasswd → ftp://$FTP_HOST$FTP_PRIVATE_DIR"
    if (( dry_run )); then
        echo "(Dry-Run) würde ausführen:"
        echo "  mkdir -p $FTP_PRIVATE_DIR/data          (chmod 700)"
        echo "  put config.prod.php → $FTP_PRIVATE_DIR/config.php  (chmod 600)"
        echo "  put .htpasswd       → $FTP_PRIVATE_DIR/.htpasswd   (chmod 644)"
        return
    fi
    # data/ wird nur angelegt, nie überschrieben oder gelöscht.
    # PHP läuft unter dem FTP-User: config.php und data/ nur für ihn lesbar.
    # .htpasswd bleibt 644, weil der Webserver sie ggf. als anderer User liest.
    run_lftp "mkdir -p -f \"$FTP_PRIVATE_DIR/data\"
chmod 700 \"$FTP_PRIVATE_DIR/data\"
put config.prod.php -o \"$FTP_PRIVATE_DIR/config.php\"
chmod 600 \"$FTP_PRIVATE_DIR/config.php\"
put .htpasswd -o \"$FTP_PRIVATE_DIR/.htpasswd\"
chmod 644 \"$FTP_PRIVATE_DIR/.htpasswd\"
cls -l \"$FTP_PRIVATE_DIR/\""
}

"$mode"
