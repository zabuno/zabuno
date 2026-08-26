#!/usr/bin/env bash
#
# Yerel Zabuno test hesabını macOS Keychain'deki paroladan geri kurar.
#
# Hesap yalnız yerel veritabanında yaşar; migrate:fresh, yeni bir worktree veya
# herhangi bir veritabanı sıfırlaması onu siler. Keychain kaydı kalıcıdır, bu
# script ikisini birleştirir.
#
# Kullanım:
#   scripts/restore-local-test-account.sh [e-posta]
#
# Parolayı bir kez Keychain'e koymak için:
#   security add-generic-password -U -a <e-posta> -s zabuno-test-account -w
#   (-w değer verilmezse parola interaktif sorulur ve argümanlara düşmez)

set -euo pipefail

SERVICE="zabuno-test-account"
EMAIL="${1:-tolgaaksen@gmail.com}"

if ! command -v security >/dev/null 2>&1; then
    echo "HATA: macOS 'security' aracı bulunamadı; bu script yalnız macOS içindir." >&2
    exit 1
fi

if ! PASSWORD="$(security find-generic-password -s "$SERVICE" -a "$EMAIL" -w 2>/dev/null)"; then
    echo "HATA: Keychain'de kayıt yok (servis=$SERVICE, hesap=$EMAIL)." >&2
    echo "Şununla ekleyin: security add-generic-password -U -a $EMAIL -s $SERVICE -w" >&2
    exit 1
fi

cd "$(dirname "$0")/.."

LOCAL_TEST_ACCOUNT_EMAIL="$EMAIL" \
LOCAL_TEST_ACCOUNT_PASSWORD="$PASSWORD" \
    php artisan db:seed --class="Database\\Seeders\\LocalTestAccountSeeder" --no-interaction
