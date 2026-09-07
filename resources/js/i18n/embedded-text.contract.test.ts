import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';

import { workspaceTranslations } from './workspace';

/**
 * DS-I18N-EMBEDDED — kodda gömülü metin ve arayüzde birleştirilen cümle
 * (`docs/121` Ö1, Ö2/Ö5, Ö3).
 *
 * ═══ BU KAPI NEDEN VAR ═══
 *
 * Deponun mevcut kapısı (`I18N-SSR-RATCHET`) SUNUCUDA üretilen metni ölçer:
 * Blade şablonlarındaki gömülü dizeleri yakalar. React katalog bileşenlerinin
 * PROP VARSAYILANLARINI hiç görmez — ve bir varsayılan da ekrana çıkar.
 * Sahte-yerelleştirme (`docs/121` §4) o boşluğu görünür kıldı: katalogdan
 * geçen her metin ölçüm diline döner, gömülü olan DÖNMEZ ve gözle bulunur.
 *
 * Bulunanlar bunlardı ve hepsi ürünün canlı ekranlarına ulaşıyordu:
 *
 *   `SidebarNav`          → `label = 'Primary'`
 *   `Breadcrumbs`         → `label = 'Breadcrumb'`, `'Empty breadcrumb trail'`
 *   `ResponsiveDataTable` → `emptyMessage = 'No data to display.'`
 *   `MobileChrome`        → `title={navLabel ?? 'Menu'}`
 *
 * `docs/35` kataloğun bileşenlerine metin bilmeyi zaten yasaklıyor: metin
 * PROP olarak gelir. Varsayılan bir dize o yasağın sessiz istisnasıydı —
 * çağıran unuttuğunda bileşen kendi kelimesini konuşuyordu.
 *
 * BU BİR ÇEVİRİ İŞİ DEĞİLDİR. Hiçbir metin çevrilmedi, çeviri kilidi
 * açılmadı, `shipped_locales` genişlemedi; yalnız metnin katalogdan geçmesi
 * zorunlu kılındı.
 *
 * Requirement IDs: DS-I18N-EMBEDDED-01 … DS-I18N-EMBEDDED-03.
 */

/**
 * Katalog metnini PROP olarak alan, kendi kelimesi olmayan bileşenler.
 *
 * Liste sahte-yerelleştirme koşusunun bulduğu dosyalardan doğdu; yeni bir
 * bileşen buraya eklendiğinde kapı onu da tutar.
 */
const TEXT_FREE_COMPONENTS = [
    'resources/js/components/catalog/layout/compound/SidebarNav.tsx',
    'resources/js/components/catalog/navigation/compound/Breadcrumbs.tsx',
    'resources/js/components/catalog/data-display/compound/ResponsiveDataTable.tsx',
    'resources/js/components/catalog/layout/macro/PageHeader.tsx',
    'resources/js/components/catalog/data-display/compound/TrendChart.tsx',
    'resources/js/components/catalog/menu/compound/MenuScreenActions.tsx',
];

const SWITCHER = 'resources/js/components/workspace/chrome/WorkspaceSwitcherTrigger.tsx';
const MOBILE_CHROME = 'resources/js/components/workspace/chrome/MobileChrome.tsx';

/**
 * Yorumları düşürür.
 *
 * Bu dosyaların yorumları KUSURUN KENDİSİNİ alıntılıyor ("varsayılanı
 * `'Primary'` idi"). Kaynağı ham tararsak kapı, kusuru anlatan cümleyi
 * kusur sanardı — ve o zaman tek çıkış yolu gerekçeyi silmek olurdu.
 */
function code(file: string): string {
    return readFileSync(file, 'utf8')
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/(^|[^:])\/\/.*$/gm, '$1');
}

