import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { MediaAuditRegion } from './MediaAuditRegion';

/**
 * DENETİM İZİNİN AEP RİTMİ — kanonik teslim paketi (`DESIGN_SPEC.md` §7
 * "Denetim izi": kapalı `<details>`, satırlarda zaman damgası SABİT genişlikli
 * bir sütunda, `font-variant-numeric: tabular-nums` ile).
 *
 * Restoran sahibinin yolculuğu: menüden bir yemeğin görseli kayboldu ve
 * sahibin tek sorusu var — "kim, ne zaman?". Bu bir KARŞILAŞTIRMA sorusudur:
 * göz zaman damgalarını yukarıdan aşağıya tarar. Orantılı rakamlarda "11:04"
 * ile "18:41" farklı genişlikte çizilir, sütun titrer ve tarama bozulur.
 *
 * Satır da kart değildir: bir `<details>` kartının İÇİDİR. Ayraç ÜSTE konur —
 * alttan ayraçta son satırın çizgisi kartın kendi kenarlığıyla çakışır ve o
 * susturma her yeni kayıt türünde yeniden hatırlanmak zorunda kalır.
 */
const ROWS = [
    { id: 1, mediaAssetId: 7, action: 'trashed', actor: 'Tolga', at: '2026-08-28 18:41' },
    { id: 2, mediaAssetId: 7, action: 'uploaded', actor: 'Tolga', at: '2026-08-27 11:04' },
];

function mount() {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({
            ok: true,
            status: 200,
            headers: new Headers(),
            json: async () => ({ data: ROWS }),
        })),
    );

    render(<MediaAuditRegion workspaceId={4} />);
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('MediaAuditRegion — zaman damgası hizalanır, satır ayraçlıdır', () => {
    it('zaman damgası tabular-nums ile ve meta rolünde yazılır', async () => {
        mount();

        const stamp = await screen.findByText('2026-08-28 18:41');

        expect(stamp).toHaveClass('tabular-nums');
        // Zaman damgası, `text-meta`nın MEŞRU tek kullanımıdır.
        expect(stamp).toHaveClass('text-meta');
    });

    it('satırlar ÜSTTEN ayraçlıdır ve ilk satırda ayraç yoktur', async () => {
        mount();

        const row = (await screen.findByText('2026-08-28 18:41')).closest('li');

        expect(row).toHaveClass('border-t');
        expect(row).toHaveClass('first:border-t-0');
        expect(row?.className ?? '').not.toMatch(/(^|\s)border-b\b/);
    });

    it('satırlar arasında boşluk YOKTUR: liste tek kartın içidir', async () => {
        mount();

        const list = (await screen.findByText('2026-08-28 18:41')).closest('ul');

        expect(list?.className ?? '').not.toMatch(/(^|\s)gap-/);
    });

    it('yardım cümlesi gövde metnidir', async () => {
        /*
            Denetim izinin ne olduğunu anlatan cümle, sahibin bu bölümü ilk
            kez açtığında okuduğu tek şeydir; sayaç değildir.
        */
        mount();

        await screen.findByText('2026-08-28 18:41');
        const help = screen.getByText(/Every upload, rename/i);

        expect(help).toHaveClass('text-body');
        expect(help.className).not.toMatch(/text-meta/);
    });
});
