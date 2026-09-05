# syntax=docker/dockerfile:1.7

# Zabuno üretim imajı — netcup VPS (AMD EPYC, linux/amd64).
#
# Üç aşama var ve ayrı olmalarının sebebi boyut değil, YÜZEY: derleme
# araçları (node, composer, git) çalışan sunucuda bulunmamalı. Son imaj
# yalnız PHP çalışma zamanını ve derlenmiş çıktıyı taşır.

# --- 1) Ön yüz varlıkları -------------------------------------------------
FROM node:22-bookworm-slim AS assets

WORKDIR /build

# Önce yalnız manifest: bağımlılık katmanı, kaynak her değiştiğinde
# yeniden kurulmasın.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY resources ./resources
COPY lang ./lang
COPY scripts ./scripts
COPY vite.config.ts tsconfig.json ./

# Çeviri projeksiyonları derleme zamanında üretilir. `docs/40` bunun
# çalışma zamanına taşınmasını planlıyor; o gelene kadar imaj, katalogları
# içinde dondurur ve PO değişikliği yeni imaj gerektirir.
RUN npm run i18n:build && npm run build

# --- 2) Ortak PHP tabanı ---------------------------------------------------
# Eklentiler TEK yerde kurulur ve hem bağımlılık çözümü hem çalışma zamanı
# aynı tabandan türer. Ayrı kurulsalardı, `composer install` bir kümede
# doğrulanır, uygulama başka bir kümede koşardı — ve fark, ancak QR ya da
# PDF üretilirken ortaya çıkardı.
#
# Küme `composer.json`'daki `ext-*` bildirimleriyle birebir olmalı. Bu
# derleme, bildirim ile gerçeğin ayrıştığı gün DURUR: ilk yazıldığında tam
# olarak öyle oldu, çünkü `composer` imajında gd yoktu.
FROM php:8.3-fpm-bookworm AS php-base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
# `vips` KARARI (docs/49 Faz 1, 2026-08-27).
#
# `gd` kalıyor: QR ve PDF üretimi onu kullanıyor ve çalışıyor.
#
# `vips` EKLENİYOR, çünkü medya motoru `gd` ile yazılamaz:
#   - `gd` görüntüyü tümüyle belleğe açar; tepe bellek dosya boyutuyla
#     büyür. `vips` sequential access ile sabit bellekte çalışır — 30 MB'lık
#     bir fotoğraf sunucuyu düşürmemeli.
#   - `gd`'de ICC profili yönetimi yok; CMYK ya da AdobeRGB bir fotoğraf
#     sRGB'ye çevrilmeden menüde yanlış renkte görünür.
#   - Decompression bomb'u DECODE ETMEDEN reddetmek başlık okumayı
#     gerektirir; `vips` bunu sağlar.
RUN install-php-extensions \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        vips \
        zip

# --- 3) PHP bağımlılıkları -------------------------------------------------
FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /build
COPY composer.json composer.lock ./

# `--no-scripts`: artisan burada yok, paket keşfi çalışma zamanına kalır.
# `--no-dev`: geliştirme bağımlılıkları üretim imajına girmez.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

# --- 4) Çalışma zamanı -----------------------------------------------------
FROM php-base AS runtime

# `clamdscan` — virüs tarayıcının İNCE İSTEMCİSİ (DEPLOY-MALWARE-SCAN-12).
# Daemon ve imza veritabanı bu imaja GİRMEZ; onlar compose'daki `clamav`
# servisinde yaşar. Buraya yalnız soketten konuşan istemci girer, birkaç
# megabayttır. `clamav-daemon` ya da `clamav-freshclam` eklenmez.
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        nginx supervisor curl clamdscan \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Opcache üretim ayarı. `validate_timestamps=0` bilinçli: imaj değişmezdir,
# dosya değişikliği yalnız yeni imajla gelir. Bu ayarın FTP iş akışını
# sessizce öldürdüğü not edilmişti (`docs/13` §7) — orada tehlike, burada
# doğru davranış, çünkü burada dosya elle değiştirilmiyor.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'expose_php=Off'; \
        # Yükleme sınırları. İmaj varsayılanı 2M/8M'dir ve uygulamanın
        # ilan ettiği sınırın (50 MB, `StoreMediaRequest`) çok altında
        # kalıyordu: telefonla çekilmiş neredeyse her fotoğraf reddediliyordu.
        #
        # Daha kötüsü, `post_max_size` aşıldığında PHP gövdeyi TAMAMEN atar;
        # `$_FILES` boş gelir ve Laravel "dosya alanı zorunludur" der.
        # Kullanıcı dosyayı yükler ve ona yüklemediği söylenir.
        echo 'upload_max_filesize=50M'; \
        # Gövde, dosyadan biraz büyük olmalı: aynı istekte alt metin, slot
        # ve CSRF alanı da gidiyor.
        echo 'post_max_size=52M'; \
        # 50 MB'lık bir görüntüyü işlemek 128M'ye sığmaz.
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=120'; \
    } > /usr/local/etc/php/conf.d/zabuno.ini

WORKDIR /var/www/html

COPY --from=vendor /build/vendor ./vendor
COPY . .
COPY --from=assets /build/public/build ./public/build
COPY --from=assets /build/lang ./lang
COPY --from=assets /build/resources/js/i18n/generated ./resources/js/i18n/generated

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/zabuno.conf
COPY docker/entrypoint.sh /usr/local/bin/zabuno-entrypoint
# Tarama sarmalayıcısı: compose'un `MEDIA_SCANNER_CLAMAV_BINARY_PATH`
# varsayılanı tam bu yoldur; iki uç aynı yolu söylemek zorunda. Çalıştırma
# biti açıkça verilir — adaptör `is_executable` ile bakar ve kapalıysa
# hiçbir dosyayı ilerletmez.
COPY docker/zabuno-scan /usr/local/bin/zabuno-scan
COPY docker/clamd.client.conf /etc/clamav/zabuno-clamd.conf
RUN chmod +x /usr/local/bin/zabuno-entrypoint /usr/local/bin/zabuno-scan \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

# Sağlık kontrolü uygulamanın kendi ucundan geçer; nginx'in ayakta olması
# uygulamanın çalıştığı anlamına gelmez.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1


ENTRYPOINT ["zabuno-entrypoint"]
