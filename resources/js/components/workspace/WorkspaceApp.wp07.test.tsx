import type React from 'react';
import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';

/**
 * SURFACE-SEPARATION — yayın kanıtı restoran ekranı DEĞİLDİR.
 *
 * Bu dosya eskiden tam tersini donduruyordu: tenant kabuğunda bir "Launch
 * readiness" bölümü, `#security` adresi ve altı kanıt kalemi. Sahibi
 * kararını verdi; ekran geliştirici paneline taşındı (UX raporu §4.3 ve
 * §9.10). Kanıt ekranının kendi testleri taşındığı yerde yaşıyor:
 * `components/admin/pages/ReleaseReadinessPage.test.tsx`.
 */

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;
const EVIDENCE_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/security/evidence/tenant-isolation`;
const BACKUP_EVIDENCE_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/security/evidence/backup-restore`;

const CLAIM =
    'This evidence reflects the result of running the frozen set of selected automated local feature tests for tenant isolation. It is not an ASVS audit, not a pentest, and not a production proof.';

const BACKUP_CLAIM =
    'This evidence reflects one local SQLite online-backup and isolated file-copy restore drill against a frozen table manifest. It is not an RPO/RTO proof, not a production DR drill, and does not test cross-host or point-in-time recovery.';

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

function evidenceEnvelope(status: 'passed' | 'failed', overrides: Record<string, unknown> = {}) {
    return {
        data: {
            id: 1,
            key: 'tenant-isolation',
            status,
            scope: 'tenant-isolation',
            runner: 'vitest',
            ran_at: '2026-08-24T09:00:00.000Z',
            duration_ms: 842,
            exit_code: status === 'passed' ? 0 : 1,
            git_sha: 'abc1234',
            git_dirty: false,
            source_snapshot_sha256: 'a'.repeat(64),
            suite_manifest_sha256: 'b'.repeat(64),
            output_sha256: 'c'.repeat(64),
            integrity_sha256: 'd'.repeat(64),
            claim: CLAIM,
            ...overrides,
        },
    };
}

function backupEvidenceEnvelope(
    status: 'passed' | 'failed',
    overrides: Record<string, unknown> = {},
) {
    return {
        data: {
            id: 2,
            key: 'backup-restore',
            status,
            scope: 'backup-restore',
            runner: 'sqlite-backup-drill',
            ran_at: '2026-08-24T09:00:00.000Z',
            duration_ms: 4213,
            restored_row_count: 1280,
            exit_code: status === 'passed' ? 0 : 1,
            git_sha: 'abc1234',
            git_dirty: false,
            claim: BACKUP_CLAIM,
            ...overrides,
        },
    };
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
        if (String(url) === EVIDENCE_ENDPOINT && method === 'GET') {
            return jsonResponse(200, evidenceEnvelope('passed'));
        }
        if (String(url) === BACKUP_EVIDENCE_ENDPOINT && method === 'GET') {
            return jsonResponse(200, backupEvidenceEnvelope('passed'));
        }

        throw new Error(`Unhandled fetch in WorkspaceApp wp07 RED test: ${method} ${String(url)}`);
    };
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

describe('WorkspaceApp — release evidence is not a restaurant screen', () => {
    // Bu dosya eskiden BUNUN TERSİNİ donduruyordu: tenant kabuğunda bir
    // "Launch readiness" bölümü olsun, `#security` adresine gitsin ve
    // tenant izolasyonu / yedek tatbikatı kanıtlarını göstersin.
    //
    // Sahibi kararını verdi ve ekran geliştirici paneline taşındı. Sebebi
    // görsel değil, YÜZEY ayrımıdır: commit hash'i, test süresi ve RPO/RTO
    // restoran sahibinin işi değildir (UX raporu §4.3 ve §9.10).
    //
    // Kanıt ekranının kendi testleri taşındığı yerde yaşamaya devam ediyor:
    // `components/admin/pages/ReleaseReadinessPage.test.tsx`.
    it('exposes no Launch readiness destination in the restaurant navigation', async () => {
        await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        expect(within(nav).queryByRole('link', { name: /launch readiness/i })).toBeNull();
        expect(within(nav).queryByRole('link', { name: /readiness/i })).toBeNull();
        expect(document.querySelector('#section-security')).toBeNull();
    });

    it('shows no engineering vocabulary anywhere in the restaurant shell', async () => {
        await renderCurrentWorkspace();

        // Tenant metninde geçmemesi gereken kelimeler (UX raporu §11.9).
        for (const forbidden of [/tenant isolation/i, /RPO/, /RTO/, /ASVS/]) {
            expect(document.body.textContent ?? '').not.toMatch(forbidden);
        }
    });
});
