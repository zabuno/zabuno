import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import type { ReactElement } from 'react';
import { render } from '@testing-library/react';
import { Button as FlowbiteButton } from 'flowbite-react/components/Button';
import { ThemeProvider } from 'flowbite-react/theme/provider';

import { Button } from '../components/catalog/forms/micro/Button';
import { Checkbox } from '../components/catalog/forms/micro/Checkbox';
import { Select } from '../components/catalog/forms/micro/Select';
import { Textarea } from '../components/catalog/forms/micro/Textarea';
import { TextInput } from '../components/catalog/forms/micro/TextInput';
import { readCustomProperties } from './contrast';
import { FLOWBITE_TOKEN_APPLY, flowbiteTokenTheme } from './flowbite-theme';
import { RAW_PALETTE_PATTERN } from './semantic-map';

/**
 * Flowbite tema bağlamasının zorlayıcı kontrolü.
 *
 * **Neden kaynak taraması yetmez.** `DS-RATCHET-01` `resources/js` altındaki
 * dosyaları tarar — ama Flowbite'ın ham paleti `node_modules` içindedir ve
 * hiçbir kaynak tarayıcısı onu göremez. Depoda `h-10` yazan tek bir satır
 * yokken üretilen HTML'de `h-10` vardı; sistem kurallıydı, ürün değildi.
 * Bu yüzden buradaki kural KAYNAĞA değil, RENDER EDİLEN sınıf listesine
 * bakar: hata nerede yaşadıysa ölçüm oradadır.
 *
 * İki yol ayrı ayrı ölçülür, çünkü ikisi ayrı ayrı bozulabilir:
 *
 * 1. **Katalog primitifi** — kendi dilimini prop olarak uygular, yani
 *    sağlayıcısız bir testte/story'de bile bağlıdır.
 * 2. **`ThemeProvider`** — `auth/`, `workspace/` ve `admin/` altında
 *    Flowbite'ı DOĞRUDAN import eden dosyaları kapsar. Birincisi GREEN
 *    iken ikincisi sessizce kopabilir.
 *
 * Requirement ID: DS-FLOWBITE-TOKEN-BIND-10.
 */

/**
 * Sabit piksel geometrisi. Yoğunluk modu yalnız token okuyan bir kontrolü
 * değiştirebilir; `h-10` yazan bir kontrol her modda aynı kalır ve
 * `DS-DENSITY-CONTRACT-05`'i bileşen tarafında sessizce boşa çıkarır.
 */
const FIXED_GEOMETRY_PATTERN =
    /(^|\s)(min-|max-)?[hw]-(\d+(\.\d+)?|px)(?=\s|$)|(^|\s)(min-|max-)?[hw]-\[\d+px\]/;

/** Cihaz breakpoint'i — külliyat container-query öncelikli bir sistem şart koşar. */
const BREAKPOINT_PATTERN = /(^|\s)(sm|md|lg|xl|2xl):/;

function classListsOf(container: HTMLElement): string[] {
    return [...container.querySelectorAll<HTMLElement>('*')]
        .map((element) => element.className)
        .filter(
            (className): className is string => typeof className === 'string' && className !== '',
        );
}

function offendersIn(container: HTMLElement) {
    return classListsOf(container).flatMap((classList) => {
        const found: string[] = [];
        const palette = classList.match(RAW_PALETTE_PATTERN);
        if (palette) found.push(`ham palet: ${palette.join(', ')}`);
        if (FIXED_GEOMETRY_PATTERN.test(classList)) found.push(`sabit geometri: ${classList}`);
        if (BREAKPOINT_PATTERN.test(classList)) found.push(`breakpoint: ${classList}`);
        return found;
    });
}

