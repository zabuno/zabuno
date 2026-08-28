import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { BulkQrWizardFields } from './BulkQrWizardFields';

/**
 * BULK_QR_WIZARD_WIRING_RED — S1-WP04b10
 *
 * Retires the prior fully-inert/no-button RED suite in this file (that
 * contract froze the wizard as a static, never-submitting fieldset while no
 * backend bulk endpoint existed). A real backend bulk table/QR endpoint now
 * exists per the S1-WP04b10 delivery contract, so BulkQrWizardFields must
 * thread real workspaceId/locationId/menuId/hasCurrentPublication, expose a
 * distinct accessible "Create table QR codes" action, CSRF-bootstrap then
 * POST exactly to /api/workspaces/{workspaceId}/brand/locations/{locationId}
 * /tables/bulk with the numeric payload contract, and render only real
 * server-derived counts/table-QR pairs on 201 — never inventing ids, tokens
 * or URLs client-side. BulkQrWizardFields today renders no button at all and
 * performs zero fetch on any interaction, so every submit-path assertion
 * below fails against current production, not from a syntax/bootstrap
 * defect in this suite. No production, i18n, Storybook, backend or Git
 * edits are made from this file.
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

function getWizard() {
    return screen.getByRole('group', { name: /bulk qr wizard/i });
}

function getFields(wizard: HTMLElement) {
    return {
        areaSectionCount: within(wizard).getByLabelText(/area\/section count/i) as HTMLInputElement,
        tableCount: within(wizard).getByLabelText(/table count/i) as HTMLInputElement,
        namingPrefix: within(wizard).getByLabelText(/naming prefix/i) as HTMLInputElement,
        namingSequenceStart: within(wizard).getByLabelText(
            /naming sequence start/i,
        ) as HTMLInputElement,
        namingRange: within(wizard).getByLabelText(/naming range/i) as HTMLInputElement,
        seatCountPerTable: within(wizard).getByLabelText(
            /seat count per table/i,
        ) as HTMLInputElement,
    };
}

async function fillValidPlan(user: ReturnType<typeof userEvent.setup>, wizard: HTMLElement) {
    const { areaSectionCount, tableCount, seatCountPerTable } = getFields(wizard);
    await user.type(areaSectionCount, '3');
    await user.type(tableCount, '12');
    await user.type(seatCountPerTable, '2');
}

const BULK_URL = /brand\/locations\/923\/tables\/bulk$/;

const DEFAULT_PROPS = {
    workspaceId: 71,
    locationId: 923,
    menuId: 42,
    hasCurrentPublication: true,
};

describe('BulkQrWizardFields real submit wiring (BULK_QR_WIZARD_WIRING_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    });

    it('renders a distinct accessible "Create table QR codes" action, disabled while the plan is invalid', () => {
        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);

        const wizard = getWizard();
        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });

        expect(submit).toBeDisabled();
    });

    it('enables the create action only once a valid plan is filled and disables it again when hasCurrentPublication is false', async () => {
        const user = userEvent.setup();
        const { rerender } = render(<BulkQrWizardFields {...DEFAULT_PROPS} />);

        const wizard = getWizard();
        await fillValidPlan(user, wizard);

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        expect(submit).toBeEnabled();

        rerender(<BulkQrWizardFields {...DEFAULT_PROPS} hasCurrentPublication={false} />);
        expect(
            within(getWizard()).getByRole('button', { name: /create table qr codes/i }),
        ).toBeDisabled();
    });

    it('performs zero fetch when the create action is clicked while the plan is invalid', async () => {
        const user = userEvent.setup();
        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);

        const wizard = getWizard();
        const { tableCount } = getFields(wizard);
        await user.type(tableCount, '600');
        await user.tab();

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        await user.click(submit);

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('performs zero fetch for a cross-field-invalid naming range whose inclusive cardinality does not equal tableCount', async () => {
        const user = userEvent.setup();
        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);

        const wizard = getWizard();
        await fillValidPlan(user, wizard);
        const { namingRange } = getFields(wizard);
        await user.type(namingRange, '1-5');
        await user.tab();

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        expect(submit).toBeDisabled();
        await user.click(submit);

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('performs zero fetch when the derived end sequence (sequenceStart + tableCount - 1) exceeds 9999 and no range is supplied', async () => {
        const user = userEvent.setup();
        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);

        const wizard = getWizard();
        await fillValidPlan(user, wizard);
        const { namingSequenceStart } = getFields(wizard);
        await user.type(namingSequenceStart, '9990');
        await user.tab();

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        expect(submit).toBeDisabled();
        await user.click(submit);

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('performs zero fetch when a supplied sequence start does not equal the naming range start', async () => {
        const user = userEvent.setup();
        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);

        const wizard = getWizard();
        await fillValidPlan(user, wizard);
        const { namingSequenceStart, namingRange } = getFields(wizard);
        await user.type(namingSequenceStart, '5');
        await user.type(namingRange, '1-12');
        await user.tab();

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        expect(submit).toBeDisabled();
        await user.click(submit);

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('bootstraps CSRF then POSTs exact URL/headers and a numeric-only payload with blank optional keys omitted', async () => {
        document.cookie = 'XSRF-TOKEN=wp04b10-test-token';
        const user = userEvent.setup();

        fetchSpy.mockImplementation(async (url: string) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
            if (BULK_URL.test(href)) {
                return jsonResponse(201, { areas: [], tables: [], qrCodes: [] });
            }
            return jsonResponse(404, { message: 'Not found' });
        });

        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);
        const wizard = getWizard();
        await fillValidPlan(user, wizard);

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        await user.click(submit);

        await waitFor(() => {
            expect(
                fetchSpy.mock.calls.some(([url]) => /sanctum\/csrf-cookie/.test(String(url))),
            ).toBe(true);
        });

        await waitFor(() => {
            expect(fetchSpy.mock.calls.some(([url]) => BULK_URL.test(String(url)))).toBe(true);
        });

        const csrfCallIndex = fetchSpy.mock.calls.findIndex(([url]) =>
            /sanctum\/csrf-cookie/.test(String(url)),
        );
        const postCallIndex = fetchSpy.mock.calls.findIndex(([url]) => BULK_URL.test(String(url)));
        expect(csrfCallIndex).toBeGreaterThanOrEqual(0);
        expect(postCallIndex).toBeGreaterThan(csrfCallIndex);

        const postCall = fetchSpy.mock.calls[postCallIndex];
        const postInit = postCall[1] as RequestInit;
        expect(postInit.method).toBe('POST');
        expect(postInit.credentials).toBe('include');

        const headers = new Headers(postInit.headers);
        expect(headers.get('Accept')).toBe('application/json');
        expect(headers.get('Content-Type')).toMatch(/application\/json/);
        expect(headers.get('X-XSRF-TOKEN')).toBe('wp04b10-test-token');

        const payload = JSON.parse(String(postInit.body));
        expect(payload).toMatchObject({
            menuId: 42,
            areaSectionCount: 3,
            tableCount: 12,
            seatCountPerTable: 2,
        });
        expect(payload).not.toHaveProperty('namingPrefix');
        expect(payload).not.toHaveProperty('namingRange');
        expect(payload).not.toHaveProperty('namingSequenceStart');
    });

    it('trims a nonblank naming prefix/range and sends an integer naming sequence start when supplied', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
            if (BULK_URL.test(href))
                return jsonResponse(201, { areas: [], tables: [], qrCodes: [] });
            return jsonResponse(404, { message: 'Not found' });
        });
        const user = userEvent.setup();

        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);
        const wizard = getWizard();
        await fillValidPlan(user, wizard);
        const { namingPrefix, namingSequenceStart, namingRange } = getFields(wizard);
        await user.type(namingPrefix, '  T  ');
        await user.type(namingSequenceStart, '1');
        await user.type(namingRange, '1-12');
        await user.tab();

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        await user.click(submit);

        await waitFor(() => {
            expect(fetchSpy.mock.calls.some(([url]) => BULK_URL.test(String(url)))).toBe(true);
        });

        const postCall = fetchSpy.mock.calls.find(([url]) => BULK_URL.test(String(url)));
        const payload = JSON.parse(String((postCall?.[1] as RequestInit).body));
        expect(payload.namingPrefix).toBe('T');
        expect(payload.namingRange).toBe('1-12');
        expect(payload.namingSequenceStart).toBe(1);
        expect(typeof payload.namingSequenceStart).toBe('number');
    });

    it('disables the create action and shows honest loading while the request is in flight, suppressing a duplicate submit', async () => {
        let resolvePost: (value: Response) => void = () => {};
        fetchSpy.mockImplementation(async (url: string) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
            if (BULK_URL.test(href)) {
                return new Promise<Response>((resolve) => {
                    resolvePost = resolve;
                });
            }
            return jsonResponse(404, { message: 'Not found' });
        });
        const user = userEvent.setup();

        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);
        const wizard = getWizard();
        await fillValidPlan(user, wizard);

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        await user.click(submit);

        await waitFor(() => expect(submit).toBeDisabled());
        const loadingStatuses = within(wizard)
            .getAllByRole('status')
            .filter((el) => el.textContent === 'Creating table QR codes...');
        expect(loadingStatuses).toHaveLength(1);

        await user.click(submit);

        resolvePost(jsonResponse(201, { areas: [], tables: [], qrCodes: [] }));
        await waitFor(() => expect(submit).toBeEnabled());

        const postCalls = fetchSpy.mock.calls.filter(([url]) => BULK_URL.test(String(url)));
        expect(postCalls).toHaveLength(1);
    });

    it('shows a localized alert and never fabricates success on a 422 response, without auto-retry, while the honest plan summary remains', async () => {
        const onCreated = vi.fn();
        fetchSpy.mockImplementation(async (url: string) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
            if (BULK_URL.test(href)) return jsonResponse(422, { message: 'Invalid plan' });
            return jsonResponse(404, { message: 'Not found' });
        });
        const user = userEvent.setup();

        render(<BulkQrWizardFields {...DEFAULT_PROPS} onCreated={onCreated} />);
        const wizard = getWizard();
        await fillValidPlan(user, wizard);

        expect(within(wizard).getByText(/12 tables across 3 areas/i)).toBeInTheDocument();

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        await user.click(submit);

        await waitFor(() => {
            expect(within(wizard).getByRole('alert')).toBeInTheDocument();
        });

        expect(within(wizard).getByText(/12 tables across 3 areas/i)).toBeInTheDocument();
        expect(
            within(wizard).queryByText(/^Created \d+ areas, \d+ tables, \d+ QR codes\.$/),
        ).not.toBeInTheDocument();
        expect(within(wizard).queryByRole('list')).not.toBeInTheDocument();
        expect(onCreated).not.toHaveBeenCalled();

        const postCalls = fetchSpy.mock.calls.filter(([url]) => BULK_URL.test(String(url)));
        expect(postCalls).toHaveLength(1);
    });

    it.each([403, 404, 429])(
        'shows the same localized alert on a %d response, performs exactly one POST, and never auto-retries or invokes the callback',
        async (status) => {
            const onCreated = vi.fn();
            fetchSpy.mockImplementation(async (url: string) => {
                const href = String(url);
                if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
                if (BULK_URL.test(href)) return jsonResponse(status, { message: 'Rejected' });
                return jsonResponse(404, { message: 'Not found' });
            });
            const user = userEvent.setup();

            render(<BulkQrWizardFields {...DEFAULT_PROPS} onCreated={onCreated} />);
            const wizard = getWizard();
            await fillValidPlan(user, wizard);

            const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
            await user.click(submit);

            await waitFor(() => {
                expect(within(wizard).getByRole('alert')).toBeInTheDocument();
            });

            expect(
                within(wizard).queryByText(/^Created \d+ areas, \d+ tables, \d+ QR codes\.$/),
            ).not.toBeInTheDocument();
            expect(onCreated).not.toHaveBeenCalled();

            const postCalls = fetchSpy.mock.calls.filter(([url]) => BULK_URL.test(String(url)));
            expect(postCalls).toHaveLength(1);
        },
    );

    it('shows an alert and never renders the Created message or invokes the callback when the 201 body has a non-array areas, tables, or qrCodes field', async () => {
        const malformedBodies = [
            { areas: 'not-an-array', tables: [], qrCodes: [] },
            { areas: [], tables: 'not-an-array', qrCodes: [] },
            { areas: [], tables: [], qrCodes: 'not-an-array' },
        ];

        for (const body of malformedBodies) {
            const onCreated = vi.fn();
            fetchSpy = vi.fn();
            vi.stubGlobal('fetch', fetchSpy);
            fetchSpy.mockImplementation(async (url: string) => {
                const href = String(url);
                if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
                if (BULK_URL.test(href)) return jsonResponse(201, body);
                return jsonResponse(404, { message: 'Not found' });
            });
            const user = userEvent.setup();

            const { unmount } = render(
                <BulkQrWizardFields {...DEFAULT_PROPS} onCreated={onCreated} />,
            );
            const wizard = getWizard();
            await fillValidPlan(user, wizard);

            const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
            await user.click(submit);

            await waitFor(() => {
                expect(within(wizard).getByRole('alert')).toBeInTheDocument();
            });

            expect(
                within(wizard).queryByText(/^Created \d+ areas, \d+ tables, \d+ QR codes\.$/),
            ).not.toBeInTheDocument();
            expect(onCreated).not.toHaveBeenCalled();

            unmount();
            vi.unstubAllGlobals();
        }
    });

    it('shows a localized alert on a network failure without invoking any success callback', async () => {
        const onCreated = vi.fn();
        fetchSpy.mockImplementation(async (url: string) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
            if (BULK_URL.test(href)) throw new Error('network down');
            return jsonResponse(404, { message: 'Not found' });
        });
        const user = userEvent.setup();

        render(<BulkQrWizardFields {...DEFAULT_PROPS} onCreated={onCreated} />);
        const wizard = getWizard();
        await fillValidPlan(user, wizard);

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        await user.click(submit);

        await waitFor(() => {
            expect(within(wizard).getByRole('alert')).toBeInTheDocument();
        });

        expect(onCreated).not.toHaveBeenCalled();
    });

    it('on a 201 with array areas/tables/qrCodes renders localized server-derived counts and a fluid list pairing each table name with its matching QR resolverUrl, then invokes the success callback', async () => {
        const serverBody = {
            areas: [{ id: 1 }, { id: 2 }, { id: 3 }],
            tables: [
                { id: 501, name: 'T1' },
                { id: 502, name: 'T2' },
            ],
            qrCodes: [
                {
                    id: 901,
                    workspaceId: 71,
                    locationId: 923,
                    menuId: 42,
                    tableId: 501,
                    token: 'a'.repeat(43),
                    resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
                    destinationType: 'published_menu',
                    state: 'active',
                },
                {
                    id: 902,
                    workspaceId: 71,
                    locationId: 923,
                    menuId: 42,
                    tableId: 502,
                    token: 'b'.repeat(43),
                    resolverUrl: `https://example.test/q/${'b'.repeat(43)}`,
                    destinationType: 'published_menu',
                    state: 'active',
                },
            ],
        };
        fetchSpy.mockImplementation(async (url: string) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
            if (BULK_URL.test(href)) return jsonResponse(201, serverBody);
            return jsonResponse(404, { message: 'Not found' });
        });
        const onCreated = vi.fn();
        const user = userEvent.setup();

        render(<BulkQrWizardFields {...DEFAULT_PROPS} onCreated={onCreated} />);
        const wizard = getWizard();
        await fillValidPlan(user, wizard);

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        await user.click(submit);

        await waitFor(() => {
            expect(
                within(wizard).getByText('Created 3 areas, 2 tables, 2 QR codes.'),
            ).toBeInTheDocument();
        });

        const t1Link = within(wizard).getByRole('link', { name: /T1/ });
        expect(t1Link).toHaveAttribute('href', serverBody.qrCodes[0].resolverUrl);

        const t2Link = within(wizard).getByRole('link', { name: /T2/ });
        expect(t2Link).toHaveAttribute('href', serverBody.qrCodes[1].resolverUrl);

        expect(onCreated).toHaveBeenCalledTimes(1);
        expect(onCreated).toHaveBeenCalledWith(serverBody.qrCodes);
    });

    it('does not render breakpoint or fixed-pixel sizing classes anywhere in the wizard subtree at 320x480', () => {
        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);

        const wizard = getWizard();
        const classLists = collectClassLists(wizard as HTMLElement);

        classLists.forEach((classList) => {
            expect(classList).not.toMatch(FIXED_PIXEL_CLASS_PATTERN);
            expect(classList).not.toMatch(BREAKPOINT_CLASS_PATTERN);
        });
    });

    it('preserves all six controlled inputs and client-side validity mirroring the prior contract', async () => {
        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);

        const wizard = getWizard();
        const fields = getFields(wizard);
        const domInputs = Array.from(wizard.querySelectorAll('input'));

        expect(domInputs).toHaveLength(6);
        expect(fields.areaSectionCount).toHaveAttribute('min', '1');
        expect(fields.areaSectionCount).toHaveAttribute('max', '50');
        expect(fields.tableCount).toHaveAttribute('min', '1');
        expect(fields.tableCount).toHaveAttribute('max', '500');
        expect(fields.seatCountPerTable).toHaveAttribute('min', '1');
        expect(fields.seatCountPerTable).toHaveAttribute('max', '20');
    });
});

/**
 * BULK_QR_WIZARD_HONEST_STATE_RED — S1-WP04b10 post-green correction
 *
 * Four honest-state gaps found by MASTER against the current (post-GREEN)
 * production snapshot 337264c304aae6c87046865b33e9c3fcafdae3b7bade13c10697f1
 * ef36b3290f: the "has not been submitted" notice is rendered
 * unconditionally today (never suppressed after a real attempt), the
 * "Created ..." success message is a plain paragraph with no role="status",
 * and a cross-field-invalid plan (range cardinality mismatch) shows no
 * explanatory alert at all — only per-field errors. Every assertion below
 * fails against current production, not from a syntax/bootstrap defect in
 * this suite.
 */
