#!/usr/bin/env bash
# Zabuno — READ-ONLY sunucu denetimi (OPS-READ-01).
#
# Bu betik SUNUCUDA çalışır: runner onu `ssh ... bash -s -- "$DEPLOY_DIR"`
# ile stdin'den gönderir. Tek argümanı mutlak DEPLOY_DIR'dir.
#
# SÖZLEŞME — bu betik ASLA:
#   dosya yazmaz/silmez, `docker run`/`docker compose up`/`restart`/`stop`
#   çalıştırmaz, veritabanı geri yüklemez, yapılandırma/port değiştirmez,
#   kimlik bilgisi üretmez, ağdan indirme yapmaz, ortam değişkeni dökmez,
#   müşteri satırı basmaz. Yalnız sabit, salt-okunur komutlar çalıştırır.
#
# OLGU SÖZLÜĞÜ — bu betikte değişmezdir:
#   yes / no    : okuma BAŞARILI oldu; olgu var / yok
#   none        : okuma BAŞARILI oldu; sonuç kümesi boş (gerçek sıfır)
#   absent      : okuma BAŞARILI oldu; aranan nesne sunucuda yok
#   unknown     : okuma BAŞARISIZ/erişilemez — bu ASLA "temiz hayır" değildir
# Kural: bir komutun ÇIKIŞ DURUMU okunmadan hiçbir `no`, `absent` ya da `0`
# basılmaz. Başarısız okuma ile başarılı-boş okuma birbirine karıştırılmaz.

set -uo pipefail
umask 077

