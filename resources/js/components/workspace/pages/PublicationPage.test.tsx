import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { PublicationPage } from './PublicationPage';
import type { DashboardMenuTree } from './DashboardPage';

/**
 * PUBLICATION_REAL_RED — S1-WP04a
 *
 * RED suite for the real synchronous Publication surface (scope synthesis:
 * Publication is deterministic, human-triggered, tenant/location/menu-
 * scoped; QR is a non-goal for this package — the prior QR destination/
 * export RED suite in this file is retired). PublicationPage today accepts
 * only dashboardMenuTree and renders a static "unavailable" stub with no
 * workspaceId/menuId props, no current-publication fetch, and no working
 * publish action — so every assertion below fails against current
 * production, not from a syntax/bootstrap defect in this suite.
 */
const FIXED_PIXEL_CLASS_PATTERN =
    /(^|[\s"'`])(w|h|min-w|max-w|min-h|max-h)-\[\d+px\]|(^|[\s"'`])(w|h)-(px|0\.5|1|2|3|4|5|6|7|8|9|10|11|12|14|16|20|24|28|32|36|40|44|48|52|56|60|64|72|80|96)(?=[\s"'`]|$)/;
const BREAKPOINT_CLASS_PATTERN = /(^|[\s"'`])(sm|md|lg|xl|2xl):/;

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

function collectClassLists(root: HTMLElement): string[] {
    const classLists: string[] = [];
    if (root.className) classLists.push(root.className);
    root.querySelectorAll<HTMLElement>('*').forEach((el) => {
        if (el.className && typeof el.className === 'string') classLists.push(el.className);
    });
    return classLists;
}

function makeMenuTree(): DashboardMenuTree {
    return {
        id: 42,
        workspaceId: 71,
        locationId: 923,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 5,
                menuId: 42,
                name: 'Starters',
                position: 0,
                menuItems: [
                    {
                        id: 101,
                        categoryId: 5,
                        productId: 901,
                        productName: 'Kahve',
                        priceMinorAmount: 4250,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: ['milk'],
                        isVisible: true,
                    },
                ],
            },
        ],
    };
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

