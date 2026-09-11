/*
    ORTAK TARAYICI OTURUMU — sahne kapılarının paylaştığı tek Chrome yolu.

    ═══ NEDEN VAR ═══

    Bu depoda gerçek bir tarayıcıda ölçen dört araç var ve dördü de aynı
    otuz satırı kendi içinde tekrar ediyordu: Chrome'u bul, başsız başlat,
    hata ayıklama portunu bekle, WebSocket aç, iş bitince öldür. Tekrar tek
    başına bir kusur değildi; kusur, o otuz satırın BİRBİRİNDEN AYRIŞMASIYDI:

      · `scene-visual-gate` ve `scene-perf-gate` Chrome ÇIKMADIYSA sonsuza
        kadar bekliyordu. `kill()` çağrılır, ardından `chrome.once('exit')`
        beklenirdi — ama Chrome zaten çıkmışsa o olay BİR DAHA GELMEZ. Ölçüldü
        (2026-09-08, Chrome'un başlayamadığı bir kapta): araç hiçbir şey
        BASMADAN asılı kaldı. Hiç ölçmeyen bir kapı, kırılan bir kapıdan
        kötüdür — çünkü kırılan kapı görünür.
      · Ölçülen sayfanın DİLİ tarayıcının işletim sistemi dilinden geliyordu.
        Kurumsal site dili ziyaretçinin `Accept-Language` başlığından seçer
        (`SiteText`); yani aynı kapı Türkçe bir makinede Türkçe, İngilizce bir
        koşucuda İngilizce bir sayfa ölçüyordu. Görsel imza karşılaştıran bir
        kapı için bu, tabanın MAKİNEYE bağlı olması demektir.

    Bu dosya o iki cevabı tek yerde tutar. Bir kapı Chrome'u buradan alır ve
    "hangi dili ölçüyorum" sorusunun cevabını da buradan alır.

    ═══ NE YAPMAZ ═══

    Ölçmez. Burada tek bir eşik, tek bir bütçe ve tek bir iddia yoktur —
    onlar kapıların kendi dosyalarında kalır. Burası yalnız ULAŞIM katmanı.
*/

import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

/*
    CHROME NEREDE.

    `CHROME_PATH` ilk sırada: bir koşucuda tarayıcı başka bir yerde olabilir
    ve o durumda kapının cevabı "bulamadım" değil, "söylediğin yerde baktım"
    olmalı.
*/
export const CHROME_CANDIDATES = [
    process.env.CHROME_PATH,
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
].filter(Boolean);

/**
 * Diskte gerçekten duran ilk aday.
 *
 * TARAYICI BULUNAMAZSA KAPI GEÇMEZ, KIRILIR. "Chrome yok, atlıyorum" demek
 * bu deponun en sık tekrar eden kusuru olurdu (`docs/109` §8.7): çalışan ama
 * söylediği şeyi ölçmeyen bir kapı. Ölçüm yapılmadıysa sonuç "geçti" değil
 * "bilinmiyor"dur ve bilinmeyen bir sonuç yeşil gösterilmez.
 *
 * @returns {string|null}
 */
export function findChrome() {
    return CHROME_CANDIDATES.find((candidate) => existsSync(candidate)) ?? null;
}

/**
 * Chrome bulunamadıysa sebebi yazıp süreci düşürür.
 *
 * @param {string} tool Kapının adı — hata satırı hangi kapının sustuğunu söyler.
 * @returns {string} Bulunan tarayıcının yolu.
 */
export function requireChrome(tool) {
    const chrome = findChrome();

    if (chrome === null) {
        console.error(`${tool}: Chrome bulunamadı — ÖLÇÜM YAPILMADI, sonuç "bilinmiyor".`);
        console.error(`denenen yollar: ${CHROME_CANDIDATES.join(', ')}`);
        console.error('CHROME_PATH ortam değişkeniyle yol verilebilir.');
        process.exit(2);
    }

    return chrome;
}

/*
    ÖLÇÜLEN SAYFANIN DİLİ SABİTTİR.

    Kurumsal sitenin dili ziyaretçinin tarayıcı tercihinden gelir ve bu doğru
    bir ürün kararıdır — ama bir ÖLÇÜM aracı için değişken bir girdidir.
    Ürünün bugün gönderdiği tek dil İngilizce (`shipped_locales`), yani
    kapının ölçtüğü sayfa da İngilizce olmalı. Bu bir çeviri kararı değil,
    bir ölçüm kararıdır: aynı kapı her makinede AYNI sayfayı görmezse
    karşılaştırdığı taban bir şey ifade etmez.
*/
export const MEASURED_LANGUAGE = 'en-US,en;q=0.9';

