import type React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * RED test freezing the shell-level AI Command Center contract
 * (S1-WP01A frontend-first Global AI Command Center shell).
 * WorkspaceApp does not compose an AI command center trigger/dialog yet,
 * so every assertion here must fail RED.
 */

vi.mock('./BrandOnboardingForm', () => ({
    BrandOnboardingForm: () => <div data-testid="brand-onboarding-form" />,
}));

vi.mock('./LocationOnboardingForm', () => ({
    LocationOnboardingForm: () => <div data-testid="location-onboarding-form" />,
}));

vi.mock('../catalog/menu/macro/MenuCatalogWorkspace', () => ({
    MenuCatalogWorkspace: (props: { workspaceId: number; locationId: number }) => (
        <div
            data-testid="menu-catalog-workspace"
            data-workspace-id={props.workspaceId}
            data-location-id={props.locationId}
        />
    ),
}));

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;
const AI_URL_PATTERN = /ai|assistant|command/i;

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

function makeLocation(overrides: Partial<Record<string, unknown>> = {}) {
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
        ...overrides,
    };
}

function buildFetchMock() {
    return vi.fn(async (url: string, init?: RequestInit) => {
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

        if (AI_URL_PATTERN.test(String(url))) {
            throw new Error(
                `Unexpected AI-related fetch in AI command center RED test: ${method} ${String(url)}`,
            );
        }

        throw new Error(
            `Unhandled fetch in WorkspaceApp AI command center test: ${method} ${String(url)}`,
        );
    });
}

async function renderCurrentWorkspace() {
    const fetchMock = buildFetchMock();
    vi.stubGlobal('fetch', fetchMock);

    const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>();
    render(<WorkspaceApp />);

    await screen.findByRole('navigation', { name: 'Restaurant admin' });

    return fetchMock;
}

describe('WorkspaceApp — AI command center shell (S1-WP01A, RED)', () => {
    beforeEach(() => {
        history.replaceState(null, '', window.location.pathname);
    });

    it('exposes exactly one shell-level "Open AI command center" trigger in the top bar that survives a route change', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        expect(screen.getByRole('button', { name: 'Open AI command center' })).toBeInTheDocument();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Menu' }));

        expect(screen.getAllByRole('button', { name: 'Open AI command center' })).toHaveLength(1);

        vi.unstubAllGlobals();
    });

    it('opens an "AI command center" dialog showing the real workspace name, selected location display_name, an honest no-AI-connected status, a disabled command input, an honest empty Recent commands region, no fabricated preview/suggestion rows, and a disabled approval control', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        await user.click(screen.getByRole('button', { name: 'Open AI command center' }));

        const dialog = await screen.findByRole('dialog', { name: 'AI command center' });

        expect(within(dialog).getByText('Zeytin Restoranları')).toBeInTheDocument();
        expect(within(dialog).getByText('Kadıköy')).toBeInTheDocument();

        expect(
            within(dialog).getByText(/no ai (gateway|assistant).*(connected|available)/i),
        ).toBeInTheDocument();

        const commandInput = within(dialog).getByRole('textbox', { name: /command/i });
        expect(commandInput).toBeDisabled();

        expect(within(dialog).queryByTestId('ai-command-affected-record-preview')).toBeNull();
        expect(within(dialog).queryByText(/suggestion|previous run/i)).toBeNull();

        const recentCommands = within(dialog).getByRole('region', { name: 'Recent commands' });
        expect(
            within(recentCommands).getByText('No commands have been run yet.'),
        ).toBeInTheDocument();
        expect(within(recentCommands).queryAllByRole('listitem')).toHaveLength(0);
        expect(within(recentCommands).queryAllByRole('row')).toHaveLength(0);

        const approveButton = within(dialog).getByRole('button', { name: /approve/i });
        expect(approveButton).toBeDisabled();

        vi.unstubAllGlobals();
    });

    it('makes no AI/assistant/command network request when the AI command center is opened', async () => {
        const fetchMock = await renderCurrentWorkspace();
        const callCountBeforeOpen = fetchMock.mock.calls.length;

        await userEvent
            .setup()
            .click(screen.getByRole('button', { name: 'Open AI command center' }));
        await screen.findByRole('dialog', { name: 'AI command center' });

        const callsAfterOpen = fetchMock.mock.calls.slice(callCountBeforeOpen);
        expect(callsAfterOpen.some(([url]) => AI_URL_PATTERN.test(String(url)))).toBe(false);

        vi.unstubAllGlobals();
    });

    it('offers Dashboard, Locations, Menu, and Publication quick actions; selecting Publication closes the dialog, updates #publication, and renders the real Publication destination, and reopening proves the shell trigger remains', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        await user.click(screen.getByRole('button', { name: 'Open AI command center' }));
        const dialog = await screen.findByRole('dialog', { name: 'AI command center' });

        expect(within(dialog).getByRole('button', { name: 'Dashboard' })).toBeInTheDocument();
        expect(within(dialog).getByRole('button', { name: 'Locations' })).toBeInTheDocument();
        expect(within(dialog).getByRole('button', { name: 'Menu' })).toBeInTheDocument();
        const publicationAction = within(dialog).getByRole('button', { name: 'Publication' });

        await user.click(publicationAction);

        await waitFor(() => {
            expect(
                screen.queryByRole('dialog', { name: 'AI command center' }),
            ).not.toBeInTheDocument();
        });

        expect(window.location.hash).toBe('#publication');

        const main = screen.getByRole('main');
        const publicationDestination = main.querySelector('#publication');
        expect(publicationDestination).not.toBeNull();
        expect(
            within(publicationDestination as HTMLElement).getAllByText(/publication/i).length,
        ).toBeGreaterThan(0);

        await user.click(screen.getByRole('button', { name: 'Open AI command center' }));
        const reopenedDialog = await screen.findByRole('dialog', { name: 'AI command center' });
        expect(reopenedDialog).toBeInTheDocument();

        const recentCommandsAfterReopen = within(reopenedDialog).getByRole('region', {
            name: 'Recent commands',
        });
        expect(
            within(recentCommandsAfterReopen).getByText('No commands have been run yet.'),
        ).toBeInTheDocument();
        expect(within(recentCommandsAfterReopen).queryAllByRole('listitem')).toHaveLength(0);

        vi.unstubAllGlobals();
    });

    it('returns focus to the launcher button when the AI command center dialog is closed via Escape', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const launcher = screen.getByRole('button', { name: 'Open AI command center' });
        await user.click(launcher);
        await screen.findByRole('dialog', { name: 'AI command center' });

        await user.keyboard('{Escape}');

        await waitFor(() => {
            expect(
                screen.queryByRole('dialog', { name: 'AI command center' }),
            ).not.toBeInTheDocument();
        });

        expect(document.activeElement).toBe(launcher);

        vi.unstubAllGlobals();
    });
});
