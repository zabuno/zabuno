/*
    TARAYICI OTURUMU — gerçek bir düzen motorunu açan TEK yol.

    ═══ NEDEN VAR ═══

    Bu depoda gerçek bir tarayıcıda ölçen iki kapı var: `shell-scroll-gate`
    ve `mobile-ux-audit`. İkisi de Chrome'u kendi kopyaladığı kodla
    başlatıyordu ve o iki kopya çoktan AYRIŞMIŞTI:

    - `shell-scroll-gate` sabit bir port (9333) kullanıyordu ve portun
      açılmasını 15 saniye bekliyordu.
    - `mobile-ux-audit` portu Chrome'a seçtiriyordu (`--remote-debugging-port=0`)
      ve 30 saniye bekliyordu.

    Yani aynı makinede, aynı Chrome'a, iki farklı sözleşmeyle bağlanılıyordu;
    birinde düzeltilen bir şey ötekinde düzelmiyordu. `docs/147` bunu
    ölçtü: son 300 CI koşumunun 39 başarısızlığından tam olarak biri
    (run 33997789409) tarayıcı başlatılamadığı için düştü, ve düşen taraf
    sabit portlu, kısa süreli olandı.

    ═══ NE SAĞLIYOR ═══

    1. Tarayıcı ikilisi ve SÜRÜMÜ tek yerde çözülür ve kayda geçer. Sürüm
       sessizce değişirse ölçümler de sessizce değişir — bu depoda yazı
       tipi yüzünden birebir bu yaşandı (`scripts/mobile-ux-audit`).
    2. Portun GERÇEKTEN dinlemeye başlaması beklenir; sabit uyku yok.
    3. Yeniden deneme SINIRLIDIR ve her deneme temiz bir profil dizini
       kullanır. Sonsuz deneme bir kapıyı sessizce iptal ederdi.
    4. Başarısızlık TEŞHİS EDİLEBİLİR: hangi ikili, hangi sürüm, hangi
       bayraklar, süreç yaşıyor muydu, çıkış kodu neydi, stderr'in son
       satırları neydi, port dosyası yazıldı mı.
    5. Altyapı arızası ile gerçek kusur AYRI çıkış kodlarıyla biter:
       3 = tarayıcı başlatılamadı (ölçüm YAPILMADI),
       1 = ölçüm yapıldı ve kural ihlal edildi.

    ═══ NEYİ SAĞLAMIYOR ═══

    Kapıyı gevşetmiyor. Tarayıcı açılamazsa sonuç hâlâ KIRMIZIDIR ve
    "geçti" değildir: ölçüm yapılmadıysa sonuç "bilinmiyor"dur ve bilinmeyen
    bir sonuç yeşil gösterilemez (küresel `TOUCH-FIRST-INTERFACE` madde 4).
    Yeniden deneme, kararsız bir başlatmayı kurtarır; ihlal eden bir ölçümü
    kurtarmaz — ölçüm başladıktan sonra bu modülün işi biter.

    ═══ ÖN KONTROL ═══

        node scripts/browser-session.mjs --preflight

    İkiliyi çözer, sürümü basar, bir kez gerçekten açıp kapatır. CI'da
    pahalı derleme adımlarından ÖNCE koşar: altyapı arızası, üç dakikalık
    bir Storybook derlemesinin sonunda değil başında görünür.
*/

import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

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

export const DEFAULT_CANDIDATES = [
    process.env.CHROME_PATH,
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
].filter(Boolean);

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
