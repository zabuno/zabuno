import { afterEach, describe, expect, it, vi } from 'vitest';
import { FrameGovernor, demote, estimateTier } from './tier';
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

    it('merdiven yalnız aşağı iner', () => {
        expect(demote('full')).toBe('reduced');
        expect(demote('reduced')).toBe('minimal');
        expect(demote('minimal')).toBe('minimal');
    });
});

describe('kare süresi yöneticisi (SAHNE-04)', () => {
    it('tek bir uzun kare dereceyi düşürmez', () => {
        const governor = new FrameGovernor(22, 30);

        expect(governor.observe(0.05)).toBe(false);
    });

    it('sürekli aşım dereceyi düşürür', () => {
        const governor = new FrameGovernor(22, 5);
        const verdicts = [1, 2, 3, 4, 5].map(() => governor.observe(0.04));

        expect(verdicts).toEqual([false, false, false, false, true]);
    });

    it('araya giren tek bir hızlı kare sayacı sıfırlar', () => {
        const governor = new FrameGovernor(22, 3);
        governor.observe(0.04);
        governor.observe(0.04);
        governor.observe(0.008);

        expect(governor.observe(0.04)).toBe(false);
    });

    it('arka plandaki sekmenin yavaş kareleri ölçüme girmez', () => {
        const governor = new FrameGovernor(22, 2);
        /* Sekme gizliyken tarayıcı kareleri saniyede bire düşürür. */
        expect(governor.observe(1)).toBe(false);
        expect(governor.observe(1)).toBe(false);
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
