import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, within, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * FrontendBlitz.integration.test.tsx (RED)
 *
 * Freezes three currently-visible gaps in the S1-WP01A frontend batch:
 *
 * 1) BrandEditForm on Edit does not expose an accessible Slug field
 *    prefilled from the server brand (read-only, since UpdateBrand does not
 *    accept slug — submitting must never send a slug mutation field).
 * 2) MenuCatalogWorkspace does not render category/menu-item ordering as
 *    accessible read-only fields derived from the server position values
 *    (human-friendly position + 1; no reorder mutation/API).
 * 3) Media and Publication page shells retain their existing visible
 *    disabled/unavailable status controls at a 320x480 viewport (smoke).
 *
 * Real component composition, realistic API-shaped fetch mocks. No
 * Storybook, no backend, no docs, no invented ids/data.
 */

import { BrandEditForm, type BrandProfile } from './BrandEditForm';
import { MenuCatalogWorkspace } from '../catalog/menu/macro/MenuCatalogWorkspace';
import { MediaPage } from './pages/MediaPage';
import { PublicationPage } from './pages/PublicationPage';

const WORKSPACE_ID = 71;
const LOCATION_ID = 923;

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

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: width,
    });
    Object.defineProperty(window, 'innerHeight', {
        writable: true,
        configurable: true,
        value: height,
    });
    window.dispatchEvent(new Event('resize'));
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

function makeBrand(): BrandProfile {
    return {
        id: 811,
        workspace_id: WORKSPACE_ID,
        name: 'Zeytin',
        slug: 'zeytin',
        locale: 'tr',
        timezone: 'Europe/Istanbul',
        currency: 'TRY',
        description: null,
        contact_email: null,
        contact_phone: null,
    };
}

