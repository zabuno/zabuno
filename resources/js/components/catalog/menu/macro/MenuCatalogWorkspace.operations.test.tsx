import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * MENÜYÜ İŞLETMEK — `docs/73` (P0-01).
 *
 * Ürün bir menüyü yayımlayabiliyor ama işletemiyordu: yanlış yazılan bir
 * ürünü düzeltmenin, sezonluk bir kategoriyi kaldırmanın ve sırayı
 * değiştirmenin yolu yoktu. Uç noktalar tek başına yetmez; sahibi ekranda
 * yapabilmelidir.
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

function tree() {
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
                name: 'Çorbalr',
                position: 1,
                menuItems: [
                    {
                        id: 11,
                        categoryId: 5,
                        productId: 9,
                        productName: 'Mercimek Çorbsı',
                        priceMinorAmount: 4500,
                        currencyCode: 'TRY',
                        position: 1,
                        isVisible: true,
                        allergens: [],
                    },
                    {
                        id: 12,
                        categoryId: 5,
                        productId: 10,
                        productName: 'Ezogelin',
                        priceMinorAmount: 5000,
                        currencyCode: 'TRY',
                        position: 2,
                        isVisible: true,
                        allergens: [],
                    },
                ],
            },
            { id: 6, menuId: MENU_ID, name: 'İçecekler', position: 2, menuItems: [] },
        ],
    };
}

type Call = { url: string; method: string; body: unknown };

async function renderWorkspace() {
    const calls: Call[] = [];

    const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();
        calls.push({
            url: String(url),
            method,
            body: init?.body ? JSON.parse(String(init.body)) : null,
        });

        if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
        if (String(url).endsWith('/brand') && method === 'GET') {
            return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
        }
        if (String(url).endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
            return jsonResponse(200, tree());
        }

        return jsonResponse(200, { ok: true });
    });

    vi.stubGlobal('fetch', fetchMock);

    const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
        MenuCatalogWorkspace: React.ComponentType<{ workspaceId: number; locationId: number }>;
    };

    render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);
    await screen.findByRole('heading', { name: 'Çorbalr' });

    return { calls, user: userEvent.setup() };
}

function write(calls: Call[], method: string): Call | undefined {
    return calls.find((call) => call.method === method && !call.url.includes('csrf'));
}

/*
    ADI DÜZELTMEK artık tarayıcı diyaloğu değil, SATIR İÇİ düzenlemedir
    (FF-101). Yardımcı, testlerin niyetini tek yerde tutar: adı aç, yaz,
    kaydet. Sözleşme değişmedi — PUT gider, boş ad reddedilir; değişen tek
    şey, kullanıcının bunu ürünün içinde yapması.
*/
async function renameInline(
    user: ReturnType<typeof userEvent.setup>,
    label: string,
    next: string,
): Promise<void> {
    await user.click(screen.getByRole('button', { name: label }));
    const field = await screen.findByRole('textbox', { name: label });
    await user.clear(field);

    if (next !== '') {
        await user.type(field, next);
    }

    await user.click(screen.getByRole('button', { name: 'Save' }));
}

/* SİLME de ürünün kendi diyaloğuyla onaylanır: taşma menüsü → Kaldır → onay. */
async function openDeleteDialog(
    user: ReturnType<typeof userEvent.setup>,
    moreLabel: string,
): Promise<void> {
    await user.click(screen.getByRole('button', { name: moreLabel }));
    await user.click(await screen.findByRole('menuitem', { name: 'Remove' }));
}

