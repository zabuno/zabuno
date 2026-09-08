import { describe, expect, it } from 'vitest';
import { globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * KAPI — İŞLETİLEMEYEN BİR ŞEY, İŞLETİLEBİLİR GİBİ ÇİZİLMEZ.
 *
 * ═══ NEDEN VAR ═══
 *
 * Medya > Ayarlar > "Security and privacy" bölümünde dört önlemin yanında
 * ANAHTAR duruyordu ve altında "Cannot be switched off" yazıyordu. Ekran aynı
 * anda iki şey söylüyordu: "bunu değiştirebilirsin" (anahtarın kendisi) ve
 * "değiştiremezsin" (yanındaki cümle). Kullanıcı dokunuyor, hiçbir şey
 * olmuyordu — ve dokunmanın işe yaramadığını ancak DENEYEREK öğreniyordu.
 *
 * Aynı sayfanın üst yarısı bunu doğru yapıyordu: dizin/dosya adı deseni
 * anahtar değil, düz cümledir ("Nothing here is a choice, so there is nothing
 * to save"). Kusur bir bilgi eksikliği değil, bir BİÇİM yalanıydı.
 *
 * ═══ KURAL ═══
 *
 * Bir öğe kendini AÇILIP KAPANAN bir kontrol diye tanıtıyorsa
 * (`role="switch"`, `role="checkbox"`, `role="radio"`, `<input
 * type="checkbox">`, `<input type="radio">`), gerçekten açılıp kapanabilir
 * olmalıdır:
 *
 *   1. KALICI OLARAK devre dışı olamaz. Koşula bağlı `disabled={saving}`
 *      geçerlidir — o bir ANIN gerçeğidir, ekranın sözü değil. Koşulsuz
 *      `disabled`, `disabled={true}` ve `aria-disabled="true"` geçmez:
 *      onlar ekranda bir yalan bırakır.
 *   2. Bir işleyicisi olmalıdır. Hiçbir şeye bağlanmamış bir anahtar,
 *      dokunulduğunda sessiz kalır.
 *
 * Değiştirilemeyen bir GERÇEK anlatılacaksa kontrol değil DURUM yazılır:
 * etiket + hâl + sebebi. `MediaAssetStatusBadge` bu deponun bu iş için hazır
 * sözcüğüdür.
 *
 * ═══ NEDEN AYRI BİR KAPI ═══
 *
 * `forms.guard.test.ts` "kalıcı devre dışı alan" kuralını zaten taşıyor ama
 * yalnız `<form` GEÇEN dosyalarda ve yalnız `TextInput/Textarea/Select`
 * öğelerinde bakıyor. Kusurun geçtiği iki dosyanın ikisinde de form yoktu ve
 * öğe bir `<button role="switch">`ti; kapı ikisini de görmüyordu.
 *
 * ═══ KAPI KENDİ TESTİYLE KANITLANIR ═══
 *
 * Aşağıdaki "kapı kuralı bozan örneği yakalar" testi, kuralı bozan bir
 * parçayı dedektöre verir ve YAKALANDIĞINI gösterir. Yakaladığını
 * gösteremeyen bir kapı, kapı değildir.
 */

const COMPONENTS_DIR = path.dirname(fileURLToPath(import.meta.url));

const COMPONENT_FILES = globSync('**/*.tsx', { cwd: COMPONENTS_DIR })
    .filter((file) => !file.includes('.test.'))
    .filter((file) => !file.includes('.stories.'))
    .map((file) => path.join(COMPONENTS_DIR, file));

/** Blok ve satır yorumlarını atar; yorumdaki örnek kod bulgu sayılmasın. */
function stripComments(source: string): string {
    return source.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
}

/**
 * Açılış etiketlerini çıkarır.
 *
 * Regex ile "ilk `>`e kadar" almak İŞE YARAMAZ: `onClick={() => x}`
 * içindeki ok işareti etiketi erkenden keser ve etiketin geri kalanı
 * görünmez olur — `forms.guard.test.ts` aynı tuzağa düşüp `noValidate`
 * aramaktan vazgeçmişti. Bu yüzden metin elle taranır: süslü parantez
 * derinliği ve dizge sınırları takip edilir, etiket ancak derinlik sıfırken
 * gelen `>` ile biter.
 */
export function openingTags(source: string): string[] {
    const tags: string[] = [];

    for (let i = 0; i < source.length; i++) {
        if (source[i] !== '<' || !/[A-Za-z]/.test(source[i + 1] ?? '')) continue;

        let depth = 0;
        let quote: string | null = null;

        for (let j = i + 1; j < source.length; j++) {
            const c = source[j];

            if (quote !== null) {
                if (c === quote) quote = null;
                continue;
            }

            if (c === '"' || c === "'" || c === '`') {
                quote = c;
                continue;
            }

            if (c === '{') depth++;
            else if (c === '}') depth--;
            else if (c === '<' && depth === 0) break;
            else if (c === '>' && depth === 0) {
                tags.push(source.slice(i, j + 1));
                i = j;
                break;
            }
        }
    }

    return tags;
}

/** Kendini açılıp kapanan bir kontrol diye tanıtan etiket. */
const TOGGLE_SHAPED = /\brole="(?:switch|checkbox|radio)"|\btype="(?:checkbox|radio)"/;

/**
 * KALICI olarak işletilemez kılan yazımlar.
 *
 * `disabled={saving}` bilerek dışarıdadır: o bir ANIN gerçeğidir ve saniyeler
 * içinde geri döner. Yasaklanan şey, ekranın ömrü boyunca dönmeyecek olandır.
 */
const PERMANENTLY_INOPERABLE: Array<[RegExp, string]> = [
    [/(?:^|\s)disabled(?=\s|\/|>|$)/, 'koşulsuz `disabled`'],
    [/\bdisabled=\{true\}/, '`disabled={true}`'],
    [/\bdisabled="true"/, '`disabled="true"`'],
    [/\baria-disabled=(?:\{true\}|"true")/, '`aria-disabled` her zaman doğru'],
];

/** Etiketin adı — `<button` → `button`, `<Checkbox` → `Checkbox`. */
function tagName(tag: string): string {
    return /^<([A-Za-z][A-Za-z0-9.]*)/.exec(tag)?.[1] ?? '';
}

export type ControlFinding = { tag: string; why: string };

/**
 * Kuralı bozan etiketleri döndürür. Kaynak metni okur, ÇALIŞTIRMAZ: amaç bir
 * davranışı değil, bir YAZIM BİÇİMİNİ yasaklamaktır.
 */
export function findInoperableControls(source: string): ControlFinding[] {
    const findings: ControlFinding[] = [];

    for (const tag of openingTags(stripComments(source))) {
        if (!TOGGLE_SHAPED.test(tag)) continue;

        for (const [pattern, why] of PERMANENTLY_INOPERABLE) {
            if (pattern.test(tag)) {
                findings.push({ tag: tag.replace(/\s+/g, ' ').slice(0, 90), why });
            }
        }

        /*
            İşleyici yalnız YERLİ öğelerde aranır. Büyük harfle başlayan bir
            bileşen (`<Checkbox …/>`) işleyicisini kendi içinde bağlamış
            olabilir; onu burada aramak, olmayan bir borcu raporlamak olurdu.
        */
        const native = /^[a-z]/.test(tagName(tag));

        if (native && !/\bon(?:Click|Change|KeyDown|Pointer[A-Z])/.test(tag)) {
            findings.push({
                tag: tag.replace(/\s+/g, ' ').slice(0, 90),
                why: 'hiçbir işleyiciye bağlı değil',
            });
        }
    }

    return findings;
}

describe('kontrol dürüstlüğü — işletilemeyen şey işletilebilir gibi çizilmez', () => {
    it('bileşen dosyası bulunmadan geçmez', () => {
        expect(COMPONENT_FILES.length).toBeGreaterThan(50);
    });

    it('kapı kuralı bozan örneği YAKALAR', () => {
        // Kusurun kendisi: anahtar çizilir, hiçbir zaman çevrilemez.
        const broken = `
            <button type="button" role="switch" aria-checked={checked} disabled>
                <span />
            </button>
        `;

        expect(findInoperableControls(broken).map((finding) => finding.why)).toEqual([
            'koşulsuz `disabled`',
            'hiçbir işleyiciye bağlı değil',
        ]);
    });

    it('kapı GERÇEKTEN çevrilebilen anahtarı rahat bırakır', () => {
        // Koşullu `disabled` bir ANIN gerçeğidir; kural onu yasaklamaz.
        const fine = `
            <button
                type="button"
                role="switch"
                aria-checked={item.isVisible}
                disabled={pending[item.id] === true}
                onClick={() => toggle(item)}
                className="disabled:cursor-not-allowed disabled:opacity-50"
            />
        `;

        expect(findInoperableControls(fine)).toEqual([]);
    });

    it('kapı yorumdaki örnek koda kanmaz', () => {
        const commented = `/* <button role="switch" disabled /> */\n<span />`;

        expect(findInoperableControls(commented)).toEqual([]);
    });

    it('panelde kalıcı olarak çevrilemeyen hiçbir anahtar yoktur', () => {
        const offenders: string[] = [];

        for (const file of COMPONENT_FILES) {
            for (const finding of findInoperableControls(readFileSync(file, 'utf8'))) {
                offenders.push(
                    `${path.relative(COMPONENTS_DIR, file)}: ${finding.why} — ${finding.tag}`,
                );
            }
        }

        expect(
            offenders,
            'KONTROL DÜRÜSTLÜĞÜ: anahtar/onay kutusu biçiminde çizilmiş ama ' +
                'çevrilemeyen bir öğe bulundu. Bir kontrol, dokunulduğunda bir şeyin ' +
                'değişeceğine söz verir; değişmeyecekse kontrol değil DURUM yazılır ' +
                '(etiket + hâl + sebebi).',
        ).toEqual([]);
    });
});
