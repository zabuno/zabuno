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
});
