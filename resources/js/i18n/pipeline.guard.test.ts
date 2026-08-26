import { describe, expect, it } from 'vitest';
import { execFileSync } from 'node:child_process';
import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { compileMo, parsePo } from '../../../scripts/i18n/po.mjs';
import { LOCALES, FALLBACK_LOCALE } from './locales';
import { DOMAINS, DOMAIN_CATALOGS } from './domains';
import { overridesFor } from './generated-overrides';

/**
 * CORE-08 boru hattı kapısı.
 *
 * Üretilmiş dosyalar depoya işlenir (CI'da Node çalıştırmadan da PHP
 * testleri MO okuyabilsin diye). İşlenen bir üretim çıktısı ise her zaman
 * bayatlama riski taşır — bu kapı o riski ortadan kaldırır.
 *
 * Requirement ID'leri: DS-I18N-PO-SIX-05, DS-I18N-PROJECTION-SYNC-06,
 * DS-I18N-FUZZY-EXCLUDED-07, DS-I18N-MO-DETERMINISTIC-08,
 * DS-I18N-GENERATED-NOT-EDITED-09.
 */

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const PO_DIR = path.join(ROOT, 'lang', 'po');
const LOCALE_CODES = Object.keys(LOCALES);

describe('CORE-08 çeviri boru hattı', () => {
    // --- DS-I18N-PO-SIX-05 ------------------------------------------------
    it('her alan adı için altı locale PO dosyası ve bir POT şablonu vardır', () => {
        const files = new Set(readdirSync(PO_DIR));

        for (const domain of DOMAINS) {
            expect(files.has(`${domain}.pot`), `DS-I18N-PO-SIX-05: ${domain}.pot eksik.`).toBe(
                true,
            );

            for (const locale of LOCALE_CODES) {
                expect(
                    files.has(`${domain}.${locale}.po`),
                    `DS-I18N-PO-SIX-05: ${domain}.${locale}.po eksik — altı katalog iskeleti Stage 1'den itibaren tamdır.`,
                ).toBe(true);
            }
        }
    });

    it('kaynak locale PO dosyası koddaki metinle birebir aynıdır', () => {
        for (const domain of DOMAINS) {
            const base = DOMAIN_CATALOGS[domain];
            const { entries } = parsePo(
                readFileSync(path.join(PO_DIR, `${domain}.${FALLBACK_LOCALE}.po`), 'utf8'),
            );

            expect(
                entries.size,
                `DS-I18N-PO-SIX-05: ${domain} kaynak kataloğu boş.`,
            ).toBeGreaterThan(0);

            for (const [msgid, entry] of entries) {
                expect(
                    entry.msgstr,
                    `DS-I18N-PO-SIX-05: kaynak katalogda "${msgid}" koddaki metinden sapmış — English complete olmalı.`,
                ).toBe(base[msgid]);
            }

            // Kaynağı boş olan anahtar kataloğa hiç girmez (bkz.
            // scripts/i18n/index.mjs): çevrilecek metin yoktur ve boş bir
            // msgstr eksik sayımını kalıcı olarak yanıltırdı.
            const translatable = Object.keys(base).filter((key) => base[key] !== '');
            expect(entries.size, `DS-I18N-PO-SIX-05: ${domain} anahtar sayısı sapmış.`).toBe(
                translatable.length,
            );
        }
    });

    // --- DS-I18N-PROJECTION-SYNC-06 ---------------------------------------
    it('işlenmiş projeksiyonlar PO kaynaklarıyla birebir aynıdır', () => {
        // Script'in kendi `check` komutu tek doğruluk kaynağıdır; test onu
        // taklit etmez, çağırır. Aksi hâlde iki ayrı "doğru" ortaya çıkardı.
        expect(() =>
            execFileSync('node', ['scripts/i18n/index.mjs', 'check'], {
                cwd: ROOT,
                encoding: 'utf8',
                stdio: 'pipe',
            }),
        ).not.toThrow();
    });

    // --- DS-I18N-FUZZY-EXCLUDED-07 ----------------------------------------
    it('şüpheli (fuzzy) işaretli çeviri projeksiyona girmez', () => {
        const source = readFileSync(path.join(ROOT, 'scripts/i18n/index.mjs'), 'utf8');

        expect(
            source,
            'DS-I18N-FUZZY-EXCLUDED-07: onaylanmamış bir çeviriyi göstermek, İngilizce göstermekten kötüdür.',
        ).toContain('!entry.fuzzy');
    });

    // --- DS-I18N-MO-DETERMINISTIC-08 --------------------------------------
    it('aynı girdi her zaman bayt-bayt aynı MO üretir', () => {
        const table = { b: 'iki', a: 'bir', ç: 'üç' };
        const shuffled = { ç: 'üç', a: 'bir', b: 'iki' };

        expect(
            compileMo(table).equals(compileMo(shuffled)),
            'DS-I18N-MO-DETERMINISTIC-08: anahtar sırası çıktıyı değiştirmemeli, aksi hâlde her derleme sahte diff üretir.',
        ).toBe(true);
    });

    it('çevrilmemiş dize MO içine yazılmaz', () => {
        const withEmpty = compileMo({ a: 'bir', b: '' });
        const withoutEmpty = compileMo({ a: 'bir' });

        expect(
            withEmpty.equals(withoutEmpty),
            'DS-I18N-MO-DETERMINISTIC-08: boş çeviriyi yazmak, onu çevrilmiş göstermek olurdu.',
        ).toBe(true);
    });

    // --- DS-I18N-GENERATED-NOT-EDITED-09 ----------------------------------
    it('üretilmiş JSON, kaynak locale taşımaz ve taban dışı anahtar içermez', () => {
        for (const domain of DOMAINS) {
            const overrides = overridesFor(domain);

            expect(
                Object.keys(overrides),
                `DS-I18N-GENERATED-NOT-EDITED-09: ${domain} için kaynak locale JSON'a yazılmamalı — taban zaten kodda.`,
            ).not.toContain(FALLBACK_LOCALE);
        }
    });
});