/*
    ORTAK BAŞLATMA BAYRAKLARI.

    `--enable-unsafe-swiftshader`: başsız Chrome'da WebGL yolu yazılım
    rasterleştiriciye düşebilir. Kapatmak (`--disable-gpu`) WebGL'i tamamen
    yok ederdi ve o zaman ölçülen şey sahne değil, sahnenin yedek yolu olurdu.

    `--lang` ve `--accept-lang`: yukarıdaki dil kararının komut satırı ayağı.
    CDP tarafında ayrıca `Network.setExtraHTTPHeaders` yazılır; ikisi birden
    gerekir, çünkü biri `navigator.language`ı, öteki isteğin başlığını belirler.

    `--no-sandbox` YALNIZ istendiğinde: kum havuzunu varsayılan olarak kapatmak,
    bir ölçüm aracının güvenlik kararını sessizce gevşetmesi olurdu. Kök
    kullanıcı olarak koşan bir kapta (yalnız orada) `CHROME_NO_SANDBOX=1`
    ile açılır.
*/
export function chromeArgs(profilePath, extra = []) {
    return [
        '--headless=new',
        '--remote-debugging-port=0',
        `--user-data-dir=${profilePath}`,
        '--no-first-run',
        '--enable-unsafe-swiftshader',
        `--lang=${MEASURED_LANGUAGE.split(',')[0]}`,
        `--accept-lang=${MEASURED_LANGUAGE}`,
        ...(process.env.CHROME_NO_SANDBOX === '1' ? ['--no-sandbox'] : []),
        ...extra,
        'about:blank',
    ];
}

/**
 * Başsız Chrome'u başlatır ve geçici profil dizinini birlikte döndürür.
 *
 * @param {string} chromePath
 * @param {string} prefix Geçici dizin öneki — hangi kapının profili olduğu görünsün.
 * @param {string[]} extra Ek bayraklar.
 */
export function launchChrome(chromePath, prefix, extra = []) {
    const profileDir = mkdtempSync(join(tmpdir(), prefix));
    const profilePath = join(profileDir, 'profile');
    const chrome = spawn(chromePath, chromeArgs(profilePath, extra), { stdio: 'ignore' });

    return { chrome, profileDir, profilePath };
}

/**
 * Chrome'u kapatır ve profil dizinini temizler — ASILMADAN.
 *
 * `kill()` bir sinyaldir, bir söz değil; ama Chrome ZATEN çıkmışsa `exit`
 * olayı bir daha gelmez. Eski kod tam orada asılıyordu: Chrome başlayamadığı
 * her durumda kapı hiçbir şey basmadan bekliyordu. `exitCode`/`signalCode`
 * kontrolü bu bekleyişi koşullu yapar ve zaman aşımı onu üst sınıra bağlar.
 */
