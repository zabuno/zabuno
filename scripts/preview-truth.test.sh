#!/usr/bin/env bash
# scripts/preview-truth için belirlenimci kabuk testi (docs/52).
#
# Kapı, gerçek bir HTTP yanıtından meta etiketi okur; bu yüzden test de gerçek
# bir yerel sunucu ayağa kaldırır. Sahte bir `curl` ile taklit etmek, kapının
# ASIL kırıldığı yeri (HTML'den doğru etiketi çıkarmak) sınamazdı.
#
# Ağ yok: yalnız 127.0.0.1 üzerinde geçici bir python sunucusu.

set -u
cd "$(dirname "$0")/.."
GATE="scripts/preview-truth"

TMP="$(mktemp -d)"
PORT=""
SERVER_PID=""
failures=0

cleanup() {
  [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null
  rm -rf "$TMP"
}
trap cleanup EXIT

serve() {
  # $1 = index.html gövdesi
  [ -n "$SERVER_PID" ] && { kill "$SERVER_PID" 2>/dev/null; wait "$SERVER_PID" 2>/dev/null; }
  printf '%s' "$1" > "$TMP/index.html"
  PORT=$((20000 + RANDOM % 20000))
  # `exec` ŞART: onsuz $! alt kabuğun PID'idir, python'un değil — ve
  # alt kabuğu öldürmek sunucuyu ayakta bırakır. O hâlde "ulaşılamayan
  # sunucu" senaryosu sunucu ÇALIŞIRKEN koşar ve hiçbir şey sınamaz.
  (cd "$TMP" && exec python3 -m http.server "$PORT" >/dev/null 2>&1) &
  SERVER_PID=$!
  for _ in $(seq 1 50); do
    curl -fsS --max-time 1 "http://127.0.0.1:$PORT/" >/dev/null 2>&1 && return 0
    sleep 0.1
  done
  echo "sunucu ayağa kalkmadı" >&2
  return 1
}

expect_verdict() {
  # $1 = senaryo adı, $2 = beklenen belirteç, $3.. = ek argümanlar
  local name="$1" want="$2"; shift 2
  local got
  got="$("$GATE" check --url "http://127.0.0.1:$PORT/" "$@" 2>/dev/null | tail -n1)"
  if [ "$got" = "$want" ]; then
    echo "ok   — $name ($got)"
  else
    echo "FAIL — $name: beklenen $want, gelen ${got:-<boş>}"
    failures=$((failures + 1))
  fi
}

SHA_A="$(printf 'a%.0s' $(seq 1 40))"
SHA_B="$(printf 'b%.0s' $(seq 1 40))"

page() {
  # $1 = revision, $2 = stale
  printf '<html><head><meta name="zabuno-build-revision" content="%s"><meta name="zabuno-build-stale" content="%s"></head><body></body></html>' "$1" "$2"
}

# 1) Sunulan sürüm beklenene eşit, derleme taze → PASS
serve "$(page "$SHA_A" false)" || exit 2
expect_verdict "eşleşen sürüm, taze derleme" PASS --expect "$SHA_A"

# 2) Sunulan sürüm farklı → REVISION_MISMATCH.
#    Raporlanan asıl olay bu: geliştirme checkout'u bir SHA'da, localhost başka
#    bir SHA'da; ekranda bunu gösteren hiçbir şey yok.
expect_verdict "sürüm uyuşmazlığı" REVISION_MISMATCH --expect "$SHA_B"

# 3) Sürümler eşit AMA derleme bayat → BUILD_STALE.
#    Sürüm karşılaştırmasının YAPISAL olarak göremediği durum: commit yok,
#    yalnız derlenmemiş bir düzenleme var.
serve "$(page "$SHA_A" true)" || exit 2
expect_verdict "eşit sürüm, bayat derleme" BUILD_STALE --expect "$SHA_A"

# 4) Kimlik hiç yok → NO_BUILD_IDENTITY.
#    "Eşit" varsaymak kapıyı sessizce işlevsiz kılardı.
serve '<html><head></head><body>no identity here</body></html>' || exit 2
expect_verdict "kimlik taşımayan sayfa" NO_BUILD_IDENTITY --expect "$SHA_A"

# 5) Sunucu yok → UNREACHABLE (sessiz PASS değil).
kill "$SERVER_PID" 2>/dev/null; wait "$SERVER_PID" 2>/dev/null; SERVER_PID=""
for _ in $(seq 1 50); do
  curl -fsS --max-time 1 "http://127.0.0.1:$PORT/" >/dev/null 2>&1 || break
  sleep 0.1
done
expect_verdict "ulaşılamayan sunucu" UNREACHABLE --expect "$SHA_A"

# 6) Beklenen sürüm ÇÖZÜLEMEDİ → NO_EXPECTED_REVISION, sessiz PASS DEĞİL.
#
#    Kapının kendi körlüğü: git olmayan bir dizin (ya da yanlış bir
#    `--worktree` yolu) karşılaştırmayı imkânsız kılar. Bunu "geçti" saymak,
#    kapıyı hiçbir uyarı vermeden her zaman PASS döndüren bir süse çevirirdi
#    — ve bu tam olarak kapının kurulduğu arıza sınıfıdır.
serve "$(page "$SHA_A" false)" || exit 2
mkdir -p "$TMP/not-a-repo"
expect_verdict "beklenen sürüm çözülemedi" NO_EXPECTED_REVISION --worktree "$TMP/not-a-repo"

echo "─────────────"
if [ "$failures" -eq 0 ]; then
  echo "preview-truth: TÜM SENARYOLAR GEÇTİ"
  exit 0
fi
echo "preview-truth: $failures senaryo düştü"
exit 1
