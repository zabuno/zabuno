// Barrel (`'flowbite-react'`) DEĞİL alt-yol import'u. Barrel, kütüphanenin
// tamamını çeker; bu dosyayı `ThemeRoot` üzerinden PAYLAŞILAN chunk'a bağlı
// olduğu için o fark ~67 KB gzip'e mal oluyordu (`DS-BUNDLE-BUDGET-07`).
// `createTheme` kimlik fonksiyonudur; yalnız Tailwind IntelliSense içindir.
// Varsayılan temalar da ALT YOLDAN gelir; barrel bu dosyayı paylaşılan
// chunk'a bağladığı için ~67 KB gzip'e mal olurdu (`DS-BUNDLE-BUDGET-07`).
import { dropdownTheme } from 'flowbite-react/components/Dropdown';
import { drawerTheme } from 'flowbite-react/components/Drawer';
import { modalTheme } from 'flowbite-react/components/Modal';
import { tableTheme } from 'flowbite-react/components/Table';
import { createTheme } from 'flowbite-react/helpers/create-theme';

/**
 * Flowbite → token kökü bağlaması.
 *
 * **Neden var.** `docs/37 §2.3` primitive kaynağı olarak Flowbite React'i
 * seçer, ama Flowbite'ın VARSAYILAN teması kendi ham Tailwind paletini ve
 * sabit piksel geometrisini getirir. `<Button color="light">` tek başına iki
 * kuralı birden çiğniyordu:
 *
 * - Gri bir kenarlık, beyaz bir zemin ve gri bir metin — üçü de ham
 *   Tailwind paletinden, hiçbiri token'dan (`DS-RATCHET-01`). Bir tonu
 *   token kökünde değiştirmek bu butonu değiştirmiyordu, yani "merkezî
 *   yönetilebilirlik testi" (`docs/37 §2.1`) bu bileşende BAŞARISIZDI.
 * - `h-10` — sabit yükseklik (`DS-DENSITY-CONTRACT-05`). Yoğunluk modu
 *   comfortable'a alındığında satırlar büyüyor, butonlar büyümüyordu.
 *
 * Bu yüzden bir süre `PlainButton` yaşadı: yalnız token'lardan giyinen,
 * Flowbite'ı atlayan ikinci bir buton. İkinci bir primitive `docs/37 §2.3`
 * "duplicate yasağı"nın tam olarak yasakladığı şeydir ve `PlainButton`'ın
 * kendi docblock'u da bunu geçici ilan ediyordu. Bu dosya o geçiciliği
 * bitirir: tema köke bağlanır, `PlainButton` kaldırılır ve tek bir buton
 * kalır.
 *
 * **Nasıl okunur.** Her sınıf ya semantic bir renk token'ıdır
 * (`bg-surface`, `text-fg`, `border-border-danger`, `outline-focus`) ya da
 * bir geometri token'ıdır (`var(--control-height)`,
 * `var(--control-padding-inline)`). Ham palet ve ham piksel burada da
 * yasaktır — bu dosya token kökünün İSTİSNASI değil, TÜKETİCİSİDİR.
 *
 * **`applyTheme: 'replace'`.** Varsayılan `merge`, iki sınıf listesini
 * `twMerge` ile BİRLEŞTİRİR — ve `twMerge` yalnız aynı CSS özelliğini
 * çakıştırabilir. `h-10` ile `min-h-[var(--control-height)]` farklı
 * özelliklerdir, yani merge altında Flowbite'ın sabit yüksekliği HAYATTA
 * KALIRDI. `replace` bunu kapatır: bağlanan her anahtarda yalnız bu
 * dosyadaki değer geçerlidir.
 *
 * `replace` bağlanmayan bir anahtarı BOŞALTMAZ, Flowbite'ın varsayılanını
 * bırakır. Bu yüzden aşağıdaki `BLANK_PALETTE_*` haritaları var: sistemde
 * semantik karşılığı olmayan palet adları (`purple`, `teal`, `lime` …)
 * açıkça boşaltılır, yoksa `color="purple"` yazan biri ham palete sessizce
 * geri düşerdi.
 *
 * **Yazı boyutu burada AÇIK UZUNLUK yazımıyla verilir.**
 * `tailwind-merge`, tanımadığı bir `text-*` sınıfını RENK sanar. Rol adlı
 * ölçeğimiz (`text-body`, `text-meta`) tam olarak bu tuzağa düşüyordu:
 *
 *     twMerge('text-fg', 'text-body')  →  'text-body'   ← renk KAYBOLDU
 *
 * `resources/js/lib/utils.ts` içindeki `cn()` bunu birleştiriciye tanıtarak
 * çözüyor, ama Flowbite KENDİ twMerge örneğini kullanır ve ona ulaşamayız.
 * `text-[length:…]` yazımı ise stok birleştiricinin kesinlikle yazı boyutu
 * olarak tanıdığı biçimdir; renk hayatta kalır, iki boyut yine doğru
 * çakışır. Bu yüzden yalnız BU dosyada bu yazım kullanılır.
 *
 * **`base` nötr görünümü taşır.** Flowbite `twMerge(base, size, …, color)`
 * sırasıyla birleştirir, yani `color` her zaman `base`'i yener. Nötr
 * görünümü `base`'e koymak, haritada olmayan bir Flowbite palet adının
 * (`color="purple"` gibi) renksiz değil NÖTR düşmesini sağlar: sistemde
 * anlamı olmayan bir renk sessizce ürüne sızmaz, ama arayüz de bozulmaz.
 *
 * Requirement ID: DS-FLOWBITE-TOKEN-BIND-10.
 */

