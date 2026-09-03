import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * FOTOĞRAFTAN İÇE AKTARMA (AI) — `docs/92`/`docs/97` Yolculuk A.
 *
 * Backend zaten vardı (FF-32…FF-34); bu ekran onu ilk kez menü kataloğunda
 * gösteriyor. Yükleme Media sayfasında olur (`menuImportSource` slotu) —
 * bu ekran yalnız hazır bir görseli okutur, inceletir, taslağa uygular.
 * `ApplyMenuArtifact` toplu/otomatik uygular: okunamayan satır atlanır,
 * kullanıcı satır satır düzenlemez — bu ekran o davranışı DOĞRU yansıtır,
 * icat etmez.
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
                name: 'Kebaplar',
                position: 1,
                menuItems: [
                    {
                        id: 11,
                        categoryId: 5,
                        productId: 9,
                        productName: 'Adana Kebap',
                        priceMinorAmount: 38000,
                        currencyCode: 'TRY',
                        position: 1,
                        isVisible: true,
                        allergens: [],
                    },
                ],
            },
        ],
    };
}

type Options = {
    media?: unknown[];
    storeResponse?: Response;
    showResponse?: Response;
    applyResponse?: Response;
};

/** Toplu okuma cevabının tek-fotoğraflı, başarılı biçimi. */
function batchOk(id = 501, usedFallback = false) {
    return { results: [{ mediaAssetId: 71, id, usedFallback }] };
}

async function renderWorkspace(options: Options = {}) {
    const calls: { url: string; method: string; body: unknown }[] = [];

    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
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
            if (String(url).endsWith('/menu/duplicate-candidates') && method === 'GET') {
                return jsonResponse(200, { candidates: [] });
            }
            if (String(url).endsWith('/media') && method === 'GET') {
                return jsonResponse(200, {
                    data: options.media ?? [
                        {
                            id: 71,
                            altText: 'Menü fotoğrafı',
                            slot: 'menuImportSource',
                            status: 'ready',
                        },
                    ],
                });
            }
            if (String(url).endsWith('/ai-imports/batch') && method === 'POST') {
                return options.storeResponse ?? jsonResponse(201, batchOk());
            }
            if (String(url).endsWith('/ai-imports/501') && method === 'GET') {
                return options.showResponse ?? jsonResponse(200, { fields: [] });
            }
            if (String(url).endsWith('/ai-imports/batch/apply') && method === 'POST') {
                return (
                    options.applyResponse ??
                    jsonResponse(200, { importedItems: 0, importedCategories: 0, rejectedRows: [] })
                );
            }

            return jsonResponse(200, { ok: true });
        }),
    );

    const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
        MenuCatalogWorkspace: React.ComponentType<{ workspaceId: number; locationId: number }>;
    };

    render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);
    await screen.findByRole('heading', { name: 'Kebaplar' });

    return { calls, user: userEvent.setup() };
}

async function openSection(user: ReturnType<typeof userEvent.setup>) {
    await user.click(screen.getByRole('button', { name: 'Import from a photo (AI)' }));
    await screen.findByText('Choose the photos to read');
}

/** Bir fotoğrafı seçer — artık açılır liste değil, onay kutusu (`docs/96` Faz 3). */
async function choosePhoto(user: ReturnType<typeof userEvent.setup>, name = 'Menü fotoğrafı') {
    await user.click(screen.getByRole('checkbox', { name }));
}

