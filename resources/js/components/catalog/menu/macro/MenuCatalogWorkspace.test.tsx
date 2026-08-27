import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';

/**
 * Blind RED test candidate. No ./MenuCatalogWorkspace module exists in this
 * snapshot, so every dynamic import below fails RED (module-not-found) until
 * the component is built. Contract frozen to the six Menu API routes already
 * in routes/api.php plus GET brand and /sanctum/csrf-cookie:
 *   GET  /api/workspaces/{workspace}/brand
 *   GET  /api/workspaces/{workspace}/brand/locations/{location}/menu
 *   POST /api/workspaces/{workspace}/brand/locations/{location}/menu
 *   POST /api/workspaces/{workspace}/menu/{menu}/categories
 *   POST /api/workspaces/{workspace}/menu-categories/{category}/products
 *   POST /api/workspaces/{workspace}/menu-categories/{category}/menu-items
 *   PUT  /api/workspaces/{workspace}/menu-items/{menuItem}/allergens
 *   PUT  /api/workspaces/{workspace}/menu-items/{menuItem}/visibility
 * No fixed 14-allergen list, media, publication, QR, reorder/delete,
 * or WorkspaceApp integration is in scope here.
 */
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 7;
const LOCATION_ID = 3;

function brandUrl(workspaceId: number): string {
    return `/api/workspaces/${workspaceId}/brand`;
}

function menuUrl(workspaceId: number, locationId: number): string {
    return `/api/workspaces/${workspaceId}/brand/locations/${locationId}/menu`;
}

function categoriesUrl(workspaceId: number, menuId: number): string {
    return `/api/workspaces/${workspaceId}/menu/${menuId}/categories`;
}

function productsUrl(workspaceId: number, categoryId: number): string {
    return `/api/workspaces/${workspaceId}/menu-categories/${categoryId}/products`;
}

function menuItemsUrl(workspaceId: number, categoryId: number): string {
    return `/api/workspaces/${workspaceId}/menu-categories/${categoryId}/menu-items`;
}

/**
 * Menüye ürün eklemenin TEK uç noktası.
 *
 * Öncesinde bu üç çağrıydı — ürün, satır, alerjen — ve arayüz kullanıcıya
 * üç ayrı form gösteriyordu. Sahibi bunu reddetti: kategori eklemek ile
 * ürün eklemek aynı formda olamaz, ve bir ürün eklemek üç kayıt değil tek
 * bir iştir.
 */
function menuEntriesUrl(workspaceId: number, categoryId: number): string {
    return `/api/workspaces/${workspaceId}/menu-categories/${categoryId}/menu-entries`;
}

function allergensUrl(workspaceId: number, menuItemId: number): string {
    return `/api/workspaces/${workspaceId}/menu-items/${menuItemId}/allergens`;
}

function priceUrl(workspaceId: number, menuItemId: number): string {
    return `/api/workspaces/${workspaceId}/menu-items/${menuItemId}/price`;
}

function visibilityUrl(workspaceId: number, menuItemId: number): string {
    return `/api/workspaces/${workspaceId}/menu-items/${menuItemId}/visibility`;
}

function importMenuCatalogModule<
    T extends Record<string, unknown> = Record<string, unknown>,
>(): Promise<T> {
    return import(/* @vite-ignore */ './MenuCatalogWorkspace') as unknown as Promise<T>;
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function makeBrand(overrides: Partial<{ currency: string }> = {}) {
    return {
        id: 1,
        workspace_id: WORKSPACE_ID,
        name: 'Zeytin Restoranları',
        slug: 'zeytin-restoranlari',
        locale: 'tr-TR',
        timezone: 'Europe/Istanbul',
        currency: overrides.currency ?? 'TRY',
        description: null,
        contact_email: null,
        contact_phone: null,
    };
}

function makeMenuTree(overrides: Partial<{ categories: unknown[] }> = {}) {
    return {
        id: 42,
        workspaceId: WORKSPACE_ID,
        locationId: LOCATION_ID,
        name: 'Ana Menü',
        state: 'draft',
        categories: overrides.categories ?? [],
    };
}

type FetchHandlers = {
    brand?: () => Response;
    menu?: () => Response;
    createMenu?: (body: unknown) => Response;
    createCategory?: (body: unknown) => Response;
    createProduct?: (body: unknown) => Response;
    createMenuItem?: (body: unknown) => Response;
    createMenuEntry?: (body: unknown) => Response;
    updateAllergens?: (body: unknown) => Response;
};

function buildFetchMock(handlers: FetchHandlers) {
    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();
        const body = init?.body ? JSON.parse(String(init.body)) : {};

        if (String(url) === CSRF_COOKIE_URL) {
            return jsonResponse(204, {});
        }
        if (String(url) === brandUrl(WORKSPACE_ID) && method === 'GET') {
            return handlers.brand ? handlers.brand() : jsonResponse(200, makeBrand());
        }
        if (String(url) === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET') {
            return handlers.menu ? handlers.menu() : jsonResponse(404, { message: 'Not Found.' });
        }
        if (String(url) === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'POST') {
            return handlers.createMenu
                ? handlers.createMenu(body)
                : jsonResponse(201, {
                      id: 42,
                      workspaceId: WORKSPACE_ID,
                      locationId: LOCATION_ID,
                      name: body.name,
                      state: 'draft',
                  });
        }
        if (String(url) === categoriesUrl(WORKSPACE_ID, 42) && method === 'POST') {
            return handlers.createCategory
                ? handlers.createCategory(body)
                : jsonResponse(201, { id: 5, menuId: 42, name: body.name, position: 0 });
        }
        if (String(url) === productsUrl(WORKSPACE_ID, 5) && method === 'POST') {
            return handlers.createProduct
                ? handlers.createProduct(body)
                : jsonResponse(201, { id: 9, workspaceId: WORKSPACE_ID, name: body.name });
        }
        if (String(url) === menuItemsUrl(WORKSPACE_ID, 5) && method === 'POST') {
            return handlers.createMenuItem
                ? handlers.createMenuItem(body)
                : jsonResponse(201, {
                      id: 11,
                      categoryId: 5,
                      productId: body.productId,
                      priceMinorAmount: 4250,
                      currencyCode: 'TRY',
                      position: 0,
                  });
        }
        if (String(url) === menuEntriesUrl(WORKSPACE_ID, 5) && method === 'POST') {
            return handlers.createMenuEntry
                ? handlers.createMenuEntry(body)
                : jsonResponse(201, {
                      id: 11,
                      categoryId: 5,
                      productId: 9,
                      productName: body.productName,
                      priceMinorAmount: 4250,
                      currencyCode: 'TRY',
                      position: 0,
                      isVisible: false,
                      allergens: body.allergens ?? [],
                  });
        }
        if (String(url) === menuEntriesUrl(WORKSPACE_ID, 6) && method === 'POST') {
            return handlers.createMenuEntry
                ? handlers.createMenuEntry(body)
                : jsonResponse(201, {
                      id: 12,
                      categoryId: 6,
                      productId: 10,
                      productName: body.productName,
                      priceMinorAmount: 4250,
                      currencyCode: 'TRY',
                      position: 0,
                      isVisible: false,
                      allergens: body.allergens ?? [],
                  });
        }
        if (String(url) === allergensUrl(WORKSPACE_ID, 11) && method === 'PUT') {
            return handlers.updateAllergens
                ? handlers.updateAllergens(body)
                : jsonResponse(200, { id: 11, allergens: body.allergens });
        }

        throw new Error(`Unhandled fetch in MenuCatalogWorkspace test: ${method} ${String(url)}`);
    });
}