describe('PublicationPage — real synchronous publication (PUBLICATION_REAL_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        setViewport(320, 480);
        fetchSpy = vi.fn().mockResolvedValue(jsonResponse(404, { message: 'Not found' }));
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('loads the current publication on mount with same-origin credentials using the real workspaceId and menuId', async () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const call = fetchSpy.mock.calls.find(([url]) => /publications\/current/.test(url));
        expect(call).toBeDefined();
        expect(String(call?.[0])).toMatch(/\/api\/workspaces\/71\/menu\/42\/publications\/current/);
        expect(call?.[1]?.credentials).toBe('include');
    });

    it('shows an honest not-published state on a 404 current response', async () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => {
            const region = screen.getByRole('region', { name: /publication status/i });
            expect(
                within(region).getByText(/not published|never published|no publication/i),
            ).toBeInTheDocument();
        });
    });

    it('renders the server id/version/state/publishedAt and snapshot when a current publication exists', async () => {
        fetchSpy.mockResolvedValue(
            jsonResponse(200, {
                id: 501,
                workspaceId: 71,
                menuId: 42,
                locationId: 923,
                version: 3,
                state: 'published',
                publishedAt: '2026-08-20T10:00:00Z',
                snapshot: {
                    categories: [{ name: 'Starters', menuItems: [{ productName: 'Kahve' }] }],
                },
            }),
        );

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => {
            const region = screen.getByRole('region', { name: /publication status/i });
            expect(within(region).getByText(/#501|\b501\b/)).toBeInTheDocument();
            expect(within(region).getByText(/v3|version 3/i)).toBeInTheDocument();
            expect(within(region).getByText(/published/i)).toBeInTheDocument();
        });
    });

    it('enables the review checklist checkbox only once every readiness rule is Ready, and enables Publish only after the checkbox is checked', async () => {
        const user = userEvent.setup();
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const statusRegion = screen.getByRole('region', { name: /publication status/i });
        const checkbox = within(statusRegion).getByRole('checkbox', {
            name: /reviewed the publish checklist/i,
        });
        const publishButton = within(statusRegion).getByRole('button', { name: /publish/i });

        expect(checkbox).toBeEnabled();
        expect(checkbox).not.toBeChecked();
        expect(publishButton).toBeDisabled();

        await user.click(checkbox);

        expect(checkbox).toBeChecked();
        expect(publishButton).toBeEnabled();
    });

    it('bootstraps CSRF then POSTs the publish request with credentials and the XSRF header helper, rendering the server response on success', async () => {
        document.cookie = 'XSRF-TOKEN=s1-wp04a-test-token';

        const user = userEvent.setup();
        fetchSpy.mockImplementation(async (url: string) => {
            if (/sanctum\/csrf-cookie/.test(url)) {
                return jsonResponse(204, null);
            }
            if (/publications\/current/.test(url)) {
                return jsonResponse(404, { message: 'Not found' });
            }
            if (/publications$/.test(url)) {
                return jsonResponse(201, {
                    id: 900,
                    workspaceId: 71,
                    menuId: 42,
                    locationId: 923,
                    version: 1,
                    state: 'published',
                    publishedAt: '2026-08-22T09:00:00Z',
                    snapshot: { categories: [] },
                });
            }
            return jsonResponse(404, { message: 'Not found' });
        });

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const currentCallIndexBeforePublish = fetchSpy.mock.calls.findIndex(([url]) =>
            /publications\/current/.test(url),
        );
        expect(currentCallIndexBeforePublish).toBeGreaterThanOrEqual(0);

        const statusRegion = screen.getByRole('region', { name: /publication status/i });
        const checkbox = within(statusRegion).getByRole('checkbox', {
            name: /reviewed the publish checklist/i,
        });
        await user.click(checkbox);

        const publishButton = within(statusRegion).getByRole('button', { name: /publish/i });
        expect(publishButton).toBeEnabled();
        await user.click(publishButton);

        await waitFor(() => {
            const csrfCall = fetchSpy.mock.calls.find(([url]) => /sanctum\/csrf-cookie/.test(url));
            expect(csrfCall).toBeDefined();
        });

        await waitFor(() => {
            const publishCall = fetchSpy.mock.calls.find(
                ([url, init]) => /publications$/.test(url) && init?.method === 'POST',
            );
            expect(publishCall).toBeDefined();
        });

        const csrfCallIndex = fetchSpy.mock.calls.findIndex(([url]) =>
            /sanctum\/csrf-cookie/.test(url),
        );
        const publishCallIndex = fetchSpy.mock.calls.findIndex(
            ([url, init]) => /publications$/.test(url) && init?.method === 'POST',
        );
        expect(currentCallIndexBeforePublish).toBeLessThan(csrfCallIndex);
        expect(csrfCallIndex).toBeLessThan(publishCallIndex);

        const publishCall = fetchSpy.mock.calls[publishCallIndex];
        const publishInit = publishCall[1] as RequestInit;
        const publishHeaders = new Headers(publishInit.headers);
        expect(publishCall[0]).toMatch(/publications$/);
        expect(publishInit.method).toBe('POST');
        expect(publishInit.credentials).toBe('include');
        expect(publishHeaders.get('Accept')).toBe('application/json');
        expect(publishHeaders.get('X-XSRF-TOKEN')).toBe('s1-wp04a-test-token');

        await waitFor(() => {
            expect(within(statusRegion).getByText(/#900|\b900\b/)).toBeInTheDocument();
            expect(within(statusRegion).getByText(/published/i)).toBeInTheDocument();
        });
    });

    it('preserves the prior successful publication status and exposes an accessible error when publish fails', async () => {
        const user = userEvent.setup();
        fetchSpy.mockImplementation(async (url: string) => {
            if (/sanctum\/csrf-cookie/.test(url)) {
                return jsonResponse(204, null);
            }
            if (/publications\/current/.test(url)) {
                return jsonResponse(200, {
                    id: 800,
                    workspaceId: 71,
                    menuId: 42,
                    locationId: 923,
                    version: 2,
                    state: 'published',
                    publishedAt: '2026-08-21T09:00:00Z',
                    snapshot: { categories: [] },
                });
            }
            if (/qr-codes$/.test(url)) {
                return jsonResponse(200, []);
            }
            if (/publications$/.test(url)) {
                return jsonResponse(500, { message: 'Internal error' });
            }
            return jsonResponse(404, { message: 'Not found' });
        });

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        const statusRegion = screen.getByRole('region', { name: /publication status/i });
        await waitFor(() =>
            expect(within(statusRegion).getByText(/#800|\b800\b/)).toBeInTheDocument(),
        );

        const checkbox = within(statusRegion).getByRole('checkbox', {
            name: /reviewed the publish checklist/i,
        });
        await user.click(checkbox);
        await user.click(within(statusRegion).getByRole('button', { name: /publish/i }));

        await waitFor(() => {
            expect(within(statusRegion).getByRole('alert')).toBeInTheDocument();
        });

        expect(within(statusRegion).getByText(/#800|\b800\b/)).toBeInTheDocument();
        expect(within(statusRegion).getByText(/v2|version 2/i)).toBeInTheDocument();
    });

    it('shows the real active QR PNG preview/download and keeps every other QR/export control disabled with no scheduled option selectable', async () => {
        const activeQrItem = {
            id: 900,
            workspaceId: 71,
            locationId: 923,
            menuId: 42,
            token: 'a'.repeat(43),
            resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
            destinationType: 'published_menu',
            state: 'active',
        };

        fetchSpy.mockImplementation(async (url: string) => {
            if (/publications\/current/.test(url)) {
                return jsonResponse(200, {
                    id: 501,
                    workspaceId: 71,
                    menuId: 42,
                    locationId: 923,
                    version: 3,
                    state: 'published',
                    publishedAt: '2026-08-20T10:00:00Z',
                    snapshot: { categories: [] },
                });
            }
            if (/qr-codes$/.test(url)) {
                return jsonResponse(200, [activeQrItem]);
            }
            return jsonResponse(404, { message: 'Not found' });
        });

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const qrDestinationRegion = await waitFor(() => {
            const region = screen.getByRole('region', { name: /qr destination/i });
            expect(within(region).getByText(activeQrItem.token, { exact: false })).toBeTruthy();
            return region;
        });
        const qrExportRegion = screen.getByRole('region', { name: /qr print export/i });
        expect(qrDestinationRegion).toBeInTheDocument();
        expect(qrExportRegion).toBeInTheDocument();

        expect(
            screen.getByText(/scheduled publish is not available in stage 1/i),
        ).toBeInTheDocument();

        const publishModeSelect = screen.getByLabelText(/publish mode/i);
        const optionLabels = within(publishModeSelect as HTMLElement)
            .getAllByRole('option')
            .map((option) => option.textContent ?? '');
        expect(optionLabels.some((label) => /scheduled/i.test(label))).toBe(false);

        const expectedExportUrl = `/api/workspaces/${activeQrItem.workspaceId}/qr-codes/${activeQrItem.id}/export.png`;

        const img = within(qrExportRegion).getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute('src', expectedExportUrl);

        const downloadLink = within(qrExportRegion).getByRole('link', { name: /download png/i });
        expect(downloadLink).toHaveAttribute('href', `${expectedExportUrl}?download=1`);

        const outputFormatSelect = within(qrExportRegion).getByLabelText(/output format/i);
        expect(outputFormatSelect).toBeEnabled();
        expect((outputFormatSelect as HTMLSelectElement).value).toBe('png');
        const formatOptions = within(outputFormatSelect as HTMLElement).getAllByRole('option');
        const svgOption = formatOptions.find((option) => /svg/i.test(option.textContent ?? ''));
        const pdfOption = formatOptions.find((option) => /pdf/i.test(option.textContent ?? ''));
        expect(svgOption).not.toBeDisabled();
        expect(pdfOption).not.toBeDisabled();

        const destinationTypeSelect = within(qrExportRegion).getByLabelText(/destination type/i);
        const paperSizeSelect = within(qrExportRegion).getByLabelText(/paper/i);
        const orientationSelect = within(qrExportRegion).getByLabelText(/orientation/i);
        const bulkInput = within(qrExportRegion).getByLabelText(/bulk range or count/i);
        const genericExportButton = within(qrExportRegion).getByRole('button', {
            name: /^export$/i,
        });
        const themeButtons = within(qrExportRegion).getAllByRole('button', { name: /theme$/i });

        expect(destinationTypeSelect).toBeDisabled();
        expect(paperSizeSelect).toBeDisabled();
        expect(orientationSelect).toBeDisabled();
        expect(bulkInput).toBeDisabled();
        expect(genericExportButton).toBeDisabled();
        expect(themeButtons).toHaveLength(6);
        themeButtons.forEach((button) => expect(button).toBeEnabled());
        const pressedThemeButtons = themeButtons.filter(
            (button) => button.getAttribute('aria-pressed') === 'true',
        );
        expect(pressedThemeButtons).toHaveLength(1);
        expect(pressedThemeButtons[0]).toHaveAccessibleName(/^classic theme$/i);

        expect(fetchSpy.mock.calls.some(([, init]) => init?.method === 'POST')).toBe(false);
        const bodyText = document.body.textContent ?? '';
        expect(bodyText).not.toMatch(/\btoken\b/i);
    });

    it('carries no fixed-pixel or breakpoint class on the batch-owned Publication surface (excluding the shared AI panel)', async () => {
        const { container } = render(
            <PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />,
        );

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const root = container.querySelector('#publication') as HTMLElement | null;
        expect(root).not.toBeNull();

        const aiPanel = (root as HTMLElement).querySelector('section[aria-label*="Publication"]');
        if (aiPanel) aiPanel.remove();

        const classLists = collectClassLists(root as HTMLElement);
        const offenders = classLists.filter(
            (classList) =>
                FIXED_PIXEL_CLASS_PATTERN.test(classList) ||
                BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
    });
});

/**
 * PUBLICATION_DRAFT_PREVIEW_RED
 *
 * RED suite for a Draft menu preview on PublicationPage: given the current
 * already-loaded server-returned DashboardMenuTree (the same tree Dashboard
 * and Menu already render from), Publication must show an accessible,
 * explicitly non-public preview of it — menu name, categories, product
 * names, price/currency, allergens and visible/hidden state derived only
 * from that fixture, with an honest not-loaded/empty status when the tree
 * is null.
 */
function makeDraftMenuTree(): DashboardMenuTree {
    return {
        id: 42,
        workspaceId: 71,
        locationId: 923,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 5,
                menuId: 42,
                name: 'Starters',
                position: 0,
                menuItems: [
                    {
                        id: 101,
                        categoryId: 5,
                        productId: 901,
                        productName: 'Kahve',
                        priceMinorAmount: 4250,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: ['milk'],
                        isVisible: true,
                    },
                    {
                        id: 102,
                        categoryId: 5,
                        productId: 902,
                        productName: 'Simit',
                        priceMinorAmount: 1500,
                        currencyCode: 'TRY',
                        position: 1,
                        allergens: ['gluten'],
                        isVisible: false,
                    },
                ],
            },
        ],
    };
}

describe('PublicationPage — Draft menu preview from the current loaded tree (PUBLICATION_DRAFT_PREVIEW_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn().mockResolvedValue(jsonResponse(404, { message: 'Not found' }));
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('exposes an accessible, explicitly non-public Draft menu preview region', () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeDraftMenuTree()} />);

        const region = screen.getByRole('region', { name: /draft.*preview|preview.*draft/i });
        expect(region.textContent ?? '').toMatch(/not public|non-public|draft only/i);
    });

    it('shows the fixture menu name, category, product names, price/currency and allergens', () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeDraftMenuTree()} />);

        const region = screen.getByRole('region', { name: /draft.*preview|preview.*draft/i });
        const regionText = region.textContent ?? '';

        expect(regionText).toMatch(/Ana Menü/);
        expect(regionText).toMatch(/Starters/);
        expect(regionText).toMatch(/Kahve/);
        expect(regionText).toMatch(/Simit/);
        expect(regionText).toMatch(/42[.,]50|4250/);
        expect(regionText).toMatch(/TRY/);
        expect(regionText).toMatch(/milk/i);
        expect(regionText).toMatch(/gluten/i);
    });

    it('derives visible/hidden state per item only from the fixture, not invented data', () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeDraftMenuTree()} />);

        const region = screen.getByRole('region', { name: /draft.*preview|preview.*draft/i });
        const kahveRow = within(region).getByText('Kahve').closest('li, tr, div') as HTMLElement;
        const simitRow = within(region).getByText('Simit').closest('li, tr, div') as HTMLElement;

        expect(kahveRow.textContent ?? '').toMatch(/visible/i);
        expect(kahveRow.textContent ?? '').not.toMatch(/hidden/i);
        expect(simitRow.textContent ?? '').toMatch(/hidden/i);
    });

    it('shows an honest not-loaded/empty status when the tree is null, with no fixture content', () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={null} />);

        const region = screen.getByRole('region', { name: /draft.*preview|preview.*draft/i });
        expect(within(region).getByText(/not loaded|no menu|empty/i)).toBeInTheDocument();
        expect(screen.queryByText('Ana Menü')).toBeNull();
        expect(screen.queryByText('Kahve')).toBeNull();
    });

    it('renders no fake QR URL/token/image from the draft preview', () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeDraftMenuTree()} />);

        const region = screen.getByRole('region', { name: /draft.*preview|preview.*draft/i });
        const regionText = region.textContent ?? '';

        expect(regionText).not.toMatch(/https?:\/\//i);
        expect(regionText).not.toMatch(/\btoken\b/i);
        expect(within(region).queryByRole('img')).toBeNull();
        expect(within(region).queryAllByRole('link', { name: /download/i })).toHaveLength(0);
    });

    it('introduces no fixed-width or breakpoint/responsive classes on the draft preview region', () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeDraftMenuTree()} />);

        const region = screen.getByRole('region', { name: /draft.*preview|preview.*draft/i });
        const classLists = collectClassLists(region);
        const offenders = classLists.filter(
            (classList) =>
                FIXED_PIXEL_CLASS_PATTERN.test(classList) ||
                BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
    });
});

