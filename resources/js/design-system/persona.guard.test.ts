import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { FLOWBITE_TOKEN_APPLY, flowbiteTokenTheme } from './flowbite-theme';

/**
 * DS-PERSONA-SCOPE-01 — persona rengi YALNIZ platform kabuğundadır.
 *
 * Sahibin kararı (2026-09-04): superadmin/mühendislik yüzeyi lacivere çalan
 * bir zeminde çalışır; restoran paneli kromasız kalır. İki panel aynı
 * tarayıcıda açıkken renk hangi tarafta olunduğunu söyler.
 *
 * Bu kapı üç şeyi dondurur:
 *   1. `persona="platform"` yalnız `OpsShell`'de verilir (platform +
 *      mühendislik kabuklarının ortak gövdesi).
 *   2. Kiracı kabuğu (WorkspaceApp / DesktopChrome / MobileChrome) persona
 *      vermez — restoran tarafına renk sızmaz.
 *   3. Persona token'ları yalnız YÜZEY jetonlarını değiştirir; marka, odak,
 *      durum renkleri ve metin tonları ortak kalır (ikinci bir tasarım
 *      sistemi doğmaz).
 */
const OPS_SHELL = 'resources/js/components/ops/OpsShell.tsx';
const TENANT_FILES = [
    'resources/js/components/workspace/WorkspaceApp.tsx',
    'resources/js/components/workspace/chrome/DesktopChrome.tsx',
    'resources/js/components/workspace/chrome/MobileChrome.tsx',
];
const CSS = 'resources/css/app.css';

const ALLOWED_PERSONA_TOKENS = [
    '--canvas',
    '--surface',
    '--surface-subtle',
    '--surface-hover',
    '--surface-active',
    '--border',
    '--border-strong',
    '--color-canvas',
    '--color-surface',
    '--color-surface-subtle',
    '--color-surface-hover',
    '--color-surface-active',
    '--color-border',
];

describe('persona rengi kapsamı', () => {
    it('platform kabuğu persona verir', () => {
        expect(readFileSync(OPS_SHELL, 'utf8')).toMatch(/persona="platform"/);
    });

    it.each(TENANT_FILES)('%s persona vermez', (file) => {
        expect(readFileSync(file, 'utf8')).not.toMatch(/persona=/);
    });

    it('persona blokları yalnız yüzey jetonlarını değiştirir', () => {
        const css = readFileSync(CSS, 'utf8');
        const blocks = [...css.matchAll(/\[data-persona='platform'\][^{]*\{([^}]*)\}/g)].map(
            (match) => match[1],
        );

        expect(blocks.length).toBeGreaterThan(0);

        const declared = blocks.flatMap((block) =>
            [...block.matchAll(/(--[a-z0-9-]+)\s*:/g)].map((match) => match[1]),
        );
        const offenders = [...new Set(declared)].filter(
            (token) => !ALLOWED_PERSONA_TOKENS.includes(token),
        );

        expect(
            offenders,
            'DS-PERSONA-SCOPE-01: persona yalnız yüzey/kenarlık tonunu değiştirir. ' +
                'Marka, odak, durum ve metin jetonları ortak kalmalı:\n' +
                offenders.join('\n'),
        ).toEqual([]);
    });

    /*
        Flowbite'ın `gray` paleti maviye çalar (`gray-700` = rgb(55 65 81)).
        Bağlanmayan aile o paletle çizilir; 2026-09-04'te restoran panelinde
        hesap menüsü ve çekmece lacivert-gri bir yüzeyde açılıyordu. Üç
        katman da bağlı kalmalı — aksi hâlde mavi sessizce geri gelir.
    */
    it.each(['dropdown', 'drawer', 'modal'])('%s ailesi token temasına bağlıdır', (family) => {
        expect(Object.keys(flowbiteTokenTheme)).toContain(family);
        expect(FLOWBITE_TOKEN_APPLY[family as keyof typeof FLOWBITE_TOKEN_APPLY]).toBe('replace');
    });

    it('katman temalarında Flowbite gri paleti kalmaz', () => {
        const source = readFileSync('resources/js/design-system/flowbite-theme.ts', 'utf8');
        const overrides = source.slice(source.indexOf('export const dropdownTokenTheme'));
        const offenders = [
            ...overrides.matchAll(/(?:dark:)?(?:bg|text|border|divide)-gray-\d+/g),
        ].map((match) => match[0]);

        expect(offenders, "Yüzey token'dan gelir, kütüphanenin gri paletinden değil.").toEqual([]);
    });
});
