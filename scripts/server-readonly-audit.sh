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
# Çıktı `ANAHTAR=DEĞER` satırlarıdır; erişilemeyen her olgu `unknown` döner.

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
# Sabit komutları zaman sınırlı ve çıktısı sınırlı çalıştırır. `timeout`
# yoksa (asgari imajlar) sınırsız değil, yalnız zaman sınırı olmadan koşar.
if have timeout; then
  run() { timeout 25 "$@" 2>/dev/null | head -c 20000; }
else
  run() { "$@" 2>/dev/null | head -c 20000; }
fi

echo "### section=meta"
say audit_version "ops-read-01"
say audit_started_utc "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
say deploy_dir_present "$([ -d "$DEPLOY_DIR" ] && echo yes || echo no)"
say docker_cli "$(have docker && echo yes || echo no)"

echo "### section=disk"
if have df; then
  df -P -h / "$DEPLOY_DIR" 2>/dev/null | awk 'NR>1{printf "disk_mount_%d=%s size=%s used=%s avail=%s use=%s\n",NR-1,$6,$2,$3,$4,$5}'
else
  say disk "unknown"
fi

echo "### section=compose"
PROJECT="unknown"
if have docker; then
  PROJECT="$(run docker ps -a --filter "label=com.docker.compose.project.working_dir=$DEPLOY_DIR" \
    --format '{{index .Labels "com.docker.compose.project"}}' | head -n 1)"
  [ -n "$PROJECT" ] || PROJECT="unknown"
fi
say compose_project "$PROJECT"

svc_container() {
  [ "$PROJECT" = unknown ] && return 1
  run docker ps -a --filter "label=com.docker.compose.project=$PROJECT" \
    --filter "label=com.docker.compose.service=$1" --format '{{.Names}}' | head -n 1
}
for svc in app db clamav proxy; do
  c="$(svc_container "$svc")"
  if [ -z "${c:-}" ]; then
    say "svc_${svc}" "unknown"
    continue
  fi
  state="$(run docker inspect -f '{{.State.Status}}' "$c")"
  health="$(run docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$c")"
  restarts="$(run docker inspect -f '{{.RestartCount}}' "$c")"
  say "svc_${svc}" "state=${state:-unknown} health=${health:-unknown} restarts=${restarts:-unknown}"
done

