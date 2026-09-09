import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bindTheme, readChoice, resolveTheme } from './theme';

/**
 * GÖRÜNÜM TERCİHİNİN DURUM MANTIĞI (C2).
 *
 * ── NE ÖLÇÜLÜYOR, NE ÖLÇÜLMÜYOR ──
 *
 * Burası jsdom: DÜZEN HESAPLAMAZ ve tek bir renk BOYAMAZ. "Koyu kipte
 * bölümler arası sıçrama kalktı mı", "denetim 320 pikselde taşıyor mu"
 * soruları burada SORULAMAZ; cevapları gerçek Chrome'da ölçülür.
 *
 * Burada ölçülen şey KARARDIR: hangi tercih hangi kipe çözülür, depo
 * bozukken ne olur, işletim sistemi ne zaman dinlenir ve denetimin
 * kopyaları ayrışabilir mi.
 */

/** Kabuğun iki ayrı sunumu — ikisi de aynı durumdan beslenmeli. */
function controls(): string {
    return `
        <div data-theme-control hidden>
            <button data-theme-choice="light" aria-pressed="false">Light</button>
            <button data-theme-choice="dark" aria-pressed="false">Dark</button>
            <button data-theme-choice="system" aria-pressed="false">System</button>
        </div>
        <div data-theme-control hidden>
            <button data-theme-choice="light" aria-pressed="false">Light</button>
            <button data-theme-choice="dark" aria-pressed="false">Dark</button>
            <button data-theme-choice="system" aria-pressed="false">System</button>
        </div>
    `;
}

function memoryStorage(): Storage {
    const map = new Map<string, string>();

    return {
        getItem: (key) => map.get(key) ?? null,
        setItem: (key, value) => void map.set(key, value),
        removeItem: (key) => void map.delete(key),
        clear: () => map.clear(),
        key: () => null,
        get length() {
            return map.size;
        },
    } as Storage;
}

/** `matchMedia` YOKTUR jsdom'da; işletim sistemi burada elle taklit edilir. */
function fakeView(storage: Storage | null, prefersDark: boolean) {
    const listeners = new Set<() => void>();
    const media = {
        matches: prefersDark,
        addEventListener: (_: string, handler: () => void) => void listeners.add(handler),
        removeEventListener: (_: string, handler: () => void) => void listeners.delete(handler),
    };

    return {
        view: {
            get localStorage() {
                if (storage === null) {
                    throw new Error('storage blocked');
                }

                return storage;
            },
            matchMedia: () => media,
        } as unknown as Window,
        setSystemDark(value: boolean) {
            media.matches = value;

            for (const handler of listeners) {
                handler();
            }
        },
    };
}

function pressed(): string[] {
    return Array.from(document.querySelectorAll('[data-theme-choice]'))
        .filter((option) => option.getAttribute('aria-pressed') === 'true')
        .map((option) => (option as HTMLElement).dataset.themeChoice ?? '');
}

let release: (() => void) | null = null;

beforeEach(() => {
    document.body.innerHTML = controls();
    document.documentElement.className = '';
    document.documentElement.removeAttribute('data-theme');
});

afterEach(() => {
    release?.();
    release = null;
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

describe('tercih okuma', () => {
    it('anahtarın YOKLUĞU "sistem" demektir', () => {
        // `theme-bootstrap` bugün de böyle okuyor; üçüncü bir değer yazmak o
        // sözleşmeyi bozardı ve panelin tema davranışını da değiştirirdi.
        expect(readChoice(memoryStorage())).toBe('system');
    });

    it('tanınmayan bir değer "sistem"e düşer, hataya değil', () => {
        const storage = memoryStorage();
        storage.setItem('zabuno-theme', 'neon');

        expect(readChoice(storage)).toBe('system');
    });

    it('depo okunamıyorsa sayfa yine bir kip seçer', () => {
        const broken = {
            getItem: () => {
                throw new Error('blocked');
            },
        } as unknown as Storage;

        expect(readChoice(broken)).toBe('system');
    });
});

describe('kipe çözme', () => {
    it('açık ve koyu, işletim sistemini DİNLEMEZ', () => {
        expect(resolveTheme('light', true)).toBe('light');
        expect(resolveTheme('dark', false)).toBe('dark');
    });

    it('yalnız "sistem" işletim sistemini okur', () => {
        expect(resolveTheme('system', true)).toBe('dark');
        expect(resolveTheme('system', false)).toBe('light');
    });
});

describe('denetimin bağlanması', () => {
    it('bağlanana kadar gizlidir, bağlanınca açılır', () => {
        const { view } = fakeView(memoryStorage(), false);

        expect(document.querySelectorAll('[data-theme-control][hidden]')).toHaveLength(2);

        release = bindTheme(document, view);

        expect(document.querySelectorAll('[data-theme-control][hidden]')).toHaveLength(0);
    });

    it('bir kopyada yapılan seçim ÖTEKİ kopyada da işaretlenir', () => {
        const { view } = fakeView(memoryStorage(), false);
        release = bindTheme(document, view);

        const [firstControl, secondControl] = Array.from(
            document.querySelectorAll<HTMLElement>('[data-theme-control]'),
        );

        firstControl.querySelector<HTMLButtonElement>('[data-theme-choice="dark"]')?.click();

        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(pressed()).toEqual(['dark', 'dark']);
        expect(
            secondControl.querySelector('[data-theme-choice="dark"]')?.getAttribute('aria-pressed'),
        ).toBe('true');
    });

    it('seçim depoya yazılır; "sistem" anahtarı SİLER', () => {
        const storage = memoryStorage();
        const { view } = fakeView(storage, false);
        release = bindTheme(document, view);

        document.querySelector<HTMLButtonElement>('[data-theme-choice="light"]')?.click();
        expect(storage.getItem('zabuno-theme')).toBe('light');

        document.querySelector<HTMLButtonElement>('[data-theme-choice="system"]')?.click();
        expect(storage.getItem('zabuno-theme')).toBeNull();
    });

    it('depo yazılamıyorsa seçim yine UYGULANIR — yalnız hatırlanmaz', () => {
        const { view } = fakeView(null, false);
        release = bindTheme(document, view);

        document.querySelector<HTMLButtonElement>('[data-theme-choice="dark"]')?.click();

        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
        expect(pressed()).toEqual(['dark', 'dark']);
    });

    it('işletim sistemi YALNIZ "sistem" hâlinde dinlenir', () => {
        const { view, setSystemDark } = fakeView(memoryStorage(), false);
        release = bindTheme(document, view);

        expect(document.documentElement.getAttribute('data-theme')).toBe('light');

        setSystemDark(true);
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');

        // Açık kipi ELLE seçen biri, akşam cihazı koyulaştığında aydınlıkta kalır.
        document.querySelector<HTMLButtonElement>('[data-theme-choice="light"]')?.click();
        setSystemDark(true);
        expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    });

    it('denetim yoksa hiçbir dinleyici açılmaz', () => {
        document.body.innerHTML = '<p>yasal metin</p>';
        const { view } = fakeView(memoryStorage(), true);

        release = bindTheme(document, view);

        expect(document.documentElement.getAttribute('data-theme')).toBeNull();
    });
});