/**
 * PUBLICATION_READINESS_RED
 *
 * RED suite for a deterministic Publish readiness checklist derived only
 * from the already-loaded dashboardMenuTree: an accessible checklist region
 * with one Ready/Needs attention item per rule, an honest not-loaded status
 * when the tree is null.
 */
function makeReadinessMenuTree(): DashboardMenuTree {
    return {
        id: 77,
        workspaceId: 71,
        locationId: 923,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 5,
                menuId: 77,
                name: 'Starters',
                position: 0,
                menuItems: [
                    {
                        id: 101,
                        categoryId: 5,
                        productId: 901,
                        productName: 'Kahve',
                        priceMinorAmount: 4250,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: ['milk'],
                        isVisible: true,
                    },
                    {
                        id: 102,
                        categoryId: 5,
                        productId: 902,
                        productName: '',
                        priceMinorAmount: 1500,
                        currencyCode: 'TRY',
                        position: 1,
                        allergens: ['gluten'],
                        isVisible: true,
                    },
                ],
            },
            {
                id: 6,
                menuId: 77,
                name: 'Desserts',
                position: 1,
                menuItems: [
                    {
                        id: 103,
                        categoryId: 6,
                        productId: 903,
                        productName: 'Baklava',
                        priceMinorAmount: 0,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: [],
                        isVisible: true,
                    },
                    {
                        id: 104,
                        categoryId: 6,
                        productId: 904,
                        productName: 'Sütlaç',
                        priceMinorAmount: 3000,
                        currencyCode: '',
                        position: 1,
                        allergens: [],
                        isVisible: true,
                    },
                    {
                        id: 105,
                        categoryId: 6,
                        productId: 905,
                        productName: 'Künefe',
                        priceMinorAmount: 3200,
                        currencyCode: 'TRY',
                        position: 2,
                        allergens: [],
                        isVisible: false,
                    },
                ],
            },
        ],
    };
}