echo "### section=db_exposure"
DBC="$(svc_container db)"
if [ -n "${DBC:-}" ]; then
  pmap="$(run docker inspect -f '{{json .NetworkSettings.Ports}}' "$DBC")"
  pub="$(run docker inspect -f '{{range $p, $c := .NetworkSettings.Ports}}{{if $c}}{{$p}} {{end}}{{end}}' "$DBC")"
  say db_published_ports "$([ -n "${pub// /}" ] && echo "yes:${pub}" || echo no)"
  say db_port_map_len "${#pmap}"
  if have ss; then
    say host_listening_5432 "$(ss -H -ltn 2>/dev/null | awk '$4 ~ /:5432$/{n++} END{print (n?"yes":"no")}')"
  elif have netstat; then
    say host_listening_5432 "$(netstat -ltn 2>/dev/null | awk '$4 ~ /:5432$/{n++} END{print (n?"yes":"no")}')"
  else
    say host_listening_5432 "unknown"
  fi
else
  say db_published_ports "unknown"
  say host_listening_5432 "unknown"
fi

echo "### section=metabase"
if have docker; then
  MBC="$(run docker ps -a --filter 'name=metabase' --format '{{.Names}}' | head -n 1)"
  if [ -n "${MBC:-}" ]; then
    say metabase_present "yes"
    say metabase_state "$(run docker inspect -f '{{.State.Status}}' "$MBC")"
    say metabase_networks "$(run docker inspect -f '{{range $n, $_ := .NetworkSettings.Networks}}{{$n}} {{end}}' "$MBC")"
    say metabase_published "$(run docker inspect -f '{{range $p, $c := .NetworkSettings.Ports}}{{if $c}}{{$p}} {{end}}{{end}}' "$MBC")"
  else
    say metabase_present "no"
  fi
else
  say metabase_present "unknown"
fi

echo "### section=host_caddy"
# Depodaki docker/Caddyfile kenar proxy DEĞİLDİR; aktif kenar host Caddy'sidir.
CADDY_SITE="/etc/caddy/sites-enabled/zabuno.com"
if [ -r "$CADDY_SITE" ]; then
  say caddy_site_readable "yes"
  say caddy_encode_gzip_zstd "$(grep -Eqi '^[[:space:]]*encode([[:space:]]|$).*(gzip|zstd)|^[[:space:]]*encode[[:space:]]+zstd[[:space:]]+gzip' "$CADDY_SITE" && echo yes || echo no)"
  say caddy_hsts "$(grep -Eqi 'strict-transport-security' "$CADDY_SITE" && echo yes || echo no)"
  say caddy_max_body_52mb "$(grep -Eqi 'request_body|max_size|client_max_body' "$CADDY_SITE" && grep -Eqi '52[[:space:]]*(MB|M)\b' "$CADDY_SITE" && echo yes || echo no)"
  say caddy_forwarded_proto "$(grep -Eqi 'X-Forwarded-Proto' "$CADDY_SITE" && echo yes || echo no)"
else
  say caddy_site_readable "no"
  for k in caddy_encode_gzip_zstd caddy_hsts caddy_max_body_52mb caddy_forwarded_proto; do say "$k" unknown; done
fi

echo "### section=backups"
BVOL="$(run docker volume ls --filter "label=com.docker.compose.project=$PROJECT" \
  --filter 'label=com.docker.compose.volume=db-backups' --format '{{.Name}}' | head -n 1)"
say backup_volume "${BVOL:-unknown}"
if [ -n "${DBC:-}" ]; then
  say backup_mount_df "$(run docker exec "$DBC" df -h /backups | awk 'NR==2{print "size="$2" used="$3" avail="$4}')"
  say backup_dump_count "$(run docker exec "$DBC" sh -c 'ls -1 /backups/*.dump 2>/dev/null | wc -l' | tr -d ' ')"
  say backup_archive_count "$(run docker exec "$DBC" sh -c 'ls -1 /backups/*.tar /backups/*.tar.gz /backups/*.tgz 2>/dev/null | wc -l' | tr -d ' ')"
  say backup_total_kb "$(run docker exec "$DBC" sh -c 'du -sk /backups 2>/dev/null | cut -f1')"
  say backup_newest "$(run docker exec "$DBC" sh -c 'f=$(ls -t /backups/*.dump 2>/dev/null | head -n 1); [ -n "$f" ] && stat -c "%n size=%s mtime=%y" "$f" || echo unknown')"
  say backup_oldest "$(run docker exec "$DBC" sh -c 'f=$(ls -t /backups/*.dump 2>/dev/null | tail -n 1); [ -n "$f" ] && stat -c "%n mtime=%y" "$f" || echo unknown')"
  # pg_restore --list yalnız DOĞRULAR: --dbname yok, çıktı bastırılır, geri yükleme yok.
  say backup_pg_restore_list_ok "$(run docker exec "$DBC" sh -c \
    'f=$(ls -t /backups/*.dump 2>/dev/null | head -n 1); [ -n "$f" ] || { echo unknown; exit 0; }; pg_restore --list "$f" >/dev/null 2>&1 && echo yes || echo no')"
  say backup_media_tar_list_ok "$(run docker exec "$DBC" sh -c \
    'f=$(ls -t /backups/*.tar /backups/*.tar.gz /backups/*.tgz 2>/dev/null | head -n 1); [ -n "$f" ] || { echo unknown; exit 0; }; tar -tf "$f" >/dev/null 2>&1 && echo yes || echo no')"
else
  for k in backup_mount_df backup_dump_count backup_archive_count backup_total_kb backup_newest backup_oldest backup_pg_restore_list_ok backup_media_tar_list_ok; do say "$k" unknown; done
fi
# Zamanlanmış yedek işi: yalnız VARLIK ve zaman damgası; günlük dökülmez.
say backup_cron_entries "$( { crontab -l 2>/dev/null; cat /etc/cron.d/* 2>/dev/null; } | grep -Eci 'pg_dump|backup' | head -n 1)"
for lg in /var/log/zabuno-backup.log "$DEPLOY_DIR/backup.log"; do
  if [ -r "$lg" ]; then say backup_log "path=$lg size=$(stat -c %s "$lg" 2>/dev/null) mtime=$(stat -c %y "$lg" 2>/dev/null)"; fi
done
[ -r /var/log/zabuno-backup.log ] || [ -r "$DEPLOY_DIR/backup.log" ] || say backup_log "unknown"

echo "### section=php_imaging"
APPC="$(svc_container app)"
if [ -n "${APPC:-}" ]; then
  say php_ext_vips "$(run docker exec "$APPC" php -r 'echo extension_loaded("vips")?"yes":"no";')"
  say php_ext_gd "$(run docker exec "$APPC" php -r 'echo extension_loaded("gd")?"yes":"no";')"
  say php_func_vips_call "$(run docker exec "$APPC" php -r 'echo function_exists("vips_call")?"yes":"no";')"
  say lib_vips_present "$(run docker exec "$APPC" sh -c 'ls /usr/lib/*/libvips.so* /usr/lib/libvips.so* >/dev/null 2>&1 && echo yes || echo no')"
  say lib_heif_present "$(run docker exec "$APPC" sh -c 'ls /usr/lib/*/libheif.so* /usr/lib/libheif.so* >/dev/null 2>&1 && echo yes || echo no')"
  # Bayraklar YALNIZ varlık bildirir; gerçek HEIC çözme kanıtı DEĞİLDİR.
  say heic_decode_claim "not_proven_by_availability"
  # Yalnız boolean: değer basılmaz, uygulama bootstrap edilmez.
  say mail_host_configured "$(run docker exec "$APPC" sh -c '[ -n "${MAIL_HOST:-}" ] && echo yes || echo no')"
  say mail_from_configured "$(run docker exec "$APPC" sh -c '[ -n "${MAIL_FROM_ADDRESS:-}" ] && echo yes || echo no')"
else
  for k in php_ext_vips php_ext_gd php_func_vips_call lib_vips_present lib_heif_present mail_host_configured mail_from_configured; do say "$k" unknown; done
  say heic_decode_claim "not_proven_by_availability"
fi

echo "### section=db_role_metabase_ro"
if [ -z "${DBC:-}" ]; then
  say metabase_ro "unknown_no_db_container"
else
  PSQL='psql -X -q -A -t -v ON_ERROR_STOP=1 -U "${POSTGRES_USER:-zabuno}" -d "${POSTGRES_DB:-zabuno}"'
  exists="$(run docker exec "$DBC" sh -c "$PSQL -c \"BEGIN READ ONLY; SELECT count(*) FROM pg_roles WHERE rolname='metabase_ro'; COMMIT;\"" | tr -d '[:space:]')"
  if [ "${exists:-}" = "1" ]; then
    say metabase_ro_exists "yes"
    run docker exec "$DBC" sh -c "$PSQL -c \"
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
SELECT 'metabase_ro_write_tables='||count(*) FROM pg_class c
  JOIN pg_namespace n ON n.oid=c.relnamespace
 WHERE c.relkind IN ('r','p') AND n.nspname NOT IN ('pg_catalog','information_schema')
   AND (has_table_privilege('metabase_ro',c.oid,'INSERT')
     OR has_table_privilege('metabase_ro',c.oid,'UPDATE')
     OR has_table_privilege('metabase_ro',c.oid,'DELETE')
     OR has_table_privilege('metabase_ro',c.oid,'TRUNCATE'));
