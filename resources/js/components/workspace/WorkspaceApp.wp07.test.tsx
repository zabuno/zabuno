import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * RED contract for the S1-WP07 visible #security destination
 * ("Launch readiness"): WorkspaceApp nav link + section swap, plus the
 * LaunchReadinessPage component contract (checklist, six canonical
 * evidence items, no fabricated GREEN, no self-triggered network call, no
 * destructive Scan/Backup/Restore actions, 320px-fluid with no
 * breakpoint-prefixed classes). Neither WorkspaceApp nor
 * ./pages/LaunchReadinessPage carry this contract yet.
 */

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;
const EVIDENCE_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/security/evidence/tenant-isolation`;
const BACKUP_EVIDENCE_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/security/evidence/backup-restore`;
const BREAKPOINT_TOKEN = /(?:^|\s)(sm|md|lg|xl|2xl):/;

const CLAIM =
    'This evidence reflects the result of running the frozen set of selected automated local feature tests for tenant isolation. It is not an ASVS audit, not a pentest, and not a production proof.';

const BACKUP_CLAIM =
    'This evidence reflects one local SQLite online-backup and isolated file-copy restore drill against a frozen table manifest. It is not an RPO/RTO proof, not a production DR drill, and does not test cross-host or point-in-time recovery.';

const CANONICAL_EVIDENCE_ITEMS = [
    /owasp\s*asvs/i,
    /tenant isolation/i,
    /qr scan/i,
    /backup.*restore|restore.*backup/i,
    /rpo.*rto|rto.*rpo/i,
    /shared.host/i,
];

const OTHER_CANONICAL_EVIDENCE_ITEMS = [
    /owasp\s*asvs/i,
    /qr scan/i,
    /rpo.*rto|rto.*rpo/i,
    /shared.host/i,
];

function importWorkspaceModule<
    T extends Record<string, unknown> = Record<string, unknown>,
>(): Promise<T> {
    return import('./WorkspaceApp') as unknown as Promise<T>;
}

function importLaunchReadinessPageModule<
    T extends Record<string, unknown> = Record<string, unknown>,
>(): Promise<T> {
    return import('./pages/LaunchReadinessPage') as unknown as Promise<T>;
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

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', {
        configurable: true,
        writable: true,
        value: width,
    });
    Object.defineProperty(window, 'innerHeight', {
        configurable: true,
        writable: true,
        value: height,
    });
    window.dispatchEvent(new Event('resize'));
}

function allClassAttributes(container: HTMLElement): string[] {
    return Array.from(container.querySelectorAll('[class]')).map(
        (el) => el.getAttribute('class') ?? '',
    );
}