describe('BulkQrWizardFields honest-state gaps (BULK_QR_WIZARD_HONEST_STATE_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    });

    it('(A) shows the not-submitted notice before any attempt, hides it after a successful submit, and gives the Created message role status', async () => {
        const serverBody = {
            areas: [{ id: 1 }, { id: 2 }, { id: 3 }],
            tables: [
                { id: 501, name: 'T1' },
                { id: 502, name: 'T2' },
            ],
            qrCodes: [
                {
                    id: 901,
                    workspaceId: 71,
                    locationId: 923,
                    menuId: 42,
                    tableId: 501,
                    token: 'a'.repeat(43),
                    resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
                    destinationType: 'published_menu',
                    state: 'active',
                },
                {
                    id: 902,
                    workspaceId: 71,
                    locationId: 923,
                    menuId: 42,
                    tableId: 502,
                    token: 'b'.repeat(43),
                    resolverUrl: `https://example.test/q/${'b'.repeat(43)}`,
                    destinationType: 'published_menu',
                    state: 'active',
                },
            ],
        };
        fetchSpy.mockImplementation(async (url: string) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
            if (BULK_URL.test(href)) return jsonResponse(201, serverBody);
            return jsonResponse(404, { message: 'Not found' });
        });
        const user = userEvent.setup();

        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);
        const wizard = getWizard();

        expect(within(wizard).getByText(/fill in the table layout below/i)).toBeInTheDocument();

        await fillValidPlan(user, wizard);
        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        await user.click(submit);

        const createdMessage = await within(wizard).findByText(
            'Created 3 areas, 2 tables, 2 QR codes.',
        );
        expect(createdMessage).toHaveAttribute('role', 'status');

        expect(
            within(wizard).queryByText(/fill in the table layout below/i),
        ).not.toBeInTheDocument();
    });

    it('(B) hides the not-submitted notice after a 422 attempt while the honest plan summary remains and an alert is shown', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
            if (BULK_URL.test(href)) return jsonResponse(422, { message: 'Invalid plan' });
            return jsonResponse(404, { message: 'Not found' });
        });
        const user = userEvent.setup();

        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);
        const wizard = getWizard();
        await fillValidPlan(user, wizard);

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        await user.click(submit);

        await waitFor(() => {
            expect(within(wizard).getByRole('alert')).toBeInTheDocument();
        });

        expect(
            within(wizard).queryByText(/fill in the table layout below/i),
        ).not.toBeInTheDocument();
        expect(within(wizard).getByText(/12 tables across 3 areas/i)).toBeInTheDocument();
    });

    it('(C) shows an explanatory alert with the exact cross-field mismatch text and performs zero fetch when tableCount and namingRange cardinality disagree', async () => {
        const user = userEvent.setup();
        render(<BulkQrWizardFields {...DEFAULT_PROPS} />);
        const wizard = getWizard();

        const { tableCount, seatCountPerTable, namingRange } = getFields(wizard);
        await user.type(tableCount, '12');
        await user.type(seatCountPerTable, '2');
        await user.type(namingRange, '1-5');
        await user.tab();

        const alerts = within(wizard).getAllByRole('alert');
        const crossFieldAlert = alerts.find(
            (el) =>
                el.textContent ===
                'Naming range and sequence must match the table count and stay within 0-9999.',
        );
        expect(crossFieldAlert).toBeDefined();

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('(E) treats an HTTP 200 with otherwise valid areas/tables/qrCodes arrays as an error, since the contract accepts exactly 201', async () => {
        const onCreated = vi.fn();
        fetchSpy.mockImplementation(async (url: string) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
            if (BULK_URL.test(href)) {
                return jsonResponse(200, {
                    areas: [{ id: 1 }, { id: 2 }, { id: 3 }],
                    tables: [
                        { id: 501, name: 'T1' },
                        { id: 502, name: 'T2' },
                    ],
                    qrCodes: [
                        {
                            id: 901,
                            workspaceId: 71,
                            locationId: 923,
                            menuId: 42,
                            tableId: 501,
                            token: 'a'.repeat(43),
                            resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
                            destinationType: 'published_menu',
                            state: 'active',
                        },
                        {
                            id: 902,
                            workspaceId: 71,
                            locationId: 923,
                            menuId: 42,
                            tableId: 502,
                            token: 'b'.repeat(43),
                            resolverUrl: `https://example.test/q/${'b'.repeat(43)}`,
                            destinationType: 'published_menu',
                            state: 'active',
                        },
                    ],
                });
            }
            return jsonResponse(404, { message: 'Not found' });
        });
        const user = userEvent.setup();

        render(<BulkQrWizardFields {...DEFAULT_PROPS} onCreated={onCreated} />);
        const wizard = getWizard();
        await fillValidPlan(user, wizard);

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        await user.click(submit);

        await waitFor(() => {
            expect(within(wizard).getByRole('alert')).toBeInTheDocument();
        });

        expect(
            within(wizard).queryByText(/^Created \d+ areas, \d+ tables, \d+ QR codes\.$/),
        ).not.toBeInTheDocument();
        expect(onCreated).not.toHaveBeenCalled();

        const postCalls = fetchSpy.mock.calls.filter(([url]) => BULK_URL.test(String(url)));
        expect(postCalls).toHaveLength(1);
    });
});