SELECT 'metabase_ro_readable_tables='||count(*) FROM pg_class c
  JOIN pg_namespace n ON n.oid=c.relnamespace
 WHERE c.relkind IN ('r','p') AND n.nspname NOT IN ('pg_catalog','information_schema')
   AND has_table_privilege('metabase_ro',c.oid,'SELECT');
SELECT 'metabase_ro_public_create='||has_schema_privilege('metabase_ro','public','CREATE');
SELECT 'metabase_ro_default_acls='||count(*) FROM pg_default_acl
 WHERE array_to_string(defaclacl,',') LIKE '%metabase_ro%';
COMMIT;\""
    # Satır DÖNDÜRMEYEN yetki denemesi: LIMIT 0, hiçbir müşteri satırı okunmaz.
    say metabase_ro_select_users_limit0 "$(run docker exec "$DBC" sh -c "$PSQL -c \"BEGIN READ ONLY; SET LOCAL ROLE metabase_ro; SELECT * FROM public.users LIMIT 0; COMMIT;\" >/dev/null 2>&1 && echo allowed || echo denied_or_absent")"
  elif [ "${exists:-}" = "0" ]; then
    say metabase_ro_exists "no"
  else
    say metabase_ro_exists "unknown_psql_unreachable"
  fi
fi

echo "### section=end"
say audit_finished_utc "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
exit 0
