import type React from 'react';
import { beforeEach, describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Regression contract for three visible AdminShell destinations added in
 * S1-WP05a: #analytics -> AnalyticsPage, #team -> TeamPage,
 * #billing -> BillingPage. This suite established RED before those
 * destinations existed in WorkspaceApp; it now serves as the GREEN
 * regression contract for their implementation.
 */

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;
const TEAM_MEMBERS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/members`;
const TEAM_INVITATIONS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/invitations`;
const PLANS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/plans`;
const BREAKPOINT_TOKEN = /(?:^|\s)(sm|md|lg|xl|2xl):/;
const RESPONSIVE_WORDING = /\bresponsive\b/i;

function makePlans() {
    return [
        {
            id: 21,
            name: 'Zabuno Test Plan',
            code: 'wp05-test-plan',
            entitlements: ['Test entitlement'],
            amount_minor: 99900,
            currency: 'TRY',
        },
    ];
}

function importWorkspaceModule<
    T extends Record<string, unknown> = Record<string, unknown>,
>(): Promise<T> {
    return import('./WorkspaceApp') as unknown as Promise<T>;
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function makeUser() {
    return { id: 1, name: 'Ada Lovelace', email: 'ada@example.com' };
}

function makeWorkspace() {
    return {
        id: WORKSPACE_ID,
        name: 'Zeytin Restoranları',
        slug: 'zeytin-restoranlari',
        state: 'active',
    };
}

function makeLocation() {
    return {
        id: 923,
        workspace_id: WORKSPACE_ID,
        brand_id: 811,
        display_name: 'Kadıköy',
        country_code: 'TR',
        city: 'İstanbul',
        address_line1: 'Bahariye Cd. 1',
        address_line2: null,
        postal_code: null,
    };
}

function buildFetchMock() {
    return async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();

        if (String(url) === CSRF_COOKIE_URL) {
            return jsonResponse(204, {});
        }
        if (String(url) === '/api/user' && method === 'GET') {
            return jsonResponse(200, makeUser());
        }
        if (String(url) === '/api/workspaces' && method === 'GET') {
            return jsonResponse(200, [makeWorkspace()]);
        }
        if (String(url) === '/api/workspace-context' && method === 'GET') {
            return jsonResponse(200, makeWorkspace());
        }
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand` && method === 'GET') {
            return jsonResponse(200, { id: 811, workspace_id: WORKSPACE_ID, name: 'Zeytin' });
        }
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations` && method === 'GET') {
            return jsonResponse(200, [makeLocation()]);
        }
        if (
            String(url) ===
                `/api/workspaces/${WORKSPACE_ID}/brand/locations/923/analytics/summary?range=today` &&
            method === 'GET'
        ) {
            return jsonResponse(200, {
                qrResolveCount: 0,
                menuOpenCount: 0,
            });
        }
        if (String(url) === TEAM_MEMBERS_ENDPOINT && method === 'GET') {
            return jsonResponse(200, []);
        }
        if (String(url) === TEAM_INVITATIONS_ENDPOINT && method === 'GET') {
            return jsonResponse(200, []);
        }
        if (String(url) === PLANS_ENDPOINT && method === 'GET') {
            return jsonResponse(200, makePlans());
        }

        throw new Error(`Unhandled fetch in WorkspaceApp wp05 RED test: ${method} ${String(url)}`);
    };
}

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', {
        configurable: true,
        writable: true,
        value: width,
    });
    Object.defineProperty(window, 'innerHeight', {
        configurable: true,
        writable: true,
        value: height,
    });
    window.dispatchEvent(new Event('resize'));
}

function allClassAttributes(container: HTMLElement): string[] {
    return Array.from(container.querySelectorAll('[class]')).map(
        (el) => el.getAttribute('class') ?? '',
    );
}

function allTextContent(container: HTMLElement): string {
    return container.textContent ?? '';
}

async function renderCurrentWorkspace() {
    const originalFetch = window.fetch;
    Object.defineProperty(window, 'fetch', {
        configurable: true,
        writable: true,
        value: buildFetchMock(),
    });

    const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>();
    const rendered = render(<WorkspaceApp />);

    await screen.findByRole('navigation', { name: 'Restaurant admin' });

    return {
        ...rendered,
        restoreFetch: () => {
            window.fetch = originalFetch;
        },
    };
}

describe('WorkspaceApp — Analytics/Team/Billing AdminShell destinations (S1-WP05a, RED)', () => {
    beforeEach(() => {
        history.replaceState(null, '', window.location.pathname);
        setViewport(320, 480);
    });

    it('exposes accessible Analytics, Team, and Billing nav links pointing at #analytics, #team, #billing', async () => {
        const { restoreFetch } = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        expect(within(nav).getByRole('link', { name: 'Analytics' })).toHaveAttribute(
            'href',
            '#analytics',
        );
        expect(within(nav).getByRole('link', { name: 'Team' })).toHaveAttribute('href', '#team');
        expect(within(nav).getByRole('link', { name: 'Billing' })).toHaveAttribute(
            'href',
            '#billing',
        );

        restoreFetch();
    });

    it('#analytics renders AnalyticsPage with a heading, a range control, and the real zero-count summary metric result', async () => {
        const user = userEvent.setup();
        const { restoreFetch } = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Analytics' }));

        const main = screen.getByRole('main');
        expect(main.querySelector('#section-analytics')).not.toBeNull();

        const analyticsRegion = main.querySelector('#section-analytics') as HTMLElement;

        expect(
            within(analyticsRegion).getByRole('heading', { name: /Analytics/i }),
        ).toBeInTheDocument();

        expect(
            within(analyticsRegion).getByRole('combobox', { name: /range/i }) ??
                within(analyticsRegion).getByRole('group', { name: /range/i }),
        ).toBeTruthy();

        const metricRegion = within(analyticsRegion).getByRole('region', {
            name: /metric|report/i,
        });
        expect(metricRegion).toBeInTheDocument();

        expect(await within(metricRegion).findAllByText('0')).toHaveLength(2);

        const regionText = allTextContent(analyticsRegion);
        expect(regionText).not.toMatch(/not available|unavailable|fabricated/i);

        restoreFetch();
    });

    it('#team renders TeamPage with a heading, invite email input, an Editor-only invitation control (no Owner invite control), a member region, and an Invite action gated on a valid email', async () => {
        const user = userEvent.setup();
        const { restoreFetch } = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Team' }));

        const main = screen.getByRole('main');
        expect(main.querySelector('#section-team')).not.toBeNull();

        const teamRegion = main.querySelector('#section-team') as HTMLElement;

        expect(within(teamRegion).getByRole('heading', { name: /Team/i })).toBeInTheDocument();

        expect(within(teamRegion).getByLabelText(/invite.*email/i)).toBeInTheDocument();

        expect(within(teamRegion).queryByRole('radio', { name: /Owner/i })).not.toBeInTheDocument();

        expect(within(teamRegion).getByRole('region', { name: /member/i })).toBeInTheDocument();

        const inviteButton = within(teamRegion).getByRole('button', { name: /invite/i });
        expect(inviteButton).toBeDisabled();

        const emailInput = within(teamRegion).getByLabelText(/invite.*email/i);
        await user.type(emailInput, 'valid@example.test');
        expect(inviteButton).toBeEnabled();

        restoreFetch();
    });

    it('#billing renders BillingPage with a heading, plan/current-plan/manual-payment regions, the server-returned plan with its exact derived price, an honest current-plan load-failure alert with an enabled Retry, disabled manual-payment actions, and an honest Iyzico subscription-status alert with enabled Retry and disabled Start sandbox checkout', async () => {
        const user = userEvent.setup();
        const { restoreFetch } = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Billing' }));

        const main = screen.getByRole('main');
        expect(main.querySelector('#section-billing')).not.toBeNull();

        const billingRegion = main.querySelector('#section-billing') as HTMLElement;

        expect(
            within(billingRegion).getByRole('heading', { name: /Billing/i }),
        ).toBeInTheDocument();

        const planRegion = within(billingRegion).getByRole('region', { name: /^plan$/i });
        const currentPlanRegion = within(billingRegion).getByRole('region', {
            name: /current.plan/i,
        });
        const manualPaymentRegion = within(billingRegion).getByRole('region', {
            name: /manual.payment/i,
        });
        expect(currentPlanRegion).toBeInTheDocument();
        expect(manualPaymentRegion).toBeInTheDocument();

        await within(planRegion).findByText('Zabuno Test Plan');
        expect(within(planRegion).getByText('wp05-test-plan')).toBeInTheDocument();
        expect(within(planRegion).getByText('Test entitlement')).toBeInTheDocument();
        expect(within(planRegion).getByText('999,00 TRY')).toBeInTheDocument();

        // The mock intentionally leaves the subscription endpoint unhandled,
        // so Current plan honestly reports the load failure with a retry
        // instead of silently disabling every action.
        expect(within(currentPlanRegion).getByRole('alert')).toHaveTextContent(
            'We could not load the current plan.',
        );
        expect(within(currentPlanRegion).getByRole('button', { name: /retry/i })).toBeEnabled();

        for (const button of within(manualPaymentRegion).queryAllByRole('button')) {
            expect(button).toBeDisabled();
        }

        const iyzicoRegion = within(billingRegion).getByRole('region', { name: /iyzico sandbox/i });
        expect(within(iyzicoRegion).getByRole('alert')).toHaveTextContent(
            'Could not load your subscription status.',
        );
        expect(within(iyzicoRegion).getByRole('button', { name: /retry/i })).toBeEnabled();
        expect(
            within(iyzicoRegion).getByRole('button', { name: /start sandbox checkout/i }),
        ).toBeDisabled();

        restoreFetch();
    });

    it('keeps Analytics, Team, and Billing destinations reachable at a simulated 320x480 CSS px viewport with fluid intrinsic layout and no breakpoint-prefixed classes or responsive wording', async () => {
        const user = userEvent.setup();
        const { container, restoreFetch } = await renderCurrentWorkspace();

        setViewport(320, 480);

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        for (const [label, hash] of [
            ['Analytics', '#analytics'],
            ['Team', '#team'],
            ['Billing', '#billing'],
        ] as const) {
            await user.click(within(nav).getByRole('link', { name: label }));

            const main = screen.getByRole('main');
            // Hash bir ROTA, kap id'si ise bir ELEMAN kimliğidir; ikisi
            // artık kasten ayrıdır. Aynı olmaları tarayıcının o elemana
            // kaydırmasına ve gezinti tıklamasında sayfanın sıçramasına yol
            // açıyordu.
            const destination = main.querySelector(`#section-${hash.slice(1)}`);
            expect(destination).not.toBeNull();

            for (const classAttribute of allClassAttributes(destination as HTMLElement)) {
                expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
            }

            expect(allTextContent(destination as HTMLElement)).not.toMatch(RESPONSIVE_WORDING);
        }

        for (const classAttribute of allClassAttributes(container)) {
            expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
        }

        restoreFetch();
    });

    it('shows a shared page-frame description and honest status badge on Analytics, distinct Pending invitations/Team members regions on Team, and canonical disabled manual-payment fields plus a disabled Iyzico Start sandbox checkout action with an honest subscription-status alert on Billing (S1-WP05a page-frame batch, RED)', async () => {
        const user = userEvent.setup();
        const { restoreFetch } = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        // Analytics: a page-frame description plus an honest status badge
        // reflecting the real fetch/range status, not a fabricated label.
        await user.click(within(nav).getByRole('link', { name: 'Analytics' }));
        const analyticsRegion = screen
            .getByRole('main')
            .querySelector('#section-analytics') as HTMLElement;
        await within(analyticsRegion).findAllByText('0');
        expect(
            within(analyticsRegion).getByText(/qr resolve and confirmed menu open/i),
        ).toBeInTheDocument();
        expect(within(analyticsRegion).getByTestId('flowbite-badge')).toHaveTextContent(/today/i);

        // Team: grouped Invite surface plus two DISTINCT regions — Pending
        // invitations and Team members — each with its own honest
        // unavailable status, not one shared "members" region.
        await user.click(within(nav).getByRole('link', { name: 'Team' }));
        const teamRegion = screen.getByRole('main').querySelector('#section-team') as HTMLElement;
        expect(
            within(teamRegion).getByRole('region', { name: /pending invitation/i }),
        ).toBeInTheDocument();
        expect(
            within(teamRegion).getByRole('region', { name: /^team members$/i }),
        ).toBeInTheDocument();

        // Billing: manual payment carries the canonical Stage 1 fields —
        // plan assignment, end date, payment note, document reference —
        // all disabled and empty, plus a disabled Iyzico sandbox region.
        await user.click(within(nav).getByRole('link', { name: 'Billing' }));
        const billingRegion = screen
            .getByRole('main')
            .querySelector('#section-billing') as HTMLElement;
        const manualPaymentRegion = within(billingRegion).getByRole('region', {
            name: /manual.payment/i,
        });

        for (const fieldName of [
            /plan assignment/i,
            /end date/i,
            /payment note/i,
            /document reference/i,
        ]) {
            const field = within(manualPaymentRegion).getByLabelText(fieldName);
            expect(field).toBeDisabled();
            expect(field).toHaveValue('');
        }

        const iyzicoRegion = within(billingRegion).getByRole('region', { name: /iyzico sandbox/i });
        expect(
            within(iyzicoRegion).getByRole('button', { name: /start sandbox checkout/i }),
        ).toBeDisabled();
        expect(within(iyzicoRegion).getByRole('alert')).toHaveTextContent(
            'Could not load your subscription status.',
        );

        restoreFetch();
    });
});
