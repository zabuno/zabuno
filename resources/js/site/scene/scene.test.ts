import { afterEach, describe, expect, it, vi } from 'vitest';
import { FrameGovernor, demote, estimateTier, promote } from './tier';
import { createParallax, createProgress, observeReveals } from './depth';
import { motionRequested } from './runtime';
import { mountScene } from './mount';
import { parseColor } from './field';
import { bindTilt } from './tilt';

/**
 * SAHNE MOTORUNUN KAPILARI — `docs/146` §7.
 *
 * ── NE ÖLÇÜLÜYOR, NE ÖLÇÜLMÜYOR ──
 *
 * Burası jsdom: DÜZEN HESAPLAMAZ. Hiçbir kutunun boyu yok, hiçbir şey
 * taşmıyor, hiçbir kare çizilmiyor. "Sahne güzel mi", "60 fps sürüyor mu",
 * "320 pikselde taşıyor mu" soruları burada SORULAMAZ ve bu dosya onları
 * sorduğunu iddia etmiyor — cevapları `scripts/mobile-ux-audit` ve
 * `scripts/scene-perf-gate` içinde, gerçek Chrome'da.
 *
 * Burada ölçülen şey KARAR MANTIĞI: hareket kapısı, derece merdiveni, giriş
 * kipi ayrımı ve temizlik. Üçü de saf mantıktır ve düzen motoru gerektirmez.
 */

const MEDIA: Record<string, boolean> = {};

function stubMatchMedia(view: Window & typeof globalThis, matches: Record<string, boolean>): void {
    for (const key of Object.keys(MEDIA)) {
        delete MEDIA[key];
    }

    Object.assign(MEDIA, matches);

    Object.defineProperty(view, 'matchMedia', {
        configurable: true,
        writable: true,
        value: (query: string) => ({
            matches: MEDIA[query] ?? false,
            media: query,
            addEventListener: () => {},
            removeEventListener: () => {},
        }),
    });
}

afterEach(() => {
    document.body.innerHTML = '';
    delete document.documentElement.dataset.motion;
    delete document.documentElement.dataset.sceneTier;
    vi.restoreAllMocks();
});

describe('hareket kapısı (SAHNE-01)', () => {
    it('azaltılmış hareket isteyen ziyaretçide sahne HİÇ başlamaz', () => {
        stubMatchMedia(window, { '(prefers-reduced-motion: reduce)': true });
        document.body.innerHTML = '<canvas data-scene="field"></canvas>';

        const runtime = mountScene(window);

        expect(runtime).toBeNull();
        expect(
            document.documentElement.dataset.motion,
            'SAHNE-01: `data-motion` yazılmış — CSS sahne kurallarını doğurur ve ' +
                'parallax bazı insanlarda fiziksel rahatsızlık yapar.',
        ).toBeUndefined();
    });

    it('`matchMedia` yoksa cevap "hareket istenmedi"dir', () => {
        const view = { matchMedia: undefined } as unknown as Window;

        expect(motionRequested(view)).toBe(false);
    });

    it('tercih belirtilmemişse hareket doğar', () => {
        stubMatchMedia(window, { '(prefers-reduced-motion: reduce)': false });

        expect(motionRequested(window)).toBe(true);
    });
});

describe('sahnesiz sayfa (SAHNE-02)', () => {
    it('kaydedilecek efekt yoksa döngü açılmaz ve `data-motion` yazılmaz', () => {
        stubMatchMedia(window, { '(prefers-reduced-motion: reduce)': false });
        document.body.innerHTML = '<p>salt metin</p>';

        mountScene(window);

        expect(
            document.documentElement.dataset.motion,
            'SAHNE-02: sahnesi olmayan bir sayfada döngü açmak, hiç kimsenin ' +
                'görmediği bir iş için pil harcamaktır.',
        ).toBeUndefined();
    });

    it('sahne varsa derece ve hareket kancası kök öğeye yazılır', () => {
        stubMatchMedia(window, { '(prefers-reduced-motion: reduce)': false });
        document.body.innerHTML = '<div class="site-stage"><span data-plane="far"></span></div>';

        const runtime = mountScene(window);

        expect(document.documentElement.dataset.motion).toBe('on');
        expect(document.documentElement.dataset.sceneTier).toMatch(/^(full|reduced|minimal)$/);

        runtime?.stop();

        expect(
            document.documentElement.dataset.motion,
            'SAHNE-02: sahne durdurulduğunda kanca da silinmeli; kalan bir ' +
                'öznitelik, artık çalışmayan bir motora ait kuralları doğurur.',
        ).toBeUndefined();
    });
});