/**
 * Klavye odağı: `app.css` tabanıyla aynı dil — ring değil outline.
 *
 * `focus:outline-none` KALDIRILDI. Öğe hem `:focus` hem `:focus-visible`
 * olduğunda ikisi de uygulanıyor ve `outline-none`, Tailwind'in
 * `--tw-outline-style` değişkenini `none` yapıyordu; `outline-2` ile
 * `outline-focus` genişliği ve rengi verse de çizgi hiç çizilmiyordu.
 * Tarayıcının kendi halkasını kapatmak zaten `app.css`'teki `:focus`
 * kuralının işi (docs/71).
 *
 * `outline-solid` AÇIKÇA yazılır: genişlik ve renk sınıfları biçimi
 * belirlemez ve varsayılan biçim bir başkası tarafından `none`'a
 * çekilebilir.
 */
const FOCUS_RING =
    'focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus';

/** Geçiş: ham süre değil motion token'ı (`DS-MOTION-CONTRACT-08`). */
const TRANSITION =
    'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)]';

/**
 * Metin girdisi ailesinin ortak gövdesi (input / select / textarea).
 * Üçü aynı satırda yan yana durduğunda birbirine BENZEMEK zorunda değil,
 * aynı olmalıdır — bu yüzden tek bir sabitten türerler.
 */
const FIELD_BASE = [
    'block w-full rounded-lg border bg-surface text-fg',
    'placeholder:text-fg-muted',
    TRANSITION,
    FOCUS_RING,
    'disabled:cursor-not-allowed disabled:opacity-60',
].join(' ');

/**
 * Alanın kenarlığı durumu anlatır; zemini değil. Flowbite varsayılanı hata
 * durumunda alanın ZEMİNİNİ de soluk kırmızıya çeviriyordu ve o zemin
 * karanlık temada metni okunmaz yapıyordu. Durum tek bir kanaldan —
 * kenarlıktan — anlatılır,
 * `aria-invalid` ile birlikte (renk tek başına anlam taşımaz, WCAG 1.4.1).
 */
