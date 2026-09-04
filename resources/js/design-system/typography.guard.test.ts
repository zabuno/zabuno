import { describe, expect, it } from 'vitest';
import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import debt from './typography-debt.json';

/**
 * Tipografi kapısı — token zincirinin en son kapanan halkası.
 *
 * Ölçüm (2026-08-27): kod tabanında `text-meta` 259, `text-xs` 41,
 * `text-body` yalnız 10 kez geçiyordu. Yani panelin fiilî gövde boyutu
 * 14px, meta boyutu 12px'ti ve bir başlık ölçeği yoktu. Renk ve boşluk
 * token'lıyken tipografi ham sınıflarla seçiliyordu; "master değişince
 * hepsi değişir" tipografide GEÇERLİ DEĞİLDİ.
 *
 * Külliyat bunu açıkça yasaklar: gövde için taban 1rem, ve yoğunluk font
 * küçültülerek değil padding/hiyerarşi ile sağlanır
 * (`design-corpus/saas-panel-tasarim-sistemi.md`).
 *
 * Bu kural MUTLAK değil, RATCHET'tir. 283 kullanımı tek pakette
 * değiştirmek, gözle doğrulanamayacak bir diff üretirdi. Borç kayıtlıdır
 * ve yalnız AZALABİLİR.
 *
 * Requirement ID'leri: DS-TYPE-SCALE-01, DS-TYPE-RATCHET-02,
 * DS-TYPE-MIGRATED-03.
 */

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..', 'js');
/*
    HAM ölçek = Tailwind'in kendi t-shirt boyutları. Rol adları (`text-body`,
    `text-meta`, `text-section` …) buraya GİRMEZ: onlar ölçeğin kendisidir,
    ölçeği delen şey değil. Önceki liste ikisini karıştırıyordu ve rol adına
    geçen her dosya borcu artırmış sayılıyordu.
*/
const RAW_SIZE = /\b(text-(?:xs|sm|base|lg|xl|2xl|3xl|4xl|5xl))\b/g;

function sourceFiles(dir: string, found: string[] = []): string[] {
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);

        if (entry.isDirectory()) {
            sourceFiles(full, found);
            continue;
        }

        if (!/\.(ts|tsx)$/.test(entry.name)) continue;
        if (/\.(test|stories)\./.test(entry.name)) continue;

        found.push(full);
    }

    return found;
}

/**
 * "Taban altı" bir SINIF ADI değil, bir DEĞERDİR (FF-125).
 *
 * Bu sayaç `text-meta`yı sabit biçimde taban altı sayıyordu, çünkü yazıldığı
 * gün `--text-meta` 0.875rem'di. AEP tabanı her yeri 1rem'e çıkarınca sayaç
 * ürünü yanlış suçladı: 14px'ten 16px'e ÇIKAN her sınıf, borcu artırmış gibi
 * göründü (2 → 149) — yani kural, tam olarak istediği düzeltmeyi cezalandırdı.
 *
 * Artık eşik `app.css`'ten okunur. Bir rol adı taban altı sayılmak için
 * gerçekten 1rem'in altında bir değere bağlı olmalıdır.
 */
function belowFloorRoles(): Set<string> {
    const css = readFileSync(path.resolve(ROOT, '../css/app.css'), 'utf8');
    const below = new Set<string>();

    for (const [, role, value] of css.matchAll(/--text-([a-z]+):\s*([\d.]+)rem/g)) {
        if (Number(value) < 1) below.add(`text-${role}`);
    }

    // Ham Tailwind ölçeğinin taban altı basamakları: 0.75rem ve 0.875rem.
    below.add('text-xs');
    below.add('text-sm');

    return below;
}

function countRawSizes(): { belowBodyFloor: number; otherRawSizes: number } {
    let belowBodyFloor = 0;
    let otherRawSizes = 0;

    const below = belowFloorRoles();

    for (const file of sourceFiles(ROOT)) {
        for (const hit of readFileSync(file, 'utf8').match(RAW_SIZE) ?? []) {
            if (below.has(hit)) belowBodyFloor++;
            else otherRawSizes++;
        }
    }

    return { belowBodyFloor, otherRawSizes };
}

describe('tipografi kapısı', () => {
    // --- DS-TYPE-SCALE-01 --------------------------------------------------
    it('rol adlı ölçek token kökünde yayımlanır', () => {
        const css = readFileSync(path.resolve(ROOT, '../css/app.css'), 'utf8');
        const theme = css.slice(css.indexOf('@theme'), css.indexOf('\n}', css.indexOf('@theme')));

        // İsimler ROLE göredir, boyuta göre değil: gövde bir gün 17px olursa
        // `text-body` yazan hiçbir bileşen değişmez.
        for (const token of [
            '--text-title',
            '--text-section',
            '--text-subsection',
            '--text-body',
            '--text-meta',
        ]) {
            expect(theme, `DS-TYPE-SCALE-01: ${token} @theme'de yok; utility üretilmez.`).toContain(
                token,
            );
        }
    });

    it('gövde tabanı 1rem altına inmez', () => {
        const css = readFileSync(path.resolve(ROOT, '../css/app.css'), 'utf8');

        expect(
            /--text-body:\s*1rem/.test(css),
            'DS-TYPE-SCALE-01: gövde tabanı 1rem olmalı — yoğunluk padding ile sağlanır, font küçülterek değil.',
        ).toBe(true);
    });

    // --- DS-TYPE-RATCHET-02 ------------------------------------------------
    it('ham yazı boyutu borcu artmaz', () => {
        const actual = countRawSizes();

        expect(
            actual.belowBodyFloor,
            `DS-TYPE-RATCHET-02: 1rem tabanının altındaki kullanım arttı (${actual.belowBodyFloor} > ${debt.belowBodyFloor}). ` +
                'Yeni kod rol adlı ölçeği kullanmalı; borç yalnız azalabilir.',
        ).toBeLessThanOrEqual(debt.belowBodyFloor);

        expect(actual.otherRawSizes).toBeLessThanOrEqual(debt.otherRawSizes);
    });

    it('borç kaydı kendi gerekçesini taşır', () => {
        // Sayı taşıyan ama nedenini taşımayan bir borç kaydı, altı ay sonra
        // kimsenin dokunmaya cesaret edemediği bir sabite dönüşür.
        expect(debt.why.length).toBeGreaterThan(80);
        expect(debt.howToBurnDown).toContain('text-body');
    });

    // --- DS-TYPE-MIGRATED-03 -----------------------------------------------
    it('sayfa kimliğini taşıyan yüzeyler ölçeğe geçmiştir', () => {
        // Bu üç yüzey her ekranda görünür; ölçek onlarda yoksa sistem
        // kullanıcı için var olmuş sayılmaz.
        const migrated: Array<[string, string]> = [
            ['components/catalog/layout/macro/PageHeader.tsx', 'text-title'],
            ['components/workspace/pages/shared/WorkspacePageFrame.tsx', 'text-body'],
            ['components/catalog/menu/macro/MenuCatalogWorkspace.tsx', 'text-body'],
        ];

        for (const [relative, expected] of migrated) {
            expect(
                readFileSync(path.join(ROOT, relative), 'utf8'),
                `DS-TYPE-MIGRATED-03: ${relative} rol adlı ölçeği kullanmıyor.`,
            ).toContain(expected);
        }
    });
});