describe('cihaz derecesi (SAHNE-03)', () => {
    const view = (cores: number, memory: number, width = 1280, height = 800, dpr = 1) =>
        ({
            navigator: { hardwareConcurrency: cores, deviceMemory: memory },
            innerWidth: width,
            innerHeight: height,
            devicePixelRatio: dpr,
        }) as unknown as Window;

    it('bilinmeyen bir cihaz GÜÇLÜ sayılmaz', () => {
        const unknown = {
            navigator: {},
            innerWidth: 1280,
            innerHeight: 800,
            devicePixelRatio: 1,
        } as unknown as Window;

        expect(
            estimateTier(unknown),
            'SAHNE-03: ipucu vermeyen bir cihazı `full` saymak, hatayı en zayıf ' +
                'cihaza ödetirdi.',
        ).toBe('reduced');
    });

    it('iki çekirdekli bir telefon en düşük dereceye iner', () => {
        expect(estimateTier(view(2, 2, 320, 480, 2))).toBe('minimal');
    });

    it('çok pikselli bir ekran, çekirdek sayısı yüksek olsa da derecelenir', () => {
        expect(estimateTier(view(8, 8, 2560, 1440, 2))).toBe('reduced');
    });

    it('güçlü bir masaüstü tam dereceyi alır', () => {
        expect(estimateTier(view(8, 8, 1440, 900, 1))).toBe('full');
    });

    it('merdiven aşağı iner ve TEK BASAMAK yukarı çıkar', () => {
        expect(demote('full')).toBe('reduced');
        expect(demote('reduced')).toBe('minimal');
        expect(demote('minimal')).toBe('minimal');

        /*
            Yükseliş tek basamak: `minimal`den doğrudan `full`e çıkmak,
            cihazın kaldıramadığı yükü tek adımda geri yüklemek olurdu.
        */
        expect(promote('minimal')).toBe('reduced');
        expect(promote('reduced')).toBe('full');
        expect(promote('full')).toBe('full');
    });
});

describe('kare süresi yöneticisi (SAHNE-04)', () => {
    it('tek bir uzun kare dereceyi düşürmez', () => {
        const governor = new FrameGovernor(22, 30);

        expect(governor.observe(0.05)).toBeNull();
    });

    it('sürekli aşım dereceyi düşürür', () => {
        const governor = new FrameGovernor(22, 5);
        const verdicts = [1, 2, 3, 4, 5].map(() => governor.observe(0.04));

        expect(verdicts).toEqual([null, null, null, null, 'demote']);
    });

    it('araya giren tek bir hızlı kare sayacı sıfırlar', () => {
        const governor = new FrameGovernor(22, 3);
        governor.observe(0.04);
        governor.observe(0.04);
        governor.observe(0.008);

        expect(governor.observe(0.04)).toBeNull();
    });

    it('arka plandaki sekmenin yavaş kareleri ölçüme girmez', () => {
        const governor = new FrameGovernor(22, 2);
        /* Sekme gizliyken tarayıcı kareleri saniyede bire düşürür. */
        expect(governor.observe(1)).toBeNull();
        expect(governor.observe(1)).toBeNull();
    });
});