type MenuCatalogWorkspaceProps = { workspaceId: number; locationId: number };

/**
 * Bir kategorinin ürün formunu açar.
 *
 * Kategori artık bir alan değil, tıkladığın yer: form o kategorinin
 * ALTINDA açılır ve kategori sorulmaz.
 */
function openEntryForm(categoryName: string): HTMLElement {
    const category = screen
        .getByRole('heading', { name: categoryName })
        .closest('li') as HTMLElement;

    fireEvent.click(within(category).getByRole('button', { name: 'Add product' }));

    return category;
}

function assertMutationRequestInit(init: RequestInit | undefined): void {
    expect(init?.credentials).toBe('include');
    const headers = new Headers(init?.headers as HeadersInit | undefined);
    expect(headers.get('Accept')).toBe('application/json');
    expect(headers.get('Content-Type')).toBe('application/json');
}

/*
 * `selector: 'input'` bilerek eklendi: "alerjenleri düzenle" butonu artık
 * TAM erişilebilir isim taşıyor (görünen metin kısa, `aria-label` uzun),
 * dolayısıyla `/allergens/i` hem alanı hem butonu eşliyor. Sorgu neyi
 * aradığını açıkça söylemeli; belirsiz kalırsa ilk eklenen kardeşte kırılır.
 */
