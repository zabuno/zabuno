#!/usr/bin/env bash
# scripts/browser-session.mjs için belirlenimci test (docs/147).
#
# Bu paket bir ÖLÇÜMDEN doğdu: son 300 CI koşumunun 39 başarısızlığından
# tam olarak biri (run 33997789409, 2026-09-05) tarayıcı başlatılamadığı
# için düştü ve düştüğünde tek söylediği şey "Chrome hata ayıklama portu
# açılmadı." idi. Hangi ikili denendi, hangi bayraklarla, süreç yaşıyor
# muydu, ne yazdı — hiçbiri kayıtta yok, çünkü `stdio: 'ignore'` ile
# stderr çöpe atılıyordu. Kök neden ölçülemedi; ölçülemez olmasının
# kendisi kusurdu.
#
# Burada Chrome'un kendisi test edilmiyor. Test edilen şey, tarayıcı
# başlatma yolunun DAVRANIŞI: sahte "tarayıcı" ikilileriyle her arıza
# biçimi belirlenimci olarak kurulur. Gerçek Chrome'a karşı koşan bir test
# CI'da tam olarak düzeltmeye çalıştığımız kararsızlığı taşırdı.
#
# Kapının GEVŞEMEDİĞİ de burada ölçülür: yeniden deneme sayısı sınırlıdır
# (sonsuz değil) ve başarısızlık hâlâ sıfırdan farklı bir çıkışla biter.

set -u
cd "$(dirname "$0")/.."
REPO="$(pwd)"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
failures=0

pass() { printf '  ok   %s\n' "$1"; }
fail() {
    printf '  FAIL %s\n' "$1"
    failures=$((failures + 1))
}

expect_eq() {
    # $1 = beklenen, $2 = gelen, $3 = açıklama
    if [ "$1" = "$2" ]; then
        pass "$3"
    else
        fail "$3 (beklenen '$1', gelen '$2')"
    fi
}

expect_contains() {
    # $1 = aranan parça, $2 = metin, $3 = açıklama
    case "$2" in
    *"$1"*) pass "$3" ;;
    *) fail "$3 (metinde '$1' yok)" ;;
    esac
}

expect_not_contains() {
    case "$2" in
    *"$1"*) fail "$3 (metinde '$1' VAR)" ;;
    *) pass "$3" ;;
    esac
}

# ── SAHTE TARAYICILAR ────────────────────────────────────────────────────
#
# Her biri `--version` sorusuna cevap verir (araç sürümü kaydeder) ve
# `--user-data-dir=` bayrağını okur. Ayrıldıkları tek yer, hata ayıklama
# portunu açıp açmadıkları.

mkdir -p "$TMP/bin"

# Portu gerçekten açan sunucu: `DevToolsActivePort` dosyasını profil
# dizinine yazar ve `/json/list` cevaplar. `FAKE_PORT_DELAY_MS` ile portun
# GEÇ açılması kurulabilir — sabit uyku yerine koşul beklendiğini ölçmek
# için.
cat >"$TMP/bin/fake-server.mjs" <<'FAKESERVER'
import { createServer } from 'node:http';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const flag = process.argv.find((a) => a.startsWith('--user-data-dir='));
const profile = flag.slice('--user-data-dir='.length);
const delay = Number(process.env.FAKE_PORT_DELAY_MS ?? 0);

const server = createServer((req, res) => {
    if (req.url === '/json/list') {
        res.setHeader('content-type', 'application/json');
        res.end(
            JSON.stringify([
                { type: 'page', webSocketDebuggerUrl: 'ws://127.0.0.1/devtools/page/FAKE' },
            ]),
        );
        return;
    }
    res.statusCode = 404;
    res.end('');
});

server.listen(0, '127.0.0.1', () => {
    const { port } = server.address();
    setTimeout(() => {
        mkdirSync(profile, { recursive: true });
        writeFileSync(join(profile, 'DevToolsActivePort'), `${port}\n/devtools/browser/FAKE\n`);
    }, delay);
});
FAKESERVER

# 1. Açılan tarayıcı.
cat >"$TMP/bin/ok" <<FAKEOK
#!/usr/bin/env bash
if [ "\${1:-}" = "--version" ]; then echo "SahteTarayici 123.0.4567.89"; exit 0; fi
echo "\$@" >> "$TMP/ok.calls"
exec node "$TMP/bin/fake-server.mjs" "\$@"
FAKEOK

# 2. Hemen çöken tarayıcı — stderr'e konuşur ve 127 ile çıkar.
cat >"$TMP/bin/crash" <<FAKECRASH
#!/usr/bin/env bash
if [ "\${1:-}" = "--version" ]; then echo "SahteTarayici 123.0.4567.89"; exit 0; fi
echo "\$@" >> "$TMP/crash.calls"
echo "birinci satir" >&2
echo "Failed to move to new namespace: Operation not permitted" >&2
exit 127
FAKECRASH