describe('merdiven iki yönlü ama SİMETRİK DEĞİL (SAHNE-08)', () => {
    /*
        Döngü 1'de merdiven tek yönlüydü ve bunun ölçülmemiş bir bedeli vardı:
        bir kez ısınan cihaz, rahatladıktan sonra bile oturum boyunca en sade
        sahnede kalıyordu (`docs/146` §9 madde 10). Döngü 2 yükselişi ekledi
        ama PAHALI yaptı — aşağıdaki üç test tam olarak o pahalılığı ölçüyor.
    */
    it('sakin kareler dereceyi yükseltir, ama inişten ÇOK daha geç', () => {
        const governor = new FrameGovernor(22, 3, 10);

        /* Tavanın altında ama sakin eşiğinin (22 × 0,75 = 16,5 ms) üstünde
           seyreden bir cihaz yükselmez: tam tavanda gezineni yükseltmek, onu
           bir kare sonra yine indirmek olurdu. */
        for (let i = 0; i < 40; i += 1) {
            expect(governor.observe(0.02)).toBeNull();
        }

        const verdicts = Array.from({ length: 10 }, () => governor.observe(0.008));

        expect(verdicts.slice(0, 9)).toEqual(Array(9).fill(null));
        expect(verdicts[9]).toBe('promote');
    });

    it('her karardan sonra gereken sakinlik İKİYE KATLANIR', () => {
        const governor = new FrameGovernor(22, 3, 4);

        /* Birinci yükseliş: dört sakin kare. */
        expect([1, 2, 3, 4].map(() => governor.observe(0.008)).at(-1)).toBe('promote');

        /* İkincisi sekiz kare ister — dördüncüde HÂLÂ yükselmez. */
        expect([1, 2, 3, 4].map(() => governor.observe(0.008)).at(-1)).toBeNull();
        expect([1, 2, 3, 4].map(() => governor.observe(0.008)).at(-1)).toBe('promote');
    });

    it('üç yükselişten sonra merdiven yine tek yönlü olur', () => {
        const governor = new FrameGovernor(22, 3, 1);
        const moves: Array<string | null> = [];

        for (let i = 0; i < 200; i += 1) {
            moves.push(governor.observe(0.008));
        }

        expect(
            moves.filter((move) => move === 'promote').length,
            'SAHNE-08: salınım kendi kendini söndürmeli; sonsuz yükselen bir ' +
                'merdiven, kılık değiştiren bir sahne demektir.',
        ).toBe(3);
    });
});

describe('giriş kipi ayrımı (SAHNE-05)', () => {
    it('dokunmalı cihazda eğilme dinleyicisi HİÇ bağlanmaz', () => {
        stubMatchMedia(window, { '(pointer: fine)': false });
        document.body.innerHTML =
            '<div class="scene-tilt"><div class="scene-tilt-face"></div></div>';

        const card = document.querySelector('.scene-tilt') as HTMLElement;
        const spy = vi.spyOn(card, 'addEventListener');

        bindTilt(document, window);

        expect(
            spy,
            'SAHNE-05: dokunmada `hover` yoktur ve parmak kartın üstünü kapatır; ' +
                'orada görünmeyen bir efekt için olay işlemek boşa iştir.',
        ).not.toHaveBeenCalled();
    });

    it('işaretleyicili cihazda eğilme bağlanır ve bırakıldığında sökülür', () => {
        stubMatchMedia(window, { '(pointer: fine)': true });
        document.body.innerHTML =
            '<div class="scene-tilt"><div class="scene-tilt-face"></div></div>';

        const card = document.querySelector('.scene-tilt') as HTMLElement;
        const bound = vi.spyOn(card, 'addEventListener');
        const unbound = vi.spyOn(card, 'removeEventListener');

        const release = bindTilt(document, window);

        expect(bound).toHaveBeenCalled();

        release();

        expect(unbound).toHaveBeenCalled();
    });
});

describe('derinlik (SAHNE-06)', () => {
    it('düzlem yoksa efekt kaydedilmez', () => {
        document.body.innerHTML = '<div class="site-stage"></div>';

        expect(createParallax(document)).toBeNull();
        expect(createProgress(document)).toBeNull();
    });

    it('parallax −1…1 arasında bir ilerleme yazar ve bırakınca siler', () => {
        document.body.innerHTML = '<div class="site-stage"><span data-plane="far"></span></div>';

        const effect = createParallax(document);
        const plane = document.querySelector('[data-plane]') as HTMLElement;

        expect(effect).not.toBeNull();
        effect?.measure?.('full');
        effect?.frame({
            time: 0,
            delta: 0.016,
            scroll: 4000,
            width: 320,
            height: 480,
            pointerX: 0,
            pointerY: 0,
        });

        const shift = Number(plane.style.getPropertyValue('--scene-shift'));

        expect(shift).toBeGreaterThanOrEqual(-1);
        expect(shift).toBeLessThanOrEqual(1);

        effect?.destroy?.();

        expect(plane.style.getPropertyValue('--scene-shift')).toBe('');
    });

    it('bölüm ilerlemesi 0 ile 1 arasında kalır', () => {
        document.body.innerHTML = '<section data-scene-progress></section>';

        const effect = createProgress(document);
        const section = document.querySelector('[data-scene-progress]') as HTMLElement;

        effect?.measure?.('full');
        effect?.frame({
            time: 0,
            delta: 0.016,
            scroll: -9999,
            width: 320,
            height: 480,
            pointerX: 0,
            pointerY: 0,
        });

        expect(Number(section.style.getPropertyValue('--scene-progress'))).toBe(0);
    });

    it('`IntersectionObserver` yoksa her bölüm HEMEN doğar', () => {
        const original = globalThis.IntersectionObserver;

        // @ts-expect-error — desteklemeyen ortam bilerek taklit ediliyor.
        delete globalThis.IntersectionObserver;
        document.body.innerHTML = '<div class="scene-reveal"></div>';

        observeReveals(document);

        expect(
            (document.querySelector('.scene-reveal') as HTMLElement).dataset.revealed,
            'SAHNE-06: gözlemci yoksa içerik GİZLİ KALAMAZ — "önce gizle, sonra ' +
                'betikle göster" deseni betik düşerse sayfayı boş bırakır.',
        ).toBe('true');

        globalThis.IntersectionObserver = original;
    });
});

