import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { BillingPage } from './BillingPage';

/**
 * BILLING_SUBSCRIPTION_FRONTEND_RED
 *
 * RED suite for the S1-WP01A tenant Billing "Current plan" region
 * (read-only subscription view for the manual-payment frontend-first
 * package). Current production renders "Current plan" as a static,
 * permanently-empty UnavailableRegion with zero network call — this suite
 * freezes the real contract instead. Every assertion below must fail
 * against current production.
 *
 * Frozen contract:
 *  - GET /api/workspaces/{workspaceId}/subscription, credentials
 *    same-origin, Accept application/json; honest loading, none, active,
 *    and error/retry states from exact server plan code/name/version and
 *    end date — never fabricated.
 *  - Current subscription is read-only: no active manual-payment mutation
 *    control in the tenant UI; copy explains that platform finance records
 *    manual payments.
 *  - The Iyzico sandbox region remains honest and independently
 *    server-backed, untouched by the subscription contract.
 *  - No fake plan, workspace, price, dates, or IDs anywhere.
 */

const WORKSPACE_ID = 34;
const SUBSCRIPTION_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/subscription`;
const PLANS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/plans`;
const IYZICO_SESSION_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/iyzico-sandbox/session`;

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
            id: 11,
            name: 'Growth',
            code: 'growth',
            entitlements: ['Unlimited menus'],
            amount_minor: 149900,
            currency: 'TRY',
        },
    ];
}

function makeNoneSubscription() {
    return { state: 'none' };
}

function makeActiveSubscription() {
    return {
        state: 'active',
        plan_code: 'growth',
        plan_name: 'Growth',
        plan_version: 3,
        ends_at: '2026-12-31',
    };
}

describe('BillingPage — S1-WP01A tenant Current plan / subscription (BILLING_SUBSCRIPTION_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn(async (url: string) => {
            throw new Error(`Unhandled fetch in BillingPage subscription test: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    function currentPlanRegion() {
        return screen.getByRole('region', { name: /current plan/i });
    }

    it('fetches the workspace subscription on mount with credentials same-origin and Accept application/json', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            if (String(url) === SUBSCRIPTION_ENDPOINT)
                return jsonResponse(200, makeNoneSubscription());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            const call = fetchSpy.mock.calls.find(
                ([calledUrl]) => String(calledUrl) === SUBSCRIPTION_ENDPOINT,
            );
            expect(call).toBeDefined();
        });

        const call = fetchSpy.mock.calls.find(
            ([calledUrl]) => String(calledUrl) === SUBSCRIPTION_ENDPOINT,
        );
        const init = call?.[1] as RequestInit | undefined;
        expect(init?.credentials).toBe('same-origin');
        expect(new Headers(init?.headers).get('Accept')).toBe('application/json');
    });

    it('renders an accessible loading state for Current plan before the fetch resolves', () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            if (String(url) === SUBSCRIPTION_ENDPOINT) return new Promise(() => {});
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        expect(within(currentPlanRegion()).getByRole('status')).toHaveTextContent(/loading/i);
    });

    it('renders an honest none state with zero fabricated plan/date values', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            if (String(url) === SUBSCRIPTION_ENDPOINT)
                return jsonResponse(200, makeNoneSubscription());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(currentPlanRegion()).getByRole('status')).toHaveTextContent(
                /no active subscription|none/i,
            );
        });

        expect(
            within(currentPlanRegion()).queryByText(/\d{4}-\d{2}-\d{2}/),
        ).not.toBeInTheDocument();
    });

    it('renders the exact server plan code/name/version and end date for an active subscription', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            if (String(url) === SUBSCRIPTION_ENDPOINT)
                return jsonResponse(200, makeActiveSubscription());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(currentPlanRegion()).getByText('growth')).toBeInTheDocument();
        });

        expect(within(currentPlanRegion()).getByText('Growth')).toBeInTheDocument();
        expect(within(currentPlanRegion()).getByText(/version\s*3|v3/i)).toBeInTheDocument();
        expect(within(currentPlanRegion()).getByText('2026-12-31')).toBeInTheDocument();
    });

    it('renders an alert plus Retry on a non-OK subscription response, and Retry adds exactly one more request', async () => {
        const user = userEvent.setup();
        let callCount = 0;

        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            if (String(url) === IYZICO_SESSION_ENDPOINT)
                return jsonResponse(200, { state: 'none' });
            if (String(url) === SUBSCRIPTION_ENDPOINT) {
                callCount += 1;
                if (callCount === 1) return jsonResponse(500, { message: 'Failed' });
                return jsonResponse(200, makeActiveSubscription());
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(currentPlanRegion()).getByRole('alert')).toBeInTheDocument();
        });

        const subscriptionCallsBeforeRetry = fetchSpy.mock.calls.filter(
            ([calledUrl]) => String(calledUrl) === SUBSCRIPTION_ENDPOINT,
        ).length;

        await user.click(within(currentPlanRegion()).getByRole('button', { name: /retry/i }));

        await waitFor(() => {
            expect(within(currentPlanRegion()).getByText('Growth')).toBeInTheDocument();
        });

        // Two independent production consumers (CurrentSubscriptionStatus and
        // IyzicoSandboxCheckout) each fetch the subscription on initial mount,
        // then the Current-plan Retry click adds exactly one more request.
        const subscriptionCalls = fetchSpy.mock.calls.filter(
            ([calledUrl]) => String(calledUrl) === SUBSCRIPTION_ENDPOINT,
        );
        expect(subscriptionCalls.length).toBe(3);
        expect(subscriptionCalls.length - subscriptionCallsBeforeRetry).toBe(1);
    });

    it('never offers an active manual-payment mutation control and explains that platform finance records manual payments', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            if (String(url) === SUBSCRIPTION_ENDPOINT)
                return jsonResponse(200, makeActiveSubscription());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(currentPlanRegion()).getByText('Growth')).toBeInTheDocument();
        });

        const manualPaymentRegion = screen.getByRole('region', { name: /manual payment/i });
        const activeMutationButtons = within(manualPaymentRegion)
            .queryAllByRole('button')
            .filter((button) => !button.hasAttribute('disabled'));
        expect(activeMutationButtons).toHaveLength(0);
        expect(within(manualPaymentRegion).getByText(/platform finance/i)).toBeInTheDocument();
    });

    it('shows the Iyzico sandbox region as ready with an enabled Start once the sandbox session endpoint reports state:none', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            if (String(url) === SUBSCRIPTION_ENDPOINT)
                return jsonResponse(200, makeActiveSubscription());
            if (String(url) === IYZICO_SESSION_ENDPOINT)
                return jsonResponse(200, { state: 'none' });
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(currentPlanRegion()).getByText('Growth')).toBeInTheDocument();
        });

        const iyzicoRegion = screen.getByRole('region', { name: /iyzico sandbox/i });

        await waitFor(() => {
            expect(within(iyzicoRegion).getByText(/ready/i)).toBeInTheDocument();
        });

        expect(
            within(iyzicoRegion).getByRole('button', { name: /start sandbox checkout/i }),
        ).toBeEnabled();
    });

    it('honestly states the live/server-backed copy and status badge instead of the stale blanket "not connected" claim', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            if (String(url) === SUBSCRIPTION_ENDPOINT)
                return jsonResponse(200, makeActiveSubscription());
            if (String(url) === IYZICO_SESSION_ENDPOINT)
                return jsonResponse(200, { state: 'none' });
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(currentPlanRegion()).getByText('Growth')).toBeInTheDocument();
        });

        const page = document.getElementById('section-billing') ?? document.body;

        expect(within(page).getByText(/plan catalog/i)).toHaveTextContent(/live|server/i);
        expect(page).toHaveTextContent(
            /current (plan|subscription).{0,120}(live|server)|(?:live|server).{0,120}current (plan|subscription)/i,
        );
        expect(within(page).getByText(/platform finance/i)).toBeInTheDocument();

        expect(
            screen.queryByText(
                /current plan,\s*manual payment record,\s*and iyzico sandbox are not connected to a real billing api yet/i,
            ),
        ).not.toBeInTheDocument();

        expect(screen.queryByText(/^not connected$/i)).not.toBeInTheDocument();

        const iyzicoRegion = screen.getByRole('region', { name: /iyzico sandbox/i });

        await waitFor(() => {
            expect(
                within(iyzicoRegion).getByText(/no real money|not real money|no real payment/i),
            ).toBeInTheDocument();
        });

        expect(within(iyzicoRegion).getByText(/ready/i)).toBeInTheDocument();
        expect(
            within(iyzicoRegion).getByRole('button', { name: /start sandbox checkout/i }),
        ).toBeEnabled();
    });
});