describe('MenuCatalogWorkspace — full owner journey (RED, module-not-found)', () => {
    it('fetches brand currency and menu on mount, then creates the menu, one category, and adds a priced product with allergens in a SINGLE submit, CSRF-before-write', async () => {
        let currentMenuId: number | null = null;
        let allergensUpdated = false;
        const finalTree = makeMenuTree({
            categories: [
                {
                    id: 5,
                    menuId: 42,
                    name: 'Başlangıçlar',
                    position: 0,
                    menuItems: [
                        {
                            id: 11,
                            categoryId: 5,
                            productId: 9,
                            productName: 'Mercimek Çorbası',
                            priceMinorAmount: 4250,
                            currencyCode: 'TRY',
                            position: 0,
                            allergens: ['gluten', 'süt'],
                        },
                    ],
                },
            ],
        });
        const fetchMock = buildFetchMock({
            menu: () => {
                if (currentMenuId === null) return jsonResponse(404, { message: 'Not Found.' });
                if (allergensUpdated) return jsonResponse(200, finalTree);
                return jsonResponse(200, makeMenuTree());
            },
            createMenu: (body) => {
                expect((body as { name: string }).name).toBe('Ana Menü');
                currentMenuId = 42;
                return jsonResponse(201, {
                    id: 42,
                    workspaceId: WORKSPACE_ID,
                    locationId: LOCATION_ID,
                    name: (body as { name: string }).name,
                    state: 'draft',
                });
            },
            createCategory: (body) => {
                expect((body as { name: string }).name).toBe('Başlangıçlar');
                return jsonResponse(201, {
                    id: 5,
                    menuId: 42,
                    name: (body as { name: string }).name,
                    position: 0,
                });
            },
            // Ürün, fiyatı ve alerjenleri TEK istekte gider. Üç ayrı
            // istek olsaydı ikincisi düştüğünde hiçbir menüde görünmeyen
            // öksüz bir ürün geride kalırdı.
            createMenuEntry: (body) => {
                const typed = body as {
                    productName: string;
                    price: string;
                    currency: string;
                    allergens: string[];
                };
                expect(typed.productName).toBe('Mercimek Çorbası');
                expect(typed.price).toBe('42.50');
                expect(typed.currency).toBe('TRY');
                expect(typed.allergens).toEqual(['gluten', 'süt']);
                allergensUpdated = true;

                return jsonResponse(201, {
                    id: 11,
                    categoryId: 5,
                    productId: 9,
                    productName: typed.productName,
                    priceMinorAmount: 4250,
                    currencyCode: 'TRY',
                    position: 0,
                    isVisible: false,
                    allergens: typed.allergens,
                });
            },
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await waitFor(() => {
            const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
            expect(calledUrls).toContain(brandUrl(WORKSPACE_ID));
            expect(calledUrls).toContain(menuUrl(WORKSPACE_ID, LOCATION_ID));
        });

        function csrfPrecedesWrite(writeUrl: string, method: string): void {
            const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
            const csrfIndex = calledUrls.lastIndexOf(CSRF_COOKIE_URL);
            const writeIndex = fetchMock.mock.calls.findIndex(
                (call) =>
                    String(call[0]) === writeUrl &&
                    ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase() ===
                        method,
            );
            expect(csrfIndex).toBeGreaterThanOrEqual(0);
            expect(writeIndex).toBeGreaterThan(csrfIndex);
            assertMutationRequestInit(
                fetchMock.mock.calls[writeIndex][1] as RequestInit | undefined,
            );
        }

        const menuNameInput = await screen.findByLabelText(/menu name/i);
        fireEvent.change(menuNameInput, { target: { value: 'Ana Menü' } });
        fireEvent.click(screen.getByRole('button', { name: /create menu/i }));
        await waitFor(() => csrfPrecedesWrite(menuUrl(WORKSPACE_ID, LOCATION_ID), 'POST'));
        expect(screen.getByRole('heading', { name: 'Ana Menü' })).toBeInTheDocument();

        // Kategori ekleme KAPALI başlar ve kendi eylemidir: nadir yapılan
        // bir iş, sürekli yapılan "ürün ekle" ile aynı ağırlıkta duramaz.
        fireEvent.click(screen.getByRole('button', { name: /add category/i }));
        const categoryNameInput = await screen.findByLabelText(/category name/i);
        fireEvent.change(categoryNameInput, { target: { value: 'Başlangıçlar' } });
        fireEvent.click(screen.getByRole('button', { name: /^add category$/i }));
        await waitFor(() => csrfPrecedesWrite(categoriesUrl(WORKSPACE_ID, 42), 'POST'));

        // TEK form: ad, fiyat ve alerjen birlikte doldurulur, bir kez
        // gönderilir. Öncesinde bu üç ayrı form ve üç ayrı kaydetmeydi.
        const productNameInput = await screen.findByLabelText(/product name/i);
        fireEvent.change(productNameInput, { target: { value: 'Mercimek Çorbası' } });

        const priceInput = screen.getByLabelText(/^price$/i, { selector: 'input' });
        fireEvent.change(priceInput, { target: { value: '42.50' } });

        const allergensInput = screen.getByLabelText(/allergens \(comma/i, { selector: 'input' });
        fireEvent.change(allergensInput, { target: { value: 'gluten, süt, gluten, süt' } });

        fireEvent.click(screen.getByRole('button', { name: /add to menu/i }));
        await waitFor(() => csrfPrecedesWrite(menuEntriesUrl(WORKSPACE_ID, 5), 'POST'));

        expect(
            screen.getByRole('button', { name: 'Edit price for Mercimek Çorbası' }),
        ).toBeInTheDocument();

        // Ürünü eklemek için gereken sunucu turu SAYISI sözleşmenin
        // parçasıdır: üçe çıkarsa zincir geri gelmiş demektir.
        const entryWrites = fetchMock.mock.calls.filter(
            (call) =>
                String(call[0]).includes('/menu-entries') ||
                String(call[0]).includes('/products') ||
                String(call[0]).includes('/menu-items'),
        );
        expect(entryWrites).toHaveLength(1);

        await waitFor(() => {
            // Başlık olarak arıyoruz: kategori adı artık iki yerde geçiyor —
            // listenin başlığında ve ürün formunun kategori seçiminde. Belirsiz
            // bir sorgu, ikincisi eklendiği gün kırılırdı.
            expect(screen.getByRole('heading', { name: 'Başlangıçlar' })).toBeInTheDocument();
        });
        expect(screen.getByText('Mercimek Çorbası')).toBeInTheDocument();
        expect(screen.getByText(/42[.,]50/)).toBeInTheDocument();
        expect(screen.getByText(/TRY/)).toBeInTheDocument();
        expect(screen.getByText(/gluten/i)).toBeInTheDocument();
        expect(screen.getByText(/süt/i)).toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});

describe('MenuCatalogWorkspace — existing tree render (RED, module-not-found)', () => {
    it('renders the fetched menu tree as a semantic ordered list without triggering any write request', async () => {
        const tree = makeMenuTree({
            categories: [
                {
                    id: 5,
                    menuId: 42,
                    name: 'Başlangıçlar',
                    position: 0,
                    menuItems: [
                        {
                            id: 11,
                            categoryId: 5,
                            productId: 9,
                            productName: 'Mercimek Çorbası',
                            priceMinorAmount: 4250,
                            currencyCode: 'TRY',
                            position: 0,
                            allergens: ['gluten'],
                        },
                    ],
                },
            ],
        });
        const fetchMock = buildFetchMock({
            menu: () => jsonResponse(200, tree),
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await screen.findByRole('heading', { name: 'Başlangıçlar' });
        expect(screen.getByRole('heading', { name: tree.name })).toBeInTheDocument();
        const list = screen.getByRole('list', { name: /menu|categories/i });
        expect(list.tagName).toBe('OL');
        expect(within(list).getByText('Mercimek Çorbası')).toBeInTheDocument();

        const writeCalls = fetchMock.mock.calls.filter((call) => {
            const method = ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase();
            return method !== 'GET';
        });
        expect(writeCalls).toHaveLength(0);

        vi.unstubAllGlobals();
    });
});

describe('MenuCatalogWorkspace — required validation (RED, module-not-found)', () => {
    it('shows an accessible validation error and does not call the create-menu endpoint when the menu name is empty', async () => {
        const fetchMock = buildFetchMock({});
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await screen.findByLabelText(/menu name/i);
        fireEvent.click(screen.getByRole('button', { name: /create menu/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();

        const postCalls = fetchMock.mock.calls.filter(
            (call) =>
                String(call[0]) === menuUrl(WORKSPACE_ID, LOCATION_ID) &&
                ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'POST',
        );
        expect(postCalls).toHaveLength(0);

        vi.unstubAllGlobals();
    });
});

describe('MenuCatalogWorkspace — backend error surfacing (RED, module-not-found)', () => {
    it('renders an accessible alert on a 403 create-menu response and does not advance to the created state', async () => {
        const fetchMock = buildFetchMock({
            createMenu: () => jsonResponse(403, { message: 'Forbidden.' }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const menuNameInput = await screen.findByLabelText(/menu name/i);
        fireEvent.change(menuNameInput, { target: { value: 'Ana Menü' } });
        fireEvent.click(screen.getByRole('button', { name: /create menu/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();
        expect(screen.queryByLabelText(/category name/i)).not.toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('renders an accessible alert on a 422 create-menu response and does not advance to the created state', async () => {
        const fetchMock = buildFetchMock({
            createMenu: () =>
                jsonResponse(422, {
                    message: 'The name field is required.',
                    errors: { name: ['The name field is required.'] },
                }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const menuNameInput = await screen.findByLabelText(/menu name/i);
        fireEvent.change(menuNameInput, { target: { value: 'Ana Menü' } });
        fireEvent.click(screen.getByRole('button', { name: /create menu/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();
        expect(screen.queryByLabelText(/category name/i)).not.toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});

describe('MenuCatalogWorkspace — loading and busy state (RED, module-not-found)', () => {
    it('renders a status region while the initial brand/menu fetches are in flight', async () => {
        let resolveBrand: (value: Response) => void = () => {};
        const pendingBrand = new Promise<Response>((resolve) => {
            resolveBrand = resolve;
        });
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (String(url) === brandUrl(WORKSPACE_ID)) return pendingBrand;
            if (String(url) === menuUrl(WORKSPACE_ID, LOCATION_ID))
                return jsonResponse(404, { message: 'Not Found.' });
            throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        expect(screen.getByRole('status')).toBeInTheDocument();

        resolveBrand(jsonResponse(200, makeBrand()));
        await waitFor(() => {
            expect(screen.queryByRole('status', { name: /loading/i })).not.toBeInTheDocument();
        });

        vi.unstubAllGlobals();
    });

    it('disables the create-menu action and announces busy state while the create request is in flight', async () => {
        let resolveCreate: (value: Response) => void = () => {};
        const pendingCreate = new Promise<Response>((resolve) => {
            resolveCreate = resolve;
        });
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (String(url) === brandUrl(WORKSPACE_ID)) return jsonResponse(200, makeBrand());
            if (String(url) === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET')
                return jsonResponse(404, { message: 'Not Found.' });
            if (String(url) === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'POST')
                return pendingCreate;
            throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const menuNameInput = await screen.findByLabelText(/menu name/i);
        fireEvent.change(menuNameInput, { target: { value: 'Ana Menü' } });
        const createButton = screen.getByRole('button', { name: /create menu/i });
        fireEvent.click(createButton);

        await waitFor(() => {
            expect(createButton).toBeDisabled();
        });
        expect(screen.getByRole('status')).toBeInTheDocument();

        resolveCreate(
            jsonResponse(201, {
                id: 42,
                workspaceId: WORKSPACE_ID,
                locationId: LOCATION_ID,
                name: 'Ana Menü',
                state: 'draft',
            }),
        );
        vi.unstubAllGlobals();
    });
});

describe('MenuCatalogWorkspace — initial load error and retry (RED, module-not-found)', () => {
    it.each([
        { label: 'brand GET fails', brandStatus: 500, menuStatus: 404 },
        { label: 'menu GET fails', brandStatus: 200, menuStatus: 500 },
    ])(
        'renders an accessible alert with a Retry button instead of the create-menu form when the initial $label, and recovers on Retry',
        async ({ brandStatus, menuStatus }) => {
            let attempt = 0;
            const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
                const method = (init?.method ?? 'GET').toUpperCase();
                if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
                if (String(url) === brandUrl(WORKSPACE_ID)) {
                    return attempt === 0 && brandStatus !== 200
                        ? jsonResponse(brandStatus, { message: 'Server Error.' })
                        : jsonResponse(200, makeBrand());
                }
                if (String(url) === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET') {
                    return attempt === 0 && menuStatus !== 404
                        ? jsonResponse(menuStatus, { message: 'Server Error.' })
                        : jsonResponse(404, { message: 'Not Found.' });
                }
                throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
            });
            vi.stubGlobal('fetch', fetchMock);

            const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
                MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
            }>();
            render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

            const alert = await screen.findByRole('alert');
            expect(alert).toBeInTheDocument();
            expect(screen.queryByLabelText(/menu name/i)).not.toBeInTheDocument();

            attempt = 1;
            fireEvent.click(screen.getByRole('button', { name: /retry/i }));

            await screen.findByLabelText(/menu name/i);
            expect(screen.queryByRole('alert')).not.toBeInTheDocument();

            vi.unstubAllGlobals();
        },
    );
});

describe('MenuCatalogWorkspace — location switch resets stale state (RED)', () => {
    it('does not carry stale category/product/item selection from a populated location into a freshly created empty location', async () => {
        const LOCATION_A = LOCATION_ID;
        const LOCATION_B = LOCATION_ID + 1;

        const treeAEmptyCategory = makeMenuTree({
            categories: [{ id: 5, menuId: 42, name: 'Başlangıçlar', position: 0, menuItems: [] }],
        });

        let bMenuCreated = false;
        let bCategoryCreated = false;
        let bProductCreated = false;

        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            const body = init?.body ? JSON.parse(String(init.body)) : {};
            const calledUrl = String(url);

            if (calledUrl === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (calledUrl === brandUrl(WORKSPACE_ID) && method === 'GET')
                return jsonResponse(200, makeBrand());

            if (calledUrl === menuUrl(WORKSPACE_ID, LOCATION_A) && method === 'GET')
                return jsonResponse(200, treeAEmptyCategory);

            if (calledUrl === productsUrl(WORKSPACE_ID, 5) && method === 'POST') {
                expect((body as { name: string }).name).toBe('Mercimek Çorbası');
                return jsonResponse(201, {
                    id: 9,
                    workspaceId: WORKSPACE_ID,
                    name: (body as { name: string }).name,
                });
            }
            if (calledUrl === menuItemsUrl(WORKSPACE_ID, 5) && method === 'POST') {
                expect((body as { productId: number }).productId).toBe(9);
                return jsonResponse(201, {
                    id: 11,
                    categoryId: 5,
                    productId: 9,
                    priceMinorAmount: 4250,
                    currencyCode: 'TRY',
                    position: 0,
                });
            }

            if (calledUrl === menuUrl(WORKSPACE_ID, LOCATION_B) && method === 'GET') {
                if (!bMenuCreated) return jsonResponse(404, { message: 'Not Found.' });
                return jsonResponse(200, {
                    id: 99,
                    workspaceId: WORKSPACE_ID,
                    locationId: LOCATION_B,
                    name: 'Yeni Menü',
                    state: 'draft',
                    categories: bCategoryCreated
                        ? [{ id: 77, menuId: 99, name: 'Tatlılar', position: 0, menuItems: [] }]
                        : [],
                });
            }

            if (calledUrl === menuUrl(WORKSPACE_ID, LOCATION_B) && method === 'POST') {
                expect((body as { name: string }).name).toBe('Yeni Menü');
                bMenuCreated = true;
                return jsonResponse(201, {
                    id: 99,
                    workspaceId: WORKSPACE_ID,
                    locationId: LOCATION_B,
                    name: (body as { name: string }).name,
                    state: 'draft',
                });
            }

            if (calledUrl === categoriesUrl(WORKSPACE_ID, 99) && method === 'POST') {
                expect((body as { name: string }).name).toBe('Tatlılar');
                bCategoryCreated = true;
                return jsonResponse(201, {
                    id: 77,
                    menuId: 99,
                    name: (body as { name: string }).name,
                    position: 0,
                });
            }

            if (calledUrl === menuEntriesUrl(WORKSPACE_ID, 77) && method === 'POST') {
                bProductCreated = true;

                return jsonResponse(201, {
                    id: 88,
                    categoryId: 77,
                    productId: 88,
                    productName: (body as { productName: string }).productName,
                    priceMinorAmount: 3000,
                    currencyCode: 'TRY',
                    position: 0,
                    isVisible: false,
                    allergens: [],
                });
            }

            throw new Error(`Unhandled fetch in location-switch test: ${method} ${calledUrl}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();

        const { rerender } = render(
            <MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_A} />,
        );

        // A konumunda tek forma yazılır ama GÖNDERİLMEZ: sınanan şey,
        // yazılmış ama kaydedilmemiş verinin konum değişince taşınmamasıdır.
        await screen.findByRole('heading', { name: 'Başlangıçlar' });
        openEntryForm('Başlangıçlar');

        const aProductNameInput = await screen.findByLabelText('Product name');
        fireEvent.change(aProductNameInput, { target: { value: 'Mercimek Çorbası' } });

        const aPriceInput = screen.getByLabelText('Price');
        fireEvent.change(aPriceInput, { target: { value: '42.50' } });

        const preSwitchCallCount = fetchMock.mock.calls.length;

        rerender(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_B} />);

        const menuNameInput = await screen.findByLabelText(/menu name/i);
        expect((menuNameInput as HTMLInputElement).value).toBe('');
        expect(screen.queryByLabelText('Category name')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Product name')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Price')).not.toBeInTheDocument();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();

        fireEvent.change(menuNameInput, { target: { value: 'Yeni Menü' } });
        fireEvent.click(screen.getByRole('button', { name: /create menu/i }));

        // Menü var ama kategori yok: ürün formu alanlarını göstermez, çünkü
        // her ürün bir kategoriye ait olmak zorundadır.
        await waitFor(() => {
            expect(screen.queryByLabelText('Product name')).not.toBeInTheDocument();
        });
        expect(screen.queryByLabelText('Price')).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Add category' }));
        const categoryNameInput = await screen.findByLabelText('Category name');
        fireEvent.change(categoryNameInput, { target: { value: 'Tatlılar' } });
        fireEvent.click(screen.getByRole('button', { name: /^add category$/i }));

        await screen.findByRole('heading', { name: 'Tatlılar' });

        // Kategori gelince ürün formu açılır ve BOŞTUR.
        const bProductNameInput = await screen.findByLabelText('Product name');
        expect((bProductNameInput as HTMLInputElement).value).toBe('');
        expect((screen.getByLabelText('Price') as HTMLInputElement).value).toBe('');

        fireEvent.change(bProductNameInput, { target: { value: 'Baklava' } });
        fireEvent.change(screen.getByLabelText('Price'), { target: { value: '30.00' } });
        fireEvent.click(screen.getByRole('button', { name: 'Add to menu' }));

        await waitFor(() => {
            expect(bProductCreated).toBe(true);
        });

        for (const call of fetchMock.mock.calls.slice(preSwitchCallCount)) {
            const calledUrl = String(call[0]);
            const method = ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase();
            if (method === 'GET') continue;
            expect(calledUrl).not.toContain('/menu/42/');
            expect(calledUrl).not.toContain('menu-categories/5/');
            expect(calledUrl).not.toContain('menu-items/11/');
        }

        vi.unstubAllGlobals();
    });
});

describe('MenuCatalogWorkspace — edit allergens of an existing server-returned menu item (RED)', () => {
    it("lets a returning owner open the existing allergen form for a server-loaded item, prefilled from its own allergens, and PUTs to that item's real id", async () => {
        const FIRST_ITEM_ID = 101;
        const SECOND_ITEM_ID = 202;
        const tree = makeMenuTree({
            categories: [
                {
                    id: 5,
                    menuId: 42,
                    name: 'Başlangıçlar',
                    position: 0,
                    menuItems: [
                        {
                            id: FIRST_ITEM_ID,
                            categoryId: 5,
                            productId: 9,
                            productName: 'Mercimek Çorbası',
                            priceMinorAmount: 4250,
                            currencyCode: 'TRY',
                            position: 0,
                            allergens: ['gluten', 'süt'],
                        },
                        {
                            id: SECOND_ITEM_ID,
                            categoryId: 5,
                            productId: 21,
                            productName: 'Baklava',
                            priceMinorAmount: 5000,
                            currencyCode: 'TRY',
                            position: 1,
                            allergens: [],
                        },
                    ],
                },
            ],
        });

        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            const body = init?.body ? JSON.parse(String(init.body)) : {};
            const calledUrl = String(url);

            if (calledUrl === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (calledUrl === brandUrl(WORKSPACE_ID) && method === 'GET')
                return jsonResponse(200, makeBrand());
            if (calledUrl === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET')
                return jsonResponse(200, tree);
            if (calledUrl === allergensUrl(WORKSPACE_ID, FIRST_ITEM_ID) && method === 'PUT') {
                const typed = body as { allergens: string[] };
                expect(typed.allergens).toEqual(['gluten', 'süt']);
                return jsonResponse(200, { id: FIRST_ITEM_ID, allergens: typed.allergens });
            }

            throw new Error(
                `Unhandled fetch in existing-item allergen test: ${method} ${calledUrl}`,
            );
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await screen.findByText('Mercimek Çorbası');
        await screen.findByText('Baklava');

        const writeCallsBeforeInteraction = fetchMock.mock.calls.filter((call) => {
            const method = ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase();
            return method !== 'GET';
        });
        expect(writeCallsBeforeInteraction).toHaveLength(0);

        fireEvent.click(
            screen.getByRole('button', { name: 'Edit allergens for Mercimek Çorbası' }),
        );

        const allergensInput = (await screen.findByRole('textbox', {
            name: 'Allergens — Mercimek Çorbası',
        })) as HTMLInputElement;
        expect(allergensInput.value).toContain('gluten');
        expect(allergensInput.value).toContain('süt');

        fireEvent.change(allergensInput, { target: { value: 'gluten, süt, gluten, süt' } });
        fireEvent.click(screen.getByRole('button', { name: /save allergens|update allergens/i }));

        await waitFor(() => {
            const putCalls = fetchMock.mock.calls.filter(
                (call) =>
                    String(call[0]) === allergensUrl(WORKSPACE_ID, FIRST_ITEM_ID) &&
                    ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'PUT',
            );
            expect(putCalls).toHaveLength(1);
            assertMutationRequestInit(putCalls[0][1] as RequestInit | undefined);
        });

        const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
        const csrfIndex = calledUrls.lastIndexOf(CSRF_COOKIE_URL);
        const putIndex = fetchMock.mock.calls.findIndex(
            (call) =>
                String(call[0]) === allergensUrl(WORKSPACE_ID, FIRST_ITEM_ID) &&
                ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'PUT',
        );
        expect(csrfIndex).toBeGreaterThanOrEqual(0);
        expect(putIndex).toBeGreaterThan(csrfIndex);

        fireEvent.click(screen.getByRole('button', { name: 'Edit allergens for Baklava' }));
        const secondAllergensInput = (await screen.findByRole('textbox', {
            name: 'Allergens — Baklava',
        })) as HTMLInputElement;
        expect(secondAllergensInput.value).toBe('');

        vi.unstubAllGlobals();
    });
});

describe('MenuCatalogWorkspace — category selection routes writes and resets downstream state (RED)', () => {
    it('routes the entry POST to the category whose Add product was clicked, never to another', async () => {
        const CATEGORY_A_ID = 5;
        const CATEGORY_B_ID = 6;
        const tree = makeMenuTree({
            categories: [
                { id: CATEGORY_A_ID, menuId: 42, name: 'Başlangıçlar', position: 0, menuItems: [] },
                { id: CATEGORY_B_ID, menuId: 42, name: 'Tatlılar', position: 1, menuItems: [] },
            ],
        });

        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            const body = init?.body ? JSON.parse(String(init.body)) : {};
            const calledUrl = String(url);

            if (calledUrl === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (calledUrl === brandUrl(WORKSPACE_ID) && method === 'GET')
                return jsonResponse(200, makeBrand());
            if (calledUrl === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET')
                return jsonResponse(200, tree);
            if (calledUrl === productsUrl(WORKSPACE_ID, CATEGORY_B_ID) && method === 'POST') {
                expect((body as { name: string }).name).toBe('Baklava');
                return jsonResponse(201, {
                    id: 21,
                    workspaceId: WORKSPACE_ID,
                    name: (body as { name: string }).name,
                });
            }
            if (calledUrl === menuItemsUrl(WORKSPACE_ID, CATEGORY_B_ID) && method === 'POST') {
                expect((body as { productId: number }).productId).toBe(21);
                return jsonResponse(201, {
                    id: 33,
                    categoryId: CATEGORY_B_ID,
                    productId: 21,
                    priceMinorAmount: 5000,
                    currencyCode: 'TRY',
                    position: 0,
                });
            }
            if (
                calledUrl === productsUrl(WORKSPACE_ID, CATEGORY_A_ID) ||
                calledUrl === menuItemsUrl(WORKSPACE_ID, CATEGORY_A_ID)
            ) {
                throw new Error(
                    `Write incorrectly routed to first category: ${method} ${calledUrl}`,
                );
            }

            throw new Error(`Unhandled fetch in category-selection test: ${method} ${calledUrl}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await screen.findByRole('heading', { name: 'Başlangıçlar' });
        expect(screen.getByRole('heading', { name: 'Tatlılar' })).toBeInTheDocument();

        // Kategori artık bir AÇILIR MENÜ DEĞİL, tıkladığın yer.
        //
        // Öncesinde ürün formu sayfanın en altında tek başına duruyor ve
        // kullanıcı zaten baktığı kategoriyi bir listeden seçiyordu.
        // "Tatlılar"a ürün eklemek için "Tatlılar"ın altındaki düğmeye
        // basılır; seçim diye bir adım kalmadı.
        const dessertCategory = screen
            .getByRole('heading', { name: 'Tatlılar' })
            .closest('li') as HTMLElement;

        fireEvent.click(within(dessertCategory).getByRole('button', { name: 'Add product' }));

        const form = await screen.findByRole('form', { name: 'Add a product to Tatlılar' });

        fireEvent.change(within(form).getByLabelText('Product name'), {
            target: { value: 'Baklava' },
        });
        fireEvent.change(within(form).getByLabelText('Price'), { target: { value: '50.00' } });
        fireEvent.click(within(form).getByRole('button', { name: 'Add to menu' }));

        await waitFor(() => {
            const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
            expect(calledUrls).toContain(menuEntriesUrl(WORKSPACE_ID, CATEGORY_B_ID));
            // Başka kategoriye HİÇBİR yazma gitmemeli.
            expect(calledUrls).not.toContain(menuEntriesUrl(WORKSPACE_ID, CATEGORY_A_ID));
        });

        vi.unstubAllGlobals();
    });
});

describe('MenuCatalogWorkspace — new product creation resets a stale open existing-item allergen form (RED)', () => {
    it('never writes allergens to another item while a row editor is open and a new product is added', async () => {
        const EXISTING_ITEM_ID = 101;
        const NEW_PRODUCT_ID = 55;
        const tree = makeMenuTree({
            categories: [
                {
                    id: 5,
                    menuId: 42,
                    name: 'Başlangıçlar',
                    position: 0,
                    menuItems: [
                        {
                            id: EXISTING_ITEM_ID,
                            categoryId: 5,
                            productId: 9,
                            productName: 'Mercimek Çorbası',
                            priceMinorAmount: 4250,
                            currencyCode: 'TRY',
                            position: 0,
                            allergens: ['gluten', 'süt'],
                        },
                    ],
                },
            ],
        });

        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            const body = init?.body ? JSON.parse(String(init.body)) : {};
            const calledUrl = String(url);

            if (calledUrl === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (calledUrl === brandUrl(WORKSPACE_ID) && method === 'GET')
                return jsonResponse(200, makeBrand());
            if (calledUrl === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET')
                return jsonResponse(200, tree);
            if (calledUrl === menuEntriesUrl(WORKSPACE_ID, 5) && method === 'POST') {
                expect((body as { productName: string }).productName).toBe('Baklava');

                return jsonResponse(201, {
                    id: 555,
                    categoryId: 5,
                    productId: NEW_PRODUCT_ID,
                    productName: (body as { productName: string }).productName,
                    priceMinorAmount: 5000,
                    currencyCode: 'TRY',
                    position: 1,
                    isVisible: false,
                    allergens: [],
                });
            }
            if (calledUrl === allergensUrl(WORKSPACE_ID, EXISTING_ITEM_ID) && method === 'PUT') {
                throw new Error(
                    'Must not PUT allergens for the stale existing item during new-product creation',
                );
            }

            throw new Error(
                `Unhandled fetch in new-product-resets-stale-item test: ${method} ${calledUrl}`,
            );
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await screen.findByText('Mercimek Çorbası');

        fireEvent.click(
            screen.getByRole('button', { name: 'Edit allergens for Mercimek Çorbası' }),
        );

        // Satır içi düzenleyici: alan artık ÜRÜN ADINI taşır, dolayısıyla
        // aşağıdaki "menüye ürün ekle" formunun alerjen alanıyla karışmaz.
        const staleAllergensInput = (await screen.findByRole('textbox', {
            name: 'Allergens — Mercimek Çorbası',
        })) as HTMLInputElement;
        expect(staleAllergensInput.value).toContain('gluten');
        expect(staleAllergensInput.value).toContain('süt');

        openEntryForm('Başlangıçlar');

        const newProductNameInput = await screen.findByLabelText('Product name');
        fireEvent.change(newProductNameInput, { target: { value: 'Baklava' } });
        fireEvent.change(screen.getByLabelText('Price'), { target: { value: '50.00' } });
        fireEvent.click(screen.getByRole('button', { name: 'Add to menu' }));

        await waitFor(() => {
            const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
            expect(calledUrls).toContain(menuEntriesUrl(WORKSPACE_ID, 5));
        });

        const putCalls = fetchMock.mock.calls.filter(
            (call) =>
                String(call[0]) === allergensUrl(WORKSPACE_ID, EXISTING_ITEM_ID) &&
                ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'PUT',
        );
        expect(putCalls).toHaveLength(0);

        vi.unstubAllGlobals();
    });
});

describe('MenuCatalogWorkspace — edit price of an existing server-returned menu item (RED)', () => {
    it("lets a returning owner open the existing price form for a server-loaded item, prefilled from its own price, and PUTs to that item's real id and currency", async () => {
        const REAL_ITEM_ID = 303;
        const tree = makeMenuTree({
            categories: [
                {
                    id: 5,
                    menuId: 42,
                    name: 'Başlangıçlar',
                    position: 0,
                    menuItems: [
                        {
                            id: REAL_ITEM_ID,
                            categoryId: 5,
                            productId: 9,
                            productName: 'Mercimek Çorbası',
                            priceMinorAmount: 4250,
                            currencyCode: 'TRY',
                            position: 0,
                            allergens: [],
                        },
                    ],
                },
            ],
        });

        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            const body = init?.body ? JSON.parse(String(init.body)) : {};
            const calledUrl = String(url);

            if (calledUrl === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (calledUrl === brandUrl(WORKSPACE_ID) && method === 'GET')
                return jsonResponse(200, makeBrand());
            if (calledUrl === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET')
                return jsonResponse(200, tree);
            if (calledUrl === priceUrl(WORKSPACE_ID, REAL_ITEM_ID) && method === 'PUT') {
                const typed = body as { price: string; currency: string };
                expect(typed.price).toBe('50.00');
                expect(typed.currency).toBe('TRY');
                return jsonResponse(200, {
                    id: REAL_ITEM_ID,
                    priceMinorAmount: 5000,
                    currencyCode: 'TRY',
                });
            }

            throw new Error(`Unhandled fetch in existing-item price test: ${method} ${calledUrl}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await screen.findByText('Mercimek Çorbası');

        fireEvent.click(screen.getByRole('button', { name: 'Edit price for Mercimek Çorbası' }));

        // Satır içi düzenleyicinin adı ÜRÜNÜ söyler: ekranda aynı anda bir de
        // "menüye ürün ekle" formunun fiyat alanı var ve ikisi aynı ismi
        // taşısaydı ekran okuyucu kullanan biri hangisinde olduğunu bilemezdi.
        const priceInput = (await screen.findByRole('textbox', {
            name: 'Price — Mercimek Çorbası',
        })) as HTMLInputElement;
        expect(priceInput.value).toBe('42.50');

        fireEvent.change(priceInput, { target: { value: '50.00' } });
        fireEvent.click(screen.getByRole('button', { name: /save price|update price/i }));

        await waitFor(() => {
            const putCalls = fetchMock.mock.calls.filter(
                (call) =>
                    String(call[0]) === priceUrl(WORKSPACE_ID, REAL_ITEM_ID) &&
                    ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'PUT',
            );
            expect(putCalls).toHaveLength(1);
            assertMutationRequestInit(putCalls[0][1] as RequestInit | undefined);
        });

        const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
        const csrfIndex = calledUrls.lastIndexOf(CSRF_COOKIE_URL);
        const putIndex = fetchMock.mock.calls.findIndex(
            (call) =>
                String(call[0]) === priceUrl(WORKSPACE_ID, REAL_ITEM_ID) &&
                ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'PUT',
        );
        expect(csrfIndex).toBeGreaterThanOrEqual(0);
        expect(putIndex).toBeGreaterThan(csrfIndex);

        await waitFor(() => {
            expect(screen.getByText(/50[.,]00/)).toBeInTheDocument();
        });
        expect(screen.getByText(/TRY/)).toBeInTheDocument();
        expect(screen.queryByText(/42[.,]50/)).not.toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('surfaces an accessible price submit error on a non-OK backend response and does not show a changed price', async () => {
        const REAL_ITEM_ID = 404;
        const tree = makeMenuTree({
            categories: [
                {
                    id: 5,
                    menuId: 42,
                    name: 'Başlangıçlar',
                    position: 0,
                    menuItems: [
                        {
                            id: REAL_ITEM_ID,
                            categoryId: 5,
                            productId: 9,
                            productName: 'Mercimek Çorbası',
                            priceMinorAmount: 4250,
                            currencyCode: 'TRY',
                            position: 0,
                            allergens: [],
                        },
                    ],
                },
            ],
        });

        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            const calledUrl = String(url);

            if (calledUrl === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (calledUrl === brandUrl(WORKSPACE_ID) && method === 'GET')
                return jsonResponse(200, makeBrand());
            if (calledUrl === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET')
                return jsonResponse(200, tree);
            if (calledUrl === priceUrl(WORKSPACE_ID, REAL_ITEM_ID) && method === 'PUT') {
                return jsonResponse(422, { message: 'The price field is invalid.' });
            }

            throw new Error(`Unhandled fetch in price-rejection test: ${method} ${calledUrl}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await screen.findByText('Mercimek Çorbası');

        fireEvent.click(screen.getByRole('button', { name: 'Edit price for Mercimek Çorbası' }));

        const priceInput = (await screen.findByRole('textbox', {
            name: 'Price — Mercimek Çorbası',
        })) as HTMLInputElement;
        fireEvent.change(priceInput, { target: { value: '50.00' } });
        fireEvent.click(screen.getByRole('button', { name: /save price|update price/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();
        expect(screen.getByText(/42[.,]50/)).toBeInTheDocument();
        expect(screen.queryByText(/50[.,]00/)).not.toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});

describe("MenuCatalogWorkspace — visible price uses each currency's ISO fraction digits, consistent with edit-prefill (RED)", () => {
    it('renders JPY (0 fraction digits) and KWD (3 fraction digits) minor amounts using the correct decimal places, matching the Edit price prefill', async () => {
        const JPY_ITEM_ID = 501;
        const KWD_ITEM_ID = 502;
        const tree = makeMenuTree({
            categories: [
                {
                    id: 5,
                    menuId: 42,
                    name: 'Başlangıçlar',
                    position: 0,
                    menuItems: [
                        {
                            id: JPY_ITEM_ID,
                            categoryId: 5,
                            productId: 9,
                            productName: 'Sushi Seti',
                            priceMinorAmount: 125,
                            currencyCode: 'JPY',
                            position: 0,
                            allergens: [],
                        },
                        {
                            id: KWD_ITEM_ID,
                            categoryId: 5,
                            productId: 10,
                            productName: 'Makbous',
                            priceMinorAmount: 12345,
                            currencyCode: 'KWD',
                            position: 1,
                            allergens: [],
                        },
                    ],
                },
            ],
        });

        const fetchMock = buildFetchMock({
            menu: () => jsonResponse(200, tree),
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await screen.findByText('Sushi Seti');
        await screen.findByText('Makbous');

        expect(screen.getByText('125 JPY')).toBeInTheDocument();
        expect(screen.getByText('12.345 KWD')).toBeInTheDocument();
        expect(screen.queryByText('1.25 JPY')).not.toBeInTheDocument();
        expect(screen.queryByText('123.45 KWD')).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Edit price for Sushi Seti' }));
        const jpyPriceInput = (await screen.findByRole('textbox', {
            name: 'Price — Sushi Seti',
        })) as HTMLInputElement;
        expect(jpyPriceInput.value).toBe('125');

        fireEvent.click(screen.getByRole('button', { name: 'Edit price for Makbous' }));
        const kwdPriceInput = (await screen.findByRole('textbox', {
            name: 'Price — Makbous',
        })) as HTMLInputElement;
        expect(kwdPriceInput.value).toBe('12.345');

        vi.unstubAllGlobals();
    });
});

describe('MenuCatalogWorkspace — the entry form clears only on a successful response (RED)', () => {
    it('keeps what the owner typed when the server refuses, and clears it only after a success, without a duplicate POST from the success render', async () => {
        const RETURNED_PRODUCT_ID = 77;
        let createProductCallCount = 0;

        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            const body = init?.body ? JSON.parse(String(init.body)) : {};
            const calledUrl = String(url);

            if (calledUrl === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (calledUrl === brandUrl(WORKSPACE_ID) && method === 'GET')
                return jsonResponse(200, makeBrand());
            if (calledUrl === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET')
                return jsonResponse(404, { message: 'Not Found.' });
            if (calledUrl === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'POST') {
                return jsonResponse(201, {
                    id: 42,
                    workspaceId: WORKSPACE_ID,
                    locationId: LOCATION_ID,
                    name: (body as { name: string }).name,
                    state: 'draft',
                });
            }
            if (calledUrl === categoriesUrl(WORKSPACE_ID, 42) && method === 'POST') {
                return jsonResponse(201, {
                    id: 5,
                    menuId: 42,
                    name: (body as { name: string }).name,
                    position: 0,
                });
            }
            if (calledUrl === menuEntriesUrl(WORKSPACE_ID, 5) && method === 'POST') {
                createProductCallCount += 1;

                if (createProductCallCount === 1) {
                    return jsonResponse(500, { message: 'Server Error.' });
                }

                expect((body as { productName: string }).productName).toBe('Kahve');

                return jsonResponse(201, {
                    id: 500,
                    categoryId: 5,
                    productId: RETURNED_PRODUCT_ID,
                    productName: (body as { productName: string }).productName,
                    priceMinorAmount: 2500,
                    currencyCode: 'TRY',
                    position: 0,
                    isVisible: false,
                    allergens: [],
                });
            }

            throw new Error(`Unhandled fetch in product-name-clear test: ${method} ${calledUrl}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const menuNameInput = await screen.findByLabelText(/menu name/i);
        fireEvent.change(menuNameInput, { target: { value: 'Ana Menü' } });
        fireEvent.click(screen.getByRole('button', { name: /create menu/i }));

        fireEvent.click(await screen.findByRole('button', { name: 'Add category' }));
        const categoryNameInput = await screen.findByLabelText('Category name');
        fireEvent.change(categoryNameInput, { target: { value: 'Başlangıçlar' } });
        fireEvent.click(screen.getByRole('button', { name: /^add category$/i }));

        const productNameInput = (await screen.findByLabelText('Product name')) as HTMLInputElement;
        fireEvent.change(productNameInput, { target: { value: 'Kahve' } });
        fireEvent.change(screen.getByLabelText('Price'), { target: { value: '25.00' } });
        fireEvent.click(screen.getByRole('button', { name: 'Add to menu' }));

        // Sunucu reddettiğinde yazılan HİÇBİR ŞEY kaybolmaz. Tek forma
        // geçmenin bedeli buydu: eskiden yalnız ürün adı yazılmıştı, şimdi
        // ad, fiyat ve alerjen birlikte yazılıyor — hepsini silmek
        // kullanıcıya işi baştan yaptırırdı.
        await screen.findByRole('alert');
        expect((screen.getByLabelText('Product name') as HTMLInputElement).value).toBe('Kahve');
        expect((screen.getByLabelText('Price') as HTMLInputElement).value).toBe('25.00');

        fireEvent.click(screen.getByRole('button', { name: 'Add to menu' }));

        await waitFor(() => {
            expect((screen.getByLabelText('Product name') as HTMLInputElement).value).toBe('');
        });
        expect((screen.getByLabelText('Price') as HTMLInputElement).value).toBe('');

        await waitFor(() => {
            const entryPostCalls = fetchMock.mock.calls.filter(
                (call) =>
                    String(call[0]) === menuEntriesUrl(WORKSPACE_ID, 5) &&
                    ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase() ===
                        'POST',
            );
            expect(entryPostCalls).toHaveLength(2);
        });

        void RETURNED_PRODUCT_ID;

        vi.unstubAllGlobals();
    });
});

describe('MenuCatalogWorkspace — server-returned visibility toggle for an existing menu item (RED)', () => {
    it('reflects server isVisible in a named checkbox/switch and PUTs {isVisible:true} to the real item id with CSRF-before-write, checked only after 200', async () => {
        const VISIBLE_ITEM_ID = 601;
        const HIDDEN_ITEM_ID = 602;
        const tree = makeMenuTree({
            categories: [
                {
                    id: 5,
                    menuId: 42,
                    name: 'Başlangıçlar',
                    position: 0,
                    menuItems: [
                        {
                            id: VISIBLE_ITEM_ID,
                            categoryId: 5,
                            productId: 9,
                            productName: 'Mercimek Çorbası',
                            priceMinorAmount: 4250,
                            currencyCode: 'TRY',
                            position: 0,
                            allergens: [],
                            isVisible: true,
                        },
                        {
                            id: HIDDEN_ITEM_ID,
                            categoryId: 5,
                            productId: 21,
                            productName: 'Baklava',
                            priceMinorAmount: 5000,
                            currencyCode: 'TRY',
                            position: 1,
                            allergens: [],
                            isVisible: false,
                        },
                    ],
                },
            ],
        });

        let resolvePut: (value: Response) => void = () => {};
        const pendingPut = new Promise<Response>((resolve) => {
            resolvePut = resolve;
        });

        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            const calledUrl = String(url);

            if (calledUrl === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (calledUrl === brandUrl(WORKSPACE_ID) && method === 'GET')
                return jsonResponse(200, makeBrand());
            if (calledUrl === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET')
                return jsonResponse(200, tree);
            if (calledUrl === visibilityUrl(WORKSPACE_ID, HIDDEN_ITEM_ID) && method === 'PUT') {
                const body = init?.body ? JSON.parse(String(init.body)) : {};
                expect(body).toEqual({ isVisible: true });
                return pendingPut;
            }

            throw new Error(`Unhandled fetch in visibility-toggle test: ${method} ${calledUrl}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const visibleToggle = (await screen.findByRole('checkbox', {
            name: 'Show Mercimek Çorbası',
        })) as HTMLInputElement;
        const hiddenToggle = (await screen.findByRole('checkbox', {
            name: 'Show Baklava',
        })) as HTMLInputElement;
        expect(visibleToggle.checked).toBe(true);
        expect(hiddenToggle.checked).toBe(false);

        fireEvent.click(hiddenToggle);

        await waitFor(() => {
            const putCalls = fetchMock.mock.calls.filter(
                (call) =>
                    String(call[0]) === visibilityUrl(WORKSPACE_ID, HIDDEN_ITEM_ID) &&
                    ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'PUT',
            );
            expect(putCalls).toHaveLength(1);
            assertMutationRequestInit(putCalls[0][1] as RequestInit | undefined);
        });

        const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
        const csrfIndex = calledUrls.lastIndexOf(CSRF_COOKIE_URL);
        const putIndex = fetchMock.mock.calls.findIndex(
            (call) =>
                String(call[0]) === visibilityUrl(WORKSPACE_ID, HIDDEN_ITEM_ID) &&
                ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'PUT',
        );
        expect(csrfIndex).toBeGreaterThanOrEqual(0);
        expect(putIndex).toBeGreaterThan(csrfIndex);

        expect(
            (screen.getByRole('checkbox', { name: 'Show Baklava' }) as HTMLInputElement).checked,
        ).toBe(false);

        resolvePut(jsonResponse(200, { id: HIDDEN_ITEM_ID, isVisible: true }));

        await waitFor(() => {
            expect(
                (screen.getByRole('checkbox', { name: 'Show Baklava' }) as HTMLInputElement)
                    .checked,
            ).toBe(true);
        });

        vi.unstubAllGlobals();
    });

    it('shows an accessible error and leaves the checkbox unchanged on a non-OK visibility PUT response', async () => {
        const HIDDEN_ITEM_ID = 702;
        const tree = makeMenuTree({
            categories: [
                {
                    id: 5,
                    menuId: 42,
                    name: 'Başlangıçlar',
                    position: 0,
                    menuItems: [
                        {
                            id: HIDDEN_ITEM_ID,
                            categoryId: 5,
                            productId: 21,
                            productName: 'Baklava',
                            priceMinorAmount: 5000,
                            currencyCode: 'TRY',
                            position: 0,
                            allergens: [],
                            isVisible: false,
                        },
                    ],
                },
            ],
        });

        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            const calledUrl = String(url);

            if (calledUrl === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (calledUrl === brandUrl(WORKSPACE_ID) && method === 'GET')
                return jsonResponse(200, makeBrand());
            if (calledUrl === menuUrl(WORKSPACE_ID, LOCATION_ID) && method === 'GET')
                return jsonResponse(200, tree);
            if (calledUrl === visibilityUrl(WORKSPACE_ID, HIDDEN_ITEM_ID) && method === 'PUT') {
                return jsonResponse(422, { message: 'The isVisible field is invalid.' });
            }

            throw new Error(`Unhandled fetch in visibility-error test: ${method} ${calledUrl}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = await importMenuCatalogModule<{
            MenuCatalogWorkspace: React.ComponentType<MenuCatalogWorkspaceProps>;
        }>();
        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const toggle = (await screen.findByRole('checkbox', {
            name: 'Show Baklava',
        })) as HTMLInputElement;
        expect(toggle.checked).toBe(false);

        fireEvent.click(toggle);

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();
        expect(
            (screen.getByRole('checkbox', { name: 'Show Baklava' }) as HTMLInputElement).checked,
        ).toBe(false);

        vi.unstubAllGlobals();
    });
});
