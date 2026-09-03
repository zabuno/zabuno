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
        // Yalnız BAŞLIK satırı — FF-63'ün kayıt formundaki yardım metni de
        // "RPO … RTO" içeriyor ve bir <p>; başlık kalın olanıdır.
        if (!element.classList.contains('font-semibold')) {
            return false;
        }
        return pattern.test(element.textContent ?? '');
    });
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        // Gerçek bir `Response` HER ZAMAN `headers` taşır. Sahte yanıt
        // taşımayınca, başlık okuyan her kod yolu testte patlıyor ve
        // ağ hatası gibi görünüyordu.
        headers: new Headers(),
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
            if (String(url) === '/sanctum/csrf-cookie') {
                return jsonResponse(204, {});
            }
            if (String(url) === '/api/admin/release-attestations') {
                return jsonResponse(201, { id: 1, key: 'rpo-rto-decision' });
            }
            if (String(url) === TENANT_EVIDENCE_ENDPOINT) {
                return jsonResponse(200, evidenceEnvelope('passed'));
            }
            if (String(url) === BACKUP_EVIDENCE_ENDPOINT) {
                return jsonResponse(200, backupEvidenceEnvelope('passed'));
            }
            // FF-63: kalan dört madde de artık gerçek bir uçtan okunur; kayıt
            // yoksa 404 gelir ve satır dürüstçe "Unavailable" kalır.
            if (String(url).includes('/security/evidence/')) {
                return jsonResponse(404, { message: 'Not Found.' });
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('fetches every one of the six evidence endpoints exactly once for a real workspaceId prop', async () => {
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(6));
        const calledUrls = fetchSpy.mock.calls.map((call) => String(call[0])).sort();
        expect(calledUrls).toEqual(
            [
                BACKUP_EVIDENCE_ENDPOINT,
                TENANT_EVIDENCE_ENDPOINT,
                `/api/workspaces/${WORKSPACE_ID}/security/evidence/host-capability`,
                `/api/workspaces/${WORKSPACE_ID}/security/evidence/attestations/qr-physical-scan`,
                `/api/workspaces/${WORKSPACE_ID}/security/evidence/attestations/rpo-rto-decision`,
                `/api/workspaces/${WORKSPACE_ID}/security/evidence/attestations/owasp-asvs-audit`,
            ].sort(),
        );
    });

    it('renders a recorded attestation as "Attested", never as a machine "Passed"', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url).endsWith('/attestations/qr-physical-scan')) {
                return jsonResponse(200, {
                    data: {
                        id: 9,
                        key: 'qr-physical-scan',
                        kind: 'attestation',
                        status: 'passed',
                        summary: 'Basılı QR iPhone ile tarandı, menü açıldı.',
                        reference: null,
                        payload: { device: 'iPhone 15' },
                        attested_by: 'İsmail',
                        attested_at: '2026-09-04T10:00:00+03:00',
                        integrity_sha256: 'e'.repeat(64),
                    },
                });
            }
            return jsonResponse(404, { message: 'Not Found.' });
        });
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        expect(await screen.findByText('Attested — worked')).toBeInTheDocument();
        expect(screen.getByText(/device: iPhone 15/)).toBeInTheDocument();
        expect(screen.getByText(/Recorded by/)).toBeInTheDocument();
        expect(
            screen.getByText('This is a human attestation, not an automated check.'),
        ).toBeInTheDocument();
    });

    it('offers a record form under an unavailable attestation and posts it as the signed-in superadmin', async () => {
        const { ReadinessChecklist } = await importReadinessChecklistModule();
        document.cookie = 'XSRF-TOKEN=test-token';

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        const rpoInput = await screen.findByLabelText(/RPO/);
        await userEvent.type(rpoInput, '24');
        await userEvent.type(screen.getByLabelText(/RTO/), '4');
        const summaries = screen.getAllByLabelText('What was done, in your own words');
        // RPO/RTO satırı listede ikinci tanıklıktır (QR, RPO/RTO, ASVS).
        await userEvent.type(summaries[1], 'Günlük yedek; 24 saat kayıp, 4 saat kesinti.');
        await userEvent.click(screen.getAllByRole('button', { name: 'Record this' })[1]);

        await waitFor(() => {
            const post = fetchSpy.mock.calls.find(
                (call) =>
                    String(call[0]) === '/api/admin/release-attestations' &&
                    (call[1] as RequestInit)?.method === 'POST',
            );
            expect(post).toBeTruthy();
            const body = JSON.parse(String((post![1] as RequestInit).body));
            expect(body.key).toBe('rpo-rto-decision');
            expect(body.status).toBe('decided');
            expect(body.payload).toEqual({ rpo_hours: '24', rto_hours: '4' });
        });
    });

    it('shows the host degradation plan instead of painting a degraded host green', async () => {
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url).endsWith('/host-capability')) {
                return jsonResponse(200, {
                    data: {
                        id: 3,
                        key: 'host-capability',
                        kind: 'automated',
                        php_version: '8.4.1',
                        capabilities: { imagick: false, gd: true },
                        degradations: [
                            'image-derivatives:gd — Imagick yok; görsel türevleri GD ile üretilir.',
                        ],
                        claim: 'Read-only capability probe of the host running this process.',
                        ran_at: '2026-09-04T09:00:00+03:00',
                    },
                });
            }
            return jsonResponse(404, { message: 'Not Found.' });
        });
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        expect(
            await screen.findByText('Running with 1 planned degradation(s)'),
        ).toBeInTheDocument();
        expect(screen.getByText(/Imagick yok/)).toBeInTheDocument();
    });

    it('leaves the other four canonical readiness items honestly Unavailable when they have no record', async () => {
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        const checklist = screen.getByRole('region', { name: /release readiness/i });

        for (const pattern of OTHER_CANONICAL_ITEMS) {
            const item = within(checklist).getByText(pattern);
            expect(item).toBeInTheDocument();
        }

        // Dört madde artık gerçek uçtan okunur; 404 gelince "Unavailable"
        // olur — yükleme bitmeden sayılmaz.
        await waitFor(() =>
            expect(within(checklist).getAllByText(/unavailable/i).length).toBeGreaterThanOrEqual(
                OTHER_CANONICAL_ITEMS.length,
            ),
        );
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

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(6));

        const refreshButton = await screen.findByRole('button', { name: /refresh evidence/i });
        expect(refreshButton).toBeInTheDocument();
        expect(refreshButton).toBeEnabled();
    });

    it('makes exactly one additional request to each existing evidence endpoint, with the exact current-workspace URLs and no other endpoint, on one click', async () => {
        const user = userEvent.setup();
        const { ReadinessChecklist } = await importReadinessChecklistModule();

        render(<ReadinessChecklist workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(6));

        const refreshButton = await screen.findByRole('button', { name: /refresh evidence/i });
        await user.click(refreshButton);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(12));

        const calledUrls = fetchSpy.mock.calls.map((call) => String(call[0]));
        expect(calledUrls.filter((url) => url === TENANT_EVIDENCE_ENDPOINT)).toHaveLength(2);
        expect(calledUrls.filter((url) => url === BACKUP_EVIDENCE_ENDPOINT)).toHaveLength(2);
        // Altı uç, her biri tam iki kez — başka hiçbir adres yok.
        expect(calledUrls).toHaveLength(12);
        for (const url of calledUrls) {
            expect(url).toMatch(new RegExp(`^/api/workspaces/${WORKSPACE_ID}/security/evidence/`));
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

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(6));

        for (const pattern of OTHER_CANONICAL_ITEMS) {
            expect(findCanonicalTitle(checklist, pattern)).toBeInTheDocument();
        }
        await waitFor(() =>
            expect(within(checklist).getAllByText(/unavailable/i).length).toBeGreaterThanOrEqual(
                OTHER_CANONICAL_ITEMS.length,
            ),
        );
        const unavailableCountBefore = within(checklist).getAllByText(/unavailable/i).length;

        const refreshButton = await screen.findByRole('button', { name: /refresh evidence/i });
        await user.click(refreshButton);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(12));

        for (const pattern of OTHER_CANONICAL_ITEMS) {
            expect(findCanonicalTitle(checklist, pattern)).toBeInTheDocument();
        }
        await waitFor(() =>
            expect(within(checklist).getAllByText(/unavailable/i).length).toBe(
                unavailableCountBefore,
            ),
        );
    });
});
