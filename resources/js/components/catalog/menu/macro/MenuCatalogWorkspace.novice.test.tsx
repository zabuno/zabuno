import type React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * İÇE AKTARMA YOLU HER ZAMAN GÖRÜNÜR — kanonik kaynak
 * `docs/reference/panel-v3/panel.dc.html` satır 30205-30206.
 *
 * NEDEN ESKİ SÖZLEŞME DEĞİŞTİ (`docs/101` A5/A8 / FF-73)
 *
 * Eski kural şuydu: fotoğraftan ve CSV'den içe aktarma tek bir
 * `<details>` kutusundaydı; menü boşken açık, ürün varken kapalı gelirdi.
 * Gerekçesi makuldü — ilk ekran kalabalıklaşmasın. Ama bedeli ağırdı.
 *
 * Somut yolculuk: sahip ilk gün menüsünden üç ürünü elle girdi. Ertesi
 * gün geri kalan elli yediyi eklemeye oturuyor. Menüde artık ürün olduğu
 * için kutu KAPALI geliyor ve başlığı ("Bring in a whole menu") ne
 * yapacağını değil ne olduğunu söylüyor. Sahip fotoğraftan aktarmayı
 * bilmediği için elli yedi ürünü tek tek yazıyor.
 *
 * Kaynak o tercihi geri alıyor: iki eylem de üst şeritte, her zaman
 * görünür. Kalabalık sorununa cevap eylemi saklamak değil, PANELİ
 * saklamaktır — panel yalnız düğmesine basılınca açılır.
 *
 * Bu dosyanın koruduğu asıl değer ("yeni sahip toplu aktarmayı bulabilsin")
 * korunuyor; ölçüsü değişti: artık kutunun `open` niteliğine değil,
 * düğmenin görünürlüğüne bakılıyor.
 */
const WORKSPACE_ID = 7;
const LOCATION_ID = 3;
const MENU_ID = 42;

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

function tree(withItem: boolean) {
    return {
        id: MENU_ID,
        workspaceId: WORKSPACE_ID,
        locationId: LOCATION_ID,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 5,
                menuId: MENU_ID,
                name: 'Kebaplar',
                position: 1,
                menuItems: withItem
                    ? [
                          {
                              id: 9,
                              categoryId: 5,
                              productId: 9,
                              productName: 'Adana',
                              priceMinorAmount: 25000,
                              currencyCode: 'TRY',
                              position: 1,
                              allergens: [],
                              isVisible: true,
                          },
                      ]
                    : [],
            },
        ],
    };
}

async function renderWorkspace(withItem: boolean) {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
            if (String(url).endsWith('/brand') && method === 'GET') {
                return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
            }
            if (String(url).endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
                return jsonResponse(200, tree(withItem));
            }
            return jsonResponse(200, { ok: true });
        }),
    );

    const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
        MenuCatalogWorkspace: React.ComponentType<{ workspaceId: number; locationId: number }>;
    };

    render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);
    await screen.findByRole('heading', { name: 'Kebaplar' });
}

describe('içe aktarma yolu (ACEMI-A5-TOOLS-01)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('menü boşken hem eylemler hem yol tarifi görünür', async () => {
        await renderWorkspace(false);

        const actions = screen.getByRole('group', { name: 'Menu actions' });
        expect(within(actions).getByRole('button', { name: 'CSV' })).toBeVisible();
        expect(screen.getByText(/Start here/)).toBeInTheDocument();
    });

    it('ürün varken yol tarifi susar AMA eylemler kalır', async () => {
        await renderWorkspace(true);

        /*
            Yol tarifi ("Start here…") ilk adımı anlatır ve menü doldukça
            gereksizleşir — susması doğru. Eylemin kendisi ise susmaz:
            menüsünün yarısını elle girmiş bir sahip, kalanı fotoğraftan
            aktarabileceğini hâlâ görebilmeli.
        */
        expect(screen.queryByText(/Start here/)).toBeNull();

        const actions = screen.getByRole('group', { name: 'Menu actions' });
        expect(within(actions).getByRole('button', { name: 'CSV' })).toBeVisible();
        expect(
            within(actions).getByRole('button', { name: 'Import from a photo (AI)' }),
        ).toBeVisible();
    });

    it('panel, düğmesine basılana kadar AÇILMAZ', async () => {
        await renderWorkspace(true);

        /*
            Kalabalığın çözümü burada: eylem görünür, panel gizli. CSV
            bırakma alanı ekranda durmadan da sahip "CSV" yazısını görür.
        */
        expect(screen.queryByRole('group', { name: 'Bring in a whole menu' })).toBeNull();

        const user = userEvent.setup();
        await user.click(screen.getByRole('button', { name: 'CSV' }));

        expect(screen.getByRole('group', { name: 'Bring in a whole menu' })).toBeVisible();
    });
});
