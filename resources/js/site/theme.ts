/**
 * GÖRÜNÜM TERCİHİ — üç seçenek, tek kaynak (C2).
 *
 * ── SÖZLEŞME DEĞİŞMEDİ ──
 *
 * Belgenin kökündeki `data-theme` / `dark` / `color-scheme` üçlüsünü ilk
 * boyamadan önce `partials/theme-bootstrap.blade.php` yazıyor ve o dosyaya
 * DOKUNULMADI: aynı anahtar (`zabuno-theme`), aynı okuma kuralı, aynı
 * uygulama. Burada olan tek yeni şey, o anahtarı DEĞİŞTİRMENİN bir yolu.
 *
 * "Sistem" hâli anahtarın YOKLUĞUDUR. Bir üçüncü değer yazmak, bootstrap'in
 * bugünkü kuralını (`stored !== 'light'` ise işletim sistemine bak) sessizce
 * bozardı; oysa yokluk o kuralın zaten doğru okuduğu hâl. Böylece paneldeki
 * tema davranışı da olduğu gibi kalır.
 *
 * ── BETİK GELMEZSE ──
 *
 * Denetim sunucuda `hidden` doğar ve yalnız burası onu açar. Çalışmayan üç
 * düğme göstermektense hiç göstermemek: gezinti betiksiz de eksiksizdir
 * (`docs/118` E8), tema tercihi ise tarayıcının belleğinde yaşar ve
 * sunucunun söyleyecek bir sözü yoktur.
 */

const STORAGE_KEY = 'zabuno-theme';
const DARK_QUERY = '(prefers-color-scheme: dark)';

export type ThemeChoice = 'light' | 'dark' | 'system';

/**
 * Depodan okunan tercih. Depo yoksa ya da okumak atarsa (gizli sekme, üçüncü
 * taraf çerez engeli, dolu kota) sonuç "sistem"dir — yani ziyaretçinin
 * cihazı ne diyorsa o. Bir depo arızası burada bir HATA değil, bir varsayılan
 * üretir; sayfa hiçbir şey kaybetmez.
 */
export function readChoice(storage: Storage | null): ThemeChoice {
    try {
        const stored = storage?.getItem(STORAGE_KEY);

        return stored === 'dark' || stored === 'light' ? stored : 'system';
    } catch {
        return 'system';
    }
}

/** Tercih + işletim sistemi = ekrana basılan kip. */
export function resolveTheme(choice: ThemeChoice, prefersDark: boolean): 'light' | 'dark' {
    if (choice === 'system') {
        return prefersDark ? 'dark' : 'light';
    }

    return choice;
}

/** Bootstrap'in yazdığı üç şeyin aynısı; ikinci bir tanım yok. */
export function applyTheme(root: HTMLElement, theme: 'light' | 'dark'): void {
    root.classList.toggle('dark', theme === 'dark');
    root.setAttribute('data-theme', theme);
    root.style.colorScheme = theme;
}

function writeChoice(storage: Storage | null, choice: ThemeChoice): void {
    try {
        if (choice === 'system') {
            storage?.removeItem(STORAGE_KEY);

            return;
        }

        storage?.setItem(STORAGE_KEY, choice);
    } catch {
        /*
            Yazamamak sessizdir ve bilerek: seçim bu sayfada YİNE de uygulanır,
            yalnız bir sonraki sayfada hatırlanmaz. Kullanıcıya "tercihiniz
            kaydedilemedi" diye bir şerit göstermek, kapatamayacağı bir tarayıcı
            ayarı için ondan özür dilemek olurdu.
        */
    }
}

/**
 * Belgedeki BÜTÜN kopyaları tek durumdan besler.
 *
 * Üst çubuk, menü bölmesi ve iki altbilgi sunumu aynı parçayı taşıyor.
 * Kopyalar birbirini dinlemez — hepsi aynı `apply()` çağrısından işaretlenir,
 * dolayısıyla ikisi asla ayrışamaz.
 */
export function bindTheme(root: Document, view: Window): () => void {
    const controls = Array.from(root.querySelectorAll<HTMLElement>('[data-theme-control]'));

    if (controls.length === 0) {
        return () => {};
    }

    let storage: Storage | null = null;

    try {
        storage = view.localStorage;
    } catch {
        storage = null;
    }

    /*
        `matchMedia` YOKSA da denetim çalışır: "sistem" o zaman açık kipe
        düşer, tıpkı bootstrap'teki gibi. Bir yetenek eksikliği denetimi
        kaldırmaz, yalnız üçüncü seçeneğin cevabını sabitler.
    */
    let media: MediaQueryList | null = null;

    try {
        media = view.matchMedia ? view.matchMedia(DARK_QUERY) : null;
    } catch {
        media = null;
    }

    let choice = readChoice(storage);

    const apply = (): void => {
        applyTheme(root.documentElement, resolveTheme(choice, media?.matches === true));

        for (const control of controls) {
            for (const option of control.querySelectorAll<HTMLButtonElement>(
                '[data-theme-choice]',
            )) {
                option.setAttribute(
                    'aria-pressed',
                    option.dataset.themeChoice === choice ? 'true' : 'false',
                );
            }
        }
    };

    const onClick = (event: Event): void => {
        const option = (event.target as Element | null)?.closest<HTMLElement>(
            '[data-theme-choice]',
        );
        const picked = option?.dataset.themeChoice;

        if (picked !== 'light' && picked !== 'dark' && picked !== 'system') {
            return;
        }

        choice = picked;
        writeChoice(storage, choice);
        apply();
    };

    /*
        İŞLETİM SİSTEMİ YALNIZ "SİSTEM" HÂLİNDE DİNLENİR.

        Koyuyu elle seçmiş biri, akşam telefonu kendiliğinden koyulaştığında
        bir şey görmemelidir — zaten koyudadır. Ama açık seçmiş biri o anda
        aydınlıkta kalmalıdır; dinleyiciyi her hâlde uygulamak, kullanıcının
        kararını cihazın kararıyla EZMEK olurdu.
    */
    const onSystemChange = (): void => {
        if (choice === 'system') {
            apply();
        }
    };

    for (const control of controls) {
        control.addEventListener('click', onClick);
        control.removeAttribute('hidden');
    }

    media?.addEventListener('change', onSystemChange);

    apply();

    return () => {
        for (const control of controls) {
            control.removeEventListener('click', onClick);
        }

        media?.removeEventListener('change', onSystemChange);
    };
}