describe('Flowbite tema bağlaması — zorlayıcı kontrol', () => {
    // --- DS-FLOWBITE-TOKEN-BIND-10 (katalog yolu) -------------------------
    // Tek kural, bir fixture tablosu üzerinde. Her satır ayrı bir test
    // olsaydı ilk kırmızı satırda durur ve kalanları göstermezdi; burada
    // ihlaller TOPLANIR, yani bozulmanın kapsamı tek bakışta görünür.
    it('katalog primitifleri ham palet, sabit geometri veya breakpoint sınıfı üretmez', () => {
        const cases: [string, ReactElement][] = [
            ['Button', <Button>Kaydet</Button>],
            ['Button/light', <Button color="light">Vazgeç</Button>],
            ['Button/outline', <Button outline>Dışa aktar</Button>],
            ['Button/xs', <Button size="xs">Filtrele</Button>],
            ['Button/disabled', <Button disabled>Yayınla</Button>],
            ['TextInput', <TextInput aria-label="Ad" />],
            ['TextInput/invalid', <TextInput aria-label="Ad" invalid />],
            ['Select', <Select aria-label="Şube" />],
            ['Select/invalid', <Select aria-label="Şube" invalid />],
            ['Textarea', <Textarea aria-label="Açıklama" />],
            ['Checkbox', <Checkbox aria-label="Onaylıyorum" />],
        ];

        const offenders = cases.flatMap(([name, element]) => {
            const { container, unmount } = render(element);
            const found = offendersIn(container).map((issue) => `${name} — ${issue}`);
            unmount();
            return found;
        });

        expect(
            offenders,
            'DS-FLOWBITE-TOKEN-BIND-10: katalog primitifi Flowbite varsayılan temasına düştü. ' +
                '`theme={…TokenTheme}` ve `applyTheme="replace"` proplarını kontrol edin — ' +
                '`merge` altında `h-10` gibi sınıflar HAYATTA KALIR:\n' +
                offenders.join('\n'),
        ).toEqual([]);
    });

    // --- DS-FLOWBITE-TOKEN-BIND-10 (provider yolu) ------------------------
    it('doğrudan import edilen Flowbite bileşeni de ThemeProvider altında bağlıdır', () => {
        const { container } = render(
            <ThemeProvider theme={flowbiteTokenTheme} applyTheme={FLOWBITE_TOKEN_APPLY}>
                <FlowbiteButton color="light">Vazgeç</FlowbiteButton>
            </ThemeProvider>,
        );

        expect(
            offendersIn(container),
            'DS-FLOWBITE-TOKEN-BIND-10: `ThemeRoot`taki sağlayıcı katalog dışını kapsamıyor. ' +
                'Katalogtan geçmeyen ~20 dosya ham palete düşer.',
        ).toEqual([]);
    });

    // Sağlayıcının GERÇEKTEN bir şey yaptığını kanıtlar. Bu satır olmadan
    // yukarıdaki test, Flowbite varsayılanı zaten temiz olsaydı da geçerdi.
    it('sağlayıcısız aynı bileşen Flowbite varsayılanına düşer (ölçüm kalibrasyonu)', () => {
        const { container } = render(<FlowbiteButton color="light">Vazgeç</FlowbiteButton>);

        expect(
            offendersIn(container),
            'DS-FLOWBITE-TOKEN-BIND-10: kalibrasyon başarısız — Flowbite varsayılanı artık ' +
                'ihlal üretmiyorsa bu kuralın ölçtüğü şey kalmamış demektir.',
        ).not.toEqual([]);
    });

    // --- DS-FLOWBITE-TOKEN-BIND-10 (yoğunluk zinciri) ---------------------
    // Değeri `var()` içeren bir custom property, TANIMLANDIĞI yerde bir kez
    // ikame edilir ve alt elemanlara çözülmüş hâlde miras kalır — yani
    // `:root`ta türetilen `--control-height`, compact modu içinde
    // YENİDEN HESAPLANMAZ. İlk hâlinde tam bu yüzden yoğunluk anahtarı
    // kontrolleri hiç değiştirmiyordu: token doğruydu, zincir kopuktu.
    it("türetilmiş kontrol token'ı her yoğunluk modunda yeniden tanımlanır", () => {
        const css = readFileSync('resources/css/app.css', 'utf8');
        const root = readCustomProperties(css, ':root');

        // Yalnız YOĞUNLUĞA bağlı olanlar bayatlar. `--control-indicator-size`
        // sabit bir aralık token'ından türer ve modla değişmez; onu her moda
        // kopyalamak zorunlu tutmak, kuralı gürültüye çevirirdi.
        const derived = Object.entries(root)
            .filter(
                ([token, value]) =>
                    token.startsWith('--control-') && value.includes('var(--density-'),
            )
            .map(([token]) => token);

        expect(
            derived,
            "DS-FLOWBITE-TOKEN-BIND-10: hiç türetilmiş kontrol token'ı yok — kural ölçtüğü " +
                'şeyi kaybetmiş olabilir.',
        ).not.toEqual([]);

        for (const mode of [":root[data-density='comfortable']", ":root[data-density='compact']"]) {
            const scope = readCustomProperties(css, mode);
            const missing = derived.filter((token) => scope[token] === undefined);

            expect(
                missing,
                `DS-FLOWBITE-TOKEN-BIND-10: ${mode} bu token'ları yeniden tanımlamıyor: ` +
                    `${missing.join(', ')}. Miras alınan değer :root'ta DONMUŞTUR, yani o modda ` +
                    'yoğunluk anahtarı hiçbir şeyi değiştirmez.',
            ).toEqual([]);
        }
    });

    // --- DS-FLOWBITE-TOKEN-BIND-10 (uygulama biçimi) ----------------------
    it('bağlanan her aile `replace` ile uygulanır', () => {
        const bound = Object.keys(flowbiteTokenTheme);
        const merged = bound.filter(
            (family) =>
                FLOWBITE_TOKEN_APPLY[family as keyof typeof FLOWBITE_TOKEN_APPLY] !== 'replace',
        );

        expect(
            merged,
            'DS-FLOWBITE-TOKEN-BIND-10: `merge` iki sınıf listesini birleştirir ve `twMerge` ' +
                'yalnız AYNI CSS özelliğini çakıştırabilir — `h-10` ile ' +
                '`min-h-[var(--control-height)]` farklı özelliklerdir, yani ikisi birden ' +
                'yayınlanır. Bağlanan aile `replace` olmak zorundadır:\n' +
                merged.join('\n'),
        ).toEqual([]);
    });
});