export async function closeChrome(chrome, profileDir) {
    if (chrome.exitCode === null && chrome.signalCode === null) {
        chrome.kill();

        await new Promise((resolve) => {
            const done = () => {
                clearTimeout(timer);
                resolve();
            };
            /* Temizlik ölçümü geçersiz kılmaz: beklemek gerekiyorsa da
               SONSUZA KADAR beklenmez. */
            const timer = setTimeout(done, 5000);

            chrome.once('exit', done);

            if (chrome.exitCode !== null || chrome.signalCode !== null) {
                done();
            }
        });
    }

    try {
        rmSync(profileDir, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    } catch {
        /* Geçici dizin işletim sistemine bırakılır. */
    }
}

/**
 * Hata ayıklama portunu bekler ve sayfanın WebSocket adresini döndürür.
 *
 * Chrome ARADA ÖLDÜYSE beklemeye devam etmenin anlamı yok: hata mesajı
 * "port açılmadı" değil, "tarayıcı çıktı" olmalı — ikisi ayrı arızalardır ve
 * ayrı çözülür.
 */
export async function debuggerUrl(profilePath, chrome = null) {
    const marker = join(profilePath, 'DevToolsActivePort');

    for (let attempt = 0; attempt < 160; attempt += 1) {
        if (chrome !== null && (chrome.exitCode !== null || chrome.signalCode !== null)) {
            throw new Error(
                `Chrome başlar başlamaz çıktı (kod ${chrome.exitCode ?? chrome.signalCode}). ` +
                    'Kapta kök kullanıcı olarak koşuyorsanız CHROME_NO_SANDBOX=1 gerekir.',
            );
        }

        try {
            const [port] = readFileSync(marker, 'utf8').split('\n');
            const list = await fetch(`http://127.0.0.1:${port.trim()}/json/list`).then((r) =>
                r.json(),
            );
            const page = list.find((t) => t.type === 'page');

            if (page) return page.webSocketDebuggerUrl;
        } catch {
            /* Chrome henüz dinlemiyor. */
        }

        await new Promise((r) => setTimeout(r, 250));
    }

    throw new Error('Chrome hata ayıklama portu açılmadı.');
}

/**
 * Tek bir CDP çağrısı.
 *
 * @param {WebSocket} ws
 * @param {string} method
 * @param {object} params
 */
export function cdp(ws, method, params = {}) {
    const id = (cdp.nextId = (cdp.nextId ?? 0) + 1);
    ws.send(JSON.stringify({ id, method, params }));

    return new Promise((resolve, reject) => {
        const onMessage = (event) => {
            const message = JSON.parse(event.data);

            if (message.id !== id) return;

            ws.removeEventListener('message', onMessage);

            if (message.error) {
                reject(new Error(`${method}: ${message.error.message}`));

                return;
            }

            resolve(message.result);
        };

        ws.addEventListener('message', onMessage);
        setTimeout(() => reject(new Error(`CDP timeout: ${method}`)), 60000);
    });
}

/** Açık bir WebSocket bağlantısı kurar. */
export async function connect(url) {
    const ws = new WebSocket(url);

    await new Promise((resolve, reject) => {
        ws.addEventListener('open', resolve, { once: true });
        ws.addEventListener('error', reject, { once: true });
    });

    return ws;
}

/**
 * Ölçülen dili isteğin BAŞLIĞINA da yazar.
 *
 * `--accept-lang` tek başına yetmiyordu: CDP ile açılan hedefte başlık
 * bazen tarayıcı profilinden geliyor. İkisi birlikte yazılınca sayfanın
 * hangi dilde çizildiği artık makinenin değil kapının kararı.
 */
export async function pinLanguage(ws) {
    await cdp(ws, 'Network.enable');
    await cdp(ws, 'Network.setExtraHTTPHeaders', {
        headers: { 'Accept-Language': MEASURED_LANGUAGE },
    });
}



/* ═══════════════════════════════════════════════════════════════════
   TANILAYAN KATMAN — `docs/154`, ff-243 uzlaştırması (2026-09-11).

   Yukarısı sahne kapılarının kullandığı yüzeydir ve DEĞİŞMEDİ:
   `findChrome`, `requireChrome`, `chromeArgs`, `launchChrome`,
   `closeChrome`, `debuggerUrl`, `cdp`, `connect`, `pinLanguage`,
   `MEASURED_LANGUAGE`, `CHROME_CANDIDATES`. Üç kapı (scene-perf-gate,
   scene-visual-gate, scene-webgl-context-cost) onlara bağlı.

   Aşağısı EKLENEN katmandır. ff-243 dalı bu yüzeyin yerine geçen bir
   API yazmıştı; olduğu gibi alınsaydı o üç kapı birden kırılırdı.
   Ölçüldü ve yol değiştirildi: yüzey korunur, yetenek eklenir.

   Eklenen: sınırlı yeniden deneme, stderr'in saklanması, ayrı altyapı
   çıkış kodu, ve `--preflight`. Ölçülen kusur: son 300 CI koşumunun 39
   başarısızlığından biri tarayıcı açılmadığı için düştü ve tek
   söylediği 'port açılmadı' oldu — çünkü `stdio: 'ignore'` stderr'i
   çöpe atıyordu. Kök neden ölçülemedi; ölçülemez olması kusurdu.
   ═══════════════════════════════════════════════════════════════════ */
/*
    ÇIKIŞ KODLARI — kırmızının iki türü aynı görünmemeli.

    Bakan kişinin ilk sorusu "benim değişikliğim mi bozdu, yoksa makine
    mi?" olur. Tek bir çıkış kodu o soruyu cevapsız bırakıyordu.
*/
export const BROWSER_UNAVAILABLE_EXIT = 3;
export const MEASUREMENT_FAILED_EXIT = 1;

export class BrowserUnavailableError extends Error {
    constructor(message, diagnosis) {
        super(message);
        this.name = 'BrowserUnavailableError';
        this.exitCode = BROWSER_UNAVAILABLE_EXIT;
        this.diagnosis = diagnosis;
    }
}

/*
    ADAY LİSTESİ TEK YERDE. Yeni katman kendi listesini TAŞIMAZ:
    `CHROME_CANDIDATES` ile iki liste iki gün sonra ayrışırdı ve bu
    dosyanın var olma sebebi tam olarak o ayrışmaydı.
*/
export const DEFAULT_CANDIDATES = CHROME_CANDIDATES;

/*
    BAYRAKLAR — her biri gerekçeli.

    `--remote-debugging-port=0`: portu Chrome seçer ve seçtiğini profil
    dizinindeki `DevToolsActivePort` dosyasına yazar. Sabit bir port,
    aynı makinede eşzamanlı koşan ikinci bir kapının Chrome'una bağlanmayı
    ya da hiç bağlanamamayı mümkün kılar; CI'da iki iş aynı runner'ı
    paylaşmasa da yerelde iki çalışma ağacı sık sık paylaşır.

    `--disable-dev-shm-usage`: küçük bir `/dev/shm`, başsız Chrome'un
    bilinen açılmama/çökme sebeplerindendir; bayrak paylaşımlı bellek
    yerine diski kullandırır.

    `--disable-background-networking`: açılışta bileşen/güncelleme indirme
    isteği yapılmaz. Ölçüm için gereksizdir ve ağ bir yerde takılırsa
    açılışı geciktirir.

    `--no-first-run`, `--no-default-browser-check`: ilk açılış akışları
    başsız kipte de zaman harcar.
*/
function baseArgs(profile) {
    const args = [
        '--headless=new',
        '--remote-debugging-port=0',
        `--user-data-dir=${profile}`,
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-gpu',
        '--disable-dev-shm-usage',
        '--disable-background-networking',
    ];

    /*
        Sanal makine içinde root olarak koşan bir kap (docker) Chrome'un
        kum havuzunu açamaz. CI'da kapalıdır ve kapalı kalmalıdır: kum
        havuzunu gereksiz yere kapatmak güvenlik yüzeyini genişletir.
        Açıkça istenirse açılır ve teşhis çıktısında görünür.
    */
    if (process.env.ZABUNO_BROWSER_NO_SANDBOX === '1') {
        args.push('--no-sandbox');
    }

    return args;
}

export function resolveBrowser({ candidates = DEFAULT_CANDIDATES } = {}) {
    const found = candidates.find((candidate) => existsSync(candidate));

    if (found === undefined) {
        throw new BrowserUnavailableError('Tarayıcı ikilisi bulunamadı.', {
            reason: 'binary-not-found',
            candidates,
            attempts: [],
        });
    }

    return found;
}

/*
    SÜRÜM KAYDA GEÇER.

    Sürüm okunamazsa `bilinmiyor` döner — uydurulmuş bir sürüm, olmayan bir
    sürümden kötüdür. Ölçüm bu yüzden durmaz; sürüm ölçümün girdisi değil,
    ölçümün künyesidir.
*/
export function browserVersion(binary) {
    try {
        const probe = spawnSync(binary, ['--version'], { encoding: 'utf8', timeout: 10000 });
        const line = `${probe.stdout ?? ''}`.trim().split('\n')[0];
        return line === '' ? 'bilinmiyor' : line;
    } catch {
        return 'bilinmiyor';
    }
}

function tail(text, lines) {
    const rows = text.split('\n').filter((row) => row.trim() !== '');
    return rows.slice(-lines);
}

/*
    PORTUN DİNLEMEYE BAŞLAMASI BEKLENİR — SABİT UYKU DEĞİL.

    İki koşul birden aranır: Chrome portu profil dizinine YAZMIŞ olmalı ve
    o portta `/json/list` gerçekten cevap vermeli. İlki olmadan ikincisi
    sorulamaz; ikincisi olmadan ilki bir söz, ölçüm değildir.

    Süreç ölürse beklemeye devam edilmez: ölü bir sürecin portu asla
    açılmayacaktır ve zaman aşımını doldurmak yalnız teşhisi geciktirir.
    Bugünkü kusur tam olarak buydu — 15 saniye beklenip "port açılmadı"
    deniyordu, oysa cevap ilk yarım saniyede belliydi.
*/
async function waitForDebuggerUrl(profile, timeoutMs, isAlive) {
    const marker = join(profile, 'DevToolsActivePort');
    const deadline = Date.now() + timeoutMs;
    let markerSeen = false;

    while (Date.now() < deadline) {
        if (existsSync(marker)) {
            markerSeen = true;

            try {
                const [port] = readFileSync(marker, 'utf8').split('\n');
                const list = await fetch(`http://127.0.0.1:${port.trim()}/json/list`).then((r) =>
                    r.json(),
                );
                const page = list.find((target) => target.type === 'page');
                if (page) return { debuggerUrl: page.webSocketDebuggerUrl, markerSeen };
            } catch {
                /* Port yazıldı ama henüz cevap vermiyor. */
            }
        }

        if (!isAlive()) {
            return { debuggerUrl: null, markerSeen, reason: 'process-exited' };
        }

        await new Promise((resolve) => setTimeout(resolve, 100));
    }

    return { debuggerUrl: null, markerSeen, reason: 'timeout' };
}

/*
    TEMİZLİK BİR AYRINTIDIR, ÖLÇÜM SONUCU DEĞİL.

    Tarayıcı kapanırken profil dizinine hâlâ yazıyor olabilir ve dizini o
    anda silmek `ENOTEMPTY` ile patlıyordu; `mobile-ux-audit` bu yüzden bir
    kez BÜTÜN ölçümünü raporlayamadan kaybetti. Silme başarısız olursa
    geçici dizin işletim sistemine bırakılır.
*/
function discard(dir) {
    try {
        rmSync(dir, { recursive: true, force: true });
    } catch {
        /* Geçici dizin işletim sistemine bırakılır. */
    }
}

async function attemptLaunch({ binary, extraArgs, timeoutMs, index }) {
    const profileRoot = mkdtempSync(join(tmpdir(), 'zabuno-browser-'));
    const profile = join(profileRoot, `profile-${index}`);
    const args = [...baseArgs(profile), ...extraArgs, 'about:blank'];

    let stderrBuffer = '';
    let exited = null;
    let spawnError = null;

    /*
        stderr ÇÖPE ATILMAZ.

        Bu paketten önce `stdio: 'ignore'` kullanılıyordu ve tarayıcı
        açılmadığında elimizde tek bir satır kalıyordu: "port açılmadı".
        Chrome'un kendi söyledikleri — kum havuzu reddi, eksik kütüphane,
        kilitli profil — hepsi atılıyordu.
    */
    const child = spawn(binary, args, { stdio: ['ignore', 'ignore', 'pipe'] });

    child.stderr.setEncoding('utf8');
    child.stderr.on('data', (chunk) => {
        stderrBuffer = `${stderrBuffer}${chunk}`.slice(-8000);
    });
    child.on('error', (error) => {
        spawnError = String(error.message ?? error);
    });
    child.on('exit', (code, signal) => {
        exited = { code, signal };
    });

    const started = Date.now();
    const outcome = await waitForDebuggerUrl(
        profile,
        timeoutMs,
        () => exited === null && spawnError === null,
    );
    const waitedMs = Date.now() - started;

    if (outcome.debuggerUrl !== null) {
        return {
            ok: true,
            debuggerUrl: outcome.debuggerUrl,
            close: () => {
                child.kill();
                discard(profileRoot);
            },
        };
    }

    child.kill();
    discard(profileRoot);

    return {
        ok: false,
        record: {
            attempt: index + 1,
            args,
            waitedMs,
            reason: spawnError !== null ? 'spawn-error' : outcome.reason,
            spawnError,
            exitCode: exited?.code ?? null,
            signal: exited?.signal ?? null,
            devToolsPortWritten: outcome.markerSeen,
            stderrTail: tail(stderrBuffer, 12),
        },
    };
}

/*
    YENİDEN DENEME SINIRLIDIR.

    Üç deneme, çünkü kararsız bir açılış genellikle ilkinde düzelir ve
    sonsuz deneme bir kapıyı sessizce iptal eder: iş asla bitmez, kimse
    kırmızı görmez. Üçü de başarısızsa sonuç KIRMIZIDIR — ama altyapı
    kırmızısı olarak işaretlenmiş bir kırmızı.

    Her deneme KENDİ profil dizinini alır: kilitli ya da yarım kalmış bir
    profil, Chrome'un açılmamasının bilinen sebeplerindendir ve aynı
    dizinle yeniden denemek aynı sonucu verirdi.
*/
export async function launchBrowser({
    label = 'browser',
    candidates = DEFAULT_CANDIDATES,
    extraArgs = [],
    attempts = 3,
    timeoutMs = Number(process.env.ZABUNO_BROWSER_TIMEOUT_MS ?? 20000),
} = {}) {
    const binary = resolveBrowser({ candidates });
    const version = browserVersion(binary);
    const records = [];

    for (let index = 0; index < attempts; index++) {
        const result = await attemptLaunch({ binary, extraArgs, timeoutMs, index });

        if (result.ok) {
            return {
                label,
                binary,
                version,
                debuggerUrl: result.debuggerUrl,
                attemptsUsed: index + 1,
                close: result.close,
            };
        }

        records.push(result.record);
    }

    throw new BrowserUnavailableError('Tarayıcı hata ayıklama portu açılmadı.', {
        reason: 'debugger-port-never-opened',
        label,
        binary,
        version,
        candidates,
        timeoutMs,
        attempts: records,
    });
}

/*
    TEŞHİS METNİ — bakan kişinin ilk bakışta cevaplayabilmesi gereken
    sorular: hangi ikili, hangi sürüm, hangi bayraklar, süreç yaşadı mı,
    ne yazdı.
*/
export function describeBrowserFailure(error) {
    if (!(error instanceof BrowserUnavailableError)) {
        return String(error?.stack ?? error);
    }

    const d = error.diagnosis;
    const lines = [
        '',
        '═══ ALTYAPI ARIZASI — TARAYICI BAŞLATILAMADI ═══',
        '',
        'Bu bir ölçüm sonucu DEĞİLDİR: ölçüm hiç yapılamadı, yani düzenin',
        'kurala uyup uymadığı BİLİNMİYOR. Bilinmeyen bir sonuç yeşil',
        'gösterilmez; bu yüzden kapı kırmızıdır.',
        '',
        `sebep         : ${d.reason}`,
    ];

    if (d.reason === 'binary-not-found') {
        lines.push('denenen yollar:');
        for (const candidate of d.candidates) lines.push(`  - ${candidate}`);
        lines.push('');
        lines.push('CHROME_PATH ortam değişkeniyle yol verilebilir.');
        return lines.join('\n');
    }

    lines.push(`kapı          : ${d.label}`);
    lines.push(`ikili         : ${d.binary}`);
    lines.push(`sürüm         : ${d.version}`);
    lines.push(`deneme başına : ${d.timeoutMs} ms`);
    lines.push('');

    for (const attempt of d.attempts) {
        lines.push(`── deneme ${attempt.attempt} ──`);
        lines.push(`  sonuç              : ${attempt.reason} (${attempt.waitedMs} ms sonra)`);
        lines.push(`  süreç çıkış kodu   : ${attempt.exitCode ?? 'çıkmadı'}`);
        lines.push(`  süreç sinyali      : ${attempt.signal ?? 'yok'}`);
        if (attempt.spawnError !== null) lines.push(`  başlatma hatası    : ${attempt.spawnError}`);
        lines.push(
            `  DevToolsActivePort : ${attempt.devToolsPortWritten ? 'yazıldı' : 'YAZILMADI'}`,
        );
        lines.push(`  bayraklar          : ${attempt.args.join(' ')}`);
        lines.push(
            attempt.stderrTail.length === 0
                ? '  stderr             : (boş)'
                : `  stderr (son ${attempt.stderrTail.length} satır):`,
        );
        for (const row of attempt.stderrTail) lines.push(`      ${row}`);
        lines.push('');
    }

    return lines.join('\n');
}

/*
    Kapıların ortak kapanışı: teşhisi bas ve ALTYAPI koduyla çık. Ölçüm
    ihlalinin kodu (1) bu yolda hiç kullanılmaz.
*/
export function exitBrowserUnavailable(error) {
    console.error(describeBrowserFailure(error));
    process.exit(error?.exitCode ?? BROWSER_UNAVAILABLE_EXIT);
}

async function preflight() {
    let session;

    try {
        session = await launchBrowser({ label: 'preflight' });
    } catch (error) {
        exitBrowserUnavailable(error);
        return;
    }

    console.log('browser-preflight');
    console.log(`  ikili   : ${session.binary}`);
    console.log(`  sürüm   : ${session.version}`);
    console.log(`  deneme  : ${session.attemptsUsed}`);
    console.log(`  hata ayıklama : ${session.debuggerUrl}`);
    session.close();
    console.log('tarayıcı açıldı ve kapandı — gerçek tarayıcı kapıları koşabilir');
}

if (process.argv[1] !== undefined && fileURLToPath(import.meta.url) === process.argv[1]) {
    if (process.argv.includes('--preflight')) {
        await preflight();
    } else {
        console.error('kullanım: node scripts/browser-session.mjs --preflight');
        process.exit(2);
    }
}
