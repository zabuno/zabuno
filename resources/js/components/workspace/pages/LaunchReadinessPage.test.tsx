import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';

/**
 * LAUNCHREADINESS_TENANT_EVIDENCE_FRONTEND_RED
 *
 * S1-WP07 tenant isolation evidence — LaunchReadinessPage must accept a
 * real workspaceId prop, render the tenant isolation checklist row as a
 * TenantIsolationEvidenceItem backed by the real
 * GET /api/workspaces/{workspaceId}/security/evidence/tenant-isolation
 * contract, and leave every other canonical readiness item honestly
 * Unavailable. None of this exists yet: the current LaunchReadinessPage
 * takes no props and renders a fully static checklist, so every
 * assertion below fails RED. No production, i18n, Storybook, backend or
 * Git edits are made from this file.
 */

const WORKSPACE_ID = 71;
const TENANT_EVIDENCE_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/security/evidence/tenant-isolation`;
const BACKUP_EVIDENCE_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/security/evidence/backup-restore`;
const BREAKPOINT_CLASS_PATTERN = /(^|[\s"'`])(sm|md|lg|xl|2xl):/;

const CLAIM =
    'This evidence reflects the result of running the frozen set of selected automated local feature tests for tenant isolation. It is not an ASVS audit, not a pentest, and not a production proof.';

const BACKUP_CLAIM =
    'This evidence reflects one local SQLite online-backup and isolated file-copy restore drill against a frozen table manifest. It is not an RPO/RTO proof, not a production DR drill, and does not test cross-host or point-in-time recovery.';

const OTHER_CANONICAL_ITEMS = [/owasp\s*asvs/i, /qr scan/i, /rpo.*rto|rto.*rpo/i, /shared.host/i];

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

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: width,
    });
    Object.defineProperty(window, 'innerHeight', {
        writable: true,
        configurable: true,
        value: height,
    });
    window.dispatchEvent(new Event('resize'));
}

async function importLaunchReadinessPageModule() {
    return import('./LaunchReadinessPage') as unknown as Promise<{
        LaunchReadinessPage: React.ComponentType<{ workspaceId: number }>;
    }>;
}

describe('LaunchReadinessPage — real tenant isolation evidence wiring (S1-WP07, RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        setViewport(320, 480);
        fetchSpy = vi.fn(async (url: string) => {
            if (String(url) === TENANT_EVIDENCE_ENDPOINT) {
                return jsonResponse(200, evidenceEnvelope('passed'));
            }
            if (String(url) === BACKUP_EVIDENCE_ENDPOINT) {
                return jsonResponse(200, backupEvidenceEnvelope('passed'));
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('fetches the exact workspace-scoped tenant isolation and backup-restore evidence endpoints exactly once each for a real workspaceId prop', async () => {
        const { LaunchReadinessPage } = await importLaunchReadinessPageModule();

        render(<LaunchReadinessPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(2));
        const calledUrls = fetchSpy.mock.calls.map((call) => String(call[0])).sort();
        expect(calledUrls).toEqual([BACKUP_EVIDENCE_ENDPOINT, TENANT_EVIDENCE_ENDPOINT].sort());
    });

    it('leaves the other four canonical readiness items honestly Unavailable while tenant isolation and backup-restore resolve', async () => {
        const { LaunchReadinessPage } = await importLaunchReadinessPageModule();

        render(<LaunchReadinessPage workspaceId={WORKSPACE_ID} />);

        const checklist = screen.getByRole('region', { name: /launch readiness/i });

        for (const pattern of OTHER_CANONICAL_ITEMS) {
            const item = within(checklist).getByText(pattern);
            expect(item).toBeInTheDocument();
        }

        expect(within(checklist).getAllByText(/unavailable/i).length).toBeGreaterThanOrEqual(
            OTHER_CANONICAL_ITEMS.length,
        );

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());
    });

    it('renders the resolved tenant isolation and backup-restore statuses inside the checklist region once the fetches resolve', async () => {
        const { LaunchReadinessPage } = await importLaunchReadinessPageModule();

        render(<LaunchReadinessPage workspaceId={WORKSPACE_ID} />);

        const checklist = screen.getByRole('region', { name: /launch readiness/i });

        await waitFor(() =>
            expect(within(checklist).getAllByText(/passed/i).length).toBeGreaterThanOrEqual(2),
        );
    });

    it('stays 320 CSS px fluid with no breakpoint-prefixed classes anywhere in the page', async () => {
        const { LaunchReadinessPage } = await importLaunchReadinessPageModule();

        const { container } = render(<LaunchReadinessPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        for (const el of container.querySelectorAll<HTMLElement>('[class]')) {
            expect(el.getAttribute('class') ?? '').not.toMatch(BREAKPOINT_CLASS_PATTERN);
        }
    });
});