# 3. Yaşayan ama portu HİÇ açmayan tarayıcı — en sinsi arıza: süreç ayakta
#    olduğu için "çalışıyor" gibi görünür, oysa hiçbir şey ölçülemez.
cat >"$TMP/bin/silent" <<FAKESILENT
#!/usr/bin/env bash
if [ "\${1:-}" = "--version" ]; then echo "SahteTarayici 123.0.4567.89"; exit 0; fi
echo "\$@" >> "$TMP/silent.calls"
echo "sessiz kaldim" >&2
exec sleep 30
FAKESILENT

# 4. Kararsız tarayıcı — ilk denemede çöker, ikincide açılır. Yeniden
#    denemenin bir işe yaradığını ölçen tek durum budur.
cat >"$TMP/bin/flaky" <<FAKEFLAKY
#!/usr/bin/env bash
if [ "\${1:-}" = "--version" ]; then echo "SahteTarayici 123.0.4567.89"; exit 0; fi
echo "\$@" >> "$TMP/flaky.calls"
if [ ! -f "$TMP/flaky.once" ]; then
  touch "$TMP/flaky.once"
  echo "ilk deneme coktu" >&2
  exit 1
fi
exec node "$TMP/bin/fake-server.mjs" "\$@"
FAKEFLAKY

chmod +x "$TMP/bin/ok" "$TMP/bin/crash" "$TMP/bin/silent" "$TMP/bin/flaky"

# ── SÜRÜCÜ ───────────────────────────────────────────────────────────────
#
# Modülü çağırır ve tek satırlık bir hüküm basar; ayrıntı stderr'e gider.

cat >"$TMP/drive.mjs" <<DRIVER
import { launchBrowser, describeBrowserFailure } from '$REPO/scripts/browser-session.mjs';

const candidates = process.env.DRIVE_CANDIDATES.split(',').filter(Boolean);
const attempts = Number(process.env.DRIVE_ATTEMPTS ?? 3);
const timeoutMs = Number(process.env.DRIVE_TIMEOUT_MS ?? 4000);