describe('BrandEditForm — visible Slug field (FrontendBlitz RED)', () => {
    it('exposes a read-only Slug field prefilled from the server brand, and never sends a slug mutation field', async () => {
        const user = userEvent.setup();
        const brand = makeBrand();
        const onSaved = vi.fn();

        const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
            const url = String(input);
            if (url === '/sanctum/csrf-cookie') {
                return jsonResponse(204, {});
            }
            if (
                url === `/api/workspaces/${WORKSPACE_ID}/brand` &&
                (init?.method ?? 'GET').toUpperCase() === 'PUT'
            ) {
                return jsonResponse(200, { ...brand, name: 'Zeytin Renamed' });
            }
            if (url.startsWith('/api/reference/markets')) {
                // Form seçeneklerini sunucudan alır; liste gelmezse mevcut
                // değerlerle çalışmaya devam eder.
                return jsonResponse(200, {
                    markets: [{ code: 'TR', name: 'Türkiye' }],
                    currencies: [{ code: 'TRY', name: 'Turkish lira', symbol: '₺' }],
                    locales: [{ code: 'en', name: 'English' }],
                    timezones: [{ id: 'Europe/Istanbul', label: 'İstanbul — UTC+03:00' }],
                    defaults: { timezone: 'Europe/Istanbul', currency: 'TRY' },
                    suggestedCountry: 'TR',
                });
            }
            throw new Error(`unexpected fetch: ${url}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        render(<BrandEditForm workspaceId={WORKSPACE_ID} brand={brand} onSaved={onSaved} />);

        await user.click(screen.getByRole('button', { name: /edit/i }));

        /*
            Etiket artık "Slug" değil. Kullanıcının gördüğü şey
            `olga-restaurant-6x4f08` ve etiketi "Slug"tı — ne olduğu, neyi
            etkilediği ve neden değiştirilemediği hiçbir yerde yazmıyordu.
            Alan hâlâ salt-okunur; değişen, ne olduğunun SÖYLENMESİ.
        */
        const slugField = screen.getByLabelText('Menu web address');
        expect(slugField).toHaveValue(brand.slug);
        expect(slugField).toBeDisabled();

        expect(screen.getByLabelText(/name/i)).toHaveValue(brand.name);
        expect(screen.getByLabelText(/menu language/i)).toHaveValue(brand.locale);
        expect(screen.getByLabelText(/time zone/i)).toHaveValue(brand.timezone);
        expect(screen.getByLabelText(/currency/i)).toHaveValue(brand.currency);

        await user.click(screen.getByRole('button', { name: /save/i }));

        await waitFor(() => expect(onSaved).toHaveBeenCalled());

        const putCall = fetchMock.mock.calls.find(
            ([reqUrl, reqInit]) =>
                String(reqUrl) === `/api/workspaces/${WORKSPACE_ID}/brand` &&
                ((reqInit as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'PUT',
        );
        expect(putCall).toBeDefined();
        const sentBody = JSON.parse(String((putCall?.[1] as RequestInit).body)) as Record<
            string,
            unknown
        >;
        expect(sentBody).not.toHaveProperty('slug');
    });
});

describe('MenuCatalogWorkspace — visible category/item ordering (FrontendBlitz RED)', () => {
    it('renders human-friendly (position + 1) order values for categories and menu items from the server fixture', async () => {
        const brand = {
            id: 811,
            workspaceId: WORKSPACE_ID,
            name: 'Zeytin',
            slug: 'zeytin',
            locale: 'tr',
            timezone: 'Europe/Istanbul',
            currency: 'TRY',
            description: null,
            contactEmail: null,
            contactPhone: null,
        };

        const tree = {
            id: 501,
            workspaceId: WORKSPACE_ID,
            locationId: LOCATION_ID,
            name: 'Ana Menü',
            state: 'draft',
            categories: [
                {
                    id: 1,
                    menuId: 501,
                    name: 'Kahve',
                    position: 0,
                    menuItems: [
                        {
                            id: 11,
                            categoryId: 1,
                            productId: 101,
                            productName: 'Espresso',
                            priceMinorAmount: 5000,
                            currencyCode: 'TRY',
                            position: 0,
                            allergens: ['milk'],
                            isVisible: true,
                        },
                        {
                            id: 12,
                            categoryId: 1,
                            productId: 102,
                            productName: 'Latte',
                            priceMinorAmount: 6500,
                            currencyCode: 'TRY',
                            position: 1,
                            allergens: [],
                            isVisible: true,
                        },
                    ],
                },
                {
                    id: 2,
                    menuId: 501,
                    name: 'Tatlı',
                    position: 1,
                    menuItems: [],
                },
            ],
        };

        const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
            void init;
            const url = String(input);
            if (url === `/api/workspaces/${WORKSPACE_ID}/brand`) {
                return jsonResponse(200, brand);
            }
            if (url === `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/menu`) {
                return jsonResponse(200, tree);
            }
            if (url.startsWith('/api/reference/markets')) {
                // Form seçeneklerini sunucudan alır; liste gelmezse mevcut
                // değerlerle çalışmaya devam eder.
                return jsonResponse(200, {
                    markets: [{ code: 'TR', name: 'Türkiye' }],
                    currencies: [{ code: 'TRY', name: 'Turkish lira', symbol: '₺' }],
                    locales: [{ code: 'en', name: 'English' }],
                    timezones: [{ id: 'Europe/Istanbul', label: 'İstanbul — UTC+03:00' }],
                    defaults: { timezone: 'Europe/Istanbul', currency: 'TRY' },
                    suggestedCountry: 'TR',
                });
            }
            throw new Error(`unexpected fetch: ${url}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await screen.findByText('Espresso');

        const kahveOrderField = screen.getByLabelText(/order.*Kahve|Kahve.*order/i);
        expect(kahveOrderField).toHaveTextContent('1');

        const tatliOrderField = screen.getByLabelText(/order.*Tatlı|Tatlı.*order/i);
        expect(tatliOrderField).toHaveTextContent('2');

        const espressoOrderField = screen.getByLabelText(/order.*Espresso|Espresso.*order/i);
        expect(espressoOrderField).toHaveTextContent('1');

        const latteOrderField = screen.getByLabelText(/order.*Latte|Latte.*order/i);
        expect(latteOrderField).toHaveTextContent('2');

        expect(screen.getByText('Espresso')).toBeInTheDocument();
        expect(screen.getByText('Latte')).toBeInTheDocument();
        expect(screen.getByText(/50\.00/)).toBeInTheDocument();
        expect(screen.getAllByText(/TRY/).length).toBeGreaterThan(0);
        expect(screen.getByText('milk')).toBeInTheDocument();
        /*
            GÜNCELLENDİ (FF-102): görünürlük etiketsiz bir kutu değil, satırın
            taşma menüsünde ADIYLA duran bir madde. Görünen bir ürünün maddesi
            "menüden gizle" der — durum kutunun işaretinden değil, cümleden
            okunur.
        */
        fireEvent.click(screen.getByRole('button', { name: 'More actions for Espresso' }));
        expect(
            await screen.findByRole('menuitem', { name: 'Hide from the menu' }),
        ).toBeInTheDocument();

        const mutationCalls = fetchMock.mock.calls.filter((call) => String(call[0]).length > 0);
        mutationCalls.forEach((call) => {
            const method = ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase();
            expect(method).toBe('GET');
        });
    });
});

describe('Media and Publication shells — visible unavailable status at 320x480 (smoke)', () => {
    afterEach(() => {
        setViewport(1024, 768);
    });

    it('Media page keeps its visible not-available status region at a 320x480 viewport', () => {
        setViewport(320, 480);

        render(<MediaPage />);

        const root = document.querySelector('#section-media') as HTMLElement;
        expect(within(root).getAllByRole('status').length).toBeGreaterThan(0);
    });

    it('Publication page keeps its visible not-available status region at a 320x480 viewport', () => {
        setViewport(320, 480);

        render(<PublicationPage />);

        const root = document.querySelector('#section-publication') as HTMLElement;
        expect(within(root).getAllByRole('status').length).toBeGreaterThan(0);
    });
});
