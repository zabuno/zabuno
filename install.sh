#!/usr/bin/env bash
#
# Zabuno — tek komutla kurulum.
#
#   sudo ./install.sh
#
# Sıfırdan bir Ubuntu/Debian sunucuda çalışır: Docker'ı kurar, gizli
# değerleri üretir, yığını ayağa kaldırır ve gerçekten cevap verdiğini
# doğrular. Yeniden çalıştırılabilir — var olanı bozmaz, eksik olanı
# tamamlar.
#
# Ortam değişkeniyle sorusuz da çalışır:
#
#   ZABUNO_DOMAINS="zabuno.com, www.zabuno.com, e-menum.net" sudo -E ./install.sh
#
set -euo pipefail

readonly INSTALL_DIR="${ZABUNO_DIR:-/opt/zabuno}"
readonly REPO_URL="${ZABUNO_REPO:-https://github.com/zabuno/zabuno.git}"

log()  { printf '\033[1;36m[zabuno]\033[0m %s\n' "$1"; }
warn() { printf '\033[1;33m[zabuno]\033[0m %s\n' "$1"; }
die()  { printf '\033[1;31m[zabuno] HATA:\033[0m %s\n' "$1" >&2; exit 1; }

# --- 0. Ön koşullar --------------------------------------------------------

[ "$(id -u)" -eq 0 ] || die "Bu betik root gerektirir: sudo ./install.sh"

command -v apt-get >/dev/null 2>&1 \
    || die "Bu betik Debian/Ubuntu içindir. Başka dağıtımda docker'ı elle kurup 'docker compose up -d' çalıştırın."

# --- 1. Docker -------------------------------------------------------------

install_docker() {
    log "Docker kuruluyor (resmî apt deposu)"

    apt-get update -qq
    apt-get install -y -qq ca-certificates curl gnupg git >/dev/null

    install -m 0755 -d /etc/apt/keyrings

    # `curl | sh` YOK. Anahtar indirilir, doğrulanabilir bir depo eklenir;
    # betik indirip körlemesine çalıştırmak, sunucuyu yazarına teslim eder.
    if [ ! -f /etc/apt/keyrings/docker.asc ]; then
        curl -fsSL https://download.docker.com/linux/"$(. /etc/os-release && echo "$ID")"/gpg \
            -o /etc/apt/keyrings/docker.asc
        chmod a+r /etc/apt/keyrings/docker.asc
    fi

    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/$(. /etc/os-release && echo "$ID") \
$(. /etc/os-release && echo "${VERSION_CODENAME}") stable" \
        > /etc/apt/sources.list.d/docker.list

    apt-get update -qq
    apt-get install -y -qq \
        docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin >/dev/null

    systemctl enable --now docker
}

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    log "Docker zaten kurulu — atlanıyor"
else
    install_docker
fi

# --- 2. Kaynak -------------------------------------------------------------

if [ -d "${INSTALL_DIR}/.git" ]; then
    log "Depo güncelleniyor: ${INSTALL_DIR}"
    git -C "${INSTALL_DIR}" fetch --quiet origin
    git -C "${INSTALL_DIR}" reset --quiet --hard origin/main
else
    log "Depo klonlanıyor: ${INSTALL_DIR}"
    mkdir -p "$(dirname "${INSTALL_DIR}")"
    git clone --quiet "${REPO_URL}" "${INSTALL_DIR}"
fi

cd "${INSTALL_DIR}"

# --- 3. Yapılandırma -------------------------------------------------------
#
# `.env` ASLA depoya girmez ve ASLA üzerine yazılmaz. Var olan bir kurulumu
# yeniden çalıştırmak, üretilmiş parolaları değiştirip veritabanını
# erişilemez hâle getirmemeli.

if [ -f .env ]; then
    log ".env zaten var — korunuyor"