DEPLOY_DIR="${1:-}"
case "$DEPLOY_DIR" in
  /*) : ;;
  *) echo "audit_fatal=deploy_dir_not_absolute"; exit 2 ;;
esac
case "$DEPLOY_DIR" in
  *..*|*' '*) echo "audit_fatal=deploy_dir_rejected"; exit 2 ;;
esac

say() { printf '%s=%s\n' "$1" "$2"; }
have() { command -v "$1" >/dev/null 2>&1; }
first_line() { printf '%s\n' "$1" | head -n 1; }

# cap: sabit bir komutu çalıştırır, ÇIKTIYI ve ÇIKIŞ DURUMUNU birlikte saklar.
# Boru hattı kullanılmaz; boru hattı çıkış durumunu yutar ve `no`/`0` uydurur.
# `timeout` yoksa (asgari imajlar) komut zaman sınırı olmadan koşar.
if have timeout; then
  cap() { CAP_OUT="$(timeout 25 "$@" 2>/dev/null)"; CAP_RC=$?; CAP_OUT="${CAP_OUT:0:20000}"; }
else
  cap() { CAP_OUT="$("$@" 2>/dev/null)"; CAP_RC=$?; CAP_OUT="${CAP_OUT:0:20000}"; }
fi
CAP_OUT=""
CAP_RC=0

# Konteyner içinde sabit, salt-okunur bir kabuk parçacığı koşturur.
# Parçacıklar okunamayan durumda BİLEREK sıfırdan farklı çıkar (exit 3),
# böylece "okunamadı" ile "gerçekten boş" ayrılır.
dexec() { cap docker exec "$1" sh -c "$2"; }

# Okuma başarılıysa ilk satırı, değilse `unknown` basar.
say_dexec() {
  dexec "$2" "$3"
  if [ "$CAP_RC" -ne 0 ]; then say "$1" unknown; else say "$1" "$(first_line "$CAP_OUT")"; fi
}

# grep tabanlı bayrak: 0=eşleşti, 1=eşleşmedi, digeri=HATA → unknown.
grep_flag() {
  grep -Eqi "$1" "$2" 2>/dev/null
  case $? in
    0) echo yes ;;
    1) echo no ;;
    *) echo unknown ;;
  esac
}

# Tek satır `encode` yönergesinde bir sıkıştırıcı simgesi arar.
# Desen ORTASINDA `^` KULLANILMAZ: POSIX/GNU ERE'de ortadaki `^` ölü bir dala
# dönüşür ve `encode gzip` gibi İLK simge kaçırılırdı (yerel ugrep bunu
# yanlışlıkla eşleştirip yeşil gösteriyordu). Simgeler açıkça sayılır, böylece
# `encode gzip`, `encode gzip zstd` ve ters sıra aynı biçimde çözümlenir.
encode_flag() {
  grep_flag '^[[:space:]]*encode([[:space:]]+[^[:space:]{}]+)*[[:space:]]+'"$1"'([[:space:]]|$)' "$2"
}

echo "### section=meta"
say audit_version "ops-read-01"
say audit_started_utc "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
say deploy_dir_present "$([ -d "$DEPLOY_DIR" ] && echo yes || echo no)"

DOCKER_OK=no
if have docker; then
  say docker_cli yes
  # Daemon erişimi ayrı bir olgudur: CLI var ama daemon/izin yoksa her docker
  # okuması BAŞARISIZDIR ve `unknown` döner, "yok" DEĞİL.
  cap docker version --format '{{.Server.Version}}'
  if [ "$CAP_RC" -eq 0 ] && [ -n "$CAP_OUT" ]; then
    DOCKER_OK=yes
    say docker_daemon "reachable"
    say docker_server_version "$(first_line "$CAP_OUT")"
  else
    say docker_daemon "unreachable_or_denied"
    say docker_server_version unknown
  fi
else
  say docker_cli no
  say docker_daemon unknown
  say docker_server_version unknown
fi

echo "### section=disk"
disk_line() {
  if ! have df; then say "$2" unknown; return; fi
  cap df -P -h "$1"
  if [ "$CAP_RC" -ne 0 ]; then say "$2" unknown; return; fi
  say "$2" "$(printf '%s\n' "$CAP_OUT" | awk 'NR!=1{print "mount="$6" size="$2" used="$3" avail="$4" use="$5; exit}')"
}
disk_line / disk_root
if [ -d "$DEPLOY_DIR" ]; then
  disk_line "$DEPLOY_DIR" disk_deploy_dir
else
  say disk_deploy_dir unknown
fi

echo "### section=compose"
PROJECT=unknown
if [ "$DOCKER_OK" = yes ]; then
  cap docker ps -a --filter "label=com.docker.compose.project.working_dir=$DEPLOY_DIR" \
    --format '{{.Label "com.docker.compose.project"}}'
  if [ "$CAP_RC" -ne 0 ]; then
    say compose_project_read unknown
  elif [ -z "$CAP_OUT" ]; then
    say compose_project_read "ok_empty"
  else
    say compose_project_read ok
    PROJECT="$(first_line "$CAP_OUT")"
  fi
else
  say compose_project_read unknown
fi
say compose_project "$PROJECT"

# svc_lookup: SVC_NAME + SVC_STATUS (found|none|unknown) ayarlar.
SVC_NAME=""
SVC_STATUS=unknown
svc_lookup() {
  SVC_NAME=""
  if [ "$DOCKER_OK" != yes ] || [ "$PROJECT" = unknown ]; then SVC_STATUS=unknown; return; fi
  cap docker ps -a --filter "label=com.docker.compose.project=$PROJECT" \
    --filter "label=com.docker.compose.service=$1" --format '{{.Names}}'
  if [ "$CAP_RC" -ne 0 ]; then SVC_STATUS=unknown; return; fi
  if [ -z "$CAP_OUT" ]; then SVC_STATUS=none; return; fi
  SVC_NAME="$(first_line "$CAP_OUT")"
  SVC_STATUS=found
}

# inspect_field: okunamazsa `unknown`, okunup boş dönerse `empty`.
inspect_field() {
  cap docker inspect -f "$2" "$1"
  if [ "$CAP_RC" -ne 0 ]; then printf 'unknown'; return; fi
  if [ -z "$CAP_OUT" ]; then printf 'empty'; return; fi
  printf '%s' "$(first_line "$CAP_OUT")"
}

DBC=""
DB_STATUS=unknown
APPC=""
APP_STATUS=unknown
for svc in app db clamav proxy; do
  svc_lookup "$svc"
  case "$SVC_STATUS" in
    unknown) say "svc_${svc}" unknown ;;
    none)    say "svc_${svc}" absent ;;
    found)
      say "svc_${svc}" "state=$(inspect_field "$SVC_NAME" '{{.State.Status}}') health=$(inspect_field "$SVC_NAME" '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}') restarts=$(inspect_field "$SVC_NAME" '{{.RestartCount}}')"
      ;;
  esac
  case "$svc" in
    db)  DBC="$SVC_NAME"; DB_STATUS="$SVC_STATUS" ;;
    app) APPC="$SVC_NAME"; APP_STATUS="$SVC_STATUS" ;;
  esac
done

echo "### section=db_exposure"
if [ "$DB_STATUS" = found ]; then
  cap docker inspect -f '{{range $p, $c := .NetworkSettings.Ports}}{{if $c}}{{$p}} {{end}}{{end}}' "$DBC"
  if [ "$CAP_RC" -ne 0 ]; then
    say db_published_ports unknown
  else
    pub="$(first_line "$CAP_OUT")"
    if [ -n "${pub// /}" ]; then say db_published_ports "yes:${pub}"; else say db_published_ports no; fi
  fi
else
  say db_published_ports unknown
fi
if have ss; then
  cap ss -H -ltn
  LSN_RC="$CAP_RC"
elif have netstat; then
  cap netstat -ltn
  LSN_RC="$CAP_RC"
else
  LSN_RC=127
fi
if [ "$LSN_RC" -ne 0 ]; then
  say host_listening_5432 unknown
  say host_listening_5432_local_addrs unknown
else
  say host_listening_5432 "$(printf '%s\n' "$CAP_OUT" | awk '$4 ~ /:5432$/{n++} END{print (n?"yes":"no")}')"
  # Yalnız YEREL ADRES alanı (ss/netstat 4. sutun) — surec/PID/ortam basilmaz.
  # Sinirli: en cok 4 farkli adres, gerisi "+N_more" olarak sayilir.
  # Amac: 127.0.0.1:5432 (loopback) ile 0.0.0.0:5432 / [::]:5432 (tum
  # arayuzler) ayrimini "yes" degerinin belirsizliginden kurtarmak.
  say host_listening_5432_local_addrs "$(printf '%s\n' "$CAP_OUT" | awk '
    $4 ~ /:5432$/ && !seen[$4]++ { if (n < 4) out = (n ? out "," : "") $4; n++ }
    END { if (!n) print "none"; else if (4 < n) print out ",+" (n-4) "_more"; else print out }')"
fi

echo "### section=metabase"
if [ "$DOCKER_OK" != yes ]; then
  say metabase_present unknown
else
  cap docker ps -a --filter 'name=metabase' --format '{{.Names}}'
  if [ "$CAP_RC" -ne 0 ]; then
    say metabase_present unknown
  elif [ -z "$CAP_OUT" ]; then
    say metabase_present no
  else
    MBC="$(first_line "$CAP_OUT")"
    say metabase_present yes
    say metabase_state "$(inspect_field "$MBC" '{{.State.Status}}')"
    say metabase_networks "$(inspect_field "$MBC" '{{range $n, $_ := .NetworkSettings.Networks}}{{$n}} {{end}}')"
    say metabase_published "$(inspect_field "$MBC" '{{range $p, $c := .NetworkSettings.Ports}}{{if $c}}{{$p}} {{end}}{{end}}')"
  fi
fi

echo "### section=host_caddy"
# Depodaki docker/Caddyfile kenar proxy DEĞİLDİR; aktif kenar host Caddy'sidir.
CADDY_SITE="/etc/caddy/sites-enabled/zabuno.com"
if [ -r "$CADDY_SITE" ]; then
  say caddy_site_readable yes
  # `encode` yönergesi ve her sıkıştırıcı AYRI olgudur: biri varken ikisi de
  # varmış gibi tek bayrak basmak durumu OLDUĞUNDAN İYİ gösterirdi.
  say caddy_encode_directive "$(grep_flag '^[[:space:]]*encode([[:space:]{]|$)' "$CADDY_SITE")"
  # `encode` iki biçimde yazılır: TEK SATIR (`encode gzip zstd`) ve BLOK
  # (`encode {` … `}`). Bu betik yalnız tek satır biçimini ÇÖZÜMLER.
  eb="$(grep_flag '^[[:space:]]*encode([[:space:]]+[^[:space:]{}]+)*[[:space:]]*\{' "$CADDY_SITE")"
  say caddy_encode_block "$eb"
  eg="$(encode_flag gzip "$CADDY_SITE")"
  ez="$(encode_flag zstd "$CADDY_SITE")"
  # Blok biçimi çözümlenmedi: eşleşmeyen sıkıştırıcı `no` DEĞİL `unknown`'dır.
  # Çözümlenmemiş yapılandırmadan "yok" SONUCU ÇIKARILMAZ.
  if [ "$eb" != no ] && [ "$eg" = no ]; then eg=unknown; fi
  if [ "$eb" != no ] && [ "$ez" = no ]; then ez=unknown; fi
  say caddy_encode_gzip "$eg"
  say caddy_encode_zstd "$ez"
  if [ "$eg" = unknown ] || [ "$ez" = unknown ]; then
    say caddy_encode_gzip_and_zstd unknown
  elif [ "$eg" = yes ] && [ "$ez" = yes ]; then
    say caddy_encode_gzip_and_zstd yes
  else
    say caddy_encode_gzip_and_zstd no
  fi
  say caddy_hsts "$(grep_flag 'strict-transport-security' "$CADDY_SITE")"
  bd="$(grep_flag 'request_body|max_size|client_max_body' "$CADDY_SITE")"
  bs="$(grep_flag '52[[:space:]]*(MB|M)([^A-Za-z0-9]|$)' "$CADDY_SITE")"
  say caddy_body_limit_directive "$bd"
  say caddy_body_limit_52mb_literal "$bs"
  if [ "$bd" = unknown ] || [ "$bs" = unknown ]; then
    say caddy_max_body_52mb unknown
  elif [ "$bd" = yes ] && [ "$bs" = yes ]; then
    say caddy_max_body_52mb yes
  else
    say caddy_max_body_52mb no
  fi
  say caddy_forwarded_proto "$(grep_flag 'X-Forwarded-Proto' "$CADDY_SITE")"
else
  # Okunamayan dosya "yapılandırma yok" DEĞİLDİR; yalnız okunamadı.
  say caddy_site_readable no
  for k in caddy_encode_directive caddy_encode_block caddy_encode_gzip caddy_encode_zstd \
           caddy_encode_gzip_and_zstd caddy_hsts caddy_body_limit_directive \
           caddy_body_limit_52mb_literal caddy_max_body_52mb caddy_forwarded_proto; do
    say "$k" unknown
  done
fi

echo "### section=backups"
if [ "$DOCKER_OK" = yes ] && [ "$PROJECT" != unknown ]; then
  cap docker volume ls --filter "label=com.docker.compose.project=$PROJECT" \
    --filter 'label=com.docker.compose.volume=db-backups' --format '{{.Name}}'
  if [ "$CAP_RC" -ne 0 ]; then say backup_volume unknown
  elif [ -z "$CAP_OUT" ]; then say backup_volume absent
  else say backup_volume "$(first_line "$CAP_OUT")"; fi
else
  say backup_volume unknown
fi

if [ "$DB_STATUS" = found ]; then
  # Her parçacık: /backups okunamıyorsa exit 3 → `unknown`. Boş dizin ise
  # gerçek 0 / `none` basılır. `ls | wc -l` tek başına ikisini ayırt edemez.
  # `[ -d ]` TEK BAŞINA YETMEZ: dizin var ama okunamaz (`-r`) ya da içine
  # girilemez (`-x`) olabilir; o durumda `ls` başarısız olur, boru hattı
  # başarısızlığı yutar ve `wc -l` UYDURMA bir `0` basardı.
  BK='[ -d /backups ] && [ -r /backups ] && [ -x /backups ] || exit 3;'
  dexec "$DBC" "$BK"' df -h /backups'
  if [ "$CAP_RC" -ne 0 ]; then
    say backup_mount_df unknown
  else
    say backup_mount_df "$(printf '%s\n' "$CAP_OUT" | awk 'NR!=1{print "size="$2" used="$3" avail="$4; exit}')"
  fi
  # Sayım artık `ls | wc -l` boru hattıyla DEĞİL, doğrudan kabuk glob'uyla
  # yapılır: eşleşme yoksa gerçek `0`, dizin okunamıyorsa yukarıdaki guard
  # yüzünden `unknown`. Aradaki fark artık kaybolmuyor.
  say_dexec backup_dump_count "$DBC" "$BK"' n=0; for f in /backups/*.dump; do [ -e "$f" ] && n=$((n+1)); done; echo "$n"'
  say_dexec backup_archive_count "$DBC" "$BK"' n=0; for f in /backups/*.tar /backups/*.tar.gz /backups/*.tgz; do [ -e "$f" ] && n=$((n+1)); done; echo "$n"'
  say_dexec backup_total_kb "$DBC" "$BK"' d=$(du -sk /backups 2>/dev/null | cut -f1); [ -n "$d" ] || exit 3; echo "$d"'
  say_dexec backup_newest "$DBC" "$BK"' f=$(ls -t /backups/*.dump 2>/dev/null | head -n 1); [ -n "$f" ] || { echo none; exit 0; }; stat -c "%n size=%s mtime=%y" "$f" || exit 3'
  say_dexec backup_oldest "$DBC" "$BK"' f=$(ls -t /backups/*.dump 2>/dev/null | tail -n 1); [ -n "$f" ] || { echo none; exit 0; }; stat -c "%n mtime=%y" "$f" || exit 3'
  # pg_restore --list yalnız DOĞRULAR: --dbname yok, çıktı bastırılır,
  # hiçbir geri yükleme yapılmaz. Araç yoksa sonuç `unknown`.
  say_dexec backup_pg_restore_list_ok "$DBC" "$BK"' command -v pg_restore >/dev/null 2>&1 || exit 3; f=$(ls -t /backups/*.dump 2>/dev/null | head -n 1); [ -n "$f" ] || { echo none; exit 0; }; if pg_restore --list "$f" >/dev/null 2>&1; then echo yes; else echo no; fi'
  say_dexec backup_media_tar_list_ok "$DBC" "$BK"' command -v tar >/dev/null 2>&1 || exit 3; f=$(ls -t /backups/*.tar /backups/*.tar.gz /backups/*.tgz 2>/dev/null | head -n 1); [ -n "$f" ] || { echo none; exit 0; }; if tar -tf "$f" >/dev/null 2>&1; then echo yes; else echo no; fi'
else
  for k in backup_mount_df backup_dump_count backup_archive_count backup_total_kb \
           backup_newest backup_oldest backup_pg_restore_list_ok backup_media_tar_list_ok; do
    say "$k" unknown
  done
fi

echo "### section=backup_schedule"
# Zamanlanmış yedek işi: yalnız VARLIK, sayım ve zaman damgası; günlük ya da
# betik içeriği DÖKÜLMEZ. Okunamayan kaynak `unknown` döner — sunucuda yedek
# işi olmadığı SONUCU bu denetim koşmadan çıkarılamaz.
if have crontab; then
  cap crontab -l
  if [ "$CAP_RC" -eq 0 ]; then
    say cron_user_read ok
    say cron_user_backup_lines "$(printf '%s\n' "$CAP_OUT" | grep -Eci 'pg_dump|backup')"
  else
    # `crontab -l` boş crontab'da da sıfırdan farklı çıkar: ayırt edilemez.
    say cron_user_read "empty_or_denied"
    say cron_user_backup_lines unknown
  fi
else
  say cron_user_read unknown
  say cron_user_backup_lines unknown
fi

cron_files_read=0
cron_files_hits=0
for f in /etc/crontab /etc/cron.d/*; do
  [ -f "$f" ] || continue
  [ -r "$f" ] || continue
  cron_files_read=$((cron_files_read + 1))
  c="$(grep -Eci 'pg_dump|backup' "$f")"
  case "$c" in
    ''|*[!0-9]*) : ;;
    *) cron_files_hits=$((cron_files_hits + c)) ;;
  esac
done
say cron_etc_readable_files "$cron_files_read"
if [ "$cron_files_read" -eq 0 ]; then
  # Okunabilir dosya yoksa "yedek satırı yok" DENMEZ.
  say cron_etc_backup_lines unknown
else
  say cron_etc_backup_lines "$cron_files_hits"
fi

for d in /etc/cron.daily /etc/cron.hourly; do
  k="cron_dir_$(basename "$d")"
  if [ -d "$d" ] && [ -r "$d" ]; then
    cap ls -1 "$d"
    if [ "$CAP_RC" -ne 0 ]; then
      say "$k" unknown
    else
      say "$k" "backup_scripts=$(printf '%s\n' "$CAP_OUT" | grep -Eci 'backup|dump')"
    fi
  else
    say "$k" unknown
  fi
done

if have systemctl; then
  cap systemctl list-timers --all --no-legend
  if [ "$CAP_RC" -ne 0 ]; then
    say systemd_backup_timers unknown
  else
    say systemd_backup_timers "$(printf '%s\n' "$CAP_OUT" | grep -Eci 'backup|dump')"
  fi
else
  say systemd_backup_timers unknown
fi

# Yedek betiğinin kendisi: sabit, dar bir aday listesi taranır. Üst dizin
# okunamıyorsa `[ -f ]` de yanlış döner; bu yüzden sonuç `none_or_unreadable`.
BACKUP_SCRIPT="none_or_unreadable"
for p in "$DEPLOY_DIR/backup.sh" "$DEPLOY_DIR/scripts/backup.sh" \
         /usr/local/bin/backup.sh /opt/backup.sh; do
  if [ -f "$p" ]; then BACKUP_SCRIPT="$p"; break; fi
done
say backup_script "$BACKUP_SCRIPT"
if [ "$BACKUP_SCRIPT" != "none_or_unreadable" ]; then
  cap stat -c '%n mode=%a size=%s mtime=%y' "$BACKUP_SCRIPT"
  if [ "$CAP_RC" -ne 0 ]; then say backup_script_stat unknown; else say backup_script_stat "$(first_line "$CAP_OUT")"; fi
else
  say backup_script_stat unknown
fi

BACKUP_LOG_SEEN=no
for lg in /var/log/zabuno-backup.log "$DEPLOY_DIR/backup.log"; do
  if [ -r "$lg" ]; then
    cap stat -c 'size=%s mtime=%y' "$lg"
    if [ "$CAP_RC" -ne 0 ]; then
      say backup_log "path=$lg stat=unknown"
    else
      say backup_log "path=$lg $(first_line "$CAP_OUT")"
    fi
    BACKUP_LOG_SEEN=yes
  fi
done
[ "$BACKUP_LOG_SEEN" = yes ] || say backup_log unknown

echo "### section=php_imaging"
if [ "$APP_STATUS" = found ]; then
  say_dexec php_ext_vips "$APPC" \
    'command -v php >/dev/null 2>&1 || exit 3; php -r "echo extension_loaded(\"vips\")?\"yes\":\"no\";"'
  say_dexec php_ext_gd "$APPC" \
    'command -v php >/dev/null 2>&1 || exit 3; php -r "echo extension_loaded(\"gd\")?\"yes\":\"no\";"'
  say_dexec php_func_vips_call "$APPC" \
    'command -v php >/dev/null 2>&1 || exit 3; php -r "echo function_exists(\"vips_call\")?\"yes\":\"no\";"'
  # Kütüphane varlığı: EŞLEŞEN TEK BİR okunabilir dosya yeterlidir. Eski
  # `ls glob-A glob-B` biçimi, adaylardan biri yokken ls'i başarısız kılıp
  # kütüphane KURULUYKEN bile `no` üretiyordu.
  say_dexec lib_vips_present "$APPC" \
    'for f in /usr/lib/libvips.so* /usr/lib/*/libvips.so* /usr/local/lib/libvips.so* /usr/local/lib/*/libvips.so*; do if [ -f "$f" ] && [ -r "$f" ]; then echo yes; exit 0; fi; done; echo no'
  say_dexec lib_heif_present "$APPC" \
    'for f in /usr/lib/libheif.so* /usr/lib/*/libheif.so* /usr/local/lib/libheif.so* /usr/local/lib/*/libheif.so*; do if [ -f "$f" ] && [ -r "$f" ]; then echo yes; exit 0; fi; done; echo no'
  # Bayraklar YALNIZ varlık bildirir; gerçek HEIC çözme kanıtı DEĞİLDİR.
  say heic_decode_claim "not_proven_by_availability"
  # Yalnız boolean: değer basılmaz, uygulama bootstrap edilmez.
  say_dexec mail_host_configured "$APPC" 'if [ -n "${MAIL_HOST:-}" ]; then echo yes; else echo no; fi'
  say_dexec mail_from_configured "$APPC" 'if [ -n "${MAIL_FROM_ADDRESS:-}" ]; then echo yes; else echo no; fi'
else
  for k in php_ext_vips php_ext_gd php_func_vips_call lib_vips_present lib_heif_present \
           mail_host_configured mail_from_configured; do
    say "$k" unknown
  done
  say heic_decode_claim "not_proven_by_availability"
fi

echo "### section=db_role_metabase_ro"
# Salt-okunurluk İDDİASI yalnız default ACL sayısıyla kanıtlanamaz. Aşağıdaki
# olgular katalogdan türetilir: rolün ve PUBLIC'in tablo/sekans yazma-okuma
# yetkisi, veritabanı CREATE/TEMP yetkisi ve rolün SAHİP olduğu nesneler
# (sahip, yetki verilmemiş olsa bile yazabilir).
MB_KEYS="metabase_ro_canlogin metabase_ro_superuser metabase_ro_createrole \
metabase_ro_createdb metabase_ro_bypassrls metabase_ro_inherit \
metabase_ro_member_of metabase_ro_memberships metabase_ro_readable_tables \
metabase_ro_write_tables metabase_ro_write_sequences metabase_ro_owned_relations \
metabase_ro_owned_schemas metabase_ro_public_schema_create metabase_ro_db_create \
metabase_ro_db_temp metabase_ro_default_acls public_grant_read_tables \
public_grant_write_tables"

if [ "$DB_STATUS" != found ]; then
  say metabase_ro_exists unknown
  for k in $MB_KEYS; do say "$k" unknown; done
  say metabase_ro_set_role_supported unknown
  say metabase_ro_select_users_limit0 unknown
else
  PSQL='psql -X -q -A -t -v ON_ERROR_STOP=1 -U "${POSTGRES_USER:-zabuno}" -d "${POSTGRES_DB:-zabuno}"'
  dexec "$DBC" "$PSQL -c \"BEGIN READ ONLY; SELECT count(*) FROM pg_roles WHERE rolname='metabase_ro'; COMMIT;\""
  if [ "$CAP_RC" -ne 0 ]; then
    exists=""
  else
    exists="$(printf '%s' "$CAP_OUT" | tr -d '[:space:]')"
  fi
  if [ "${exists:-}" = "0" ]; then
    say metabase_ro_exists no
    for k in $MB_KEYS; do say "$k" "not_applicable_role_absent"; done
    say metabase_ro_set_role_supported "not_applicable_role_absent"
    say metabase_ro_select_users_limit0 "not_applicable_role_absent"
  elif [ "${exists:-}" != "1" ]; then
    say metabase_ro_exists "unknown_psql_unreachable"
    for k in $MB_KEYS; do say "$k" unknown; done
    say metabase_ro_set_role_supported unknown
    say metabase_ro_select_users_limit0 unknown
  else
    say metabase_ro_exists yes
    SQL_FACTS="
BEGIN READ ONLY;
SELECT 'metabase_ro_canlogin='||rolcanlogin||E'\n'||
       'metabase_ro_superuser='||rolsuper||E'\n'||
       'metabase_ro_createrole='||rolcreaterole||E'\n'||
       'metabase_ro_createdb='||rolcreatedb||E'\n'||
       'metabase_ro_bypassrls='||rolbypassrls||E'\n'||
       'metabase_ro_inherit='||rolinherit
  FROM pg_roles WHERE rolname='metabase_ro';
SELECT 'metabase_ro_memberships='||count(*) FROM pg_auth_members m
  JOIN pg_roles r ON r.oid=m.member WHERE r.rolname='metabase_ro';
SELECT 'metabase_ro_member_of='||coalesce(string_agg(g.rolname,'+' ORDER BY g.rolname),'none')
  FROM pg_auth_members m
  JOIN pg_roles r ON r.oid=m.member
  JOIN pg_roles g ON g.oid=m.roleid
 WHERE r.rolname='metabase_ro';
SELECT 'metabase_ro_readable_tables='||count(*) FROM pg_class c
  JOIN pg_namespace n ON n.oid=c.relnamespace
 WHERE c.relkind IN ('r','p') AND n.nspname NOT IN ('pg_catalog','information_schema')
   AND has_table_privilege('metabase_ro',c.oid,'SELECT');
SELECT 'metabase_ro_write_tables='||count(*) FROM pg_class c
  JOIN pg_namespace n ON n.oid=c.relnamespace
 WHERE c.relkind IN ('r','p') AND n.nspname NOT IN ('pg_catalog','information_schema')
   AND (has_table_privilege('metabase_ro',c.oid,'INSERT')
     OR has_table_privilege('metabase_ro',c.oid,'UPDATE')
     OR has_table_privilege('metabase_ro',c.oid,'DELETE')
     OR has_table_privilege('metabase_ro',c.oid,'TRUNCATE'));
SELECT 'metabase_ro_write_sequences='||count(*) FROM pg_class c
  JOIN pg_namespace n ON n.oid=c.relnamespace
 WHERE c.relkind='S' AND n.nspname NOT IN ('pg_catalog','information_schema')
   AND (has_sequence_privilege('metabase_ro',c.oid,'UPDATE')
     OR has_sequence_privilege('metabase_ro',c.oid,'USAGE'));
SELECT 'metabase_ro_owned_relations='||count(*) FROM pg_class c
  JOIN pg_namespace n ON n.oid=c.relnamespace
  JOIN pg_roles r ON r.oid=c.relowner
 WHERE r.rolname='metabase_ro' AND n.nspname NOT IN ('pg_catalog','information_schema');
SELECT 'metabase_ro_owned_schemas='||count(*) FROM pg_namespace n
  JOIN pg_roles r ON r.oid=n.nspowner WHERE r.rolname='metabase_ro';
SELECT 'metabase_ro_public_schema_create='||has_schema_privilege('metabase_ro','public','CREATE');
SELECT 'metabase_ro_db_create='||has_database_privilege('metabase_ro',current_database(),'CREATE');
SELECT 'metabase_ro_db_temp='||has_database_privilege('metabase_ro',current_database(),'TEMP');
SELECT 'metabase_ro_default_acls='||count(*) FROM pg_default_acl
 WHERE array_to_string(defaclacl,',') LIKE '%metabase_ro%';
SELECT 'public_grant_read_tables='||count(DISTINCT c.oid) FROM pg_class c
  JOIN pg_namespace n ON n.oid=c.relnamespace
  CROSS JOIN LATERAL aclexplode(c.relacl) a
 WHERE c.relkind IN ('r','p') AND n.nspname NOT IN ('pg_catalog','information_schema')
   AND a.grantee=0 AND a.privilege_type='SELECT';
SELECT 'public_grant_write_tables='||count(DISTINCT c.oid) FROM pg_class c
  JOIN pg_namespace n ON n.oid=c.relnamespace
  CROSS JOIN LATERAL aclexplode(c.relacl) a
 WHERE c.relkind IN ('r','p') AND n.nspname NOT IN ('pg_catalog','information_schema')
   AND a.grantee=0 AND a.privilege_type IN ('INSERT','UPDATE','DELETE','TRUNCATE');
COMMIT;"
    dexec "$DBC" "$PSQL -c \"$SQL_FACTS\""
    if [ "$CAP_RC" -ne 0 ] || [ -z "$CAP_OUT" ]; then
      # Katalog sorgusu başarısızsa hiçbir olgu "temiz" sayılmaz.
      for k in $MB_KEYS; do say "$k" unknown; done
    else
      printf '%s\n' "$CAP_OUT"
    fi
    # `SET LOCAL ROLE` yalnız çağıran rol o rolün üyesiyse (ya da superuser)
    # çalışır; desteklenmiyorsa aşağıdaki deneme SONUÇSUZDUR, "reddedildi" değil.
    dexec "$DBC" "$PSQL -c \"BEGIN READ ONLY; SET LOCAL ROLE metabase_ro; COMMIT;\""
    if [ "$CAP_RC" -eq 0 ]; then
      say metabase_ro_set_role_supported yes
      # Satır DÖNDÜRMEYEN yetki denemesi: LIMIT 0, hiçbir müşteri satırı okunmaz.
      dexec "$DBC" "$PSQL -c \"BEGIN READ ONLY; SET LOCAL ROLE metabase_ro; SELECT * FROM public.users LIMIT 0; COMMIT;\""
      if [ "$CAP_RC" -eq 0 ]; then
        say metabase_ro_select_users_limit0 allowed
      else
        say metabase_ro_select_users_limit0 "denied_or_table_absent"
      fi
    else
      say metabase_ro_set_role_supported no
      say metabase_ro_select_users_limit0 "untested_set_role_unavailable"
    fi
  fi
fi
# Yorumlanma kuralları — sayı okumak yetmez:
say metabase_ro_readonly_criteria "write_tables=0 AND write_sequences=0 AND owned_relations=0 AND owned_schemas=0 AND public_schema_create=f AND db_create=f AND superuser=f AND bypassrls=f"
say metabase_ro_membership_note "has_*_privilege sonuclari inherit=t iken devralinan uyelikleri de icerir; member_of listesi ayrica basilir"
say metabase_ro_public_grant_note "PUBLIC'e verilen yetkiler her role uygulanir; public_grant_write_tables sifirdan buyukse rol yazabilir"
say metabase_ro_password_login "untested_by_design"
say metabase_ro_default_acl_note "default ACL yalnizca GELECEK nesneleri etkiler; tek basina salt-okunurlugu kanitlamaz"

echo "### section=end"
say audit_finished_utc "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
exit 0