describe('menüyü işletmek (docs/73)', () => {
    /**
     * "Mercimek Çorbsı" yazan bir sahibin tek çaresi ürünü gizleyip
     * doğrusunu yeniden eklemekti — ve yanlış olan veritabanında kalıyordu.
     */
    it('ürün adı düzeltilebilir', async () => {
        const { calls, user } = await renderWorkspace();

        await renameInline(user, 'Rename Mercimek Çorbsı', 'Mercimek Çorbası');

        await waitFor(() => {
            expect(write(calls, 'PUT')).toBeDefined();
        });

        const put = write(calls, 'PUT')!;
        expect(put.url).toBe(`/api/workspaces/${WORKSPACE_ID}/menu-items/11`);
        expect(put.body).toEqual({ productName: 'Mercimek Çorbası' });
        expect(await screen.findByText('Mercimek Çorbası')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    /**
     * İPTAL ile BOŞ BIRAKMAK aynı şey değildir: biri vazgeçmedir, diğeri bir
     * niyettir ve sessizce yutulursa düğmeye basılıp hiçbir şey olmaz.
     */
    it('iptal sessizdir, boş ad ise söylenir', async () => {
        const { calls, user } = await renderWorkspace();

        // VAZGEÇMEK sessizdir: hiçbir istek gitmez, hiçbir uyarı çıkmaz.
        await user.click(screen.getByRole('button', { name: 'Rename Mercimek Çorbsı' }));
        await user.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(write(calls, 'PUT')).toBeUndefined();
        expect(screen.queryByText(/cannot be empty/i)).toBeNull();

        /*
            BOŞ AD bir niyettir — kullanıcı kaydet'e bastı — ve sessizce
            yutulamaz. Uyarı, yazdığı alanın hemen altında çıkar; `prompt`
            ile sayfanın başka bir yerinde beliriyordu.
        */
        await renameInline(user, 'Rename Mercimek Çorbsı', '');
        expect(await screen.findByText(/cannot be empty/i)).toBeInTheDocument();
        expect(write(calls, 'PUT')).toBeUndefined();

        vi.unstubAllGlobals();
    });

    /** Silme ONAY ister: yayınlanmış sürüm korunur ama taslak satır geri gelmez. */
    it('ürün onaydan sonra silinir ve listeden kalkar', async () => {
        const { calls, user } = await renderWorkspace();

        await openDeleteDialog(user, 'More actions for Mercimek Çorbsı');

        // Diyalog NEYİ sildiğini adıyla söyler — `confirm` bunu yapamıyordu.
        expect(
            await screen.findByText(/Remove “Mercimek Çorbsı” from this menu\?/),
        ).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Remove', hidden: false }));

        await waitFor(() => {
            expect(write(calls, 'DELETE')).toBeDefined();
        });
        expect(write(calls, 'DELETE')!.url).toBe(`/api/workspaces/${WORKSPACE_ID}/menu-items/11`);

        await waitFor(() => {
            expect(screen.queryByText('Mercimek Çorbsı')).toBeNull();
        });
        expect(screen.getByText('Ezogelin')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('onay verilmezse hiçbir istek gitmez', async () => {
        const { calls, user } = await renderWorkspace();

        await openDeleteDialog(user, 'More actions for Mercimek Çorbsı');
        await user.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(write(calls, 'DELETE')).toBeUndefined();
        expect(screen.getByText('Mercimek Çorbsı')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    /**
     * Sıralama isteği TOPLU gider: `unique(position)` yüzünden iki satırı tek
     * tek güncellemek yolun ortasında çakışır.
     */
    it('ürün aşağı taşınınca tam sıra tek istekte gönderilir', async () => {
        const { calls, user } = await renderWorkspace();

        await user.click(screen.getByRole('button', { name: 'Move Mercimek Çorbsı down' }));

        await waitFor(() => {
            expect(write(calls, 'PUT')).toBeDefined();
        });

        const put = write(calls, 'PUT')!;
        expect(put.url).toBe(`/api/workspaces/${WORKSPACE_ID}/menu-categories/5/item-order`);
        expect(put.body).toEqual({ menuItemIds: [12, 11] });

        /*
            Ekran da yeni sırayı gösterir.

            Sorgu KATEGORİNİN ÜRÜN LİSTESİNE kapsanır: kapsamsız
            `getAllByRole('listitem')` kategori satırını da döndürür ve o
            satır iki ürün adını birden içerdiği için sıralama ölçülemez —
            ilk denemede iki isim de aynı öğede bulundu.
        */
        const itemList = screen.getByRole('list', { name: 'Items in Çorbalr' });
        const names = within(itemList)
            .getAllByRole('listitem')
            .map((row) => row.textContent ?? '');

        expect(names.findIndex((n) => n.includes('Ezogelin'))).toBeLessThan(
            names.findIndex((n) => n.includes('Mercimek Çorbsı')),
        );

        vi.unstubAllGlobals();
    });

    it('kategori sırası da tek istekte gönderilir', async () => {
        const { calls, user } = await renderWorkspace();

        await user.click(screen.getByRole('button', { name: 'Move İçecekler up' }));

        await waitFor(() => {
            expect(write(calls, 'PUT')).toBeDefined();
        });

        const put = write(calls, 'PUT')!;
        expect(put.url).toBe(`/api/workspaces/${WORKSPACE_ID}/menu/${MENU_ID}/category-order`);
        expect(put.body).toEqual({ categoryIds: [6, 5] });

        vi.unstubAllGlobals();
    });

    /** Listenin ucundaki satır daha ileri gitmez ve istek de göndermez. */
    it('ilk satır yukarı taşınamaz', async () => {
        const { calls, user } = await renderWorkspace();

        await user.click(screen.getByRole('button', { name: 'Move Mercimek Çorbsı up' }));

        expect(write(calls, 'PUT')).toBeUndefined();

        vi.unstubAllGlobals();
    });

    /** Sunucu reddederse SEBEBİ söylenir; sessizce başarısız olunmaz. */
    it('sunucu reddederse hata gösterilir', async () => {
        const { user } = await renderWorkspace();

        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string) =>
                String(url) === '/sanctum/csrf-cookie'
                    ? jsonResponse(204, {})
                    : jsonResponse(403, { message: 'Forbidden.' }),
            ),
        );

        await openDeleteDialog(user, 'More actions for Mercimek Çorbsı');
        await user.click(screen.getByRole('button', { name: 'Remove', hidden: false }));

        expect(await screen.findByText(/Forbidden|could not be saved/i)).toBeInTheDocument();
        expect(screen.getByText('Mercimek Çorbsı')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});
