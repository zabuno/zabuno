import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';

/**
 * MÜHENDİSLİK KABUĞU — `docs/98` FF-66, `docs/50` §3 shell ailesi.
 *
 * Readiness ve denetim izi plan/ödeme kabuğundan ayrıldı. Bu kabuk aynı
 * gövdeyi (`OpsShell`) kullanır: adres→bölüm, gruplu ray, kardeş kabuğa
 * geçiş. Fragment yok (`docs/38` §4).
 */

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

async function importApp() {
    return import('./EngineeringApp') as unknown as Promise<{
        EngineeringApp: React.ComponentType;
    }>;
}

describe('EngineeringApp', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string) => {
                if (String(url).endsWith('/api/admin/ai/audit')) {
                    return jsonResponse(200, {
                        audits: [
                            {
                                id: 1,
                                provider: 'openai',
                                connectionId: 1,
                                connectionLabel: 'OpenAI — Menü',
                                action: 'created',
                                actor: 'İsmail',
                                at: '2026-09-04 10:00:00',
                            },
                            {
                                id: 2,
                                provider: 'openai',
                                connectionId: 1,
                                connectionLabel: 'OpenAI — Menü',
                                action: 'health:unhealthy',
                                actor: null,
                                at: '2026-09-04 11:00:00',
                            },
                        ],
                        assignments: [
                            {
                                workspaceId: 7,
                                workspaceName: 'Zeytin',
                                provider: 'openai',
                                connectionId: 1,
                                connectionLabel: 'OpenAI — Menü',
                                health: 'unhealthy',
                                since: '2026-09-04 10:30:00',
                            },
                        ],
                    });
                }
                if (String(url).endsWith('/api/admin/modules')) {
                    return jsonResponse(200, {
                        modules: [
                            {
                                code: 'CORE-13',
                                name: 'File/Media',
                                moduleClass: 'core',
                                version: '1.0.0',
                                dependencies: ['CORE-02', 'CORE-04'],
                                deterministicBaseline: 'required',
                                aiPosture: 'automated_guarded',
                            },
                        ],
                        contextGraph: { nodes: ['Media'], edges: [] },
                    });
                }
                if (String(url).endsWith('/api/admin/workspaces')) return jsonResponse(200, []);
                return jsonResponse(404, { message: 'Not Found.' });
            }),
        );
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        history.replaceState(null, '', '/');
    });

    it('opens on release readiness with an Engineering landmark and a way to the platform shell', async () => {
        history.replaceState(null, '', '/engineering');
        const { EngineeringApp } = await importApp();

        render(<EngineeringApp />);

        expect(screen.getByRole('navigation', { name: 'Engineering' })).toBeInTheDocument();
        expect(
            await screen.findByRole('heading', { name: /^release readiness$/i }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /release readiness/i })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Platform admin' })).toHaveAttribute(
            'href',
            '/platform',
        );
        expect(screen.getByRole('link', { name: /back to workspace/i })).toHaveAttribute(
            'href',
            '/app',
        );
    });

    it('reads the AI audit trail from the address and renders both tables without inventing an actor', async () => {
        history.replaceState(null, '', '/engineering/ai-audit');
        const { EngineeringApp } = await importApp();

        render(<EngineeringApp />);

        expect(
            await screen.findByRole('heading', { name: /^AI audit trail$/i }),
        ).toBeInTheDocument();

        const pinning = await screen.findByRole('region', { name: 'Restaurant → account pinning' });
        expect(within(pinning).getByText('Zeytin')).toBeInTheDocument();
        expect(within(pinning).getByText('Not responding')).toBeInTheDocument();

        const events = screen.getByRole('region', { name: 'Key and health events' });
        expect(within(events).getByText('İsmail')).toBeInTheDocument();
        // Aktörsüz kayıt: sunucu komutu — bir isim uydurulmaz.
        expect(within(events).getByText('server command')).toBeInTheDocument();
        expect(within(events).getByText('health:unhealthy')).toBeInTheDocument();
    });

    /**
     * `docs/111` §8.6 — bölüm ADRESTEN gelir.
     *
     * Fragment (`#modules`) sunucuya hiç ulaşmaz: ne ölçüme, ne paylaşılan
     * bir bağlantıya, ne de tarayıcı geçmişine güvenilir biçimde girer
     * (`docs/38` §4, kiracı ölçüm kuralı). Modül envanteri bu kuralın
     * dışında tutulmaz.
     */
    it('draws the module inventory when /engineering/modules is opened directly', async () => {
        history.replaceState(null, '', '/engineering/modules');
        const { EngineeringApp } = await importApp();

        render(<EngineeringApp />);

        expect(await screen.findByRole('heading', { name: /^modules$/i })).toBeInTheDocument();

        const registry = await screen.findByRole('region', { name: 'Core kernel registry' });
        expect(within(registry).getByText('File/Media')).toBeInTheDocument();
        expect(within(registry).getByText('automated_guarded')).toBeInTheDocument();

        expect(screen.getByRole('link', { name: 'Modules' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('falls back to the default section when the address names an unknown one', async () => {
        history.replaceState(null, '', '/engineering/modul-envanteri');
        const { EngineeringApp } = await importApp();

        render(<EngineeringApp />);

        expect(
            await screen.findByRole('heading', { name: /^release readiness$/i }),
        ).toBeInTheDocument();
    });
});