describe('PublicationPage — deterministic Publish readiness checklist (PUBLICATION_READINESS_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn().mockResolvedValue(jsonResponse(404, { message: 'Not found' }));
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('exposes an accessible Publish readiness checklist region', () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeReadinessMenuTree()} />);

        expect(
            screen.getByRole('region', { name: /publish readiness checklist/i }),
        ).toBeInTheDocument();
    });

    it('lists exactly five labeled checklist items derived from the tree', () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeReadinessMenuTree()} />);

        const region = screen.getByRole('region', { name: /publish readiness checklist/i });
        const items = within(region).getAllByRole('listitem');

        expect(items).toHaveLength(5);

        const labels = items.map((item) => item.textContent ?? '');
        expect(labels.some((text) => /category/i.test(text))).toBe(true);
        expect(labels.some((text) => /visible item/i.test(text))).toBe(true);
        expect(labels.some((text) => /product name/i.test(text))).toBe(true);
        expect(labels.some((text) => /price/i.test(text) && /currency/i.test(text))).toBe(true);
        expect(labels.some((text) => /category name/i.test(text))).toBe(true);
    });

    it('marks each checklist item Ready or Needs attention using only the crafted mixed tree', () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeReadinessMenuTree()} />);

        const region = screen.getByRole('region', { name: /publish readiness checklist/i });
        const items = within(region).getAllByRole('listitem');

        items.forEach((item) => {
            expect(item.textContent ?? '').toMatch(/Ready|Needs attention/);
        });

        const productNameItem = items.find((item) => /product name/i.test(item.textContent ?? ''));
        const priceCurrencyItem = items.find(
            (item) =>
                /price/i.test(item.textContent ?? '') && /currency/i.test(item.textContent ?? ''),
        );
        const visibleItemItem = items.find((item) => /visible item/i.test(item.textContent ?? ''));
        const categoryItem = items.find(
            (item) =>
                /category/i.test(item.textContent ?? '') &&
                !/category name/i.test(item.textContent ?? ''),
        );
        const categoryNameItem = items.find((item) =>
            /category name/i.test(item.textContent ?? ''),
        );

        expect(productNameItem?.textContent ?? '').toMatch(/Needs attention/);
        expect(priceCurrencyItem?.textContent ?? '').toMatch(/Needs attention/);
        expect(visibleItemItem?.textContent ?? '').toMatch(/Ready/);
        expect(categoryItem?.textContent ?? '').toMatch(/Ready/);
        expect(categoryNameItem?.textContent ?? '').toMatch(/Ready/);
    });

    it('shows an honest not-loaded status and zero checklist items when dashboardMenuTree is null', () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={null} />);

        const region = screen.getByRole('region', { name: /publish readiness checklist/i });
        expect(within(region).getByText(/not loaded/i)).toBeInTheDocument();
        expect(within(region).queryAllByRole('listitem')).toHaveLength(0);
    });

    it('keeps the review checkbox disabled and the Publish button disabled while readiness is not fully Ready', () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeReadinessMenuTree()} />);

        const statusRegion = screen.getByRole('region', { name: /publication status/i });
        const checkbox = within(statusRegion).getByRole('checkbox', {
            name: /reviewed the publish checklist/i,
        });
        const publishButton = within(statusRegion).getByRole('button', { name: /publish/i });

        expect(checkbox).toBeDisabled();
        expect(publishButton).toBeDisabled();
    });

    it('introduces no fake IDs, URLs, tokens, versions, numeric score/artifact or fixed-pixel/breakpoint classes', () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeReadinessMenuTree()} />);

        const region = screen.getByRole('region', { name: /publish readiness checklist/i });
        const regionText = region.textContent ?? '';

        expect(regionText).not.toMatch(/https?:\/\//i);
        expect(regionText).not.toMatch(/\btoken\b/i);
        expect(regionText).not.toMatch(/\bv\d+(\.\d+)*\b/);
        expect(regionText).not.toMatch(/#\d+/);
        expect(regionText).not.toMatch(/\bscore\b/i);

        const classLists = collectClassLists(region);
        const offenders = classLists.filter(
            (classList) =>
                FIXED_PIXEL_CLASS_PATTERN.test(classList) ||
                BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
    });
});

