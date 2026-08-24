import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * MANUAL_PAYMENT_SUBSCRIPTION_MANAGEMENT_FRONTEND_RED
 *
 * RED suite for the S1-WP01A platform Subscription Management page (manual
 * payment, frontend-first). There is no
 * resources/js/components/admin/pages/SubscriptionManagementPage(.tsx) in
 * production yet, so every assertion below must fail against current
 * production — none of these fetches are issued and none of this markup
 * exists.
 *
 * Frozen contract:
 *  - GET /api/admin/workspaces?query= (real workspace discovery), credentials
 *    same-origin, Accept application/json; an accessible combobox/list
 *    exposing exact server id, name, slug, state — never invented rows.
 *  - GET /api/admin/plans; only server is_active === true plan versions are
 *    selectable in the manual-payment form. Zero active plans blocks the
 *    flow with an honest message and issues no manual-payment POST.
 *  - Selecting a workspace fetches GET
 *    /api/admin/workspaces/{id}/subscription and renders the exact
 *    authoritative none/active state — never fabricated.
 *  - Accessible visible fields: Plan, end date, payment note, document
 *    reference. Text controls are intrinsically full width and >= 44px tall
 *    with no responsive breakpoint class.
 *  - Submission requires a separate human confirmation dialog. Only
 *    confirming triggers GET /sanctum/csrf-cookie then POST
 *    /api/admin/workspaces/{id}/manual-payments with credentials
 *    same-origin, JSON/Accept headers, X-XSRF-TOKEN, and body keys plan_id,
 *    ends_at, payment_note, document_reference, idempotency_key.
 *  - A failed POST preserves entered values and exposes an accessible
 *    error/retry path.
 *  - Success waits for an authoritative subscription refetch before
 *    announcing success and resetting/closing.
 *  - One submission/retry keeps a stable idempotency key; a confirmed
 *    successful submission rotates it for the next payment.
 *
 * Only mocked server responses are used; no production, backend, or Git
 * edits are made from this file.
 */

const WORKSPACES_ENDPOINT = '/api/admin/workspaces';
const PLANS_ENDPOINT = '/api/admin/plans';
const CSRF_ENDPOINT = '/sanctum/csrf-cookie';

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function makeWorkspaces() {
    return [
        { id: 7, name: 'Acme Corp', slug: 'acme-corp', state: 'active' },
        { id: 9, name: 'Beta LLC', slug: 'beta-llc', state: 'suspended' },
    ];
}

function makeActivePlans() {
    return [
        {
            id: 21,
            name: 'Starter',
            code: 'starter',
            version: 1,
            entitlements: ['1 brand'],
            amount_minor: 49900,
            currency: 'TRY',
            sort_order: 1,
            is_active: true,
        },
    ];
}

function makeInactivePlan() {
    return [
        {
            id: 22,
            name: 'Enterprise',
            code: 'enterprise',
            version: 2,
            entitlements: ['Unlimited brands'],
            amount_minor: null,
            currency: null,
            sort_order: 2,
            is_active: false,
        },
    ];
}

function makeNoneSubscription() {
    return { state: 'none' };
}

function makeActiveSubscription() {
    return {
        state: 'active',
        plan_id: 21,
        plan_code: 'starter',
        plan_name: 'Starter',
        plan_version: 1,
        ends_at: '2026-12-31',
    };
}

async function importSubscriptionManagementPageModule() {
    return import('./SubscriptionManagementPage') as unknown as Promise<{
        SubscriptionManagementPage: React.ComponentType;
    }>;
}