else
    if [ -z "${ZABUNO_DOMAINS:-}" ]; then
        printf '\nAlan adlarını virgülle yazın (örn: zabuno.com, www.zabuno.com, e-menum.net)\n> '
        read -r ZABUNO_DOMAINS
    fi

    [ -n "${ZABUNO_DOMAINS}" ] || die "En az bir alan adı gerekli."

    # İlk alan adı `APP_URL` olur. Diğerleri eşit derecede geçerlidir;
    # `APP_URL` yalnız arka plan işleri (kuyruk, e-posta, ödeme geri
    # çağrısı) için bir tabana ihtiyaç duyulduğunda kullanılır. Web
    # isteklerinde adresler isteğin KENDİ host'undan üretilir.
    primary="$(echo "${ZABUNO_DOMAINS}" | cut -d, -f1 | xargs)"

    # Güvenilir host listesi: şema ve boşluk temizlenmiş hâli.
    trusted="$(echo "${ZABUNO_DOMAINS}" | tr -d ' ')"

    db_password="$(head -c 32 /dev/urandom | base64 | tr -d '/+=' | head -c 32)"

    log "Secret'lar üretiliyor"

    cat > .env <<ENV
APP_ENV=production
APP_DEBUG=false
APP_URL=https://${primary}

# Caddy bu listedeki her alan adı için sertifika alır ve yeniler.
ZABUNO_DOMAINS=${ZABUNO_DOMAINS}

# Kabul edilen Host başlıkları. Kanonik host BİLİNÇLİ olarak boş: bu bir
# SaaS ve her alan adı kendi adreslerini yayar; biri diğerine yönlendirilmez.
URL_TRUSTED_HOSTS=${trusted}
URL_ENFORCE_HOST=false
URL_ENFORCE_SCHEME=true
URL_CANONICAL_SCHEME=https

DB_DATABASE=zabuno
DB_USERNAME=zabuno
DB_PASSWORD=${db_password}
ENV

    chmod 600 .env
fi

# `APP_KEY` ayrı üretilir: `.env` elle yazılmış olabilir ve anahtarı
# eksik olabilir. Bir kez üretilir, bir daha DEĞİŞTİRİLMEZ — değişirse
# şifrelenmiş her oturum ve her saklı değer okunamaz hâle gelir.
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    log "Uygulama anahtarı üretiliyor"
    key="base64:$(head -c 32 /dev/urandom | base64)"
    sed -i '/^APP_KEY=/d' .env
    echo "APP_KEY=${key}" >> .env
fi

# --- 4. İmaj ---------------------------------------------------------------

if [ -n "${ZABUNO_IMAGE:-}" ]; then
    log "Hazır imaj çekiliyor: ${ZABUNO_IMAGE}"
    docker pull --quiet "${ZABUNO_IMAGE}" >/dev/null
else
    log "İmaj kaynaktan derleniyor (ilk sefer birkaç dakika sürer)"
    ZABUNO_IMAGE="zabuno:local"
    docker build --quiet -f docker/Dockerfile -t "${ZABUNO_IMAGE}" . >/dev/null
fi

echo "ZABUNO_IMAGE=${ZABUNO_IMAGE}" > .image.env

# --- 5. Yayına alma --------------------------------------------------------

log "Yığın başlatılıyor"
docker compose --env-file .env --env-file .image.env up -d --remove-orphans

# --- 6. Doğrulama ----------------------------------------------------------
#
# Konteynerin ayakta olması uygulamanın çalıştığı anlamına gelmez. Yeşil
# bir kurulum çıktısı, düşmüş bir siteyi gizlememeli.

log "Uygulamanın cevap vermesi bekleniyor"

for attempt in $(seq 1 30); do
    if docker compose --env-file .env --env-file .image.env exec -T app \
        curl -fsS http://127.0.0.1:8080/up >/dev/null 2>&1; then
        log "Uygulama ayakta."
        break
    fi

    if [ "${attempt}" -eq 30 ]; then
        warn "Uygulama 90 saniyede cevap vermedi. Günlükler:"
        docker compose --env-file .env --env-file .image.env logs --tail 40 app
        die "Kurulum tamamlanmadı."
    fi

    sleep 3