/**
 * ZABUNO_PUBLICATION_REVIEW_RED
 *
 * RED suite for the independent-review P1 and delivery-contract gaps found
 * against the now-live Publication API: PublishActionConfigRegion still
 * renders a second, always-disabled checklist checkbox alongside the real
 * one in PublicationStatusRegion (so the page has two checklist checkboxes,
 * not one), and both PublishActionConfigRegion's copy and
 * QrDestinationRegion's fields-unavailable copy still say "until the
 * Publication API exists" even though Publication is live — QR Destination
 * is unavailable for its own reason (no QR capability/API), not because
 * Publication is missing. There is also no distinct, accessible "Published
 * snapshot" region rendering the immutable server snapshot on a successful
 * current-publication fetch, and a non-404 GET failure/exception is not
 * distinguished from a true 404 (both currently fall through to the same
 * "not published" status), so every assertion below fails against current
 * production, not from a syntax/bootstrap defect in this suite.
 */
describe('PublicationPage — reviewer-correction gaps (ZABUNO_PUBLICATION_REVIEW_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn().mockResolvedValue(jsonResponse(404, { message: 'Not found' }));
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('renders exactly one checklist-confirmation checkbox and exactly one Publish button on the page', async () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const checkboxes = screen.getAllByRole('checkbox', {
            name: /reviewed the publish checklist/i,
        });
        const publishButtons = screen.getAllByRole('button', { name: /^publish$/i });

        expect(checkboxes).toHaveLength(1);
        expect(publishButtons).toHaveLength(1);
    });

    it('keeps PublishActionConfigRegion visible as honest immediate-mode information with no duplicate checkbox or publish action, and no claim that the Publication API is absent', async () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const publishActionRegion = screen.getByRole('region', { name: /^publish action$/i });

        expect(within(publishActionRegion).queryAllByRole('checkbox')).toHaveLength(0);
        expect(within(publishActionRegion).queryAllByRole('button')).toHaveLength(0);
        expect(publishActionRegion.textContent ?? '').not.toMatch(
            /publication api (is|does not exist|has not|not) (absent|not exist|available)/i,
        );
        expect(publishActionRegion.textContent ?? '').not.toMatch(/until the publication api/i);
    });

    it('says the QR Destination capability/API is unavailable, not that the Publication API is absent', async () => {
        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const qrDestinationRegion = screen.getByRole('region', { name: /qr destination/i });
        const regionText = qrDestinationRegion.textContent ?? '';

        expect(regionText).toMatch(
            /qr destination (capability|api) (is|are)? ?not available|qr destination (capability|api) is unavailable/i,
        );
        expect(regionText).not.toMatch(/until the publication api/i);
        expect(regionText).not.toMatch(/publication api (is absent|does not exist|has not)/i);
    });

    it('renders an accessible Published snapshot region with the publishedAt timestamp and the immutable server category/product name on a successful current-publication fetch', async () => {
        fetchSpy.mockResolvedValue(
            jsonResponse(200, {
                id: 501,
                workspaceId: 71,
                menuId: 42,
                locationId: 923,
                version: 3,
                state: 'published',
                publishedAt: '2026-08-20T10:00:00Z',
                snapshot: {
                    categories: [
                        { name: 'Sunulan Kategori', menuItems: [{ productName: 'Sunulan Ürün' }] },
                    ],
                },
            }),
        );

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => {
            const snapshotRegion = screen.getByRole('region', { name: /published snapshot/i });
            expect(within(snapshotRegion).getByText(/2026-08-20/)).toBeInTheDocument();
            expect(within(snapshotRegion).getByText('Sunulan Kategori')).toBeInTheDocument();
            expect(within(snapshotRegion).getByText('Sunulan Ürün')).toBeInTheDocument();
        });

        const draftPreviewRegion = screen.getByRole('region', {
            name: /draft.*preview|preview.*draft/i,
        });
        expect(within(draftPreviewRegion).queryByText('Sunulan Kategori')).toBeNull();
        expect(within(draftPreviewRegion).queryByText('Sunulan Ürün')).toBeNull();
    });

    it('shows an accessible load error (not Never/Not published) on a non-404 current-publication GET failure', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (/qr-codes$/.test(url)) {
                return jsonResponse(200, []);
            }
            return jsonResponse(500, { message: 'Internal error' });
        });

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        const statusRegion = screen.getByRole('region', { name: /publication status/i });
        await waitFor(() => {
            expect(within(statusRegion).getByRole('alert')).toBeInTheDocument();
        });

        expect(
            within(statusRegion).queryByText(/not published|never published|no publication/i),
        ).toBeNull();
    });

    it('shows an accessible load error (not Never/Not published) when the current-publication fetch throws', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (/qr-codes$/.test(url)) {
                return jsonResponse(200, []);
            }
            throw new TypeError('network down');
        });

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        const statusRegion = screen.getByRole('region', { name: /publication status/i });
        await waitFor(() => {
            expect(within(statusRegion).getByRole('alert')).toBeInTheDocument();
        });

        expect(
            within(statusRegion).queryByText(/not published|never published|no publication/i),
        ).toBeNull();
    });

    it('still shows the honest Not published status on a true 404 current-publication response', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(404, { message: 'Not found' }));

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => {
            const statusRegion = screen.getByRole('region', { name: /publication status/i });
            expect(
                within(statusRegion).getByText(/not published|never published|no publication/i),
            ).toBeInTheDocument();
        });

        expect(screen.queryByRole('alert')).toBeNull();
    });
});

