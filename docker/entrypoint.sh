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

# Plan kataloğu ŞEMA DEĞİL VERİDİR; ayrı bir tohumdur (`docs/90`).
#
# Çalıştırması güvenli: var olan bir plan koduna dokunmaz, dolayısıyla
# sahibin panelden yaptığı fiyat düzenlemesi her dağıtımda geri alınmaz.
# Buraya konmasaydı üretimde katalog boş kalır ve fiyat sayfası "henüz
# yayımlanmadı" demeye devam ederdi.
log "plan kataloğu tohumlanıyor"
php artisan db:seed --class=Database\\Seeders\\PlanCatalogueSeeder --force

# Sayfa kütüğü de ŞEMA DEĞİL VERİDİR — `docs/128`.
#
# Kurumsal sitenin her adresi `content_pages` tablosunda bir satırdır ve
# `ShowCorporatePageController` kütükte olmayan bir yola 404 verir. Bu satır
# yazılana kadar kütüğü ÜRETİMDE dolduran hiçbir adım yoktu: komut depoda
# vardı, testleri de vardı, ama onu çalıştıran tek yer testlerdi. Sonucu,
# plan kataloğuyla aynı sınıftan bir kusurdu — dağıtım yeşil, konteyner
# ayakta, ve yazılmış kurumsal sayfaların hiçbiri açılamıyor.
#
# Çalıştırması güvenli ve TEKRARLANABİLİR: komut yıkıcı değildir, var olan
# bir kaydı çoğaltmaz, ve bir insanın verdiği yayın kararına DOKUNMAZ —
# yayın durumu, yayın tarihi ve geçmiş korunur. Yalnız kütükte olmayan
# yolları `planned` olarak ekler ve belgeden gelen alanları tazeler.
# (`ImportSiteMapCommand`, `ImportSiteMapCommandTest`.)
#
# Yayın durumunu İLERLETEN komut (`site:sync-content-status`) buraya
# BİLEREK konmadı. Kalite kapısı insanların işidir; bir betiğin her
# dağıtımda geçtiği kapı, kapı değildir. Dağıtım kütüğü DOLDURUR, karar
# vermez.
#
# Kaynak belge imajın içindedir: `.dockerignore` `docs` dizinini eler ama
# bu tek dosyayı geri alır. Geri alma bir gün silinirse komut dosyayı
# bulamaz ve BAŞARISIZ olur — `set -e` gereği konteyner hiç açılmaz ve
# sağlık kontrolü deploy'u kırmızıya çeker. Kütüğü dolmamış bir dağıtımın
# sessizce yeşil görünmesindense açıkça durması yeğdir.
log "sayfa kütüğü içe aktarılıyor"
php artisan site:import-map

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
