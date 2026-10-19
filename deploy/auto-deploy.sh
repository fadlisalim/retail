#!/usr/bin/env bash
# Auto-deploy energi.click — dijalankan cron (user situs) tiap 2 menit.
# Ada commit baru di branch yang sedang di-checkout → pull (fast-forward),
# composer install bila composer.lock berubah, migrate, seeder yang terdaftar
# di deploy/seeders.txt (sekali per baris), lalu optimize:clear.
# Log: storage/logs/deploy.log (juga tampil di Admin → Pengaturan → Status deploy).
set -uo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
cd "$APP_DIR" || exit 1

STATE_DIR="storage/app/deploy"
LOG="storage/logs/deploy.log"
mkdir -p "$STATE_DIR" storage/logs
LAST_OK_FILE="$STATE_DIR/last-ok"
SEEDED_FILE="$STATE_DIR/seeded.txt"
touch "$SEEDED_FILE"

# Satu proses saja.
exec 9>"$STATE_DIR/deploy.lock"
flock -n 9 || exit 0

log() { echo "$(date '+%Y-%m-%d %H:%M:%S') $*" >> "$LOG"; }

BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if ! git fetch -q origin "$BRANCH" 2>>"$LOG"; then
    log "GAGAL fetch origin/$BRANCH"
    exit 1
fi

REMOTE="$(git rev-parse "origin/$BRANCH")"
LAST_OK="$(cat "$LAST_OK_FILE" 2>/dev/null || true)"
HEAD_NOW="$(git rev-parse HEAD)"

# Sudah mutakhir & deploy terakhir sukses → diam.
[ "$REMOTE" = "$LAST_OK" ] && [ "$HEAD_NOW" = "$REMOTE" ] && exit 0

# Gagal berulang pada commit yang sama: coba lagi paling sering tiap 15 menit.
if [ -f "$STATE_DIR/failed" ] && [ "$(cat "$STATE_DIR/failed")" = "$REMOTE" ] \
    && [ -n "$(find "$STATE_DIR/failed" -mmin -15 2>/dev/null)" ]; then
    exit 0
fi

fail() {
    log "GAGAL: $* — perbaiki lalu push ulang (dicoba lagi otomatis tiap 15 menit)"
    echo "$REMOTE" > "$STATE_DIR/failed"
    exit 1
}

log "=== deploy ${HEAD_NOW:0:7} → ${REMOTE:0:7} ($BRANCH)"
FROM="${LAST_OK:-$HEAD_NOW}"
git cat-file -e "$FROM" 2>/dev/null || FROM="$HEAD_NOW"
CHANGED="$(git diff --name-only "$FROM" "$REMOTE" 2>/dev/null)"

if [ "$HEAD_NOW" != "$REMOTE" ]; then
    git merge --ff-only -q "origin/$BRANCH" >> "$LOG" 2>&1 \
        || fail "git merge --ff-only (ada perubahan lokal di server? cek: git status)"
    log "kode diperbarui: $(git log -1 --format='%h %s')"
fi

if echo "$CHANGED" | grep -qx 'composer.lock'; then
    COMPOSER="$(command -v composer || echo /usr/local/bin/composer)"
    "$COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader >> "$LOG" 2>&1 \
        || fail "composer install"
    log "composer install selesai"
fi

php artisan migrate --force >> "$LOG" 2>&1 || fail "php artisan migrate"

# Seeder: baris "<id> <NamaSeeder>" di deploy/seeders.txt, masing-masing dijalankan sekali.
if [ -f deploy/seeders.txt ]; then
    while read -r ID CLASS _; do
        case "$ID" in ''|'#'*) continue ;; esac
        grep -qx "$ID" "$SEEDED_FILE" && continue
        php artisan db:seed --class="$CLASS" --force >> "$LOG" 2>&1 || fail "seeder $CLASS ($ID)"
        echo "$ID" >> "$SEEDED_FILE"
        log "seeder $CLASS ($ID) selesai"
    done < deploy/seeders.txt
fi

php artisan optimize:clear >> "$LOG" 2>&1 || fail "php artisan optimize:clear"

echo "$REMOTE" > "$LAST_OK_FILE"
rm -f "$STATE_DIR/failed"
log "OK — live di ${REMOTE:0:7}: $(git log -1 --format='%s')"