/**
 * QR_DESTINATION_PAGE_WIRING_RED — S1-WP04b1
 *
 * PublicationPage must thread the real workspaceId/locationId/menuId and
 * whether a current publication exists into QrDestinationRegion, so the
 * region can fetch/create real QR codes instead of the current
 * no-props/no-fetch static stub. These assertions fail against current
 * production, not from a syntax/bootstrap defect in this suite.
 */
describe('PublicationPage — QR Destination real wiring (QR_DESTINATION_PAGE_WIRING_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn().mockResolvedValue(jsonResponse(404, { message: 'Not found' }));
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('fetches the QR code list for the real workspace/location once a current publication exists', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (/publications\/current/.test(url)) {
                return jsonResponse(200, {
                    id: 501,
                    workspaceId: 71,
                    menuId: 42,
                    locationId: 923,
                    version: 1,
                    state: 'published',
                    publishedAt: '2026-08-20T10:00:00Z',
                    snapshot: { categories: [] },
                });
            }
            if (/qr-codes$/.test(url)) {
                return jsonResponse(200, []);
            }
            return jsonResponse(404, { message: 'Not found' });
        });

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => {
            const call = fetchSpy.mock.calls.find(([url]) => /qr-codes$/.test(url));
            expect(call).toBeDefined();
            expect(String(call?.[0])).toMatch(
                /\/api\/workspaces\/71\/brand\/locations\/923\/qr-codes$/,
            );
        });
    });

    it('never calls the QR create endpoint on mount before any user action, even once a current publication exists', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (/publications\/current/.test(url)) {
                return jsonResponse(200, {
                    id: 501,
                    workspaceId: 71,
                    menuId: 42,
                    locationId: 923,
                    version: 1,
                    state: 'published',
                    publishedAt: '2026-08-20T10:00:00Z',
                    snapshot: { categories: [] },
                });
            }
            if (/qr-codes$/.test(url)) {
                return jsonResponse(200, []);
            }
            return jsonResponse(404, { message: 'Not found' });
        });

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const createCall = fetchSpy.mock.calls.find(
            ([url, init]) => /qr-codes$/.test(url) && init?.method === 'POST',
        );
        expect(createCall).toBeUndefined();
    });
});