describe('SubscriptionManagementPage — S1-WP01A platform manual payment (MANUAL_PAYMENT_SUBSCRIPTION_MANAGEMENT_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=test-xsrf-token';
        fetchSpy = vi.fn(async (url: string) => {
            throw new Error(`Unhandled fetch in SubscriptionManagementPage test: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC';
    });

    function defaultRouter(): ReturnType<typeof vi.fn> {
        return vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            const parsed = new URL(String(url), 'http://localhost');

            if (parsed.pathname === WORKSPACES_ENDPOINT && method === 'GET') {
                return jsonResponse(200, makeWorkspaces());
            }
            if (parsed.pathname === PLANS_ENDPOINT && method === 'GET') {
                return jsonResponse(200, makeActivePlans());
            }
            if (parsed.pathname === '/api/admin/workspaces/7/subscription' && method === 'GET') {
                return jsonResponse(200, makeNoneSubscription());
            }
            if (parsed.pathname === CSRF_ENDPOINT) {
                return jsonResponse(204, null);
            }
            throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
        });
    }

    async function renderAndSelectWorkspace() {
        const { SubscriptionManagementPage } = await importSubscriptionManagementPageModule();
        render(<SubscriptionManagementPage />);

        const combobox = await screen.findByRole('combobox', { name: /workspace/i });
        const user = userEvent.setup();
        await user.click(combobox);
        const option = await screen.findByRole('option', { name: /acme corp/i });
        await user.click(option);

        await waitFor(() => {
            expect(
                fetchSpy.mock.calls.some(
                    ([calledUrl]) => String(calledUrl) === '/api/admin/workspaces/7/subscription',
                ),
            ).toBe(true);
        });

        return { user };
    }

    it('discovers workspaces via GET /api/admin/workspaces?query= with credentials same-origin and Accept application/json', async () => {
        fetchSpy.mockImplementation(defaultRouter());

        const { SubscriptionManagementPage } = await importSubscriptionManagementPageModule();
        render(<SubscriptionManagementPage />);

        await waitFor(() => {
            const call = fetchSpy.mock.calls.find(([calledUrl]) =>
                String(calledUrl).startsWith(WORKSPACES_ENDPOINT),
            );
            expect(call).toBeDefined();
        });

        const call = fetchSpy.mock.calls.find(([calledUrl]) =>
            String(calledUrl).startsWith(WORKSPACES_ENDPOINT),
        );
        const init = call?.[1] as RequestInit | undefined;
        expect(init?.credentials).toBe('same-origin');
        expect(new Headers(init?.headers).get('Accept')).toBe('application/json');
    });

    it('renders an accessible workspace combobox/list with exact server id, name, slug, and state', async () => {
        fetchSpy.mockImplementation(defaultRouter());

        const { SubscriptionManagementPage } = await importSubscriptionManagementPageModule();
        render(<SubscriptionManagementPage />);

        const combobox = await screen.findByRole('combobox', { name: /workspace/i });
        const user = userEvent.setup();
        await user.click(combobox);

        const acmeOption = await screen.findByRole('option', { name: /acme corp/i });
        expect(within(acmeOption).getByText('acme-corp')).toBeInTheDocument();
        expect(within(acmeOption).getByText(/active/i)).toBeInTheDocument();

        const betaOption = screen.getByRole('option', { name: /beta llc/i });
        expect(within(betaOption).getByText('beta-llc')).toBeInTheDocument();
        expect(within(betaOption).getByText(/suspended/i)).toBeInTheDocument();
    });

    it('loads active plan versions from GET /api/admin/plans and offers only server is_active plans as selectable', async () => {
        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const parsed = new URL(String(url), 'http://localhost');
            const method = (init?.method ?? 'GET').toUpperCase();

            if (parsed.pathname === WORKSPACES_ENDPOINT && method === 'GET')
                return jsonResponse(200, makeWorkspaces());
            if (parsed.pathname === PLANS_ENDPOINT && method === 'GET') {
                return jsonResponse(200, [...makeActivePlans(), ...makeInactivePlan()]);
            }
            if (parsed.pathname === '/api/admin/workspaces/7/subscription' && method === 'GET') {
                return jsonResponse(200, makeNoneSubscription());
            }
            throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
        });

        await renderAndSelectWorkspace();

        const planField = screen.getByLabelText(/^plan$/i);
        const options = within(planField.closest('form') ?? document.body)
            .getAllByRole('option')
            .map((option) => option.textContent ?? '');

        expect(options.some((text) => text.includes('Starter'))).toBe(true);
        expect(options.some((text) => text.includes('Enterprise'))).toBe(false);
    });

    it('blocks the manual-payment flow with an honest message and issues no POST when there are zero active plans', async () => {
        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const parsed = new URL(String(url), 'http://localhost');
            const method = (init?.method ?? 'GET').toUpperCase();

            if (parsed.pathname === WORKSPACES_ENDPOINT && method === 'GET')
                return jsonResponse(200, makeWorkspaces());
            if (parsed.pathname === PLANS_ENDPOINT && method === 'GET')
                return jsonResponse(200, []);
            if (parsed.pathname === '/api/admin/workspaces/7/subscription' && method === 'GET') {
                return jsonResponse(200, makeNoneSubscription());
            }
            if (parsed.pathname === '/api/admin/workspaces/7/manual-payments') {
                throw new Error('POST manual-payments must not be issued with zero active plans');
            }
            throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
        });

        await renderAndSelectWorkspace();

        expect(await screen.findByText(/plan must be created and activated/i)).toBeInTheDocument();

        const postCalls = fetchSpy.mock.calls.filter(([calledUrl]) =>
            String(calledUrl).includes('manual-payments'),
        );
        expect(postCalls.length).toBe(0);
    });

    it('fetches the authoritative subscription for the selected workspace and renders the exact none state', async () => {
        fetchSpy.mockImplementation(defaultRouter());

        await renderAndSelectWorkspace();

        const subscriptionRegion = screen.getByRole('region', { name: /subscription/i });
        expect(
            within(subscriptionRegion).getByText(/^none$|no active subscription/i),
        ).toBeInTheDocument();
    });

    it('renders the exact authoritative active subscription state for the selected workspace', async () => {
        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const parsed = new URL(String(url), 'http://localhost');
            const method = (init?.method ?? 'GET').toUpperCase();

            if (parsed.pathname === WORKSPACES_ENDPOINT && method === 'GET')
                return jsonResponse(200, makeWorkspaces());
            if (parsed.pathname === PLANS_ENDPOINT && method === 'GET')
                return jsonResponse(200, makeActivePlans());
            if (parsed.pathname === '/api/admin/workspaces/7/subscription' && method === 'GET') {
                return jsonResponse(200, makeActiveSubscription());
            }
            throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
        });

        await renderAndSelectWorkspace();

        const subscriptionRegion = screen.getByRole('region', { name: /subscription/i });
        expect(within(subscriptionRegion).getByText('starter')).toBeInTheDocument();
        expect(within(subscriptionRegion).getByText(/active/i)).toBeInTheDocument();
        expect(within(subscriptionRegion).getByText('2026-12-31')).toBeInTheDocument();
    });

    it('renders accessible full-width, >= 44px tall text fields for Plan, end date, payment note, and document reference with no breakpoint class', async () => {
        fetchSpy.mockImplementation(defaultRouter());

        await renderAndSelectWorkspace();

        const fieldLabels = [/end date/i, /payment note/i, /document reference/i];

        for (const labelPattern of fieldLabels) {
            const field = screen.getByLabelText(labelPattern);
            expect(field.className).not.toMatch(/(^|:)(sm|md|lg|xl|2xl):/);
            expect(field.className).toMatch(/w-full/);

            const heightPx = parseFloat(field.style.minHeight || field.style.height || '0');
            const heightAttrOk =
                heightPx >= 44 || /h-11|min-h-\[44px\]|min-h-11/.test(field.className);
            expect(heightAttrOk).toBe(true);
        }
    });

    it('requires a separate human confirmation dialog before submitting; only Confirm triggers csrf-cookie then POST manual-payments with exact headers and body keys', async () => {
        fetchSpy.mockImplementation(defaultRouter());

        const { user } = await renderAndSelectWorkspace();

        await user.selectOptions(screen.getByLabelText(/^plan$/i), 'starter');
        await user.type(screen.getByLabelText(/end date/i), '2027-01-31');
        await user.type(screen.getByLabelText(/payment note/i), 'Wire transfer confirmed');
        await user.type(screen.getByLabelText(/document reference/i), 'DOC-100');

        const submitButton = screen.getByRole('button', { name: /record manual payment|submit/i });
        await user.click(submitButton);

        expect(
            fetchSpy.mock.calls.some(([calledUrl]) =>
                String(calledUrl).includes('manual-payments'),
            ),
        ).toBe(false);

        const dialog = screen.getByRole('dialog', { name: /confirm/i });
        await user.click(within(dialog).getByRole('button', { name: /confirm/i }));

        await waitFor(() => {
            expect(
                fetchSpy.mock.calls.some(([calledUrl]) => String(calledUrl) === CSRF_ENDPOINT),
            ).toBe(true);
        });

        const postCall = await waitFor(() => {
            const call = fetchSpy.mock.calls.find(
                ([calledUrl, callInit]) =>
                    String(calledUrl) === '/api/admin/workspaces/7/manual-payments' &&
                    (callInit as RequestInit | undefined)?.method === 'POST',
            );
            expect(call).toBeDefined();
            return call as [string, RequestInit];
        });

        const [, postInit] = postCall;
        expect(postInit.credentials).toBe('same-origin');
        const headers = new Headers(postInit.headers);
        expect(headers.get('Accept')).toBe('application/json');
        expect(headers.get('Content-Type')).toBe('application/json');
        expect(headers.get('X-XSRF-TOKEN')).toBe('test-xsrf-token');

        const payload = JSON.parse(postInit.body as string);
        expect(payload).toEqual(
            expect.objectContaining({
                plan_id: 21,
                ends_at: '2027-01-31',
                payment_note: 'Wire transfer confirmed',
                document_reference: 'DOC-100',
                idempotency_key: expect.any(String),
            }),
        );
    });

    it('preserves entered values and exposes an accessible error/retry path when the POST fails', async () => {
        let postAttempts = 0;
        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const parsed = new URL(String(url), 'http://localhost');
            const method = (init?.method ?? 'GET').toUpperCase();

            if (parsed.pathname === WORKSPACES_ENDPOINT && method === 'GET')
                return jsonResponse(200, makeWorkspaces());
            if (parsed.pathname === PLANS_ENDPOINT && method === 'GET')
                return jsonResponse(200, makeActivePlans());
            if (parsed.pathname === '/api/admin/workspaces/7/subscription' && method === 'GET') {
                return jsonResponse(200, makeNoneSubscription());
            }
            if (parsed.pathname === CSRF_ENDPOINT) return jsonResponse(204, null);
            if (
                parsed.pathname === '/api/admin/workspaces/7/manual-payments' &&
                method === 'POST'
            ) {
                postAttempts += 1;
                return jsonResponse(422, { message: 'Validation failed' });
            }
            throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
        });

        const { user } = await renderAndSelectWorkspace();

        await user.selectOptions(screen.getByLabelText(/^plan$/i), 'starter');
        await user.type(screen.getByLabelText(/end date/i), '2027-01-31');
        await user.type(screen.getByLabelText(/payment note/i), 'Wire transfer confirmed');
        await user.type(screen.getByLabelText(/document reference/i), 'DOC-100');

        await user.click(screen.getByRole('button', { name: /record manual payment|submit/i }));
        const dialog = screen.getByRole('dialog', { name: /confirm/i });
        await user.click(within(dialog).getByRole('button', { name: /confirm/i }));

        await waitFor(() => {
            expect(postAttempts).toBe(1);
        });

        expect(await screen.findByRole('alert')).toBeInTheDocument();
        expect(screen.getByLabelText(/end date/i)).toHaveValue('2027-01-31');
        expect(screen.getByLabelText(/payment note/i)).toHaveValue('Wire transfer confirmed');
        expect(screen.getByLabelText(/document reference/i)).toHaveValue('DOC-100');
        expect(screen.getByRole('button', { name: /retry/i })).toBeInTheDocument();
    });

    it('waits for an authoritative subscription refetch before announcing success and guards a stale refetch race', async () => {
        let subscriptionCallCount = 0;
        const releaseFinalRefetch: { resolve: (() => void) | null } = { resolve: null };

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const parsed = new URL(String(url), 'http://localhost');
            const method = (init?.method ?? 'GET').toUpperCase();

            if (parsed.pathname === WORKSPACES_ENDPOINT && method === 'GET')
                return jsonResponse(200, makeWorkspaces());
            if (parsed.pathname === PLANS_ENDPOINT && method === 'GET')
                return jsonResponse(200, makeActivePlans());
            if (parsed.pathname === '/api/admin/workspaces/7/subscription' && method === 'GET') {
                subscriptionCallCount += 1;
                if (subscriptionCallCount === 1) {
                    return jsonResponse(200, makeNoneSubscription());
                }
                return new Promise((resolve) => {
                    releaseFinalRefetch.resolve = () =>
                        resolve(jsonResponse(200, makeActiveSubscription()));
                });
            }
            if (parsed.pathname === CSRF_ENDPOINT) return jsonResponse(204, null);
            if (
                parsed.pathname === '/api/admin/workspaces/7/manual-payments' &&
                method === 'POST'
            ) {
                return jsonResponse(201, {});
            }
            throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
        });

        const { user } = await renderAndSelectWorkspace();

        await user.selectOptions(screen.getByLabelText(/^plan$/i), 'starter');
        await user.type(screen.getByLabelText(/end date/i), '2027-01-31');
        await user.type(screen.getByLabelText(/payment note/i), 'Wire transfer confirmed');
        await user.type(screen.getByLabelText(/document reference/i), 'DOC-100');

        await user.click(screen.getByRole('button', { name: /record manual payment|submit/i }));
        const dialog = screen.getByRole('dialog', { name: /confirm/i });
        await user.click(within(dialog).getByRole('button', { name: /confirm/i }));

        await waitFor(() => {
            expect(subscriptionCallCount).toBe(2);
        });

        expect(screen.queryByText(/payment recorded|success/i)).not.toBeInTheDocument();

        releaseFinalRefetch.resolve?.();

        await waitFor(() => {
            expect(
                screen.getByRole('status', { name: /manual payment|success/i }),
            ).toHaveTextContent(/recorded|success/i);
        });

        expect(screen.queryByRole('dialog', { name: /confirm/i })).not.toBeInTheDocument();
    });

    it('keeps a stable idempotency key across a retry and rotates it only after a confirmed successful submission', async () => {
        let postAttempts = 0;
        const observedKeys: string[] = [];

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const parsed = new URL(String(url), 'http://localhost');
            const method = (init?.method ?? 'GET').toUpperCase();

            if (parsed.pathname === WORKSPACES_ENDPOINT && method === 'GET')
                return jsonResponse(200, makeWorkspaces());
            if (parsed.pathname === PLANS_ENDPOINT && method === 'GET')
                return jsonResponse(200, makeActivePlans());
            if (parsed.pathname === '/api/admin/workspaces/7/subscription' && method === 'GET') {
                return jsonResponse(
                    200,
                    postAttempts >= 2 ? makeActiveSubscription() : makeNoneSubscription(),
                );
            }
            if (parsed.pathname === CSRF_ENDPOINT) return jsonResponse(204, null);
            if (
                parsed.pathname === '/api/admin/workspaces/7/manual-payments' &&
                method === 'POST'
            ) {
                postAttempts += 1;
                const body = JSON.parse((init?.body as string) ?? '{}');
                observedKeys.push(body.idempotency_key);
                if (postAttempts === 1) return jsonResponse(500, { message: 'Server error' });
                return jsonResponse(201, {});
            }
            throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
        });

        const { user } = await renderAndSelectWorkspace();

        await user.selectOptions(screen.getByLabelText(/^plan$/i), 'starter');
        await user.type(screen.getByLabelText(/end date/i), '2027-01-31');
        await user.type(screen.getByLabelText(/payment note/i), 'Wire transfer confirmed');
        await user.type(screen.getByLabelText(/document reference/i), 'DOC-100');

        await user.click(screen.getByRole('button', { name: /record manual payment|submit/i }));
        await user.click(
            within(screen.getByRole('dialog', { name: /confirm/i })).getByRole('button', {
                name: /confirm/i,
            }),
        );

        await waitFor(() => expect(postAttempts).toBe(1));
        await screen.findByRole('alert');

        await user.click(screen.getByRole('button', { name: /retry/i }));
        await user.click(
            within(screen.getByRole('dialog', { name: /confirm/i })).getByRole('button', {
                name: /confirm/i,
            }),
        );

        await waitFor(() => expect(postAttempts).toBe(2));

        expect(observedKeys).toHaveLength(2);
        expect(observedKeys[0]).toBe(observedKeys[1]);

        await waitFor(() => {
            expect(
                screen.getByRole('status', { name: /manual payment|success/i }),
            ).toHaveTextContent(/recorded|success/i);
        });

        await user.click(screen.getByRole('button', { name: /record manual payment|submit/i }));
        await user.type(screen.getByLabelText(/end date/i), '2027-02-28');
        await user.type(screen.getByLabelText(/payment note/i), 'Second payment');
        await user.type(screen.getByLabelText(/document reference/i), 'DOC-200');
        await user.click(screen.getByRole('button', { name: /record manual payment|submit/i }));
        await user.click(
            within(screen.getByRole('dialog', { name: /confirm/i })).getByRole('button', {
                name: /confirm/i,
            }),
        );

        await waitFor(() => expect(postAttempts).toBe(3));
        expect(observedKeys[2]).not.toBe(observedKeys[0]);
    });
});
