import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
    MobileBottomNav,
    MobileNavigationDrawer,
    type MobileBottomNavProps,
    type MobileChromeProps,
} from './chrome/MobileChrome';

/**
 * `docs/50` §20 / `docs/101` A4 (SHELL-MOBILE-BOTTOM-NAV-01): telefonda
 * gezinti başparmağın altındadır. Dört günlük hedef tek dokunuş, gerisi
 * "More" ile çekmecede; hamburger üst çubuktan kalkar.
 */
const WORKSPACE_ID = 4;

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function context() {
    return {
        id: WORKSPACE_ID,
        name: 'Zeytin Restoranları',
        slug: 'zeytin',
        state: 'active',
        permissions: [
            'workspace.view',
            'menu.view',
            'menu.manage',
            'qr.view',
            'analytics.view',
            'workspace.manage',
        ],
        features: {},
    };
}

function buildFetchMock() {
    return vi.fn(async (url: string) => {
        const u = String(url);
        if (u === '/sanctum/csrf-cookie') return jsonResponse(204, {});
        if (u === '/api/user')
            return jsonResponse(200, { id: 1, name: 'Ada', email: 'ada@example.com' });
        if (u === '/api/workspaces') return jsonResponse(200, [context()]);
        if (u === '/api/workspace-context') return jsonResponse(200, context());
        if (u.endsWith('/brand'))
            return jsonResponse(200, { id: 8, workspace_id: WORKSPACE_ID, name: 'Zeytin' });
        if (u.endsWith('/brand/locations')) return jsonResponse(200, []);
        return jsonResponse(404, {});
    });
}

describe('telefon alt gezintisi', () => {
    beforeEach(() => {
        history.replaceState(null, '', '/');
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('alt çubuk dört birincil hedefi gösterir, hamburger üst çubukta yoktur ve More çekmeceyi açar', async () => {
        vi.stubGlobal('fetch', buildFetchMock());
        const { WorkspaceApp } = (await import('./WorkspaceApp')) as unknown as {
            WorkspaceApp: React.ComponentType<Record<string, unknown>>;
        };

        render(
            <WorkspaceApp
                renderNavigationDrawer={(ctx: MobileChromeProps) => (
                    <MobileNavigationDrawer {...ctx} />
                )}
                renderBottomBar={(ctx: MobileBottomNavProps) => <MobileBottomNav {...ctx} />}
            />,
        );

        const bottom = await screen.findByRole('navigation', { name: 'Restaurant admin' });
        const buttons = within(bottom).getAllByRole('button');
        expect(buttons.map((button) => button.textContent)).toEqual([
            'Home',
            'Menus',
            'QR codes',
            'Insights',
            'More',
        ]);
        expect(within(bottom).getByRole('button', { name: 'Home' })).toHaveAttribute(
            'aria-current',
            'page',
        );

        // Hamburger üst çubukta YOK: aynı iş iki yerde durmaz.
        const banner = screen.getByRole('banner');
        expect(within(banner).queryByRole('button', { name: /open menu/i })).toBeNull();

        const user = userEvent.setup();
        await user.click(within(bottom).getByRole('button', { name: 'Menus' }));
        expect(window.location.pathname).toContain('/menu');

        await user.click(within(bottom).getByRole('button', { name: 'More' }));
        expect(await screen.findByRole('dialog')).toBeInTheDocument();
    });
});
