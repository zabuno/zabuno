import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';

/**
 * AI KULLANILAMIYORSA EYLEM GÖSTERİLMEZ — `docs/97` R9 / AIV-07,
 * `skills/ai-no-credit-degradation.md`.
 *
 * Bu ekranın önceki hâli düğmeyi HER ZAMAN gösteriyordu; kullanıcı ancak
 * bastıktan sonra 503 alıp "kullanılamıyor" öğreniyordu. Burada kanıtlanan
 * davranış: ekran, tıklanmadan ÖNCE sorar, eylemi kaldırır ve yerine tek
 * satırlık bir SEBEP koyar — sebep, çözümü de söylediği için tek bir genel
 * hata metniyle değiştirilemez.
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

async function renderWorkspace(availability: Response) {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();

            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
            if (String(url).endsWith('/brand') && method === 'GET') {
                return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
            }
            if (String(url).endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
                return jsonResponse(200, tree());
            }
            if (String(url).endsWith('/menu/duplicate-candidates')) {
                return jsonResponse(200, { candidates: [] });
            }
            if (String(url).endsWith('/ai/availability')) {
                return availability;
            }
            if (String(url).endsWith('/media')) {
                return jsonResponse(200, { data: [] });
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

function stateOf(capability: string, available: boolean, reason: string) {
    return { capability, available, reason };
}

describe('AI kullanılabilirliği (docs/97 R9)', () => {
    it('kapatma anahtarı kapalıyken fotoğraf eylemi görünmez, yerine sebebi yazar', async () => {
        await renderWorkspace(
            jsonResponse(200, {
                capabilities: [
                    stateOf('menu.extract', false, 'kill_switch'),
                    stateOf('product.description', false, 'kill_switch'),
                    stateOf('embedding.text', false, 'kill_switch'),
                ],
            }),
        );

        await waitFor(() => {
            expect(
                screen.queryByRole('button', { name: 'Import from a photo (AI)' }),
            ).not.toBeInTheDocument();
        });

        expect(screen.getByText('AI help is turned off right now.')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('bütçe bittiğinde sebep bütçeye özeldir — genel bir hata değil', async () => {
        await renderWorkspace(
            jsonResponse(200, {
                capabilities: [
                    stateOf('menu.extract', false, 'budget_exhausted'),
                    stateOf('product.description', false, 'budget_exhausted'),
                    stateOf('embedding.text', false, 'budget_exhausted'),
                ],
            }),
        );

        expect(
            await screen.findByText(
                'This month’s AI budget is used up. Everything else keeps working.',
            ),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('sağlayıcı yapılandırılmamışsa sebep bunu söyler', async () => {
        await renderWorkspace(
            jsonResponse(200, {
                capabilities: [
                    stateOf('menu.extract', false, 'no_route'),
                    stateOf('product.description', false, 'no_route'),
                    stateOf('embedding.text', false, 'no_route'),
                ],
            }),
        );

        expect(await screen.findByText('No AI provider is set up yet.')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('kullanılabilirken eylem normal görünür', async () => {
        await renderWorkspace(
            jsonResponse(200, {
                capabilities: [
                    stateOf('menu.extract', true, 'available'),
                    stateOf('product.description', true, 'available'),
                    stateOf('embedding.text', true, 'available'),
                ],
            }),
        );

        expect(
            await screen.findByRole('button', { name: 'Import from a photo (AI)' }),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('durum BİLİNMİYORSA (istek başarısız) eylem gizlenmez — iyimser davranır', async () => {
        // Ağ yavaş/kırık diye çalışan bir özelliği gizlemek, sahibin onu bir
        // daha aramamasına yol açardı. Bilinmeyen "kapalı" değildir.
        await renderWorkspace(jsonResponse(500, {}));

        expect(
            await screen.findByRole('button', { name: 'Import from a photo (AI)' }),
        ).toBeInTheDocument();
        expect(screen.queryByText('AI help is turned off right now.')).not.toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});