const FIELD_COLORS = {
    // `border-border` DEĞİL: o jeton kart ve ayraçlar için ve beyaz zemine
    // karşı 1.35:1 veriyordu. Bir girdinin var olduğunu anlatan tek görsel
    // ipucu kenarlığıysa, görülemeyen bir kenarlık girdiyi de görünmez yapar
    // (WCAG 2.2 AA, metin dışı kontrast 3:1).
    gray: 'border-border-control',
    info: 'border-border-info',
    failure: 'border-border-danger',
    warning: 'border-border-warning',
    success: 'border-border-success',
};

const FIELD_SIZES = {
    sm: 'min-h-[var(--control-height)] px-[var(--density-padding-inline)] py-[var(--space-1)] text-[length:var(--text-meta)]',
    md: 'min-h-[var(--control-height)] px-[var(--density-padding-inline)] py-[var(--space-2)] text-[length:var(--text-body)]',
    lg: 'min-h-[var(--control-height)] px-[var(--density-padding-inline)] py-[var(--space-3)] text-body',
};

/** Addon ve ikon kabukları — hepsi logical yön kullanır (RTL, `DS-LOGICAL-DIRECTION-06`). */
const FIELD_ADDON =
    'inline-flex items-center rounded-s-lg border border-e-0 border-border bg-surface-subtle px-[var(--density-padding-inline)] text-[length:var(--text-body)] text-fg-secondary';

const FIELD_ICON = {
    base: 'pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3',
    svg: 'size-[var(--control-indicator-size)] text-fg-muted',
};

/**
 * Bu sistemde anlamı olmayan Flowbite palet adları. Boş bırakılırlar, silinmiş
 * olmazlar: `replace` yalnız YAZILAN anahtarı değiştirir, yazılmayan anahtarda
 * Flowbite'ın ham paleti yerinde kalır. Boşaltıldıklarında görünüm `base`'in
 * nötr hâline düşer — yani yanlış bir renk adı arayüzü bozmaz, ama ham palet
 * de üretmez.
 */
const BLANK_PALETTE_NAMES = [
    'blue',
    'cyan',
    'green',
    'indigo',
    'lime',
    'pink',
    'purple',
    'red',
    'teal',
    'yellow',
] as const;

const BLANK_PALETTE: Record<string, string> = Object.fromEntries(
    BLANK_PALETTE_NAMES.map((name) => [name, '']),
);