try {
    const session = await launchBrowser({ label: 'test', candidates, attempts, timeoutMs });
    console.log(
        \`OK url=\${session.debuggerUrl} version=\${session.version} attempts=\${session.attemptsUsed}\`,
    );
    session.close();
    process.exit(0);
} catch (error) {
    console.log('LAUNCH_FAILED');
    console.log(describeBrowserFailure(error));
    process.exit(error.exitCode ?? 1);
}
DRIVER

drive() {
    # $1 = aday listesi (virgüllü). Kalanı ortam değişkeniyle verilir.
    DRIVE_CANDIDATES="$1" node "$TMP/drive.mjs" 2>&1
}

echo "browser-session"

# ── 1. İKİLİ YOK ────────────────────────────────────────────────────────
#
# "Tarayıcı yok, atlıyorum" bu deponun en sık tekrar eden kusuru olurdu
# (docs/109 §8.7). Ölçüm yapılmadıysa sonuç "geçti" değil "bilinmiyor"dur.
out="$(drive "$TMP/bin/yok-boyle-bir-ikili")"
status=$?
expect_contains "LAUNCH_FAILED" "$out" "ikili bulunamayınca kırılır"
expect_contains "$TMP/bin/yok-boyle-bir-ikili" "$out" "denenen yol raporlanır"
expect_not_contains "geçti" "$out" "ikili yokken 'geçti' denmez"

# Altyapı arızası kendi çıkış koduyla ayrılır: 3. Ölçüm ihlali 1'dir.
DRIVE_CANDIDATES="$TMP/bin/yok" node "$TMP/drive.mjs" >/dev/null 2>&1
expect_eq "3" "$?" "ikili yokluğu altyapı çıkış koduyla (3) biter"

# ── 2. AÇILAN TARAYICI ──────────────────────────────────────────────────
out="$(drive "$TMP/bin/ok")"
case "$out" in
*"OK url=ws://"*) pass "açılan tarayıcıya bağlanılır" ;;
*) fail "açılan tarayıcıya bağlanılır (gelen: $out)" ;;
esac
expect_contains "version=SahteTarayici 123.0.4567.89" "$out" "sürüm kaydedilir"
expect_contains "attempts=1" "$out" "sağlıklı tarayıcı tek denemede açılır"

# ── 3. PORT GEÇ AÇILIRSA — KOŞUL BEKLENİR, SABİT UYKU DEĞİL ────────────
#
# Sabit bir uyku, portun bir saniye geç açıldığı her koşumda kapıyı
# kırardı. Beklenen şey portun GERÇEKTEN dinlemeye başlaması.
rm -f "$TMP/ok.calls"
out="$(FAKE_PORT_DELAY_MS=1500 drive "$TMP/bin/ok")"
expect_contains "OK url=ws://" "$out" "port 1.5 sn geç açılsa da beklenir"
expect_contains "attempts=1" "$out" "geç açılan port yeniden başlatma gerektirmez"

# ── 4. HEMEN ÇÖKEN TARAYICI ─────────────────────────────────────────────
#
# Bugünkü kusur burada: süreç ölmüş olmasına rağmen araç zaman aşımı
# dolana kadar bekliyor ve sonunda "port açılmadı" diyordu. Ölü bir
# sürecin portunu beklemek, ölçülmüş bir bilgiyi görmezden gelmektir.
rm -f "$TMP/crash.calls"
started="$(date +%s)"
out="$(DRIVE_ATTEMPTS=3 DRIVE_TIMEOUT_MS=8000 drive "$TMP/bin/crash")"
elapsed=$(($(date +%s) - started))
expect_contains "LAUNCH_FAILED" "$out" "çöken tarayıcı kapıyı kırar"
expect_contains "127" "$out" "çıkış kodu raporlanır"
expect_contains "Failed to move to new namespace" "$out" "stderr'in son satırları raporlanır"
expect_contains "--headless" "$out" "kullanılan bayraklar raporlanır"
expect_contains "$TMP/bin/crash" "$out" "kullanılan ikili raporlanır"
if [ "$elapsed" -lt 8 ]; then
    pass "ölen süreç için zaman aşımı beklenmez (${elapsed} sn)"
else
    fail "ölen süreç için zaman aşımı beklenmez (${elapsed} sn, 3x8 sn beklendi)"
fi

# ── 5. YENİDEN DENEME SINIRLIDIR ────────────────────────────────────────
#
# Sonsuz yeniden deneme bir kapıyı sessizce iptal eder: iş asla bitmez,
# kimse kırmızı görmez. Sayı sabittir ve ölçülür.
calls="$(wc -l <"$TMP/crash.calls" | tr -d ' ')"
expect_eq "3" "$calls" "üç deneme yapılır, daha fazlası değil"

DRIVE_ATTEMPTS=1 drive "$TMP/bin/silent" >/dev/null 2>&1
calls="$(wc -l <"$TMP/silent.calls" | tr -d ' ')"
expect_eq "1" "$calls" "deneme sayısı çağıranın verdiği sayıdır"

# ── 6. YAŞAYAN AMA SESSİZ TARAYICI ──────────────────────────────────────
rm -f "$TMP/silent.calls"
out="$(DRIVE_ATTEMPTS=2 DRIVE_TIMEOUT_MS=1500 drive "$TMP/bin/silent")"
expect_contains "LAUNCH_FAILED" "$out" "portu açmayan tarayıcı kapıyı kırar"
expect_contains "DevToolsActivePort" "$out" "port dosyasının yazılmadığı raporlanır"
expect_contains "sessiz kaldim" "$out" "yaşayan sürecin stderr'i de raporlanır"

# ── 7. KARARSIZ TARAYICI — YENİDEN DENEME İŞE YARAR ────────────────────
rm -f "$TMP/flaky.calls" "$TMP/flaky.once"
out="$(DRIVE_ATTEMPTS=3 DRIVE_TIMEOUT_MS=4000 drive "$TMP/bin/flaky")"
expect_contains "OK url=ws://" "$out" "ilk denemede çöken tarayıcı ikincide açılır"
expect_contains "attempts=2" "$out" "kaçıncı denemede açıldığı raporlanır"

# ── 8. HER DENEME TEMİZ BİR PROFİLLE ────────────────────────────────────
#
# Kilitli ya da bozuk bir profil dizini Chrome'un açılmamasının bilinen
# sebeplerinden biridir; aynı dizinle yeniden denemek aynı sonucu verir.
first="$(head -1 "$TMP/flaky.calls" | tr ' ' '\n' | grep -- '--user-data-dir=' | head -1)"
second="$(sed -n '2p' "$TMP/flaky.calls" | tr ' ' '\n' | grep -- '--user-data-dir=' | head -1)"
if [ -n "$first" ] && [ "$first" != "$second" ]; then
    pass "her deneme kendi profil dizinini kullanır"
else
    fail "her deneme kendi profil dizinini kullanır ($first / $second)"
fi

# ── 9. ÖN KONTROL KİPİ ──────────────────────────────────────────────────
#
# CI'da tarayıcının kurulu ve sürümünün BELLİ olduğunu kaydeden adım.
# Sürüm sessizce değişirse ölçümler de sessizce değişir; kayıt, o değişimi
# görünür kılan tek şeydir.
out="$(CHROME_PATH="$TMP/bin/ok" node "$REPO/scripts/browser-session.mjs" --preflight 2>&1)"
status=$?
expect_eq "0" "$status" "ön kontrol açılan tarayıcıda geçer"
expect_contains "SahteTarayici 123.0.4567.89" "$out" "ön kontrol sürümü basar"
expect_contains "$TMP/bin/ok" "$out" "ön kontrol ikilinin yolunu basar"

CHROME_PATH="$TMP/bin/crash" node "$REPO/scripts/browser-session.mjs" --preflight >/dev/null 2>&1
expect_eq "3" "$?" "ön kontrol çöken tarayıcıda altyapı koduyla (3) kırılır"

echo
if [ "$failures" -gt 0 ]; then
    echo "$failures ölçüm başarısız"
    exit 1
fi

echo "tüm ölçümler geçti"
