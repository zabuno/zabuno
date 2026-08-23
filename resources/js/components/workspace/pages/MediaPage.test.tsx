import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';

import { MediaPage } from './MediaPage';

/**
 * MEDIA_FRONTEND_RED
 *
 * RED suite for the S1-WP01A Media surface. Correction: MediaPage is
 * frozen to the real intake API contract — GET on mount, multipart POST
 * to upload, DELETE to remove an own item — instead of a disabled,
 * zero-fetch stub. It has no accessible upload region, no library
 * region, no fetch wiring, and no enabled file/alt/slot controls today,
 * so the assertions below must fail against current production. No
 * production, i18n, Storybook, backend or Git edits are made from this
 * file.
 */

const WORKSPACE_ID = 5;
const MEDIA_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/media`;

const FIXED_PIXEL_CLASS_PATTERN = /(^|[\s"'`])(w|h|min-w|max-w|min-h|max-h)-\[\d+px\]|(^|[\s"'`])(w|h)-(px|0\.5|1|2|3|4|5|6|7|8|9|10|11|12|14|16|20|24|28|32|36|40|44|48|52|56|60|64|72|80|96)(?=[\s"'`]|$)/;
const BREAKPOINT_CLASS_PATTERN = /(^|[\s"'`])(sm|md|lg|xl|2xl):/;

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', { writable: true, configurable: true, value: width });
    Object.defineProperty(window, 'innerHeight', { writable: true, configurable: true, value: height });
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

describe('MediaPage — S1-WP01A Media surface (MEDIA_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        setViewport(320, 480);
        fetchSpy = vi.fn(async (url: string) => {
            if (String(url) === MEDIA_ENDPOINT) {
                return {
                    ok: true,
                    status: 200,
                    json: async () => ({ assets: [] }),
                } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('renders a real Media heading at a 320x480 viewport', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        expect(screen.getByRole('heading', { name: 'Media' })).toBeInTheDocument();
    });

    it('exposes an accessible Media upload region', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        expect(screen.getByRole('region', { name: /media upload/i })).toBeInTheDocument();
    });

    it('exposes an accessible Media library region', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        expect(screen.getByRole('region', { name: /media library/i })).toBeInTheDocument();
    });

    it('fetches the workspace media list on mount with credentials same-origin', async () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const [url, init] = fetchSpy.mock.calls[0];
        expect(String(url)).toBe(MEDIA_ENDPOINT);
        expect(init).toMatchObject({ credentials: 'same-origin' });
    });

    it('enables file, alt text and asset slot fields; alt text is required', async () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const uploadRegion = screen.getByRole('region', { name: /media upload/i });

        const fileField = within(uploadRegion).getByLabelText(/file/i);
        expect(fileField).not.toBeDisabled();

        const altField = within(uploadRegion).getByLabelText(/alt text/i);
        expect(altField).not.toBeDisabled();
        expect(altField).toBeRequired();

        const slotField = within(uploadRegion).getByLabelText(/asset slot/i);
        expect(slotField).not.toBeDisabled();
    });

    it('submits a multipart upload and renders the returned quarantined asset with an honest scan-pending status and no public preview', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            if (String(url) === MEDIA_ENDPOINT && (!init || init.method === undefined)) {
                return { ok: true, status: 200, json: async () => ({ assets: [] }) } as Response;
            }
            if (String(url) === MEDIA_ENDPOINT && init?.method === 'POST') {
                expect(init.body).toBeInstanceOf(FormData);
                expect(init.credentials).toBe('same-origin');
                return {
                    ok: true,
                    status: 201,
                    json: async () => ({
                        asset: { id: 42, altText: 'A test image', slot: 'hero', status: 'quarantined' },
                    }),
                } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });

        render(<MediaPage workspaceId={WORKSPACE_ID} />);
        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const uploadRegion = screen.getByRole('region', { name: /media upload/i });
        const fileField = within(uploadRegion).getByLabelText(/file/i) as HTMLInputElement;
        const altField = within(uploadRegion).getByLabelText(/alt text/i);
        const slotField = within(uploadRegion).getByLabelText(/asset slot/i);
        const submitButton = within(uploadRegion).getByRole('button', { name: /upload/i });

        const file = new File(['binary'], 'photo.png', { type: 'image/png' });
        await user.upload(fileField, file);
        await user.type(altField, 'A test image');
        await user.selectOptions(slotField, 'hero');
        await user.click(submitButton);

        await waitFor(() => {
            const postCall = fetchSpy.mock.calls.find((call) => call[1]?.method === 'POST');
            expect(postCall).toBeDefined();
        });

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        await waitFor(() => {
            expect(within(libraryRegion).getByText(/#42/)).toBeInTheDocument();
        });
        expect(within(libraryRegion).getByText('A test image')).toBeInTheDocument();
        expect(within(libraryRegion).getByText(/scan pending|quarantined/i)).toBeInTheDocument();

        expect(within(libraryRegion).queryByRole('img')).toBeNull();
        expect(screen.queryByText(/\bReady\b/)).toBeNull();
        expect(screen.queryByText(/\bPublished\b/)).toBeNull();
    });

    it('deletes an own item and removes it from the library on click', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            if (String(url) === MEDIA_ENDPOINT && (!init || init.method === undefined)) {
                return {
                    ok: true,
                    status: 200,
                    json: async () => ({
                        assets: [{ id: 7, altText: 'Owned asset', slot: 'hero', status: 'quarantined' }],
                    }),
                } as Response;
            }
            if (String(url) === `${MEDIA_ENDPOINT}/7` && init?.method === 'DELETE') {
                expect(init.credentials).toBe('same-origin');
                return { ok: true, status: 204, json: async () => ({}) } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });

        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        await waitFor(() => expect(within(libraryRegion).getByText(/#7/)).toBeInTheDocument());

        const deleteButton = within(libraryRegion).getByRole('button', { name: /delete/i });
        await user.click(deleteButton);

        await waitFor(() => {
            const deleteCall = fetchSpy.mock.calls.find((call) => call[1]?.method === 'DELETE');
            expect(deleteCall).toBeDefined();
        });

        await waitFor(() => expect(within(libraryRegion).queryByText(/#7/)).toBeNull());
    });

    it('lists the media lifecycle concepts in order, with no Ready/Published implication', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const bodyText = document.body.textContent ?? '';
        const quarantineIdx = bodyText.indexOf('Quarantine');
        const validationIdx = bodyText.indexOf('Validation');
        const scanIdx = bodyText.indexOf('Security scan');
        const derivativesIdx = bodyText.indexOf('Derivatives');

        expect(quarantineIdx).toBeGreaterThan(-1);
        expect(validationIdx).toBeGreaterThan(quarantineIdx);
        expect(scanIdx).toBeGreaterThan(validationIdx);
        expect(derivativesIdx).toBeGreaterThan(scanIdx);

        expect(screen.queryByText(/\bReady\b/)).toBeNull();
        expect(screen.queryByText(/\bPublished\b/)).toBeNull();
    });

    it('keeps the existing AI assistant present with approval still disabled', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        expect(screen.getByText('Media')).toBeInTheDocument();
        const approveButton = screen.getByRole('button', { name: /review and approve/i });
        expect(approveButton).toBeDisabled();
    });

    it('carries no fixed-pixel or breakpoint class on the batch-owned Media surface (excluding the shared AI panel)', () => {
        const { container } = render(<MediaPage workspaceId={WORKSPACE_ID} />);
        const root = container.querySelector('#media') as HTMLElement | null;

        expect(root).not.toBeNull();

        const aiPanel = (root as HTMLElement).querySelector('section[aria-label*="Media"]');
        if (aiPanel) aiPanel.remove();

        const classLists = collectClassLists(root as HTMLElement);
        const offenders = classLists.filter(
            (classList) => FIXED_PIXEL_CLASS_PATTERN.test(classList) || BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
    });

    it('exposes rights/license and expiry fields, still disabled — outside the first API contract', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const uploadRegion = screen.getByRole('region', { name: /media upload/i });

        const rightsField =
            within(uploadRegion).queryByLabelText(/rights/i) ?? within(uploadRegion).queryByLabelText(/licen[sc]e/i);
        expect(rightsField).not.toBeNull();
        expect(rightsField).toBeDisabled();

        const expiryField = within(uploadRegion).queryByLabelText(/expiry/i);
        expect(expiryField).not.toBeNull();
        expect(expiryField).toBeDisabled();
    });

    it('keeps the metadata intake form fluid at a 320x480 start with no fixed-width or breakpoint classes', () => {
        const { container } = render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const uploadRegion = screen.getByRole('region', { name: /media upload/i });
        expect(window.innerWidth).toBe(320);
        expect(window.innerHeight).toBe(480);

        const classLists = collectClassLists(uploadRegion as HTMLElement);
        const offenders = classLists.filter(
            (classList) => FIXED_PIXEL_CLASS_PATTERN.test(classList) || BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
        expect(container.querySelector('#media')).not.toBeNull();
    });

    /**
     * MEDIA_LIBRARY_SLOT_LIST_RED
     *
     * The library region has no per-category inventory today — only a single
     * "unavailable" status line. These assertions require an ordered set of
     * visible slot categories (Corporate site, Restaurant, Menu, Product, QR,
     * Email), each with its own honest status, no fake asset markers, and a
     * fluid subtree from a 320px viewport — so they fail against current
     * production.
     */
    it('lists the media slot categories in order, each with its own honest status and no fake asset', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        const bodyText = libraryRegion.textContent ?? '';

        const categories = ['Corporate site', 'Restaurant', 'Menu', 'Product', 'QR', 'Email'];
        const indices = categories.map((label) => bodyText.indexOf(label));

        indices.forEach((idx) => expect(idx).toBeGreaterThan(-1));
        for (let i = 1; i < indices.length; i += 1) {
            expect(indices[i]).toBeGreaterThan(indices[i - 1]);
        }

        const statuses = within(libraryRegion).getAllByRole('status');
        expect(statuses.length).toBeGreaterThanOrEqual(categories.length);

        categories.forEach((label) => {
            const categoryStatus = statuses.find((node) => node.textContent?.includes(label));
            expect(categoryStatus).toBeDefined();
            expect(categoryStatus?.textContent ?? '').toMatch(/not available|unavailable|no assets? yet/i);
        });

        expect(within(libraryRegion).queryByText(/#\d+/)).toBeNull();
    });

    it('keeps the slot-category inventory subtree fluid from a 320 viewport with no fixed-pixel or breakpoint classes', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        expect(window.innerWidth).toBe(320);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        const inventory = libraryRegion.querySelector('[data-testid="media-slot-category-inventory"]');

        expect(inventory).not.toBeNull();

        const classLists = collectClassLists(inventory as HTMLElement);
        const offenders = classLists.filter(
            (classList) => FIXED_PIXEL_CLASS_PATTERN.test(classList) || BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
    });
});
