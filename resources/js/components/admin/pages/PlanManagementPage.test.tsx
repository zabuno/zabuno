import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { PlanManagementPage } from './PlanManagementPage';

/**
 * PLATFORM_PLAN_MANAGEMENT_FRONTEND_RED
 *
 * RED suite for the S1-WP01A platform Plan Management page. This page does
 * not exist in production yet — this suite freezes the frozen contract
 * (Option B, standalone platform plan create/list/activate before manual
 * payment) so implementation has an executable target:
 *
 *  - GET /api/admin/plans on mount, credentials same-origin, Accept
 *    application/json.
 *  - Accessible loading, honest empty, strict error+Retry, and success list
 *    states; never a client-invented plan row.
 *  - Exact server plan shape rendered as-is: id, name, code, version,
 *    entitlements, amount_minor/currency pair (or honest unavailable),
 *    sort_order, is_active.
 *  - An accessible create form (Plan name, Code, Version, Amount, Currency,
 *    Entitlements, Sort order) with client-side validation gating the
 *    disabled Create plan submit button.
 *  - Submit sequence: GET /sanctum/csrf-cookie, then POST /api/admin/plans
 *    with same-origin credentials, JSON headers and X-XSRF-TOKEN; the exact
 *    payload excludes id/is_active; after 201, an authoritative GET refetch
 *    renders only server-returned rows (no optimistic fabrication).
 *  - Inactive plans expose an Activate action behind an accessible
 *    confirmation dialog; Cancel makes no request; Confirm follows the same
 *    CSRF + POST /api/admin/plans/{id}/activate convention, then an
 *    authoritative refetch and an aria-live success announcement; a failed
 *    activation preserves prior row state and announces an error.
 *  - Active rows never render an Activate button.
 *
 * Every assertion below must fail against current production: there is no
 * resources/js/components/admin/pages/PlanManagementPage(.tsx) at all yet.
 * No production, routing, i18n, Storybook, backend or Git edits are made
 * from this file.
 */

