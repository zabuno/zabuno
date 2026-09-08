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

import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

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
            message.error
                ? reject(new Error(`${method}: ${message.error.message}`))
                : resolve(message.result);
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