export const buttonTokenTheme = createTheme({
    base: [
        'relative inline-flex items-center justify-center gap-2 text-center font-medium',
        // Geometri yoğunluktan türer; sabit yükseklik yok. `min-h` seçildi
        // çünkü sabit `h` iki satıra sarmalanan bir etiketi taşırdı.
        'min-h-[var(--control-height)] rounded-lg border',
        // Nötr görünüm burada durur; `color` gerektiğinde üstüne yazar.
        // HOVER burada DURMAZ: `twMerge` yalnız aynı özelliği çakıştırır, yani
        // `base`'e konan bir `hover:bg-*`, onu yeniden tanımlamayan HER renk
        // varyantına sızardı — dolu sarı bir buton üzerine gelindiğinde nötr
        // griye dönüyordu. Hover, rengin kendi kararıdır.
        'border-border bg-surface text-fg-secondary',
        TRANSITION,
        FOCUS_RING,
    ].join(' '),
    // Flowbite bunu `disabled:` varyantıyla değil, prop'a bakıp doğrudan
    // uygular; bu yüzden varyant öneki yok.
    disabled: 'pointer-events-none opacity-60',
    fullSized: 'w-full',
    grouped: 'rounded-none border-s-0 first:rounded-s-lg first:border-s last:rounded-e-lg',
    pill: 'rounded-pill',
    /**
     * Boy YALNIZ tipografi ve yatay boşluğu değiştirir.
     *
     * Dokunma hedefi bir boy varyantının pazarlık konusu değildir: `xs` bir
     * butonu 32px'e indirmek, onu masaüstünde küçültmez, TELEFONDA
     * dokunulamaz yapar. Yükseklik `--control-height` ile ortaktır ve o da
     * `--density-hit-area-min`'in altına inemez (`app.css`).
     */
    size: {
        xs: 'px-[var(--control-padding-inline)] text-[length:var(--text-meta)]',
        sm: 'px-[var(--control-padding-inline)] text-[length:var(--text-body)]',
        md: 'px-[var(--control-padding-inline)] text-[length:var(--text-body)]',
        lg: 'px-[calc(var(--control-padding-inline)*1.5)] text-body',
        xl: 'px-[calc(var(--control-padding-inline)*2)] text-body',
    },
    /**
     * Renk adları Flowbite'ın sözlüğünden gelir; anlamları BU sistemden.
     * `default` birincil eylemdir ve `--color-action`'a bağlıdır — aynı ton
     * frontpage'de de kullanıldığı için marka iki yere ayrılmaz
     * (`app.css`, birincil eylem notu).
     *
     * `docs/06 §11`: marka sarısı asla metin ön planı değildir. Bu yüzden
     * sarı ZEMİN + `--color-action-fg` METİN olarak kullanılır; tersi değil.
     */
    color: {
        ...BLANK_PALETTE,
        // Dolu/tonlu yüzeyler tonlarını hover'da KORUR ve yalnız koyulaşır
        // (`PlainButton`'ın kullandığı deyim). Nötr yüzeyler bir üst tona geçer.
        default: 'border-action bg-action text-action-fg hover:brightness-95',
        alternative:
            'border-border bg-surface text-fg-secondary hover:bg-surface-hover hover:text-fg',
        light: 'border-border bg-surface text-fg-secondary hover:bg-surface-hover hover:text-fg',
        gray: 'border-border bg-surface-subtle text-fg-secondary hover:bg-surface-hover hover:text-fg',
        dark: 'border-border-strong bg-surface-active text-fg hover:bg-surface-hover',
        failure: 'border-border-danger bg-surface-danger text-fg-danger hover:brightness-95',
        success: 'border-border-success bg-surface-success text-fg-success hover:brightness-95',
        warning: 'border-border-warning bg-surface-warning text-fg-warning hover:brightness-95',
        info: 'border-border-info bg-surface-info text-fg-link hover:brightness-95',
    },
    /**
     * Outline varyantı zemini boşaltır, kenarlığı konuşturur. Metin `--fg`
     * kalır: marka sarısını metne çevirmek `docs/06 §11`'in yasakladığı şey
     * ve her temada kontrastı düşürür.
     */
    outlineColor: {
        ...BLANK_PALETTE,
        default: 'border-action bg-transparent text-fg hover:bg-action hover:text-action-fg',
        dark: 'border-border-strong bg-transparent text-fg hover:bg-surface-hover',
        light: 'border-border bg-transparent text-fg-secondary hover:bg-surface-hover hover:text-fg',
        gray: 'border-border-strong bg-transparent text-fg-secondary hover:bg-surface-hover hover:text-fg',
        failure: 'border-border-danger bg-transparent text-fg-danger hover:bg-surface-danger',
        success: 'border-border-success bg-transparent text-fg-success hover:bg-surface-success',
        warning: 'border-border-warning bg-transparent text-fg-warning hover:bg-surface-warning',
        info: 'border-border-info bg-transparent text-fg-link hover:bg-surface-info',
    },
});

export const textInputTokenTheme = createTheme({
    base: 'flex',
    addon: FIELD_ADDON,
    field: {
        base: 'relative w-full',
        icon: FIELD_ICON,
        rightIcon: {
            base: 'pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3',
            svg: FIELD_ICON.svg,
        },
        input: {
            base: FIELD_BASE,
            sizes: FIELD_SIZES,
            colors: FIELD_COLORS,
            withIcon: { on: 'ps-10', off: '' },
            withRightIcon: { on: 'pe-10', off: '' },
            withAddon: { on: 'rounded-s-none', off: '' },
            // Flat 2.0: derinlik gölgeyle değil TONLA anlatılır (`docs/37 §1`).
            withShadow: { on: '', off: '' },
        },
    },
});