describe('fotoğraftan içe aktarma (docs/97 Yolculuk A)', () => {
    it('hazır görsel yoksa Media sayfasına yönlendiren metni okur', async () => {
        const { user } = await renderWorkspace({ media: [] });

        await user.click(screen.getByRole('button', { name: 'Import from a photo (AI)' }));

        expect(
            await screen.findByText(
                'No processed photo is available yet. Upload one on the Media page (slot: Import source) first.',
            ),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('okunan satırlar incelemede gösterilir, fiyatı okunamayan satır uyarılır', async () => {
        const { calls, user } = await renderWorkspace({
            showResponse: jsonResponse(200, {
                fields: [
                    {
                        name: 'row.1',
                        value: {
                            category: 'Çorbalar',
                            product: 'Mercimek',
                            priceMinorAmount: 5000,
                            currencyCode: 'TRY',
                        },
                        confidence: 0.96,
                        uncertain: false,
                    },
                    {
                        name: 'row.2',
                        value: {
                            category: 'Kebaplar',
                            product: 'Adana',
                            priceMinorAmount: null,
                            currencyCode: 'TRY',
                        },
                        confidence: 0.4,
                        uncertain: true,
                    },
                ],
            }),
        });

        await openSection(user);
        await choosePhoto(user);
        await user.click(screen.getByRole('button', { name: 'Read these photos' }));

        expect(
            await screen.findByText('What the AI read — review before adding'),
        ).toBeInTheDocument();
        expect(screen.getByText(/Mercimek/)).toBeInTheDocument();
        expect(
            screen.getByText('Price could not be read — this row will be skipped'),
        ).toBeInTheDocument();

        await waitFor(() => {
            const store = calls.find(
                (call) => call.url.endsWith('/ai-imports/batch') && call.method === 'POST',
            );
            expect(store?.body).toEqual({ mediaAssetIds: [71] });
        });

        vi.unstubAllGlobals();
    });

    it('AI kapalıyken (503) hata değil, kısa bir mesaj gösterir', async () => {
        const { user } = await renderWorkspace({
            storeResponse: jsonResponse(503, { message: 'off', reason: 'kill_switch' }),
        });

        await openSection(user);
        await choosePhoto(user);
        await user.click(screen.getByRole('button', { name: 'Read these photos' }));

        expect(
            await screen.findByText('Reading menu photos is not available right now.'),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('yedek sağlayıcıdan okunduysa ayrı belirtilir', async () => {
        const { user } = await renderWorkspace({
            storeResponse: jsonResponse(201, batchOk(501, true)),
            showResponse: jsonResponse(200, {
                fields: [
                    {
                        name: 'row.1',
                        value: {
                            category: 'X',
                            product: 'Y',
                            priceMinorAmount: 100,
                            currencyCode: 'TRY',
                        },
                        confidence: 0.9,
                        uncertain: false,
                    },
                ],
            }),
        });

        await openSection(user);
        await choosePhoto(user);
        await user.click(screen.getByRole('button', { name: 'Read these photos' }));

        expect(await screen.findByText('Read by a backup provider.')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('Ekle taslağa yazar ve reddedilen satırları sebebiyle listeler', async () => {
        const { calls, user } = await renderWorkspace({
            showResponse: jsonResponse(200, {
                fields: [
                    {
                        name: 'row.1',
                        value: {
                            category: 'Çorbalar',
                            product: 'Mercimek',
                            priceMinorAmount: 5000,
                            currencyCode: 'TRY',
                        },
                        confidence: 0.96,
                        uncertain: false,
                    },
                ],
            }),
            applyResponse: jsonResponse(200, {
                importedItems: 1,
                importedCategories: 1,
                rejectedRows: [
                    { row: 'row.2', reason: 'Fiyat okunamadı; bu satırı elle ekleyin.' },
                ],
            }),
        });

        await openSection(user);
        await choosePhoto(user);
        await user.click(screen.getByRole('button', { name: 'Read these photos' }));
        await screen.findByText('What the AI read — review before adding');

        await user.click(screen.getByRole('button', { name: 'Add these to the draft' }));

        await waitFor(() => {
            expect(
                calls.some(
                    (call) =>
                        call.url.endsWith('/ai-imports/batch/apply') && call.method === 'POST',
                ),
            ).toBe(true);
        });

        expect(
            await screen.findByText('Imported 1 items into 1 new categories.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Row 2: Fiyat okunamadı; bu satırı elle ekleyin.'),
        ).toBeInTheDocument();

        // Uygulandıktan sonra satırlar tekrar "Ekle"ye sunulmaz.
        expect(
            screen.queryByRole('button', { name: 'Add these to the draft' }),
        ).not.toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    // --- TOPLU OKUMA (`docs/96` Faz 3) ------------------------------------

    it('birden çok fotoğraf seçilip tek istekte okunur', async () => {
        const { calls, user } = await renderWorkspace({
            media: [
                { id: 71, altText: 'Sayfa 1', slot: 'menuImportSource', status: 'ready' },
                { id: 72, altText: 'Sayfa 2', slot: 'menuImportSource', status: 'ready' },
            ],
            storeResponse: jsonResponse(201, {
                results: [
                    { mediaAssetId: 71, id: 501, usedFallback: false },
                    { mediaAssetId: 72, id: 502, usedFallback: false },
                ],
            }),
        });

        await openSection(user);
        await choosePhoto(user, 'Sayfa 1');
        await choosePhoto(user, 'Sayfa 2');
        await user.click(screen.getByRole('button', { name: 'Read these photos' }));

        await waitFor(() => {
            const store = calls.find(
                (call) => call.url.endsWith('/ai-imports/batch') && call.method === 'POST',
            );
            expect(store?.body).toEqual({ mediaAssetIds: [71, 72] });
        });

        vi.unstubAllGlobals();
    });

    it('okunamayan fotoğraf ayrıca söylenir, okunanların sonucu durur', async () => {
        const { user } = await renderWorkspace({
            media: [
                { id: 71, altText: 'Sayfa 1', slot: 'menuImportSource', status: 'ready' },
                { id: 72, altText: 'Bulanık sayfa', slot: 'menuImportSource', status: 'ready' },
            ],
            storeResponse: jsonResponse(201, {
                results: [
                    { mediaAssetId: 71, id: 501, usedFallback: false },
                    { mediaAssetId: 72, error: 'unparseable' },
                ],
            }),
            showResponse: jsonResponse(200, {
                fields: [
                    {
                        name: 'row.1',
                        value: {
                            category: 'Çorbalar',
                            product: 'Mercimek',
                            priceMinorAmount: 5000,
                            currencyCode: 'TRY',
                        },
                        confidence: 0.96,
                        uncertain: false,
                    },
                ],
            }),
        });

        await openSection(user);
        await choosePhoto(user, 'Sayfa 1');
        await choosePhoto(user, 'Bulanık sayfa');
        await user.click(screen.getByRole('button', { name: 'Read these photos' }));

        // Okunamayan söylenir…
        expect(
            await screen.findByText(
                '“Bulanık sayfa” could not be read — the other photos still went through.',
            ),
        ).toBeInTheDocument();

        // …ve okunanın sonucu ÇÖPE GİTMEZ.
        expect(screen.getByText(/Mercimek/)).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('hiç fotoğraf seçilmeden okuma düğmesi çalışmaz', async () => {
        const { user } = await renderWorkspace();

        await openSection(user);

        expect(screen.getByRole('button', { name: 'Read these photos' })).toBeDisabled();

        vi.unstubAllGlobals();
    });

    it('toplu/otomatik onay yoktur — her satır ayrı görünür, tek "onayla" düğmesi satır seçimi sunmaz', async () => {
        const { user } = await renderWorkspace({
            showResponse: jsonResponse(200, {
                fields: [
                    {
                        name: 'row.1',
                        value: {
                            category: 'A',
                            product: 'B',
                            priceMinorAmount: 100,
                            currencyCode: 'TRY',
                        },
                        confidence: 0.9,
                        uncertain: false,
                    },
                ],
            }),
        });

        await openSection(user);
        await choosePhoto(user);
        await user.click(screen.getByRole('button', { name: 'Read these photos' }));
        const preview = (
            await screen.findByText('What the AI read — review before adding')
        ).closest('div') as HTMLElement;

        // Satır bazlı bir "kabul et" ya da "reddet" kontrolü YOK — backend
        // hangi satırın uygulanacağına veri bütünlüğüne göre kendisi karar
        // verir (`docs/97`'de düzeltilen kapsam). Tek eylem "Ekle" düğmesidir.
        expect(within(preview).queryByRole('checkbox')).not.toBeInTheDocument();
        expect(within(preview).getAllByRole('button')).toHaveLength(1);

        vi.unstubAllGlobals();
    });
});
