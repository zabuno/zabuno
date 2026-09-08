import { describe, expect, it } from 'vitest';
import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { workspaceTranslations } from './workspace';
import { t, workspaceDesktopTranslations } from './workspace-desktop';
import { DOMAIN_CATALOGS } from './domains';

/**
 * CİHAZA GÖRE BÖLÜNMÜŞ DİZE KATALOGLARI — `docs/151`, `docs/153` §9 M1.
 *
 * `docs/153` bileşenleri ayırdı ve borcunu açıkça yazdı: kataloglar cihaz
 * TANIMIYORDU, yani yalnız masaüstünde çizilen on bir dize telefonun
 * paketine de iniyordu (833 B). Bu testler o borcun kapandığını ölçer.
 *
 * Ölçülen dört şey, dördü de ayrı bir arıza ailesi:
 *
 * 1. AYRIKLIK — bir anahtar iki tabloda birden olamaz (sessiz gölgeleme).
 * 2. TAM OLMA — çeviri alan adı ikisinin TOPLAMIdır; bölünme hiçbir dizeyi
 *    çeviri hattından düşürmez.
 * 3. YÖN — masaüstü kataloğu ortak tabloya SIZMAZ (`workspaceTranslations`
 *    içinde bir masaüstü anahtarı bulunamaz).
 * 4. OKUNABİLİRLİK — masaüstü paketinin `t`'si her iki tabloyu da okur;
 *    okumasaydı masaüstü bileşenleri ortak cümleleri kaybederdi.
 *
 * Paketin GERÇEKTEN ayrıldığını ise bu dosya değil
 * `scripts/adaptive-bundle-gate` kanıtlar: bir testin gördüğü modül grafiği
 * ile tarayıcıya inen paket aynı şey değildir.
 */

const I18N_DIR = path.dirname(fileURLToPath(import.meta.url));
const DESKTOP_CATALOG_DIR = path.join(I18N_DIR, 'workspace-desktop');
const DESKTOP_AGGREGATOR = path.join(I18N_DIR, 'workspace-desktop.ts');

describe('workspace i18n cihaz ayrımı', () => {
    it('masaüstü kataloglarını statik bir liste değil, glob keşfeder', () => {
        const source = readFileSync(DESKTOP_AGGREGATOR, 'utf8');

        expect(source).toMatch(/import\.meta\.glob/);

        for (const filename of readdirSync(DESKTOP_CATALOG_DIR).filter((n) => n.endsWith('.ts'))) {
            const moduleName = filename.replace(/\.ts$/, '');
            expect(source).not.toMatch(
                new RegExp(`from\\s+["'\`]\\./workspace-desktop/${moduleName}["'\`]`),
            );
        }
    });

    it('bir anahtar iki tabloda birden olamaz', () => {
        const shared = new Set(Object.keys(workspaceTranslations));
        const shadowed = Object.keys(workspaceDesktopTranslations).filter((key) => shared.has(key));

        expect(
            shadowed,
            'Aynı anahtar iki tabloda birden: masaüstü tablosu ortağı sessizce ezerdi ve ' +
                'iki cümlenin ayrıştığı gün yalnız bir yüzeyde fark edilirdi.',
        ).toEqual([]);
    });

    it('masaüstü kataloğu boş değil ve tamamı ortak tablonun DIŞINDADIR', () => {
        const desktopKeys = Object.keys(workspaceDesktopTranslations);

        // Boş bir masaüstü kataloğu bu paketin iddiasını sessizce boşa
        // çıkarırdı: ayrılmış hiçbir şey yokken de üstteki testlerin hepsi
        // geçerdi.
        expect(desktopKeys.length).toBeGreaterThan(0);

        for (const key of desktopKeys) {
            expect(workspaceTranslations[key]).toBeUndefined();
        }
    });

    it('çeviri alan adı iki tablonun TOPLAMIDIR — bölünme dize kaybettirmez', () => {
        const domain = DOMAIN_CATALOGS.workspace;

        expect(Object.keys(domain)).toHaveLength(
            Object.keys(workspaceTranslations).length +
                Object.keys(workspaceDesktopTranslations).length,
        );

        for (const [key, value] of Object.entries(workspaceDesktopTranslations)) {
            expect(domain[key]).toBe(value);
        }

        for (const [key, value] of Object.entries(workspaceTranslations)) {
            expect(domain[key]).toBe(value);
        }
    });

    it('masaüstü çevirmeni her iki tabloyu da okur ve yer tutucu doldurur', () => {
        // Masaüstüne özgü anahtar.
        expect(t('workspace.orders.queue.desktop.selected', { count: '3' })).toBe('3 selected');
        // ORTAK anahtar: masaüstü bileşeni ikinci bir çağrı öğrenmek zorunda değil.
        expect(t('workspace.orders.confirm')).toBe(
            workspaceTranslations['workspace.orders.confirm'],
        );
    });

    it('bilinmeyen bir anahtar kendisi olarak döner ve derlemede reddedilir', () => {
        function assertRejectsUnknownLiteral(): void {
            // @ts-expect-error yalnız katalogdaki değişmez anahtarlar kabul edilir
            t('workspace.__unknown_desktop_literal__');
        }
        void assertRejectsUnknownLiteral;

        const untyped = t as (key: string) => string;
        expect(untyped('workspace.__unknown_desktop_literal__')).toBe(
            'workspace.__unknown_desktop_literal__',
        );
    });
});
