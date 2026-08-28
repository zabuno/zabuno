import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';

/**
 * RED test freezing the dashboard catalog-mutation sync contract for
 * MenuCatalogWorkspace (S1-WP01A foundation). MenuCatalogWorkspace has no
 * `onTreeChange` prop today, so it must fail RED against the assertions
 * below without touching MenuCatalogWorkspace.test.tsx. Frozen contract:
 * MenuCatalogWorkspace calls an optional `onTreeChange(tree)` callback with
 * its current authoritative MenuTree (a) once after the initial menu load
 * resolves with a menu present, and (b) again after a successful visibility
 * mutation resolves, each time with the server-returned ids/shape.
 */
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 7;
const LOCATION_ID = 3;
const MENU_ID = 42;
const CATEGORY_ID = 5;
const MENU_ITEM_ID = 101;

function brandUrl(workspaceId: number): string {
    return `/api/workspaces/${workspaceId}/brand`;
}

function menuUrl(workspaceId: number, locationId: number): string {
    return `/api/workspaces/${workspaceId}/brand/locations/${locationId}/menu`;
}

function visibilityUrl(workspaceId: number, menuItemId: number): string {
    return `/api/workspaces/${workspaceId}/menu-items/${menuItemId}/visibility`;
}

function categoriesUrl(workspaceId: number, menuId: number): string {
    return `/api/workspaces/${workspaceId}/menu/${menuId}/categories`;
}

function menuEntriesUrl(workspaceId: number, categoryId: number): string {
    return `/api/workspaces/${workspaceId}/menu-categories/${categoryId}/menu-entries`;
}

const NEW_CATEGORY_ID = 6;
const NEW_PRODUCT_ID = 902;
const NEW_MENU_ITEM_ID = 102;

/**
 * Menüsü henüz olmayan bir şube: GET menu 404 döner, POST menu yeni menüyü
 * yaratır. Yayın sayfasını kilitleyen gerçek senaryo tam olarak budur —
 * paylaşılan ağaç null iken menü kurulur ve bildirilmezse Publish ölü kalır.
 */
function buildFetchMockWithoutMenu() {
    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();

        if (String(url) === CSRF_COOKIE_URL) {
            return jsonResponse(204, {});
        }
        if (String(url) === brandUrl(WORKSPACE_ID) && method === 'GET') {
            return jsonResponse(200, makeBrand());
        }
        if (String(url) === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET') {
            return jsonResponse(404, { message: 'Not Found.' });
        }
        if (String(url) === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'POST') {
            return jsonResponse(201, {
                id: MENU_ID,
                workspaceId: WORKSPACE_ID,
                locationId: LOCATION_ID,
                name: 'Ana Menü',
                state: 'draft',
            });
        }

        throw new Error(
            `Unhandled fetch in MenuCatalogWorkspace dashboardSync test: ${method} ${String(url)}`,
        );
    });
}

function importMenuCatalogModule<
    T extends Record<string, unknown> = Record<string, unknown>,
>(): Promise<T> {
    return import(/* @vite-ignore */ './MenuCatalogWorkspace') as unknown as Promise<T>;
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        // Gerçek bir `Response` HER ZAMAN `headers` taşır. Sahte yanıt
        // taşımayınca, başlık okuyan her kod yolu testte patlıyor ve
        // ağ hatası gibi görünüyordu.
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function makeBrand() {
    return {
        id: 1,
        workspaceId: WORKSPACE_ID,
        name: 'Zeytin Restoranları',
        slug: 'zeytin-restoranlari',
        locale: 'tr-TR',
        timezone: 'Europe/Istanbul',
        currency: 'TRY',
        description: null,
        contactEmail: null,
        contactPhone: null,
    };
}

function makeMenuTree(overrides: Partial<{ isVisible: boolean }> = {}) {
    return {
        id: MENU_ID,
        workspaceId: WORKSPACE_ID,
        locationId: LOCATION_ID,
        name: 'Ana Menü',
        state: 'published',
        categories: [
            {
                id: CATEGORY_ID,
                menuId: MENU_ID,
                name: 'Starters',
                position: 0,
                menuItems: [
                    {
                        id: MENU_ITEM_ID,
                        categoryId: CATEGORY_ID,
                        productId: 901,
                        productName: 'Kahve',
                        priceMinorAmount: 4250,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: [],
                        isVisible: overrides.isVisible ?? true,
                    },
                ],
            },
        ],
    };
}

function buildFetchMock() {
    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();

        if (String(url) === CSRF_COOKIE_URL) {
            return jsonResponse(204, {});
        }
        if (String(url) === brandUrl(WORKSPACE_ID) && method === 'GET') {
            return jsonResponse(200, makeBrand());
        }
        if (String(url) === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET') {
            return jsonResponse(200, makeMenuTree());
        }
        if (String(url) === visibilityUrl(WORKSPACE_ID, MENU_ITEM_ID) && method === 'PUT') {
            return jsonResponse(200, { id: MENU_ITEM_ID, isVisible: false });
        }
        if (String(url) === categoriesUrl(WORKSPACE_ID, MENU_ID) && method === 'POST') {
            return jsonResponse(201, {
                id: NEW_CATEGORY_ID,
                menuId: MENU_ID,
                name: 'Çorbalar',
                position: 1,
            });
        }
        if (String(url) === menuEntriesUrl(WORKSPACE_ID, NEW_CATEGORY_ID) && method === 'POST') {
            return jsonResponse(201, {
                id: NEW_MENU_ITEM_ID,
                categoryId: NEW_CATEGORY_ID,
                productId: NEW_PRODUCT_ID,
                productName: 'Mercimek Çorbası',
                priceMinorAmount: 4500,
                currencyCode: 'TRY',
                position: 0,
                isVisible: false,
                allergens: [],
            });
        }

        throw new Error(
            `Unhandled fetch in MenuCatalogWorkspace dashboardSync test: ${method} ${String(url)}`,
        );
    });
}