function allTextContent(container: HTMLElement): string {
    return container.textContent ?? '';
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

describe('WorkspaceApp — Launch readiness AdminShell destination (S1-WP07, RED)', () => {
    beforeEach(() => {
        history.replaceState(null, '', window.location.pathname);
        setViewport(320, 480);
    });

    it('resolves initial #security and exposes an accessible Launch readiness nav link with exactly one current page', async () => {
        history.replaceState(null, '', `${window.location.pathname}#security`);

        const { restoreFetch } = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        const link = within(nav).getByRole('link', { name: 'Launch readiness' });

        expect(link).toHaveAttribute('href', '#security');
        expect(within(nav).getAllByRole('link', { current: 'page' })).toHaveLength(1);
        expect(link).toHaveAttribute('aria-current', 'page');

        const main = screen.getByRole('main');
        expect(main.querySelector('#section-security')).not.toBeNull();

        restoreFetch();
    });

    it('selecting the Launch readiness nav link renders a distinct page root and hides the prior page', async () => {
        const user = userEvent.setup();
        const { restoreFetch } = await renderCurrentWorkspace();

        const main = screen.getByRole('main');
        expect(main.querySelector('#section-dashboard')).not.toBeNull();
        expect(main.querySelector('#section-security')).toBeNull();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Launch readiness' }));

        expect(main.querySelector('#section-dashboard')).toBeNull();
        const securityRegion = main.querySelector('#section-security');
        expect(securityRegion).not.toBeNull();

        expect(
            within(securityRegion as HTMLElement).getByRole('heading', {
                name: /launch readiness/i,
            }),
        ).toBeInTheDocument();

        restoreFetch();
    });

    it('entering #security triggers exactly one plain read-only GET each to the tenant-isolation and backup-restore evidence endpoints for the real current workspace, with no CSRF bootstrap, Authorization header, extra caller options, or destructive verb', async () => {
        const user = userEvent.setup();
        const { restoreFetch } = await renderCurrentWorkspace();

        const fetchCallsBefore = window.fetch;
        const calls: Array<[string, RequestInit | undefined]> = [];
        const countingFetch: typeof window.fetch = async (...args) => {
            calls.push([String(args[0]), args[1] as RequestInit | undefined]);
            return fetchCallsBefore(...args);
        };
        Object.defineProperty(window, 'fetch', {
            configurable: true,
            writable: true,
            value: countingFetch,
        });

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Launch readiness' }));

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(calls).toHaveLength(2);
        const calledUrls = calls.map(([url]) => url).sort();
        expect(calledUrls).toEqual([BACKUP_EVIDENCE_ENDPOINT, EVIDENCE_ENDPOINT].sort());

        for (const [, init] of calls) {
            const method = (init?.method ?? 'GET').toUpperCase();
            expect(method).toBe('GET');
            expect(method).not.toBe('POST');
            expect(method).not.toBe('PUT');
            expect(method).not.toBe('PATCH');
            expect(method).not.toBe('DELETE');

            const headers = new Headers(init?.headers ?? {});
            expect(headers.has('Authorization')).toBe(false);
            expect(headers.has('X-CSRF-TOKEN')).toBe(false);

            const optionKeys = Object.keys(init ?? {}).filter(
                (key) => key !== 'method' && key !== 'headers',
            );
            expect(optionKeys).toHaveLength(0);
        }

        const securityRegion = screen.getByRole('main').querySelector('#section-security') as HTMLElement;

        for (const button of within(securityRegion).queryAllByRole('button')) {
            const name = button.textContent ?? '';
            expect(name).not.toMatch(/\bscan\b/i);
            expect(name).not.toMatch(/\bbackup\b/i);
            expect(name).not.toMatch(/\brestore\b/i);
            expect(name).not.toMatch(/\brun\b/i);
        }

        restoreFetch();
    });

    it('keeps the Launch readiness destination reachable at a simulated 320x480 CSS px viewport with no breakpoint-prefixed classes', async () => {
        const user = userEvent.setup();
        const { restoreFetch } = await renderCurrentWorkspace();

        setViewport(320, 480);

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Launch readiness' }));

        const securityRegion = screen.getByRole('main').querySelector('#section-security') as HTMLElement;
        expect(securityRegion).not.toBeNull();

        for (const classAttribute of allClassAttributes(securityRegion)) {
            expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
        }

        restoreFetch();
    });
});

describe('LaunchReadinessPage — canonical evidence checklist contract (S1-WP07, RED)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('exposes a labeled readiness checklist, resolves the tenant isolation and backup-restore items honestly, and leaves the other four items Unavailable', async () => {
        const fetchSpy = vi.fn(async (url: string) => {
            if (String(url) === EVIDENCE_ENDPOINT) {
                return jsonResponse(200, evidenceEnvelope('passed'));
            }
            if (String(url) === BACKUP_EVIDENCE_ENDPOINT) {
                return jsonResponse(200, backupEvidenceEnvelope('passed'));
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);

        const { LaunchReadinessPage } = await importLaunchReadinessPageModule<{
            LaunchReadinessPage: React.ComponentType<{ workspaceId: number }>;
        }>();

        render(<LaunchReadinessPage workspaceId={WORKSPACE_ID} />);

        const checklist = screen.getByRole('region', { name: /launch readiness/i });

        for (const pattern of CANONICAL_EVIDENCE_ITEMS) {
            const item = within(checklist).getByText(pattern);
            expect(item).toBeInTheDocument();
        }

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(within(checklist).getAllByText(/\bpassed\b/i).length).toBeGreaterThanOrEqual(2);
        expect(within(checklist).getAllByText(/unavailable/i).length).toBeGreaterThanOrEqual(
            OTHER_CANONICAL_EVIDENCE_ITEMS.length,
        );

        const text = allTextContent(checklist);
        expect(text).not.toMatch(/\bverified\b/i);
        expect(text).not.toMatch(/\bready\b/i);
    });

    it('renders no destructive Scan/Backup/Restore action and forbids breakpoint-prefixed classes in its own markup', async () => {
        const fetchSpy = vi.fn(async (url: string) => {
            if (String(url) === EVIDENCE_ENDPOINT) {
                return jsonResponse(200, evidenceEnvelope('passed'));
            }
            if (String(url) === BACKUP_EVIDENCE_ENDPOINT) {
                return jsonResponse(200, backupEvidenceEnvelope('passed'));
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);

        const { LaunchReadinessPage } = await importLaunchReadinessPageModule<{
            LaunchReadinessPage: React.ComponentType<{ workspaceId: number }>;
        }>();

        const { container } = render(<LaunchReadinessPage workspaceId={WORKSPACE_ID} />);

        for (const button of screen.queryAllByRole('button')) {
            const name = button.textContent ?? '';
            expect(name).not.toMatch(/\bscan\b/i);
            expect(name).not.toMatch(/\bbackup\b/i);
            expect(name).not.toMatch(/\brestore\b/i);
        }

        for (const classAttribute of allClassAttributes(container)) {
            expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
        }
    });
});