describe('ilk ekran giriş animasyonuna girmez (SAHNE-09)', () => {
    /*
        GÖSTERİ, DÖNÜŞÜM EYLEMİNİ GECİKTİREMEZ.

        Döngü 1'de ilk ekrandaki bir öğe de `IntersectionObserver`in geri
        çağrımını bekliyordu; üstüne `--scene-order` gecikmesi biniyordu.
        Zincirin toplamı, ziyaretçinin karar verdiği ilk saniyeye düşerdi.
        Artık ilk ekrandaki öğeler `data-motion` yazılmadan ÖNCE doğuyor.
    */
    const box = (top: number) =>
        ({ top, bottom: top + 40, left: 0, right: 100, width: 100, height: 40 }) as DOMRect;

    it('ilk ekrandaki öğe gözlemciye HİÇ verilmez, anında doğar', () => {
        document.body.innerHTML = '<div class="scene-reveal" id="ust"></div>';

        const element = document.querySelector('#ust') as HTMLElement;
        element.getBoundingClientRect = () => box(120);

        const observe = vi.fn();
        const original = globalThis.IntersectionObserver;

        globalThis.IntersectionObserver = class {
            observe = observe;
            unobserve = () => {};
            disconnect = () => {};
        } as unknown as typeof IntersectionObserver;

        observeReveals(document, { innerHeight: 480 } as Window);

        expect(element.dataset.revealed).toBe('true');
        expect(
            observe,
            'SAHNE-09: ilk ekrandaki bir öğe gözlemciye verilirse, doğuşu bir ' +
                'sonraki kareye ve bir gecikme kuyruğuna düşer.',
        ).not.toHaveBeenCalled();

        globalThis.IntersectionObserver = original;
    });

    it('ilk ekranın ALTINDAKİ öğe eskisi gibi kaydırınca doğar', () => {
        document.body.innerHTML = '<div class="scene-reveal" id="alt"></div>';

        const element = document.querySelector('#alt') as HTMLElement;
        element.getBoundingClientRect = () => box(2400);

        const observe = vi.fn();
        const original = globalThis.IntersectionObserver;

        globalThis.IntersectionObserver = class {
            observe = observe;
            unobserve = () => {};
            disconnect = () => {};
        } as unknown as typeof IntersectionObserver;

        observeReveals(document, { innerHeight: 480 } as Window);

        expect(element.dataset.revealed).toBeUndefined();
        expect(observe).toHaveBeenCalledTimes(1);

        globalThis.IntersectionObserver = original;
    });
});

