#!/usr/bin/env bash
#
# Install or update this app on a cPanel account (ephraitech/ci4-platform deploy kit).
#
#   bash deploy/cpanel-deploy.sh            # after cPanel → Git Version Control → Update from Remote
#   bash deploy/cpanel-deploy.sh --pull     # pull main first (from Terminal / SSH)
#
# cPanel's "Deploy HEAD Commit" button runs this through .cpanel.yml.
# Safe to run again: it installs packages, backs up the database, runs new
# migrations (php spark platform:migrate), publishes the platform's assets,
# syncs roles and permissions, clears caches and ends with deploy:check.
# Set PHP_BIN or COMPOSER_BIN to override the binaries it finds.

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

mkdir -p writable/logs
LOG="writable/logs/deploy-$(date +%Y-%m-%d-%H%M%S).log"
exec > >(tee -a "$LOG") 2>&1

step() { printf '\n==> %s\n' "$*"; }
fail() { printf '\nDEPLOY STOPPED: %s\n' "$*" >&2; exit 1; }
trap 'fail "line $LINENO: $BASH_COMMAND"' ERR

# ---------------------------------------------------------------------------
# PHP 8.2+ for the command line, with the extensions the app cannot start
# without. On CloudLinux the plain "php" follows cPanel → Select PHP Version
# (and its ticked extensions), while calling /opt/alt/phpXX directly may load
# none of them, so "php" is tried first and every candidate must have intl,
# mbstring and mysqli loaded.
# ---------------------------------------------------------------------------
find_php() {
    local candidates=(
        "${PHP_BIN:-}"
        "$(command -v php || true)"
        /usr/local/bin/php
        /opt/cpanel/ea-php84/root/usr/bin/php
        /opt/cpanel/ea-php83/root/usr/bin/php
        /opt/cpanel/ea-php82/root/usr/bin/php
        /opt/alt/php84/usr/bin/php
        /opt/alt/php83/usr/bin/php
        /opt/alt/php82/usr/bin/php
    )
    local c
    for c in "${candidates[@]}"; do
        [[ -n "$c" && -x "$c" ]] || continue
        if "$c" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") && extension_loaded("intl") && extension_loaded("mbstring") && extension_loaded("mysqli") ? 0 : 1);' 2>/dev/null; then
            echo "$c"
            return 0
        fi
    done
    return 1
}

find_composer() {
    local c
    for c in "${COMPOSER_BIN:-}" "$(command -v composer || true)" /opt/cpanel/composer/bin/composer "$HOME/bin/composer.phar"; do
        [[ -n "$c" && -f "$c" ]] && { echo "$c"; return 0; }
    done
    return 1
}

install_composer() {
    # Official installer, checked against the published signature.
    mkdir -p "$HOME/bin"
    local expected actual
    expected="$("$PHP" -r 'echo trim(file_get_contents("https://composer.github.io/installer.sig"));')"
    "$PHP" -r 'copy("https://getcomposer.org/installer", "composer-setup.php");'
    actual="$("$PHP" -r 'echo hash_file("sha384", "composer-setup.php");')"
    if [[ "$expected" != "$actual" ]]; then
        rm -f composer-setup.php
        fail "the Composer installer did not match its signature; install Composer by hand"
    fi
    "$PHP" composer-setup.php --quiet --install-dir="$HOME/bin" --filename=composer.phar
    rm -f composer-setup.php
    echo "$HOME/bin/composer.phar"
}

PHP="$(find_php)" || fail "No PHP 8.2+ with the intl, mbstring and mysqli extensions was found. In cPanel → Select PHP Version (or MultiPHP Manager) choose 8.3 and tick those extensions, then check with: php -m | grep -iE 'intl|mbstring|mysqli'. Or set PHP_BIN=/path/to/php"
SPARK=("$PHP" "$ROOT/spark")

step "Deploy in $ROOT"
echo "PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

if [[ "${1:-}" == "--pull" ]]; then
    step "Pull main from GitHub"
    git pull --ff-only origin main
