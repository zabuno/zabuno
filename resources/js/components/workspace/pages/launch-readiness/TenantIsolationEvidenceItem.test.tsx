import type React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';

/**
 * TENANT_ISOLATION_EVIDENCE_ITEM_FRONTEND_RED
 *
 * S1-WP07 — TenantIsolationEvidenceItem is a new, dedicated component that
 * does not exist yet. It owns the honest lifecycle of the real
 * GET /api/workspaces/{workspaceId}/security/evidence/tenant-isolation
 * evidence: a loading state before resolution, a distinct passed/failed
 * 200 rendering with real ran_at/git_sha/duration_ms and the verbatim
 * backend claim string, a neutral Unavailable for 404 (indistinguishable
 * from denied/no-record), a distinct error state for network failure/401/500/
 * malformed payloads (including a parsed-but-out-of-range status) that never
 * carries passed/failed wording, at most one request per mount, and no
 * stale response overwrite after a workspaceId change or unmount. No
 * production, i18n, Storybook, backend or Git edits are made from this file.
 */

const WORKSPACE_ID = 71;
const OTHER_WORKSPACE_ID = 88;

const CLAIM =
    'This evidence reflects the result of running the frozen set of selected automated local feature tests for tenant isolation. It is not an ASVS audit, not a pentest, and not a production proof.';

const RAW_OUTPUT_SENTINEL = 'SECRET_RAW_OUTPUT_MUST_NOT_RENDER';

function endpointFor(workspaceId: number) {
    return `/api/workspaces/${workspaceId}/security/evidence/tenant-isolation`;
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as unknown as Response;
}

function malformedResponse(status: number): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => {
            throw new SyntaxError('Unexpected token in JSON');
        },
    } as unknown as Response;
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
            raw_output: RAW_OUTPUT_SENTINEL,
            ...overrides,
        },
    };
}

function createDeferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason?: unknown) => void;
    const promise = new Promise<T>((res, rej) => {
        resolve = res;
        reject = rej;
    });
    return { promise, resolve, reject };
}

const TENANT_ISOLATION_EVIDENCE_ITEM_MODULE_SPECIFIER = ['.', 'TenantIsolationEvidenceItem'].join(
    '/',
);

async function importTenantIsolationEvidenceItemModule() {
    return import(
        /* @vite-ignore */ TENANT_ISOLATION_EVIDENCE_ITEM_MODULE_SPECIFIER
    ) as unknown as Promise<{
        TenantIsolationEvidenceItem: React.ComponentType<{ workspaceId: number }>;
    }>;
}