describe('DS-I18N-EMBEDDED — katalog bileşeni kendi kelimesini konuşmaz', () => {
    // --- DS-I18N-EMBEDDED-01 ----------------------------------------------

    /**
     * Varsayılan bir dize, unutulduğu gün ekrana çıkan gömülü metindir.
     *
     * Aranan kalıp yıkıcı yapıdaki `prop = 'Metin'` biçimi. Teknik sabitler
     * (`'Escape'`, `'ArrowDown'`, sınıf adları) bu kalıba girmez: onlar
     * varsayılan değil, karşılaştırma değeridir.
     */
    it.each(TEXT_FREE_COMPONENTS)('%s bir metin varsayılanı taşımaz', (file) => {
        const source = code(file);

        /*
            Bir CÜMLE mi, teknik bir sabit mi?

            Sınıf listesi, jeton adı, olay adı ve seçici de dizedir; onları
            gömülü metin saymak kapıyı gürültüye boğardı. Ayıran işaret şu:
            kullanıcıya gösterilen metin BÜYÜK HARFLE başlar ve içinde CSS
            sözdizimi (`[`, `:`, `--`, `-`) bulunmaz.
        */
        const looksLikeSentence = (value: string): boolean =>
            /^[A-Z]/.test(value) && !/[[\]:]|--|-/.test(value);

        const offenders = [...source.matchAll(/(\w+) = '([^']{2,})'/g)]
            .filter(([, , value]) => looksLikeSentence(value))
            .map(([, name, value]) => `${name} = '${value}'`);

        /*
            Yalnız varsayılan değil, JSX'in içine YAZILMIŞ cümle de gömülü
            metindir — `<VisuallyHidden>Empty breadcrumb trail</…>` gibi.
        */
        const inline = [...source.matchAll(/>\s*([A-Z][a-z]+(?: [a-z]+){1,})\s*</g)].map(
            ([, text]) => text,
        );

        expect(
            [...offenders, ...inline],
            'DS-I18N-EMBEDDED-01: katalogdan geçmeyen bir metin — ' +
                'çeviri günü bu dize çevrilecekler arasında ÇIKMAZ.',
        ).toEqual([]);
    });

    it('telefon çekmecesi başlığını geri düşen bir kelimeden almaz', () => {
        /*
            `title={navLabel ?? 'Menu'}` idi: `navLabel` isteğe bağlıyken
            çekmecenin başlığı kodda gömülü İngilizce bir kelimeye düşüyordu.
        */
        expect(code(MOBILE_CHROME), "DS-I18N-EMBEDDED-01: `?? 'Menu'` geri gelmiş.").not.toMatch(
            /\?\?\s*'[A-Z]/,
        );
    });

    // --- DS-I18N-EMBEDDED-02 ----------------------------------------------

    /**
     * ARAYÜZDE BİRLEŞTİRİLEN CÜMLE ÇEVRİLEMEZ — `docs/121` Ö2/Ö5.
     *
     * Çalışma alanı seçicisinin erişilebilir adı
     * `${workspaceName} — ${t('workspace.current.switch')}` diye
     * kuruluyordu. Sahte-yerelleştirilmiş katalogla o düğme tek cümle değil
     * İKİ AYRI parça olarak göründü (⟦…⟧⟦…⟧). Çevirmen o hâlde tireyi
     * kaldıramaz, adı cümlenin sonuna alamaz, ismin hâlini uygulayamaz.
     */
    it('seçicinin erişilebilir adı tek katalog anahtarından gelir', () => {
        const source = code(SWITCHER);

        expect(source, 'DS-I18N-EMBEDDED-02: ad hâlâ birleştiriliyor.').not.toMatch(/label=\{`/);
        expect(source, 'DS-I18N-EMBEDDED-02: adlı yer tutuculu tek anahtar yok.').toMatch(
            /t\('workspace\.current\.switchFor', \{ workspace: /,
        );
    });

    // --- DS-I18N-EMBEDDED-03 ----------------------------------------------

    /**
     * YER TUTUCU ADLIDIR, SIRALI DEĞİL — `docs/121` Ö3.
     *
     * `%1$s` çevirmene sırayı değiştirme hakkı vermez; kelime sırası
     * Türkçede, Almancada ve Arapçada farklıdır.
     */
    it('çalışma alanı kataloğunda sıralı yer tutucu yoktur', () => {
        const positional = Object.entries(workspaceTranslations)
            .filter(([, value]) => /%\d+\$[sd]|%[sd]\b/.test(value))
            .map(([key]) => key);

        expect(positional, 'DS-I18N-EMBEDDED-03: sıralı yer tutucu.').toEqual([]);
    });

    it('yeni anahtar adlı yer tutucu taşır', () => {
        expect(workspaceTranslations['workspace.current.switchFor']).toContain('{workspace}');
    });
});
