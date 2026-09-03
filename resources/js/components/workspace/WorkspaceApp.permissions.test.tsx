import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { desktopChrome } from '../../test/workspaceChrome';

/**
 * `docs/98` FF-74 — yetki-görünürlük: sunucunun `workspace-context` ile
 * verdiği izin listesinde olmayan bölüm ve oluştur eylemi HİÇ çizilmez.
 *
 * Kullanıcı yolculuğu: Editor Ayşe kabuğu açar → kenar çubuğunda Team yok,
 * Insights var (analytics.view'ı var), "Create → Location" yok; hiçbir
 * yerde 403 görmez.
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

function editorContext() {
    return {
        id: WORKSPACE_ID,
        name: 'Zeytin Restoranları',
        slug: 'zeytin-restoranlari',
        state: 'active',
        role: 'editor',
        permissions: [
            'workspace.view',
            'menu.view',
            'menu.manage',
            'qr.view',
            'analytics.view',
            'media.manage',
            'media.download_original',
        ],
        features: { 'novice-home': false },
    };
}

function buildFetchMock() {
    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();
        const u = String(url);
        if (u === '/sanctum/csrf-cookie') return jsonResponse(204, {});
        if (u === '/api/user')
            return jsonResponse(200, { id: 1, name: 'Ayşe', email: 'ayse@example.com' });
        if (u === '/api/workspaces') return jsonResponse(200, [editorContext()]);
        if (u === '/api/workspace-context' && method === 'GET')
            return jsonResponse(200, editorContext());
        if (u === `/api/workspaces/${WORKSPACE_ID}/brand`)
            return jsonResponse(200, { id: 811, workspace_id: WORKSPACE_ID, name: 'Zeytin' });
        if (u === `/api/workspaces/${WORKSPACE_ID}/brand/locations`) return jsonResponse(200, []);
        return jsonResponse(404, {});
    });
}

describe('yetki-görünürlük (FF-74)', () => {
    beforeEach(() => {
        history.replaceState(null, '', '/');
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('Editor kenar çubuğunda Team görmez, Insights görür; Home "şimdi" kutusu bayrak kapalıyken çizilmez', async () => {
        vi.stubGlobal('fetch', buildFetchMock());
        const { WorkspaceApp } = (await import('./WorkspaceApp')) as unknown as {
            WorkspaceApp: React.ComponentType<Record<string, unknown>>;
        };

        render(<WorkspaceApp {...desktopChrome} />);

        const nav = await screen.findByRole('navigation', { name: 'Restaurant admin' });
        expect(within(nav).getByRole('link', { name: 'Insights' })).toBeInTheDocument();
        expect(within(nav).getByRole('link', { name: 'Menus' })).toBeInTheDocument();
        expect(within(nav).queryByRole('link', { name: 'Team' })).toBeNull();

        expect(await screen.findByRole('heading', { name: 'Home' })).toBeInTheDocument();
        expect(screen.queryByRole('region', { name: 'What to do now' })).toBeNull();
    });
});