describe('TenantIsolationEvidenceItem — real tenant isolation evidence contract (S1-WP07, RED)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        cleanup();
    });

    it('renders a loading state before the fetch resolves', async () => {
        const deferred = createDeferred<Response>();
        const fetchSpy = vi.fn(async () => deferred.promise);
        vi.stubGlobal('fetch', fetchSpy);

        const { TenantIsolationEvidenceItem } = await importTenantIsolationEvidenceItemModule();
        render(<TenantIsolationEvidenceItem workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());
        expect(screen.getByText(/loading|checking|pending/i)).toBeInTheDocument();
        expect(screen.queryByText(/\bpassed\b/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/\bfailed\b/i)).not.toBeInTheDocument();

        deferred.resolve(jsonResponse(200, evidenceEnvelope('passed')));
        await waitFor(() => expect(screen.getByText(/\bpassed\b/i)).toBeInTheDocument());
    });

    it('renders a distinct passed state with real ran_at, git_sha, duration_ms and the verbatim backend claim', async () => {
        const fetchSpy = vi.fn(async () => jsonResponse(200, evidenceEnvelope('passed')));
        vi.stubGlobal('fetch', fetchSpy);

        const { TenantIsolationEvidenceItem } = await importTenantIsolationEvidenceItemModule();
        const { container } = render(<TenantIsolationEvidenceItem workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(screen.getByText(/\bpassed\b/i)).toBeInTheDocument());

        expect(screen.getByText(/2026-08-24T09:00:00\.000Z|2026-08-24/)).toBeInTheDocument();
        expect(screen.getByText(/abc1234/)).toBeInTheDocument();
        expect(screen.getByText(/842\s*ms|842/)).toBeInTheDocument();

        const normalized = (container.textContent ?? '').replace(/\s+/g, ' ');
        expect(normalized).toContain(CLAIM);
    });

    it('renders a distinct failed state, honestly different from passed, still with real metadata and the verbatim backend claim', async () => {
        const fetchSpy = vi.fn(async () => jsonResponse(200, evidenceEnvelope('failed')));
        vi.stubGlobal('fetch', fetchSpy);

        const { TenantIsolationEvidenceItem } = await importTenantIsolationEvidenceItemModule();
        const { container } = render(<TenantIsolationEvidenceItem workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(screen.getByText(/\bfailed\b/i)).toBeInTheDocument());
        expect(screen.queryByText(/\bpassed\b/i)).not.toBeInTheDocument();
        expect(screen.getByText(/abc1234/)).toBeInTheDocument();

        const normalized = (container.textContent ?? '').replace(/\s+/g, ' ');
        expect(normalized).toContain(CLAIM);
    });

    it('never renders the raw sentinel field from the response payload', async () => {
        const fetchSpy = vi.fn(async () => jsonResponse(200, evidenceEnvelope('passed')));
        vi.stubGlobal('fetch', fetchSpy);

        const { TenantIsolationEvidenceItem } = await importTenantIsolationEvidenceItemModule();
        const { container } = render(<TenantIsolationEvidenceItem workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(screen.getByText(/\bpassed\b/i)).toBeInTheDocument());

        expect(container.textContent ?? '').not.toContain(RAW_OUTPUT_SENTINEL);
    });

    it('renders a neutral Unavailable state for a 404, indistinguishable from a denied or no-record response', async () => {
        const fetchSpy = vi.fn(async () => jsonResponse(404, { message: 'Not Found' }));
        vi.stubGlobal('fetch', fetchSpy);

        const { TenantIsolationEvidenceItem } = await importTenantIsolationEvidenceItemModule();
        render(<TenantIsolationEvidenceItem workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(screen.getByText(/unavailable/i)).toBeInTheDocument());
        expect(screen.queryByText(/\bpassed\b/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/\bfailed\b/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/error|failed to load/i)).not.toBeInTheDocument();
    });

    it.each([
        ['a network error', () => Promise.reject(new TypeError('Network request failed'))],
        ['a 401', () => Promise.resolve(jsonResponse(401, { message: 'Unauthorized' }))],
        ['a 500', () => Promise.resolve(jsonResponse(500, { message: 'Server Error' }))],
        ['a malformed 200 payload (invalid JSON)', () => Promise.resolve(malformedResponse(200))],
        [
            'a parsed 200 payload with data.status outside passed/failed',
            () =>
                Promise.resolve(
                    jsonResponse(200, evidenceEnvelope('passed', { status: 'unknown' })),
                ),
        ],
    ])(
        'renders a distinct error state for %s, never passed/failed metadata',
        async (_label, makeResult) => {
            const fetchSpy = vi.fn(makeResult);
            vi.stubGlobal('fetch', fetchSpy);

            const { TenantIsolationEvidenceItem } = await importTenantIsolationEvidenceItemModule();
            render(<TenantIsolationEvidenceItem workspaceId={WORKSPACE_ID} />);

            await waitFor(() =>
                expect(screen.getByText(/error|failed to load|couldn.t load/i)).toBeInTheDocument(),
            );
            expect(screen.queryByText(/\bpassed\b/i)).not.toBeInTheDocument();
            expect(screen.queryByText(/\bfailed\b/i)).not.toBeInTheDocument();
            expect(screen.queryByText(/unavailable/i)).not.toBeInTheDocument();
        },
    );

    it('issues at most one request per mount', async () => {
        const fetchSpy = vi.fn(async () => jsonResponse(200, evidenceEnvelope('passed')));
        vi.stubGlobal('fetch', fetchSpy);

        const { TenantIsolationEvidenceItem } = await importTenantIsolationEvidenceItemModule();
        render(<TenantIsolationEvidenceItem workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(screen.getByText(/\bpassed\b/i)).toBeInTheDocument());
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(fetchSpy).toHaveBeenCalledTimes(1);
    });

    it('drops a stale in-flight response after workspaceId changes, keeping only the newer result', async () => {
        const deferredByWorkspace: Record<number, ReturnType<typeof createDeferred<Response>>> = {
            [WORKSPACE_ID]: createDeferred<Response>(),
            [OTHER_WORKSPACE_ID]: createDeferred<Response>(),
        };
        const fetchSpy = vi.fn(async (url: string) => {
            if (String(url) === endpointFor(WORKSPACE_ID))
                return deferredByWorkspace[WORKSPACE_ID].promise;
            if (String(url) === endpointFor(OTHER_WORKSPACE_ID))
                return deferredByWorkspace[OTHER_WORKSPACE_ID].promise;
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);

        const { TenantIsolationEvidenceItem } = await importTenantIsolationEvidenceItemModule();
        const { rerender } = render(<TenantIsolationEvidenceItem workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledWith(endpointFor(WORKSPACE_ID)));

        rerender(<TenantIsolationEvidenceItem workspaceId={OTHER_WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalledWith(endpointFor(OTHER_WORKSPACE_ID)));

        deferredByWorkspace[OTHER_WORKSPACE_ID].resolve(
            jsonResponse(200, evidenceEnvelope('failed')),
        );
        await waitFor(() => expect(screen.getByText(/\bfailed\b/i)).toBeInTheDocument());

        deferredByWorkspace[WORKSPACE_ID].resolve(jsonResponse(200, evidenceEnvelope('passed')));
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(screen.getByText(/\bfailed\b/i)).toBeInTheDocument();
        expect(screen.queryByText(/\bpassed\b/i)).not.toBeInTheDocument();
    });

    it('does not overwrite state after unmount when the request resolves late', async () => {
        const deferred = createDeferred<Response>();
        const fetchSpy = vi.fn(async () => deferred.promise);
        vi.stubGlobal('fetch', fetchSpy);
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        const { TenantIsolationEvidenceItem } = await importTenantIsolationEvidenceItemModule();
        const { unmount } = render(<TenantIsolationEvidenceItem workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());
        unmount();

        deferred.resolve(jsonResponse(200, evidenceEnvelope('passed')));
        await new Promise((resolve) => setTimeout(resolve, 0));

        const reactStateUpdateWarning = errorSpy.mock.calls.some((call) =>
            String(call[0]).match(/state update.*unmounted/i),
        );
        expect(reactStateUpdateWarning).toBe(false);

        errorSpy.mockRestore();
    });
});