const PLANS_ENDPOINT = '/api/admin/plans';
const CSRF_ENDPOINT = '/sanctum/csrf-cookie';

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function makePlans() {
    return [
        {
            id: 21,
            name: 'Starter',
            code: 'starter',
            version: 1,
            entitlements: ['1 brand', '3 locations'],
            amount_minor: 49900,
            currency: 'TRY',
            sort_order: 1,
            is_active: true,
        },
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

describe('PlanManagementPage — S1-WP01A platform plan management (PLATFORM_PLAN_MANAGEMENT_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=test-xsrf-token';
        fetchSpy = vi.fn(async (url: string) => {
            throw new Error(`Unhandled fetch in PlanManagementPage test: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC';
    });

    function listRegion() {
        return screen.getByRole('region', { name: /plans/i });
    }

    it('fetches /api/admin/plans on mount with credentials same-origin and Accept application/json', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<PlanManagementPage />);

        await waitFor(() => {
            const call = fetchSpy.mock.calls.find(
                ([calledUrl]) => String(calledUrl) === PLANS_ENDPOINT,
            );
            expect(call).toBeDefined();
        });

        const call = fetchSpy.mock.calls.find(
            ([calledUrl]) => String(calledUrl) === PLANS_ENDPOINT,
        );
        const init = call?.[1] as RequestInit | undefined;
        expect(init?.credentials).toBe('same-origin');
        expect(new Headers(init?.headers).get('Accept')).toBe('application/json');
    });

    it('renders an accessible loading state before the fetch resolves', () => {
        fetchSpy.mockImplementation(() => new Promise(() => {}));

        render(<PlanManagementPage />);

        expect(within(listRegion()).getByRole('status')).toHaveTextContent(/loading/i);
    });

    it('renders an honest empty state with zero fabricated rows', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, []);
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<PlanManagementPage />);

        await waitFor(() => {
            expect(within(listRegion()).getByRole('status')).toHaveTextContent(/no plan|empty/i);
        });

        expect(within(listRegion()).queryAllByRole('listitem')).toHaveLength(0);
    });

    it('renders exact server plan values including price and inactive status, never a client-invented plan', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<PlanManagementPage />);

        await waitFor(() => {
            expect(within(listRegion()).getByText('Starter')).toBeInTheDocument();
        });

        expect(within(listRegion()).getByText('starter')).toBeInTheDocument();
        expect(within(listRegion()).getByText('3 locations')).toBeInTheDocument();

        const enterpriseRow = within(listRegion())
            .getByText('Enterprise')
            .closest('li') as HTMLElement;
        expect(
            within(enterpriseRow).getByText(/price unavailable|unavailable|not available/i),
        ).toBeInTheDocument();
        expect(within(enterpriseRow).getByText(/inactive/i)).toBeInTheDocument();
    });

    it('renders an alert plus Retry on a non-OK response, and Retry repeats the exact same request', async () => {
        const user = userEvent.setup();
        let callCount = 0;

        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) {
                callCount += 1;
                if (callCount === 1) return jsonResponse(500, { message: 'Failed' });
                return jsonResponse(200, makePlans());
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<PlanManagementPage />);

        await waitFor(() => {
            expect(within(listRegion()).getByRole('alert')).toBeInTheDocument();
        });

        await user.click(within(listRegion()).getByRole('button', { name: /retry/i }));

        await waitFor(() => {
            expect(within(listRegion()).getByText('Starter')).toBeInTheDocument();
        });

        const plansCalls = fetchSpy.mock.calls.filter(
            ([calledUrl]) => String(calledUrl) === PLANS_ENDPOINT,
        );
        expect(plansCalls.length).toBe(2);
    });

    // Korunan niyet aynı: GEÇERSİZ girdi sunucuya gitmez.
    //
    // Değişen şey mekanizma. Öncesinde düğme geçerli olana kadar devre dışı
    // duruyordu ve kullanıcıya hangi alanın eksik olduğu SÖYLENMİYORDU —
    // `docs/44`'ün devre dışı kontrol standardının ve `docs/47` Kural 5'in
    // ihlali. Artık düğmeye basılabilir, form eksiği söyler ve odağı oraya
    // taşır.
    it('refuses to submit an incomplete plan, says which field is missing, and moves focus there', async () => {
        const user = userEvent.setup();
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, []);
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<PlanManagementPage />);
        await waitFor(() => {
            expect(within(listRegion()).getByRole('status')).toHaveTextContent(/no plan|empty/i);
        });

        const submit = screen.getByRole('button', { name: /create plan/i });
        expect(submit).toBeEnabled();

        const postCount = () =>
            fetchSpy.mock.calls.filter(
                (call) => (call[1] as RequestInit | undefined)?.method === 'POST',
            ).length;

        await user.click(submit);

        expect(await screen.findByText('Enter a plan name.')).toBeInTheDocument();
        expect(screen.getByText('Enter a plan code.')).toBeInTheDocument();
        expect(document.activeElement).toBe(screen.getByLabelText(/plan name/i));
        expect(postCount()).toBe(0);

        await user.type(screen.getByLabelText(/plan name/i), '  Growth  ');
        await user.type(screen.getByLabelText(/^code$/i), '  growth  ');
        await user.type(screen.getByLabelText(/version/i), '1');
        await user.type(screen.getByLabelText(/sort order/i), '0');

        // Tutar ve para birimi BİRLİKTE anlamlıdır; yarısı yarım bir kayıttır.
        await user.type(screen.getByLabelText(/amount/i), '1000');
        await user.click(submit);

        const pairMessages = screen.getAllByText(
            'Enter the amount and the currency together, or leave both empty.',
        );
        expect(pairMessages.length).toBeGreaterThanOrEqual(1);
        expect(postCount()).toBe(0);

        await user.type(screen.getByLabelText(/currency/i), 'try');
        await user.click(submit);

        expect(screen.getByText('Use a three-letter code, for example EUR.')).toBeInTheDocument();
        expect(postCount()).toBe(0);
    });

    it('submits create via csrf-cookie then POST with trimmed/typed payload excluding id and is_active, then authoritatively refetches', async () => {
        const user = userEvent.setup();
        let getCount = 0;

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const u = String(url);
            if (u === PLANS_ENDPOINT && (!init || init.method === undefined)) {
                getCount += 1;
                return jsonResponse(200, getCount === 1 ? [] : makePlans().slice(0, 1));
            }
            if (u === CSRF_ENDPOINT) return jsonResponse(204, null);
            if (u === PLANS_ENDPOINT && init?.method === 'POST') {
                return jsonResponse(201, makePlans()[0]);
            }
            throw new Error(`Unhandled fetch: ${u} ${init?.method ?? 'GET'}`);
        });

        render(<PlanManagementPage />);
        await waitFor(() => {
            expect(within(listRegion()).getByRole('status')).toHaveTextContent(/no plan|empty/i);
        });

        await user.type(screen.getByLabelText(/plan name/i), '  Starter  ');
        await user.type(screen.getByLabelText(/^code$/i), '  starter  ');
        await user.type(screen.getByLabelText(/version/i), '1');
        await user.type(screen.getByLabelText(/sort order/i), '1');
        await user.type(screen.getByLabelText(/entitlements/i), '1 brand\n\n3 locations\n');

        await user.click(screen.getByRole('button', { name: /create plan/i }));

        await waitFor(() => {
            const csrfCall = fetchSpy.mock.calls.find(
                ([calledUrl]) => String(calledUrl) === CSRF_ENDPOINT,
            );
            expect(csrfCall).toBeDefined();
        });

        const postCall = await waitFor(() => {
            const call = fetchSpy.mock.calls.find(
                ([calledUrl, callInit]) =>
                    String(calledUrl) === PLANS_ENDPOINT &&
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
        expect(payload).toEqual({
            name: 'Starter',
            code: 'starter',
            version: 1,
            entitlements: ['1 brand', '3 locations'],
            amount_minor: null,
            currency: null,
            sort_order: 1,
        });
        expect(payload).not.toHaveProperty('id');
        expect(payload).not.toHaveProperty('is_active');

        await waitFor(() => {
            expect(within(listRegion()).getByText('Starter')).toBeInTheDocument();
        });
        expect(getCount).toBe(2);
    });

    it('opens an accessible Activate confirmation dialog identifying the plan; Cancel issues no request', async () => {
        const user = userEvent.setup();
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<PlanManagementPage />);
        await waitFor(() => {
            expect(within(listRegion()).getByText('Enterprise')).toBeInTheDocument();
        });

        const enterpriseRow = within(listRegion())
            .getByText('Enterprise')
            .closest('li') as HTMLElement;
        expect(
            within(enterpriseRow).queryByRole('button', { name: /activate/i }),
        ).toBeInTheDocument();

        const starterRow = within(listRegion()).getByText('Starter').closest('li') as HTMLElement;
        expect(
            within(starterRow).queryByRole('button', { name: /activate/i }),
        ).not.toBeInTheDocument();

        await user.click(within(enterpriseRow).getByRole('button', { name: /activate/i }));

        const dialog = screen.getByRole('dialog', { name: /activate/i });
        expect(within(dialog).getByText('Enterprise')).toBeInTheDocument();
        expect(within(dialog).getByText('enterprise')).toBeInTheDocument();
        expect(
            within(dialog).getByText(/price unavailable|unavailable|not available/i),
        ).toBeInTheDocument();

        const callsBeforeCancel = fetchSpy.mock.calls.length;
        await user.click(within(dialog).getByRole('button', { name: /cancel/i }));

        expect(fetchSpy.mock.calls.length).toBe(callsBeforeCancel);
        expect(screen.queryByRole('dialog', { name: /activate/i })).not.toBeInTheDocument();
    });

    it('confirms Activate via csrf-cookie then POST /api/admin/plans/{id}/activate, refetches authoritatively, and announces success', async () => {
        const user = userEvent.setup();
        let getCount = 0;
        const activatedPlans = [{ ...makePlans()[0] }, { ...makePlans()[1], is_active: true }];

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const u = String(url);
            if (u === PLANS_ENDPOINT && init?.method === undefined) {
                getCount += 1;
                return jsonResponse(200, getCount === 1 ? makePlans() : activatedPlans);
            }
            if (u === CSRF_ENDPOINT) return jsonResponse(204, null);
            if (u === '/api/admin/plans/22/activate' && init?.method === 'POST') {
                return jsonResponse(200, activatedPlans[1]);
            }
            throw new Error(`Unhandled fetch: ${u} ${init?.method ?? 'GET'}`);
        });

        render(<PlanManagementPage />);
        await waitFor(() => {
            expect(within(listRegion()).getByText('Enterprise')).toBeInTheDocument();
        });

        const enterpriseRow = within(listRegion())
            .getByText('Enterprise')
            .closest('li') as HTMLElement;
        await user.click(within(enterpriseRow).getByRole('button', { name: /activate/i }));

        const dialog = screen.getByRole('dialog', { name: /activate/i });
        await user.click(within(dialog).getByRole('button', { name: /confirm|activate/i }));

        await waitFor(() => {
            const activateCall = fetchSpy.mock.calls.find(
                ([calledUrl]) => String(calledUrl) === '/api/admin/plans/22/activate',
            );
            expect(activateCall).toBeDefined();
        });

        const [, activateInit] = fetchSpy.mock.calls.find(
            ([calledUrl]) => String(calledUrl) === '/api/admin/plans/22/activate',
        ) as [string, RequestInit];
        expect(activateInit.credentials).toBe('same-origin');
        expect(new Headers(activateInit.headers).get('X-XSRF-TOKEN')).toBe('test-xsrf-token');

        await waitFor(() => {
            const row = within(listRegion()).getByText('Enterprise').closest('li') as HTMLElement;
            expect(
                within(row).queryByRole('button', { name: /activate/i }),
            ).not.toBeInTheDocument();
        });

        expect(getCount).toBe(2);
        expect(screen.getByRole('status', { name: /activate|success/i })).toHaveTextContent(
            /activat/i,
        );
    });

    /**
     * PLATFORM_PLAN_MANAGEMENT_FRONTEND_CORRECTION_RED
     *
     * Reviewer finding 1: a non-OK CSRF bootstrap must block the POST and
     * announce the existing 'We could not create the plan' error (i18n key
     * platform.plans.form.error) accessibly. Current production's
     * bootstrapCsrfCookie() never inspects the csrf-cookie response status
     * and PlanForm/PlanManagementPage never render that error at all, so
     * this must fail against current production.
     */
    it('blocks Create submission and accessibly announces the create error when CSRF bootstrap responds non-OK', async () => {
        const user = userEvent.setup();

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const u = String(url);
            if (u === PLANS_ENDPOINT && init?.method === undefined) return jsonResponse(200, []);
            if (u === CSRF_ENDPOINT) return jsonResponse(500, { message: 'csrf unavailable' });
            if (u === PLANS_ENDPOINT && init?.method === 'POST') {
                throw new Error(
                    'POST /api/admin/plans must not be issued when CSRF bootstrap is non-OK',
                );
            }
            throw new Error(`Unhandled fetch: ${u} ${init?.method ?? 'GET'}`);
        });

        render(<PlanManagementPage />);
        await waitFor(() => {
            expect(within(listRegion()).getByRole('status')).toHaveTextContent(/no plan|empty/i);
        });

        await user.type(screen.getByLabelText(/plan name/i), 'Starter');
        await user.type(screen.getByLabelText(/^code$/i), 'starter');
        await user.type(screen.getByLabelText(/version/i), '1');
        await user.type(screen.getByLabelText(/sort order/i), '1');

        await user.click(screen.getByRole('button', { name: /create plan/i }));

        const errorNode = await screen.findByText(/we could not create the plan/i);
        expect(errorNode.closest('[role="alert"], [role="status"]')).not.toBeNull();

        const postCalls = fetchSpy.mock.calls.filter(
            ([calledUrl, callInit]) =>
                String(calledUrl) === PLANS_ENDPOINT &&
                (callInit as RequestInit | undefined)?.method === 'POST',
        );
        expect(postCalls.length).toBe(0);
    });

    /**
     * PLATFORM_PLAN_MANAGEMENT_FRONTEND_CORRECTION_RED
     *
     * Reviewer finding 1 (continued): a non-OK POST after a successful CSRF
     * bootstrap must also announce the same accessible create error.
     */
    it('accessibly announces the create error when the POST response is non-OK', async () => {
        const user = userEvent.setup();

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const u = String(url);
            if (u === PLANS_ENDPOINT && init?.method === undefined) return jsonResponse(200, []);
            if (u === CSRF_ENDPOINT) return jsonResponse(204, null);
            if (u === PLANS_ENDPOINT && init?.method === 'POST')
                return jsonResponse(422, { message: 'invalid' });
            throw new Error(`Unhandled fetch: ${u} ${init?.method ?? 'GET'}`);
        });

        render(<PlanManagementPage />);
        await waitFor(() => {
            expect(within(listRegion()).getByRole('status')).toHaveTextContent(/no plan|empty/i);
        });

        await user.type(screen.getByLabelText(/plan name/i), 'Starter');
        await user.type(screen.getByLabelText(/^code$/i), 'starter');
        await user.type(screen.getByLabelText(/version/i), '1');
        await user.type(screen.getByLabelText(/sort order/i), '1');

        await user.click(screen.getByRole('button', { name: /create plan/i }));

        const errorNode = await screen.findByText(/we could not create the plan/i);
        expect(errorNode.closest('[role="alert"], [role="status"]')).not.toBeNull();
    });

    /**
     * PLATFORM_PLAN_MANAGEMENT_FRONTEND_CORRECTION_RED
     *
     * Reviewer finding 2: opening the Activate dialog must move focus into
     * it, Tab/Shift+Tab must stay trapped within its focusable elements, and
     * Escape must close the dialog and return focus to the exact Activate
     * trigger that opened it. Current PlanActivationDialog manages no focus
     * and has no keydown handling at all, so this must fail against current
     * production.
     */
    it('traps focus in the Activate dialog and Escape returns focus to the triggering Activate button', async () => {
        const user = userEvent.setup();
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<PlanManagementPage />);
        await waitFor(() => {
            expect(within(listRegion()).getByText('Enterprise')).toBeInTheDocument();
        });

        const enterpriseRow = within(listRegion())
            .getByText('Enterprise')
            .closest('li') as HTMLElement;
        const activateTrigger = within(enterpriseRow).getByRole('button', { name: /activate/i });
        activateTrigger.focus();
        expect(document.activeElement).toBe(activateTrigger);

        await user.click(activateTrigger);

        const dialog = screen.getByRole('dialog', { name: /activate/i });
        expect(dialog.contains(document.activeElement)).toBe(true);

        const cancelButton = within(dialog).getByRole('button', { name: /cancel/i });
        const confirmButton = within(dialog).getByRole('button', { name: /confirm|activate/i });

        (document.activeElement as HTMLElement).blur();
        cancelButton.focus();

        await user.tab();
        expect(document.activeElement).toBe(confirmButton);

        await user.tab();
        expect(document.activeElement).toBe(cancelButton);

        await user.tab({ shift: true });
        expect(document.activeElement).toBe(confirmButton);

        await user.keyboard('{Escape}');

        expect(screen.queryByRole('dialog', { name: /activate/i })).not.toBeInTheDocument();
        expect(document.activeElement).toBe(activateTrigger);
    });
});
