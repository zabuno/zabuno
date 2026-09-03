import { describe, expect, it } from 'vitest';
import { globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * AI-14 / `docs/97` R8 — ALERJEN İDDİASI HİÇBİR EKRANDA ONAYLANAMAZ.
 *
 * Backend tarafı kilitli: `ArtifactSchemaValidator::FORBIDDEN_FIELDS` bu
 * adları taşıyan bir taslağı kaydetmeden reddeder. Ama bu kilit, ekranın
 * kendi başına bir "alerjensiz (AI onayladı)" onay kutusu ÇİZMESİNİ
 * engellemez — model hiç öyle bir alan döndürmese bile, arayüzü yazan
 * kişi "yararlı olur" diye ekleyebilir.
 *
 * Bu, sağlık ve hukuk sonucu olan tek AI kuralıdır (`docs/16` AI-14): bir
 * misafir "alerjensiz" ibaresine güvenip yiyebilir. Kapı bu yüzden davranış
 * değil YAZIM BİÇİMİ yasaklar — kaynağı okur, çalıştırmaz.
 *
 * Aynı liste iki yerde yaşamak zorunda (PHP + TS): tek kaynak yapmak,
 * frontend'i backend'in çalışma zamanına bağlardı. Liste değişirse iki
 * tarafın da değişmesi gerektiği burada yazılıdır.
 */

const THIS_DIR = path.dirname(fileURLToPath(import.meta.url));

/** `app/Infrastructure/Ai/ArtifactSchemaValidator.php` ile aynı liste. */
const FORBIDDEN_CLAIM_FIELDS = [
    'allergen_free',
    'is_allergen_free',
    'allergens_confirmed',
    'no_allergens',
    'cross_contamination',
    'is_vegan_certified',
];

const SOURCE_FILES = globSync('**/*.tsx', { cwd: THIS_DIR })
    .filter((file) => !file.includes('.test.'))
    .filter((file) => !file.includes('.stories.'))
    .map((file) => path.join(THIS_DIR, file));

function stripComments(source: string): string {
    return source.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
}

describe('AI alerjen iddiası yasağı (docs/97 R8, AI-14)', () => {
    it('taranacak bir bileşen bulunmadan geçmez', () => {
        expect(SOURCE_FILES.length).toBeGreaterThan(10);
    });

    it('hiçbir ekran yasaklı bir alerjen-iddiası alanını render etmez', () => {
        const offenders: string[] = [];

        for (const file of SOURCE_FILES) {
            const source = stripComments(readFileSync(file, 'utf8'));

            for (const field of FORBIDDEN_CLAIM_FIELDS) {
                if (source.includes(field)) {
                    offenders.push(`${path.relative(THIS_DIR, file)} → ${field}`);
                }
            }
        }

        expect(
            offenders,
            'AI-14: bir ekran alerjen iddiası alanı taşıyor. AI yalnız "olası alerjen ADAYI" bildirebilir; onay kontrolü olamaz.',
        ).toEqual([]);
    });

    it('AI inceleme ekranı alerjen için bir onay kutusu çizmez', () => {
        const workspace = stripComments(
            readFileSync(
                path.join(THIS_DIR, 'catalog/menu/macro/MenuCatalogWorkspace.tsx'),
                'utf8',
            ),
        );

        // AI bölümlerinin bulunduğu dosyada "allergen" geçen her satırın,
        // AI taslağıyla DEĞİL elle girilen alerjen listesiyle ilgili olması
        // gerekir. Onay kutusu + AI aynı satırda asla buluşmaz.
        const suspicious = workspace
            .split('\n')
            .filter((line) => /allergen/i.test(line))
            .filter((line) => /type="checkbox"|<Checkbox|role="checkbox"/.test(line));

        expect(
            suspicious,
            'AI-14: alerjen ile onay kutusu aynı satırda — bu bir "AI onayladı" kontrolüne dönüşebilir.',
        ).toEqual([]);
    });
});