done

# --- 7. CI/CD anahtarı -----------------------------------------------------
#
# Sahibi depoyu güncellediğinde site kendini güncellemeli. Bunun için
# GitHub'ın bu sunucuya SSH ile bağlanabilmesi gerekiyor. Anahtar BURADA
# üretilir ve yapıştırılacak değerler ekrana yazılır — böylece kurulum tek
# oturumda biter ve mühendisin bir daha dönmesi gerekmez.

readonly DEPLOY_KEY="/root/.ssh/zabuno_deploy"

if [ ! -f "${DEPLOY_KEY}" ]; then
    log "Deploy anahtarı üretiliyor"
    install -m 700 -d /root/.ssh
    ssh-keygen -t ed25519 -N '' -C 'zabuno-deploy' -f "${DEPLOY_KEY}" >/dev/null
fi

# Açık anahtar yetkili listeye BİR KEZ eklenir.
touch /root/.ssh/authorized_keys
chmod 600 /root/.ssh/authorized_keys
if ! grep -qF "$(cat "${DEPLOY_KEY}.pub")" /root/.ssh/authorized_keys; then
    cat "${DEPLOY_KEY}.pub" >> /root/.ssh/authorized_keys
fi

public_ip="${ZABUNO_PUBLIC_IP:-$(curl -fsS --max-time 5 https://api.ipify.org 2>/dev/null || echo '')}"
[ -n "${public_ip}" ] || public_ip="<sunucunun-IP-adresi>"

primary_domain="$(grep '^APP_URL=' .env | cut -d/ -f3)"

# Değerleri dosyaya da yaz: terminal geçmişi silinse de erişilebilsin.
secrets_file="${INSTALL_DIR}/github-secrets.txt"
{
    echo "DEPLOY_HOST=${public_ip}"
    echo "DEPLOY_USER=root"
    echo "DEPLOY_HEALTH_URL=https://${primary_domain}/up"
    echo
    echo "DEPLOY_KNOWN_HOSTS:"
    ssh-keyscan -H "${public_ip}" 2>/dev/null
    echo
    echo "DEPLOY_SSH_KEY:"
    cat "${DEPLOY_KEY}"
} > "${secrets_file}"
chmod 600 "${secrets_file}"

cat <<SECRETS

  ─────────────────────────────────────────────────────────────────────
  GitHub'a eklenecek beş secret
  ─────────────────────────────────────────────────────────────────────

  Depo → Settings → Secrets and variables → Actions → New repository secret

    DEPLOY_HOST        ${public_ip}
    DEPLOY_USER        root
    DEPLOY_HEALTH_URL  https://${primary_domain}/up

    DEPLOY_KNOWN_HOSTS ve DEPLOY_SSH_KEY uzun oldukları için dosyada:

      cat ${secrets_file}

  Beşi eklendiğinde CI/CD tamamlanır: sahibi \`main\`'e her birleştirdiğinde,
  CI yeşilse site kendini günceller.

  UYARI: ${secrets_file} bir ÖZEL ANAHTAR içerir. Değerleri GitHub'a
  ekledikten sonra dosyayı silin:  shred -u ${secrets_file}
  ─────────────────────────────────────────────────────────────────────

SECRETS

cat <<DONE

  Kurulum tamam.

  Alan adları : $(grep '^ZABUNO_DOMAINS=' .env | cut -d= -f2-)
  Dizin       : ${INSTALL_DIR}
  Durum       : docker compose --env-file .env --env-file .image.env ps

  DNS A kaydı bu sunucuya yönlendirildiğinde Caddy sertifikayı kendisi alır.
  Sertifika gelene kadar https:// hata verebilir; bu normaldir.

  Sağlık kontrolü: https://${primary_domain}/up

DONE
