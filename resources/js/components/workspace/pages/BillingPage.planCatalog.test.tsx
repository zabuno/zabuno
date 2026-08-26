import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { BillingPage } from './BillingPage';

/**
 * BILLING_PLAN_CATALOG_FRONTEND_RED
 *
 * RED suite for the S1-WP05 Billing "Plan" region. BillingPage today
 * renders that region as a permanently disabled UnavailableRegion — no
 * plan fetch, no server-authoritative plan names/codes/entitlements/price,
 * no loading/empty/error/retry lifecycle. This suite freezes the real
 * contract instead:
 *
 *  - GET /api/workspaces/{workspaceId}/plans on mount, credentials
 *    same-origin, Accept: application/json.
 *  - An accessible loading status before the fetch resolves.
 *  - Success renders exact server-returned plan name/code/entitlement
 *    labels; when amount_minor + currency are present, an exact derived
 *    price is shown; never a locally invented plan.
 *  - A plan with no published amount_minor renders an honest
 *    price-unavailable label, not a fabricated number.
 *  - An empty list renders an honest empty state, zero fabricated rows.
 *  - Non-OK / network failure renders an alert plus a Retry control, and
 *    Retry repeats the exact same request.
 *  - Current plan, Manual payment and Iyzico sandbox regions remain
 *    untouched: still disabled, honest UnavailableRegions.
 *
 * Every assertion below must fail against current production: BillingPage
 * issues no fetch at all and the Plan region is a static UnavailableRegion
 * with a permanently disabled button. No production, i18n, Storybook,
 * backend or Git edits are made from this file.
 */

const WORKSPACE_ID = 34;
const PLANS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/plans`;

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
            entitlements: ['Unlimited menus', 'Priority support'],
            amount_minor: 149900,
            currency: 'TRY',
        },
        {
            id: 12,
            name: 'Custom',
            code: 'custom',
            entitlements: ['Dedicated success manager'],
            amount_minor: null,
            currency: null,
        },
    ];
}

describe('BillingPage — S1-WP05 Plan catalog (BILLING_PLAN_CATALOG_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn(async (url: string) => {
            throw new Error(`Unhandled fetch in BillingPage plan catalog test: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    function planRegion() {
        return screen.getByRole('region', { name: 'Plan' });
    }

    it('fetches the workspace plan catalog on mount with credentials same-origin and Accept application/json', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

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
        const headers = new Headers(init?.headers);
        expect(headers.get('Accept')).toBe('application/json');
    });

    it('renders an accessible loading state for the Plan region before the fetch resolves', () => {
        fetchSpy.mockImplementation(() => new Promise(() => {}));

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        expect(within(planRegion()).getByRole('status')).toHaveTextContent(/loading/i);
    });

    it('renders exact server plan names, codes, entitlements and a derived price for a plan with a published amount', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(planRegion()).getByText('Growth')).toBeInTheDocument();
        });

        expect(within(planRegion()).getByText('growth')).toBeInTheDocument();
        expect(within(planRegion()).getByText('Unlimited menus')).toBeInTheDocument();
        expect(within(planRegion()).getByText('Priority support')).toBeInTheDocument();
        // Fiyat okuyucunun diline göre biçimlenir (CORE-12, `docs/13` §4);
        // jsdom belgesinin dili boş olduğundan taban locale `en` uygulanır.
        expect(within(planRegion()).getByText('TRY 1,499.00')).toBeInTheDocument();
    });

    it('renders an honest price-unavailable label for a plan with no published amount_minor, never a fabricated number', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(planRegion()).getByText('Custom')).toBeInTheDocument();
        });

        const customCard = within(planRegion()).getByText('Custom').closest('li') as HTMLElement;
        expect(
            within(customCard).getByText(/price unavailable|not available|unavailable/i),
        ).toBeInTheDocument();
        expect(within(customCard).queryByText(/^\d/)).not.toBeInTheDocument();
    });

    it('renders an honest empty state with zero fabricated plan rows', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, []);
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(planRegion()).getByRole('status')).toHaveTextContent(/no plan|empty/i);
        });

        expect(within(planRegion()).queryAllByRole('listitem')).toHaveLength(0);
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

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(planRegion()).getByRole('alert')).toBeInTheDocument();
        });

        const retryButton = within(planRegion()).getByRole('button', { name: /retry/i });
        await user.click(retryButton);

        await waitFor(() => {
            expect(within(planRegion()).getByText('Growth')).toBeInTheDocument();
        });

        const plansCalls = fetchSpy.mock.calls.filter(
            ([calledUrl]) => String(calledUrl) === PLANS_ENDPOINT,
        );
        expect(plansCalls.length).toBe(2);
        for (const [, init] of plansCalls) {
            const requestInit = init as RequestInit | undefined;
            expect(requestInit?.credentials).toBe('same-origin');
            expect(new Headers(requestInit?.headers).get('Accept')).toBe('application/json');
        }
    });

    it('renders an alert plus Retry on a network failure', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) throw new TypeError('Failed to fetch');
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(planRegion()).getByRole('alert')).toBeInTheDocument();
        });

        expect(within(planRegion()).getByRole('button', { name: /retry/i })).toBeInTheDocument();
    });

    it('keeps Current plan, Manual payment and Iyzico sandbox regions honest and disabled', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(planRegion()).getByText('Growth')).toBeInTheDocument();
        });

        expect(screen.getByRole('region', { name: 'Current plan' })).toBeInTheDocument();

        const manualPaymentRegion = screen.getByRole('region', { name: 'Manual payment' });
        expect(
            within(manualPaymentRegion).getByRole('button', { name: /record payment/i }),
        ).toBeDisabled();

        const iyzicoRegion = screen.getByRole('region', { name: 'Iyzico sandbox' });
        expect(
            within(iyzicoRegion).getByRole('button', { name: /start sandbox checkout/i }),
        ).toBeDisabled();
    });
});