/**
 * PUBLICATION_LOAD_STATE_RED — S1-WP04c
 *
 * RED suite for a truthful current-publication load state: while the GET is
 * pending, an accessible loading status must be shown and must not claim
 * "Not published yet."; today PublicationStatusRegion has no pending/loading
 * branch, so the initial current===null/loadError===false render already
 * shows "Not published yet." before the fetch resolves. Likewise a non-404
 * failure (rejected status or thrown network error) shows an accessible
 * error but has no Retry control to recover, so these assertions fail
 * against current production, not from a syntax/bootstrap defect.
 */
function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((res) => {
        resolve = res;
    });
    return { promise, resolve };
}

describe('PublicationPage — current-publication load state (PUBLICATION_LOAD_STATE_RED)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('shows an accessible loading status while the GET is pending and never claims Not published yet.', async () => {
        const gate = deferred<Response>();
        const fetchSpy = vi.fn().mockReturnValue(gate.promise);
        vi.stubGlobal('fetch', fetchSpy);

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const statusRegion = screen.getByRole('region', { name: /publication status/i });
        expect(within(statusRegion).getByRole('status').textContent ?? '').toMatch(
            /loading|checking|fetching/i,
        );
        expect(
            within(statusRegion).queryByText(/not published|never published|no publication/i),
        ).toBeNull();

        gate.resolve(jsonResponse(404, { message: 'Not found' }));
    });

    it('resolves an HTTP 404 to Not published yet. and removes the loading status', async () => {
        const fetchSpy = vi.fn().mockResolvedValue(jsonResponse(404, { message: 'Not found' }));
        vi.stubGlobal('fetch', fetchSpy);

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        const statusRegion = screen.getByRole('region', { name: /publication status/i });
        await waitFor(() => {
            expect(
                within(statusRegion).getByText(/not published|never published|no publication/i),
            ).toBeInTheDocument();
        });

        expect(
            within(statusRegion).queryByText(/loading|checking|fetching/i),
        ).toBeNull();
    });

    it('shows an accessible load error plus Retry on a non-2xx failure and never claims Not published yet.', async () => {
        const fetchSpy = vi.fn().mockResolvedValue(jsonResponse(500, { message: 'Internal error' }));
        vi.stubGlobal('fetch', fetchSpy);

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        const statusRegion = screen.getByRole('region', { name: /publication status/i });
        await waitFor(() => {
            expect(within(statusRegion).getByRole('alert')).toBeInTheDocument();
        });

        expect(
            within(statusRegion).queryByText(/not published|never published|no publication/i),
        ).toBeNull();
        expect(
            within(statusRegion).getByRole('button', { name: /retry/i }),
        ).toBeInTheDocument();
    });

    it('shows the same retryable error on a thrown network failure, and clicking Retry can resolve to a real current publication summary', async () => {
        const user = userEvent.setup();
        const fetchSpy = vi.fn().mockRejectedValueOnce(new TypeError('network down'));
        vi.stubGlobal('fetch', fetchSpy);

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        const statusRegion = screen.getByRole('region', { name: /publication status/i });
        await waitFor(() => {
            expect(within(statusRegion).getByRole('alert')).toBeInTheDocument();
        });
        expect(
            within(statusRegion).queryByText(/not published|never published|no publication/i),
        ).toBeNull();

        fetchSpy.mockResolvedValueOnce(
            jsonResponse(200, {
                id: 611,
                workspaceId: 71,
                menuId: 42,
                locationId: 923,
                version: 5,
                state: 'published',
                publishedAt: '2026-08-24T08:00:00Z',
                snapshot: { categories: [] },
            }),
        );

        await user.click(within(statusRegion).getByRole('button', { name: /retry/i }));

        await waitFor(() => {
            expect(within(statusRegion).getByText(/#611|\b611\b/)).toBeInTheDocument();
            expect(within(statusRegion).getByText(/v5|version 5/i)).toBeInTheDocument();
        });
        expect(within(statusRegion).queryByRole('alert')).toBeNull();
    });
});
