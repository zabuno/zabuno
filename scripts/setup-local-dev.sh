#!/usr/bin/env bash
#
# Yerel Zabuno geliştirme ortamını sıfırdan, tekrar tekrar çalıştırılabilir
# şekilde kurar. Hangi worktree'den çağrılırsa çağrılsın AYNI kalıcı
# veritabanını ve AYNI QA hesabını hedefler.
#
# Çözdüğü sorun: veritabanı ve .env ayarları worktree'nin içinde yaşıyordu.
# Worktree silinince/yeniden kurulunca hesap ve veri "kayboluyordu". Kalıcı
# durum artık worktree'lerin dışında; bu script her checkout'u ona bağlar.
#
# Kullanım:
#   scripts/setup-local-dev.sh              # kur/onar ve doğrula
#   scripts/setup-local-dev.sh --backup     # önce zaman damgalı yedek al
#
# Idempotent: istediğiniz kadar çalıştırın, var olanı bozmaz.

set -euo pipefail

STATE_DIR="${ZABUNO_STATE_DIR:-$HOME/.zabuno}"
DB_PATH="$STATE_DIR/local-dev.sqlite"
SERVICE="zabuno-test-account"
EMAIL="${ZABUNO_TEST_EMAIL:-tolgaaksen@gmail.com}"
ACCOUNT_NAME="${ZABUNO_TEST_NAME:-Tolga Aksen}"

cd "$(dirname "$0")/.."
ROOT="$(pwd)"

say() { printf '  %s\n' "$1"; }

echo "Zabuno yerel geliştirme kurulumu"
say "checkout : $ROOT"
say "kalıcı DB: $DB_PATH"

mkdir -p "$STATE_DIR"

# --- 1. .env ---------------------------------------------------------------
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --no-interaction >/dev/null
    say ".env      : .env.example'dan oluşturuldu, APP_KEY üretildi"
else
    say ".env      : mevcut"
fi

# Bir anahtarı .env'de garanti eder. Değer tırnaklanır — tırnaksız boşluk
# dotenv'i tamamen bozar ve uygulama 500 verir.
ensure_env() {
    local key="$1" value="$2"
    if grep -qE "^${key}=" .env; then
        KEY="$key" VALUE="$value" python3 - <<'PY'
import os, re
key, value = os.environ["KEY"], os.environ["VALUE"]
with open(".env") as fh:
    body = fh.read()
body = re.sub(rf'^{re.escape(key)}=.*$', f'{key}="{value}"', body, flags=re.M)
with open(".env", "w") as fh:
    fh.write(body)
PY
    else
        printf '%s="%s"\n' "$key" "$value" >> .env
    fi
}

ensure_env DB_CONNECTION sqlite
ensure_env DB_DATABASE "$DB_PATH"
ensure_env LOCAL_TEST_ACCOUNT_EMAIL "$EMAIL"
ensure_env LOCAL_TEST_ACCOUNT_NAME "$ACCOUNT_NAME"
say "env       : DB_DATABASE ve hesap değişkenleri kalıcı konuma bağlandı"

php artisan config:clear >/dev/null 2>&1 || true

# --- 2. İsteğe bağlı yedek -------------------------------------------------
if [ "${1:-}" = "--backup" ] && [ -f "$DB_PATH" ]; then
    STAMP="$(date +%Y%m%dT%H%M%S)"
    sqlite3 "$DB_PATH" ".backup '$STATE_DIR/local-dev.$STAMP.sqlite'"
    say "yedek     : $STATE_DIR/local-dev.$STAMP.sqlite"
fi

# --- 3. Veritabanı ---------------------------------------------------------
if [ ! -f "$DB_PATH" ]; then
    touch "$DB_PATH"
    say "veritabanı: yeni oluşturuldu, göçler çalıştırılıyor"
    php artisan migrate --force --no-interaction >/dev/null
else
    php artisan migrate --force --no-interaction >/dev/null
    say "veritabanı: mevcut, bekleyen göçler uygulandı"
fi

# --- 4. QA hesabı ----------------------------------------------------------
if PASSWORD="$(security find-generic-password -s "$SERVICE" -a "$EMAIL" -w 2>/dev/null)"; then
    LOCAL_TEST_ACCOUNT_EMAIL="$EMAIL" \
    LOCAL_TEST_ACCOUNT_PASSWORD="$PASSWORD" \
    LOCAL_TEST_ACCOUNT_NAME="$ACCOUNT_NAME" \
        php artisan db:seed --class="Database\\Seeders\\LocalTestAccountSeeder" --no-interaction >/dev/null
    say "hesap     : $EMAIL hazır (parola Keychain'den)"
else
    say "hesap     : ATLANDI — Keychain'de kayıt yok"
    say "            security add-generic-password -U -a $EMAIL -s $SERVICE -w"
fi

# --- 5. Doğrulama ----------------------------------------------------------
echo "Doğrulama"
php artisan tinker --execute="
\$u = \App\Models\User::where('email', '$EMAIL')->first();
echo 'ZCHK account=' . (\$u ? 'id' . \$u->id . ' verified=' . (\$u->hasVerifiedEmail() ? 'yes' : 'NO') : 'MISSING') . PHP_EOL;
echo 'ZCHK db=' . config('database.connections.sqlite.database') . PHP_EOL;
" 2>/dev/null | sed -n 's/^ZCHK account=/  hesap     : /p; s/^ZCHK db=/  veritabanı: /p'

echo "Hazır."
