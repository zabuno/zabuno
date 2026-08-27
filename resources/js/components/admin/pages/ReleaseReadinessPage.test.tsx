import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * LAUNCHREADINESS_TENANT_EVIDENCE_FRONTEND_RED
 *
 * S1-WP07 tenant isolation evidence — ReleaseReadinessPage must accept a
 * real workspaceId prop, render the tenant isolation checklist row as a
 * TenantIsolationEvidenceItem backed by the real
 * GET /api/workspaces/{workspaceId}/security/evidence/tenant-isolation
 * contract, and leave every other canonical readiness item honestly
 * Unavailable. None of this exists yet: the current ReleaseReadinessPage
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

function findCanonicalTitle(container: HTMLElement, pattern: RegExp) {
    return within(container).getByText((_content, element) => {
        if (!element || element.tagName.toLowerCase() !== 'p') {
            return false;
        }
        if (element.closest('dl')) {
            return false;
        }
        return pattern.test(element.textContent ?? '');
    });
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

// Kanıt LİSTESİ sınanıyor, onu saran sayfa değil: sayfa artık bir workspace
// seçici içeriyor (kanıt uç noktaları workspace'e bağlı) ve seçicinin kendi
// testleri abonelik ekranında yaşıyor.
async function importReadinessChecklistModule() {
    return import('./release-readiness/ReadinessChecklist') as unknown as Promise<{
        ReadinessChecklist: React.ComponentType<{ workspaceId: number }>;
    }>;
}

describe('ReleaseReadinessPage — real tenant isolation evidence wiring (S1-WP07, RED)', () => {
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
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(2));
        const calledUrls = fetchSpy.mock.calls.map((call) => String(call[0])).sort();
        expect(calledUrls).toEqual([BACKUP_EVIDENCE_ENDPOINT, TENANT_EVIDENCE_ENDPOINT].sort());
    });

    it('leaves the other four canonical readiness items honestly Unavailable while tenant isolation and backup-restore resolve', async () => {
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        const checklist = screen.getByRole('region', { name: /release readiness/i });

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
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        const checklist = screen.getByRole('region', { name: /release readiness/i });

        await waitFor(() =>
            expect(within(checklist).getAllByText(/passed/i).length).toBeGreaterThanOrEqual(2),
        );
    });

    it('stays 320 CSS px fluid with no breakpoint-prefixed classes anywhere in the page', async () => {
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        const { container } = render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        for (const el of container.querySelectorAll<HTMLElement>('[class]')) {
            expect(el.getAttribute('class') ?? '').not.toMatch(BREAKPOINT_CLASS_PATTERN);
        }
    });

    it('shows an accessible, always-visible Refresh evidence control once the initial evidence has resolved', async () => {
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(2));

        const refreshButton = await screen.findByRole('button', { name: /refresh evidence/i });
        expect(refreshButton).toBeInTheDocument();
        expect(refreshButton).toBeEnabled();
    });

    it('makes exactly one additional request to each existing evidence endpoint, with the exact current-workspace URLs and no other endpoint, on one click', async () => {
        const user = userEvent.setup();
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(2));

        const refreshButton = await screen.findByRole('button', { name: /refresh evidence/i });
        await user.click(refreshButton);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(4));

        const calledUrls = fetchSpy.mock.calls.map((call) => String(call[0]));
        expect(calledUrls.filter((url) => url === TENANT_EVIDENCE_ENDPOINT)).toHaveLength(2);
        expect(calledUrls.filter((url) => url === BACKUP_EVIDENCE_ENDPOINT)).toHaveLength(2);
        expect(calledUrls).toHaveLength(4);
        for (const url of calledUrls) {
            expect([TENANT_EVIDENCE_ENDPOINT, BACKUP_EVIDENCE_ENDPOINT]).toContain(url);
        }
    });

    it('truthfully returns both dynamic evidence rows to loading after a refresh click before resolving refreshed server states, without stale detail', async () => {
        const user = userEvent.setup();
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        let resolveTenantRefresh: ((response: Response) => void) | undefined;
        let resolveBackupRefresh: ((response: Response) => void) | undefined;
        let callCount = 0;

        fetchSpy.mockImplementation(async (url: string) => {
            callCount += 1;
            if (callCount <= 2) {
                if (String(url) === TENANT_EVIDENCE_ENDPOINT) {
                    return jsonResponse(200, evidenceEnvelope('passed'));
                }
                if (String(url) === BACKUP_EVIDENCE_ENDPOINT) {
                    return jsonResponse(200, backupEvidenceEnvelope('passed'));
                }
                throw new Error(`Unhandled fetch: ${String(url)}`);
            }

            if (String(url) === TENANT_EVIDENCE_ENDPOINT) {
                return new Promise<Response>((resolve) => {
                    resolveTenantRefresh = resolve;
                });
            }
            if (String(url) === BACKUP_EVIDENCE_ENDPOINT) {
                return new Promise<Response>((resolve) => {
                    resolveBackupRefresh = resolve;
                });
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        const checklist = screen.getByRole('region', { name: /release readiness/i });

        await waitFor(() =>
            expect(within(checklist).getAllByText(/passed/i).length).toBeGreaterThanOrEqual(2),
        );
        expect(within(checklist).queryAllByText(/abc1234/i).length).toBeGreaterThanOrEqual(2);

        const refreshButton = await screen.findByRole('button', { name: /refresh evidence/i });
        await user.click(refreshButton);

        await waitFor(() =>
            expect(within(checklist).getAllByText(/loading/i).length).toBeGreaterThanOrEqual(2),
        );
        expect(within(checklist).queryByText(/abc1234/i)).not.toBeInTheDocument();
        expect(within(checklist).queryAllByText(/passed/i)).toHaveLength(0);

        resolveTenantRefresh?.(
            jsonResponse(
                200,
                evidenceEnvelope('failed', { git_sha: 'refreshed-tenant' }),
            ) as unknown as Response,
        );
        resolveBackupRefresh?.(
            jsonResponse(
                200,
                backupEvidenceEnvelope('failed', { git_sha: 'refreshed-backup' }),
            ) as unknown as Response,
        );

        await waitFor(() =>
            expect(within(checklist).getAllByText(/failed/i).length).toBeGreaterThanOrEqual(2),
        );
        expect(within(checklist).queryByText(/abc1234/i)).not.toBeInTheDocument();
        expect(within(checklist).getAllByText(/refreshed-tenant|refreshed-backup/i).length).toBe(2);
    });

    it('leaves the other four static Unavailable requirements unchanged across a refresh', async () => {
        const user = userEvent.setup();
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        const checklist = screen.getByRole('region', { name: /release readiness/i });

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(2));

        for (const pattern of OTHER_CANONICAL_ITEMS) {
            expect(findCanonicalTitle(checklist, pattern)).toBeInTheDocument();
        }
        const unavailableCountBefore = within(checklist).getAllByText(/unavailable/i).length;
        expect(unavailableCountBefore).toBeGreaterThanOrEqual(OTHER_CANONICAL_ITEMS.length);

        const refreshButton = await screen.findByRole('button', { name: /refresh evidence/i });
        await user.click(refreshButton);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(4));

        for (const pattern of OTHER_CANONICAL_ITEMS) {
            expect(findCanonicalTitle(checklist, pattern)).toBeInTheDocument();
        }
        expect(within(checklist).getAllByText(/unavailable/i).length).toBe(unavailableCountBefore);
    });
});