export const selectTokenTheme = createTheme({
    base: 'flex',
    addon: FIELD_ADDON,
    field: {
        base: 'relative w-full',
        icon: FIELD_ICON,
        select: {
            base: [
                FIELD_BASE,
                'appearance-none pe-10',
                // Ok, Flowbite'ın sabit `#6B7280` ikonu değil tema başına
                // tanımlanmış `--control-arrow-icon`; karanlık temada da
                // görünür (`app.css`).
                'bg-[image:var(--control-arrow-icon)] bg-[length:0.75em_0.75em] bg-[position:right_0.75rem_center] bg-no-repeat',
            ].join(' '),
            sizes: FIELD_SIZES,
            colors: FIELD_COLORS,
            withIcon: { on: 'ps-10', off: '' },
            withAddon: { on: 'rounded-s-none', off: '' },
            withShadow: { on: '', off: '' },
        },
    },
});

export const textareaTokenTheme = createTheme({
    base: [
        FIELD_BASE,
        'min-h-[var(--control-height)] px-[var(--density-padding-inline)] py-[var(--space-2)] text-[length:var(--text-body)]',
    ].join(' '),
    colors: FIELD_COLORS,
    withShadow: { on: '', off: '' },
});

export const checkboxTokenTheme = createTheme({
    base: [
        'size-[var(--control-indicator-size)] shrink-0 appearance-none rounded-sm border',
        'border-border bg-surface',
        'bg-[length:0.55em_0.55em] bg-center bg-no-repeat',
        'checked:border-transparent checked:bg-[image:var(--control-check-icon)]',
        TRANSITION,
        FOCUS_RING,
        'disabled:cursor-not-allowed disabled:opacity-60',
    ].join(' '),
    /**
     * İşaretli kutunun ZEMİNİ değişir; onay işaretinin kendisi
     * `--control-check-icon`'dur ve koyu çizilir. Flowbite'ın beyaz onay
     * işareti marka sarısı bir zeminde okunmuyordu — ikonun rengi bileşenin
     * değil, token kökünün kararıdır.
     */
    color: {
        ...BLANK_PALETTE,
        default: 'checked:bg-action',
        dark: 'checked:bg-fg',
        gray: 'checked:bg-fg-secondary',
        light: 'checked:bg-fg-secondary',
        failure: 'checked:bg-fg-danger',
        success: 'checked:bg-fg-success',
        warning: 'checked:bg-fg-warning',
        info: 'checked:bg-fg-link',
    },
    indeterminate: 'border-transparent bg-action bg-[image:var(--control-dash-icon)]',
});

/**
 * `ThemeProvider` için tek nesne.
 *
 * Katalog primitifleri kendi dilimlerini AYRICA prop olarak uygular
 * (bkz. `catalog/forms/micro/*`), çünkü bir provider yalnız ağacın altındaki
 * bileşenleri kapsar: sağlayıcısız render edilen bir test ya da story
 * sessizce ham palete düşerdi. Provider ise deponun kalanını kapsar —
 * `auth/`, `workspace/` ve `admin/` altında Flowbite `Button`/`TextInput`
 * DOĞRUDAN import eden ~20 dosya var ve onlar katalogtan geçmiyor.
 *
 * İki uygulama noktası, TEK tanım: yukarıdaki nesneler.
 */
/**
 * Etiket. Flowbite'ın varsayılanı açık ve karanlık tema için iki ayrı ham
 * palet sınıfı yazar; yani aynı kararı iki kez. `--fg` her temada kendi
 * değerini bilir, bir kez yazılır.
 */
export const labelTokenTheme = createTheme({
    root: {
        base: 'text-[length:var(--text-body)] font-medium',
        disabled: 'opacity-60',
        colors: {
            default: 'text-fg',
            gray: 'text-fg-secondary',
            info: 'text-fg-link',
            failure: 'text-fg-danger',
            warning: 'text-fg-warning',
            success: 'text-fg-success',
        },
    },
});

