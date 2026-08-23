import { describe, beforeEach, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * RED test freezing the contract for the standalone authenticated
 * Platform Admin shell (S1-WP01A follow-up). There is no
 * resources/js/components/platform/PlatformApp(.tsx) in production yet, so
 * every assertion below must fail against current production.
 *
 * Frozen contract:
 * - PlatformApp exposes an accessible "Platform administration" landmark
 *   (heading and/or labelled region) distinct from the tenant admin shell.
 * - A "Plans" navigation item is selected/current for the #plans (and
 *   default, no-hash) location.
 * - A safe link back to the tenant workspace app (/app) is present.
 * - The real PlanManagementPage is rendered inside — its plan list network
 *   call (GET /api/admin/plans) fires and its rendered rows come from that
 *   mocked response, not invented data.
 * - No responsive breakpoint class token (e.g. sm:/md:/lg:/xl:/2xl:) or
 *   fixed pixel width literal appears anywhere the component tree renders;
 *   320 CSS px is the intrinsic fluid starting point.
 */

const PLANS_ENDPOINT = '/api/admin/plans';

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
    ];
}

function buildFetchMock() {
    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();

        if (String(url) === PLANS_ENDPOINT && method === 'GET') {
            return jsonResponse(200, makePlans());
        }

        throw new Error(`Unhandled fetch in PlatformApp test: ${method} ${String(url)}`);
    });
}

function collectClassNames(root: Element): string[] {
    const classes: string[] = [];
    const walk = (node: Element) => {
        for (const token of Array.from(node.classList)) {
            classes.push(token);
        }
        for (const child of Array.from(node.children)) {
            walk(child);
        }
    };
    walk(root);
    return classes;
}

async function importPlatformAppModule() {
    return import('./PlatformApp') as unknown as Promise<{ PlatformApp: React.ComponentType }>;
}

describe('PlatformApp — standalone platform admin shell (PLATFORM_SHELL_FRONTEND_RED)', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=test-xsrf-token';
        vi.stubGlobal('fetch', buildFetchMock());
        history.replaceState(null, '', window.location.pathname);
    });

    it('renders an accessible Platform administration landmark, a selected Plans nav item, and a link back to /app', async () => {
        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        expect(
            screen.getByRole('heading', { name: /platform administration/i }),
        ).toBeInTheDocument();

        const plansNavItem = await screen.findByRole('link', { name: /plans/i });
        expect(plansNavItem).toHaveAttribute('aria-current', 'page');

        const backLink = screen.getByRole('link', { name: /back to (workspace|app)/i });
        expect(backLink).toHaveAttribute('href', '/app');
    });

    it('selects the Plans nav item when the location hash is #plans', async () => {
        history.replaceState(null, '', `${window.location.pathname}#plans`);

        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        const plansNavItem = await screen.findByRole('link', { name: /plans/i });
        expect(plansNavItem).toHaveAttribute('aria-current', 'page');
    });

    it('renders the real PlanManagementPage, driving its own fetch to /api/admin/plans', async () => {
        const user = userEvent.setup();
        const { PlatformApp } = await importPlatformAppModule();
        const view = render(<PlatformApp />);
        void user;

        await screen.findByText('Starter');

        const fetchMock = vi.mocked(fetch);
        expect(fetchMock).toHaveBeenCalledWith(
            PLANS_ENDPOINT,
            expect.objectContaining({ credentials: 'same-origin' }),
        );

        view.unmount();
    });

    it('introduces no responsive breakpoint class tokens or fixed-width dependency across the rendered tree', async () => {
        const { PlatformApp } = await importPlatformAppModule();
        const { container } = render(<PlatformApp />);

        await screen.findByText('Starter');

        const classNames = collectClassNames(container);
        const breakpointPattern = /(^|:)(sm|md|lg|xl|2xl):/;
        const offenders = classNames.filter((cls) => breakpointPattern.test(cls));
        expect(offenders).toEqual([]);

        const fixedWidthElements = Array.from(container.querySelectorAll<HTMLElement>('[style]')).filter((el) => {
            const width = el.style.width;
            return width !== '' && !width.includes('%') && !width.startsWith('var(') && !width.includes('auto');
        });
        expect(fixedWidthElements).toEqual([]);
    });
});
