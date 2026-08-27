#!/usr/bin/env bash
set -euo pipefail

# Zabuno konteyner girişi.
#
# Buradaki sıra keyfî değil. Önbellek, migrasyondan ÖNCE ısıtılırsa eski
# şemayı dondurur; migrasyon, veritabanı hazır olmadan çalışırsa konteyner
# döngüye girer. Her adım kendi başarısızlığını yüksek sesle bildirir —
# sessizce devam eden bir deploy, deploy değil kumar.

log() { printf '[zabuno] %s\n' "$1"; }

log "veritabanı bekleniyor: ${DB_HOST:-db}:${DB_PORT:-5432}"
for attempt in $(seq 1 30); do
    if php -r '
        $h=getenv("DB_HOST")?:"db"; $p=getenv("DB_PORT")?:"5432";
        $s=@fsockopen($h,(int)$p,$e,$m,2); exit($s?0:1);
    '; then
        log "veritabanı hazır"
        break
    fi

    if [ "$attempt" -eq 30 ]; then
        log "HATA: veritabanına 60 saniyede ulaşılamadı"
        exit 1
    fi

    sleep 2
done

# Migrasyon deploy'un parçasıdır. `--force` üretimde onay istemez; onayı
# veren, bu imajı deploy etme kararının kendisidir.
log "migrasyonlar çalıştırılıyor"
php artisan migrate --force

# Önbellekler migrasyondan SONRA: config ve route önbelleği şemayı değil
# ama view ve event keşfi kod durumunu dondurur.
log "önbellekler ısıtılıyor"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Depolama bağlantısı: yükleme dizini kalıcı hacimde yaşar.
if [ ! -L public/storage ]; then
    php artisan storage:link || log "uyarı: storage:link atlandı"
fi

log "hazır — supervisord devralıyor"
exec supervisord -c /etc/supervisor/conf.d/zabuno.conf