/**
 * AÇILIR MENÜ, ÇEKMECE ve DİYALOG — `docs/102` §5h.
 *
 * Bu üç aile 2026-09-04'e kadar Flowbite'ın VARSAYILANIYLA çiziliyordu.
 * Flowbite'ın `gray` paleti Tailwind'in varsayılan grisidir ve MAVİYE
 * ÇALAR (`gray-700` = `rgb(55 65 81)`, hue ~260); Zabuno'nun yüzey
 * token'ları kromasızdır. Sonuç ekranda görüldü: restoran panelinde sayfa
 * nötr siyahken hesap menüsü ve gezinti çekmecesi lacivert-gri bir yüzeyde
 * açılıyordu — iki ayrı tasarım sistemi aynı ekranda.
 *
 * Çağrı noktasında `className` ile geçmek İŞE YARAMADI: Flowbite tema
 * sınıfını da aynı öğeye basar ve kazananı sınıf sırası değil CSS kaynak
 * sırası belirler (tarayıcıda ölçüldü: panel `rgb(55,65,81)` kaldı).
 *
 * Bu yüzden aile BAĞLANIR. Kapı (DS-FLOWBITE-TOKEN-BIND-10) bağlanan ailenin
 * `replace` olmasını şart koşar — haklı: yarım `merge`, geometri sınıflarını
 * üst üste yayınlar. Bu yüzden kütüphanenin KENDİ varsayılan teması taban
 * alınır ve yalnız renk taşıyan yapraklar üstüne yazılır; sonuç eksiksiz bir
 * tema olduğu için `replace` güvenlidir.
 */
type ThemeLeaf = string | { [key: string]: ThemeLeaf };

function overrideLeaves<T>(base: T, overrides: Record<string, ThemeLeaf>): T {
    const result: Record<string, ThemeLeaf> = { ...(base as Record<string, ThemeLeaf>) };

    for (const [key, value] of Object.entries(overrides)) {
        const current = result[key];

        result[key] =
            typeof value === 'string' || typeof current !== 'object' || current === null
                ? value
                : overrideLeaves(current, value as Record<string, ThemeLeaf>);
    }

    return result as T;
}

/** Flowbite'ın mavi tonlu gri yüzeyleri → Zabuno kromasız token'ları. */
export const dropdownTokenTheme = createTheme(
    overrideLeaves(dropdownTheme, {
        floating: {
            base: 'z-10 w-fit rounded-lg border border-border bg-surface text-fg-secondary focus:outline-none',
            content: 'py-1 text-body',
            divider: 'my-1 h-px bg-border',
            header: 'block px-4 py-2 text-meta text-fg-muted',
            item: {
                base: 'flex w-full cursor-pointer items-center justify-start px-4 py-2 text-body text-fg-secondary hover:bg-surface-hover focus:bg-surface-hover focus:outline-none',
            },
            style: {
                auto: 'border border-border bg-surface text-fg-secondary',
                dark: 'border border-border bg-surface text-fg-secondary',
                light: 'border border-border bg-surface text-fg-secondary',
            },
        },
    }),
);

export const drawerTokenTheme = createTheme(
    overrideLeaves(drawerTheme, {
        root: {
            base: 'fixed z-40 overflow-y-auto bg-surface p-4 text-fg-secondary transition-transform',
            backdrop: 'fixed inset-0 z-30 bg-[color-mix(in_oklch,var(--color-fg)_45%,transparent)]',
        },
        header: {
            inner: {
                titleText: 'mb-4 inline-flex items-center text-meta font-semibold text-fg-muted',
                closeButton:
                    'absolute end-2.5 top-2.5 flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-fg-muted hover:bg-surface-hover hover:text-fg',
            },
        },
    }),
);