describe('dokunma kamerası (SAHNE-10)', () => {
    /*
        Döngü 1'de dokunmalı cihazda kamera PASİFTİ (`docs/146` §9 madde 6):
        imleç yok, o yüzden sahne yalnız kaydırmaya tepki veriyordu. Dokunmanın
        kendi fiili SÜRÜKLEMEDİR ve aşağıdaki testler onun kameraya ULAŞTIĞINI
        ölçüyor.

        KAYDIRMAYI ÇALMADIĞI burada ölçülemez — jsdom kaydırmaz. O iddia
        gerçek Chrome'da, `scripts/scene-perf-gate` içinde ölçülüyor.
    */
    function stage(): HTMLElement {
        document.body.innerHTML =
            '<div class="site-stage"><span data-plane="far" id="hedef"></span></div>';

        return document.querySelector('.site-stage') as HTMLElement;
    }

    it('parmak sahneyi sürükleyince kamera hedefi değişir', async () => {
        stubMatchMedia(window, {
            '(pointer: fine)': false,
            '(prefers-reduced-motion: reduce)': false,
        });

        const target = stage();
        const runtime = mountScene(window);

        /* Olay sahnenin İÇİNDEN doğuyor: pencereye yükselirken `target`
           `.site-stage`in kendisi. */
        target.dispatchEvent(new PointerEvent('pointerdown', { clientX: 40, bubbles: true }));
        target.dispatchEvent(new PointerEvent('pointermove', { clientX: 200, bubbles: true }));

        const frames: number[] = [];
        runtime?.add({
            frame: (scene) => frames.push(scene.pointerX),
        });

        await new Promise((resolve) => setTimeout(resolve, 120));

        expect(
            frames.some((value) => value !== 0),
            'SAHNE-10: dokunmada kamera sürüklemeye tepki vermeli; yoksa dokunmalı ' +
                'cihazda sahnenin tek girdisi kaydırmadır.',
        ).toBe(true);

        runtime?.stop();
    });

    it('parmak kalkınca kamera yerine döner', async () => {
        stubMatchMedia(window, {
            '(pointer: fine)': false,
            '(prefers-reduced-motion: reduce)': false,
        });

        const target = stage();
        const runtime = mountScene(window);
        const frames: number[] = [];

        runtime?.add({ frame: (scene) => frames.push(scene.pointerX) });

        target.dispatchEvent(new PointerEvent('pointerdown', { clientX: 40, bubbles: true }));
        target.dispatchEvent(new PointerEvent('pointermove', { clientX: 400, bubbles: true }));

        await new Promise((resolve) => setTimeout(resolve, 120));

        const peak = Math.max(...frames.map(Math.abs));

        window.dispatchEvent(new PointerEvent('pointerup', { bubbles: true }));

        await new Promise((resolve) => setTimeout(resolve, 200));

        expect(
            Math.abs(frames[frames.length - 1]),
            'SAHNE-10: bırakılan sahne donduğu yerde kalmaz, yerine süzülür — ' +
                'dönüşü karedeki üstel yumuşatma yapar, ayrı bir animasyon değil.',
        ).toBeLessThan(peak);

        runtime?.stop();
    });

    it('sahnenin DIŞINDA başlayan bir sürükleme kamerayı çevirmez', async () => {
        stubMatchMedia(window, {
            '(pointer: fine)': false,
            '(prefers-reduced-motion: reduce)': false,
        });

        stage();

        const outside = document.createElement('form');
        document.body.append(outside);

        const runtime = mountScene(window);
        const frames: number[] = [];

        runtime?.add({ frame: (scene) => frames.push(scene.pointerX) });

        outside.dispatchEvent(new PointerEvent('pointerdown', { clientX: 10, bubbles: true }));
        outside.dispatchEvent(new PointerEvent('pointermove', { clientX: 300, bubbles: true }));

        await new Promise((resolve) => setTimeout(resolve, 120));

        expect(
            frames.every((value) => value === 0),
            'SAHNE-10: bir formun ya da menünün üstündeki parmak sahneyi çevirmez.',
        ).toBe(true);

        runtime?.stop();
    });
});

describe('palet köprüsü (SAHNE-07)', () => {
    it('onaltılık değeri üç kanala çevirir', () => {
        expect(parseColor('#ffffff')).toEqual([1, 1, 1]);
        expect(parseColor(' #000000 ')).toEqual([0, 0, 0]);
        expect(parseColor('#f00')).toEqual([1, 0, 0]);
    });

    it('çözülemeyen bir değer sahneyi karartmaz, beyaza düşer', () => {
        /* `color-mix(...)` `getComputedStyle` tarafından çözülmez; boş ya da
           tanınmayan bir değerde yıldızların KAYBOLMASI, sessiz bir sahne
           arızası olurdu. */
        expect(parseColor('color-mix(in oklab, red 20%, blue)')).toEqual([1, 1, 1]);
        expect(parseColor('')).toEqual([1, 1, 1]);
    });
});
