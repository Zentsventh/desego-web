#!/usr/bin/env bash
set -Eeuo pipefail

REPOSITORY_PATH="/home/zentdev/repositories/desego-web"
DOCUMENT_ROOT="/home/zentdev/public_html/desego"
LOCK_PATH="/home/zentdev/.desego-deploy.lock"
LAST_DEPLOYED_PATH="/home/zentdev/.desego-deploy-last-commit"
LOG_PATH="/home/zentdev/logs/desego-deploy.log"
PHP_BIN="/usr/local/bin/ea-php84"

mkdir -p "$(dirname "$LOG_PATH")"
exec >>"$LOG_PATH" 2>&1
printf '\n[%s] Starting deployment (%s).\n' "$(date --iso-8601=seconds)" "${1:-manual}"

if [[ ! -d "$REPOSITORY_PATH/.git" || ! -f "$REPOSITORY_PATH/.env" ]]; then
    echo "Repository or production .env file is missing."
    exit 1
fi

mkdir -p "$DOCUMENT_ROOT"
exec 9>"$LOCK_PATH"
if ! flock -n 9; then
    echo "Another deployment is already running."
    exit 0
fi

cd "$REPOSITORY_PATH"

if [[ "${1:-}" == "--auto" ]]; then
    git fetch --quiet origin main
    current_commit="$(git rev-parse HEAD)"
    remote_commit="$(git rev-parse origin/main)"

    if [[ "$current_commit" == "$remote_commit" ]]; then
        echo "Already up to date."
        exit 0
    fi

    if [[ -n "$(git status --porcelain)" ]]; then
        echo "Refusing to deploy: the cPanel repository has local changes."
        git status --short
        exit 1
    fi

    if ! git diff --quiet "$current_commit" "$remote_commit" -- composer.json composer.lock && ! command -v composer >/dev/null 2>&1; then
        echo "Composer dependencies changed, but Composer is unavailable. Install it before allowing this update."
        exit 1
    fi

    git merge --ff-only origin/main
elif [[ "${1:-}" != "--deploy" ]]; then
    echo "Usage: deploy-cpanel.sh --auto|--deploy"
    exit 2
fi

current_commit="$(git rev-parse HEAD)"
last_deployed_commit="$(cat "$LAST_DEPLOYED_PATH" 2>/dev/null || true)"
composer_lock_changed=false

if [[ -n "$last_deployed_commit" ]] && git cat-file -e "$last_deployed_commit^{commit}" 2>/dev/null; then
    if ! git diff --quiet "$last_deployed_commit" "$current_commit" -- composer.json composer.lock; then
        composer_lock_changed=true
    fi
fi

if [[ "$composer_lock_changed" == "true" ]] && ! command -v composer >/dev/null 2>&1; then
    echo "composer.json or composer.lock changed, but Composer is unavailable on this hosting account."
    exit 1
fi

if [[ ! -x "$PHP_BIN" ]]; then
    PHP_BIN="$(command -v php || true)"
fi

if [[ -z "$PHP_BIN" ]]; then
    echo "PHP CLI was not found. Set PHP_BIN in deploy-cpanel.sh to the hosting PHP 8.3+ binary."
    exit 1
fi

if ! "$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then
    echo "The selected PHP CLI is older than 8.3. Set PHP_BIN to the hosting PHP 8.3+ binary."
    exit 1
fi

if [[ ! -f vendor/autoload.php ]]; then
    echo "Composer dependencies are missing. Install production dependencies before deploying."
    exit 1
fi

if [[ "$composer_lock_changed" == "true" ]]; then
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress
fi

"$PHP_BIN" artisan optimize
/bin/cp -R public/. "$DOCUMENT_ROOT/"

printf '%s\n' "$current_commit" > "$LAST_DEPLOYED_PATH"
printf '[%s] Deployment completed at commit %s.\n' "$(date --iso-8601=seconds)" "${current_commit:0:7}"