export const modalTokenTheme = createTheme(
    overrideLeaves(modalTheme, {
        content: {
            inner: 'relative flex max-h-[90dvh] flex-col rounded-lg border border-border bg-surface',
        },
        header: {
            base: 'flex items-start justify-between rounded-t border-b border-border p-5',
            title: 'text-section font-medium text-fg',
            close: {
                base: 'ms-auto inline-flex items-center rounded-lg bg-transparent p-1.5 text-fg-muted hover:bg-surface-hover hover:text-fg',
            },
        },
        footer: { base: 'flex items-center space-x-2 rounded-b border-t border-border p-6' },
    }),
);

/**
 * TABLO — panelin belkemiği, en geç bağlanan aile (FF-126).
 *
 * Depoda tek bir ham gri sınıfı yazılı olmadığı hâlde ekrandaki her tablo
 * başlığı kütüphanenin kendi gri paletiyle, 12 piksel ve büyük harf
 * çiziliyordu. Üç ayrı sözleşme aynı anda üründe geçersizdi ve kaynak
 * taraması üçünü de GREEN görüyordu:
 *
 * 1. Ham palet yasağı (`DS-RAW-PALETTE-BANNED-01`).
 * 2. 16px taban — başlık 12 piksel çiziliyordu.
 * 3. Fiziksel yön yasağı (`DS-LOGICAL-DIRECTION-06`): kütüphanenin kök
 *    yaprağı metni fiziksel SOLA hizalıyor, yani Arapçada her tablo yanlış
 *    tarafa hizalanıyordu.
 *
 * Kök yaprağın gölge kısmı da beyaz zemin ve düşen gölgeyle geliyordu;
 * AEP'te derinlik gölgeyle değil TONLA verilir, bu yüzden yüzey jetonuna
 * bağlandı.
 */
export const tableTokenTheme = createTheme(
    overrideLeaves(tableTheme, {
        root: {
            base: 'w-full text-start text-body text-fg-secondary',
            shadow: 'absolute start-0 top-0 -z-10 h-full w-full rounded-lg bg-surface',
        },
        body: {
            cell: {
                base: 'px-[var(--space-5)] py-[var(--space-3)] group-first/body:group-first/row:first:rounded-tl-lg group-first/body:group-first/row:last:rounded-tr-lg group-last/body:group-last/row:first:rounded-bl-lg group-last/body:group-last/row:last:rounded-br-lg',
            },
        },
        head: {
            // Büyük harf ve 12 piksel gitti: hiyerarşi ağırlık ve renkle
            // kurulur (`DS-NO-UPPERCASE-12`, `DS-TYPE-SCALE-01`).
            base: 'group/head text-meta font-semibold text-fg-muted',
            cell: {
                base: 'bg-surface-subtle px-[var(--space-5)] py-[var(--space-3)] group-first/head:first:rounded-tl-lg group-first/head:last:rounded-tr-lg',
            },
        },
        row: {
            base: 'group/row',
            hovered: 'hover:bg-surface-hover',
            striped: 'odd:bg-surface even:bg-surface-subtle',
        },
    }),
);

export const flowbiteTokenTheme = {
    button: buttonTokenTheme,
    textInput: textInputTokenTheme,
    select: selectTokenTheme,
    textarea: textareaTokenTheme,
    checkbox: checkboxTokenTheme,
    label: labelTokenTheme,
    dropdown: dropdownTokenTheme,
    drawer: drawerTokenTheme,
    modal: modalTokenTheme,
    table: tableTokenTheme,
};

/**
 * Bağlanan her aile için `replace`. Bağlanmayan aileler (Modal, Dropdown,
 * Badge …) listede yoktur, yani Flowbite varsayılanıyla çalışmaya devam
 * eder — bu paketin kapsamı beş kontroldür ve kapsam dışını sessizce
 * boşaltmak bozardı.
 */
export const FLOWBITE_TOKEN_APPLY = {
    button: 'replace',
    textInput: 'replace',
    select: 'replace',
    textarea: 'replace',
    checkbox: 'replace',
    label: 'replace',
    // Taban kütüphanenin kendi teması olduğu için `replace` eksiksizdir.
    dropdown: 'replace',
    drawer: 'replace',
    modal: 'replace',
    table: 'replace',
} as const;