fi
echo "Code: $(git log -1 --format='%h %s' 2>/dev/null || echo 'not a git checkout')"
git describe --tags --exact-match >/dev/null 2>&1 && echo "Release: $(git describe --tags --exact-match)" || echo "Release: (no tag on this commit)"

[[ -f .env ]] || fail ".env is missing. Run: cp .env.production.example .env && chmod 600 .env, fill it in, then run php spark key:generate"
chmod 600 .env

step "Composer packages"
COMPOSER="$(find_composer)" || { echo "Composer not found; installing it to ~/bin"; COMPOSER="$(install_composer)"; }
if [[ ! -f composer.lock ]]; then
    echo "WARNING: composer.lock is not in the repository, so package versions are resolved fresh. Commit composer.lock from your machine."
fi
# Run Composer with our PHP when it is a PHP file (phar or script), so the
# right PHP version is used; run it directly when it is a shell wrapper.
if [[ "$COMPOSER" == *.phar ]] || head -c 64 "$COMPOSER" | grep -qE '^(#!.*php|<\?php)'; then
    COMPOSER_CMD=("$PHP" "$COMPOSER")
else
    COMPOSER_CMD=("$COMPOSER")
fi
COMPOSER_ALLOW_SUPERUSER=1 "${COMPOSER_CMD[@]}" install --no-dev --optimize-autoloader --no-interaction --no-progress

step "Folders"
for d in cache logs session uploads; do
    mkdir -p "writable/$d"
done
chmod 755 writable writable/cache writable/logs writable/session writable/uploads
find writable/uploads -type d -exec chmod 750 {} +

step "Backup the database before changing it"
env_value() {
    # Reads "key = value" from .env, without quotes.
    sed -nE "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*['\"]?([^'\"]*)['\"]?[[:space:]]*$/\1/p" .env | tail -n 1
}
DB_NAME="$(env_value 'database\.default\.database')"
if command -v mysqldump >/dev/null 2>&1 && [[ -n "$DB_NAME" ]]; then
    BACKUPS="$HOME/backups/$(basename "$ROOT")"
    mkdir -p "$BACKUPS" && chmod 700 "$BACKUPS"
    MYCNF="$(mktemp)"
    chmod 600 "$MYCNF"
    {
        echo "[client]"
        echo "user=\"$(env_value 'database\.default\.username')\""
        echo "password=\"$(env_value 'database\.default\.password')\""
        DB_HOST="$(env_value 'database\.default\.hostname')"
        DB_PORT="$(env_value 'database\.default\.port')"
        [[ -n "$DB_HOST" ]] && echo "host=\"$DB_HOST\""
        [[ -n "$DB_PORT" ]] && echo "port=$DB_PORT"
    } > "$MYCNF"
    DUMP="$BACKUPS/$DB_NAME-$(date +%Y-%m-%d-%H%M%S).sql.gz"
    if mysqldump --defaults-extra-file="$MYCNF" --single-transaction --quick --routines "$DB_NAME" | gzip > "$DUMP"; then
        chmod 600 "$DUMP"
        echo "Saved $DUMP"
        # Keep the 15 most recent.
        ls -1t "$BACKUPS"/"$DB_NAME"-*.sql.gz 2>/dev/null | tail -n +16 | xargs -r rm -f
    else
        rm -f "$DUMP"
        echo "WARNING: the backup failed; take one in phpMyAdmin before continuing if this is an update."
    fi
    rm -f "$MYCNF"
else
    echo "Skipped (mysqldump not available or no database in .env)."
fi

step "Database"
"${SPARK[@]}" platform:migrate --no-header

step "Platform assets"
"${SPARK[@]}" platform:publish --no-header

step "Roles and permissions"
"${SPARK[@]}" auth:sync --no-header

step "Caches"
"${SPARK[@]}" cache:clear --no-header

step "Checks"
trap - ERR
if "${SPARK[@]}" deploy:check --no-header; then
    printf '\nDeployed. Log: %s\n' "$LOG"
else
    printf '\nDeployed, but the checks above need attention. Log: %s\n' "$LOG"
fi