describe('MenuCatalogWorkspace — dashboard sync callback (S1-WP01A foundation, RED)', () => {
    it('calls onTreeChange with the loaded MenuTree after the initial load resolves', async () => {
        const fetchMock = buildFetchMock();
        vi.stubGlobal('fetch', fetchMock);
        const onTreeChange = vi.fn();

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<{
                workspaceId: number;
                locationId: number;
                onTreeChange?: (tree: unknown) => void;
            }>;
        }>();

        render(
            <MenuCatalogWorkspace
                workspaceId={WORKSPACE_ID}
                locationId={LOCATION_ID}
                onTreeChange={onTreeChange}
            />,
        );

        await screen.findByText('Kahve');

        await waitFor(() => {
            expect(onTreeChange).toHaveBeenCalledWith(
                expect.objectContaining({
                    id: MENU_ID,
                    categories: expect.arrayContaining([
                        expect.objectContaining({
                            id: CATEGORY_ID,
                            menuItems: expect.arrayContaining([
                                expect.objectContaining({ id: MENU_ITEM_ID, isVisible: true }),
                            ]),
                        }),
                    ]),
                }),
            );
        });

        vi.unstubAllGlobals();
    });

    it('calls onTreeChange again with the updated MenuTree after a successful visibility mutation', async () => {
        const fetchMock = buildFetchMock();
        vi.stubGlobal('fetch', fetchMock);
        const onTreeChange = vi.fn();

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<{
                workspaceId: number;
                locationId: number;
                onTreeChange?: (tree: unknown) => void;
            }>;
        }>();

        render(
            <MenuCatalogWorkspace
                workspaceId={WORKSPACE_ID}
                locationId={LOCATION_ID}
                onTreeChange={onTreeChange}
            />,
        );

        await screen.findByText('Kahve');

        await waitFor(() => {
            expect(onTreeChange).toHaveBeenCalledTimes(1);
        });

        const checkbox = screen.getByRole('checkbox', { name: 'Show Kahve' });
        fireEvent.click(checkbox);

        await waitFor(() => {
            expect(onTreeChange).toHaveBeenCalledTimes(2);
        });

        expect(onTreeChange).toHaveBeenLastCalledWith(
            expect.objectContaining({
                id: MENU_ID,
                categories: expect.arrayContaining([
                    expect.objectContaining({
                        id: CATEGORY_ID,
                        menuItems: expect.arrayContaining([
                            expect.objectContaining({ id: MENU_ITEM_ID, isVisible: false }),
                        ]),
                    }),
                ]),
            }),
        );

        vi.unstubAllGlobals();
    });

    // --- DASHBOARD-SYNC-MENU-CREATE-01 ------------------------------------
    // Yayın sayfasını kilitleyen gerçek senaryo: şubede menü yokken sahibi
    // menüyü kurar. Bildirim gitmezse paylaşılan ağaç null kalır, Publication
    // "No menu is loaded yet" der ve Publish düğmesi sessizce hiçbir istek
    // atmaz. Bu, tarayıcıda gözlemlenen P1 kusurun ta kendisidir.
    it('calls onTreeChange after a menu is created on a location that had none', async () => {
        const fetchMock = buildFetchMockWithoutMenu();
        vi.stubGlobal('fetch', fetchMock);
        const onTreeChange = vi.fn();

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<{
                workspaceId: number;
                locationId: number;
                onTreeChange?: (tree: unknown) => void;
            }>;
        }>();

        render(
            <MenuCatalogWorkspace
                workspaceId={WORKSPACE_ID}
                locationId={LOCATION_ID}
                onTreeChange={onTreeChange}
            />,
        );

        const nameInput = await screen.findByLabelText('Menu name');
        fireEvent.change(nameInput, { target: { value: 'Ana Menü' } });
        fireEvent.click(screen.getByRole('button', { name: 'Create menu' }));

        await waitFor(() => {
            expect(onTreeChange).toHaveBeenCalledWith(
                expect.objectContaining({ id: MENU_ID, locationId: LOCATION_ID, categories: [] }),
            );
        });

        vi.unstubAllGlobals();
    });

    // --- DASHBOARD-SYNC-CATEGORY-CREATE-01 --------------------------------
    it('calls onTreeChange with the new category after a category is created', async () => {
        const fetchMock = buildFetchMock();
        vi.stubGlobal('fetch', fetchMock);
        const onTreeChange = vi.fn();

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<{
                workspaceId: number;
                locationId: number;
                onTreeChange?: (tree: unknown) => void;
            }>;
        }>();

        render(
            <MenuCatalogWorkspace
                workspaceId={WORKSPACE_ID}
                locationId={LOCATION_ID}
                onTreeChange={onTreeChange}
            />,
        );

        await screen.findByText('Kahve');

        fireEvent.click(screen.getByRole('button', { name: 'Add category' }));
        fireEvent.change(screen.getByLabelText('Category name'), {
            target: { value: 'Çorbalar' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Add category' }));

        await waitFor(() => {
            expect(onTreeChange).toHaveBeenLastCalledWith(
                expect.objectContaining({
                    id: MENU_ID,
                    categories: expect.arrayContaining([
                        expect.objectContaining({ id: NEW_CATEGORY_ID, name: 'Çorbalar' }),
                    ]),
                }),
            );
        });

        vi.unstubAllGlobals();
    });

    // --- DASHBOARD-SYNC-ITEM-CREATE-01 ------------------------------------
    // Kalem eklemek yayın hazırlığını doğrudan etkiler: publish en az bir
    // görünür kalem ister. Bildirim gitmezse hazırlık listesi taslağı hiç
    // görmez.
    it('calls onTreeChange with the new menu item after an item is created', async () => {
        const fetchMock = buildFetchMock();
        vi.stubGlobal('fetch', fetchMock);
        const onTreeChange = vi.fn();

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<{
                workspaceId: number;
                locationId: number;
                onTreeChange?: (tree: unknown) => void;
            }>;
        }>();

        render(
            <MenuCatalogWorkspace
                workspaceId={WORKSPACE_ID}
                locationId={LOCATION_ID}
                onTreeChange={onTreeChange}
            />,
        );

        await screen.findByText('Kahve');

        fireEvent.click(screen.getByRole('button', { name: 'Add category' }));
        fireEvent.change(screen.getByLabelText('Category name'), {
            target: { value: 'Çorbalar' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Add category' }));

        // Kategori oluşunca ürün formu ONUN İÇİNDE kendiliğinden açılır.
        // Kategoriyi zaten içine ürün koymak için yaratıyorsun; ikinci bir
        // tıklama istemek, sorulmayan bir soruya cevap vermektir.
        await waitFor(() => {
            expect(
                screen.getByRole('form', { name: 'Add a product to Çorbalar' }),
            ).toBeInTheDocument();
        });

        // Tek form, tek gönderim: ad ve fiyat birlikte doldurulur.
        fireEvent.change(screen.getByLabelText('Product name'), {
            target: { value: 'Mercimek Çorbası' },
        });
        fireEvent.change(screen.getByLabelText('Price'), { target: { value: '45.00' } });
        fireEvent.click(screen.getByRole('button', { name: 'Add to menu' }));

        await waitFor(() => {
            expect(onTreeChange).toHaveBeenLastCalledWith(
                expect.objectContaining({
                    categories: expect.arrayContaining([
                        expect.objectContaining({
                            id: NEW_CATEGORY_ID,
                            menuItems: expect.arrayContaining([
                                expect.objectContaining({ id: NEW_MENU_ITEM_ID }),
                            ]),
                        }),
                    ]),
                }),
            );
        });

        vi.unstubAllGlobals();
    });
});
