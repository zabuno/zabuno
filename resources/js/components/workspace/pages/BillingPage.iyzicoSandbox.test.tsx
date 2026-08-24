import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { BillingPage } from './BillingPage';

/**
 * IYZICO_SANDBOX_FRONTEND_RED
 *
 * RED suite for the S1-WP01A Billing "Iyzico sandbox" checkout region.
 * Current production renders this region as a static, permanently
 * disabled UnavailableRegion with zero network call. This suite freezes
 * the real frontend-only sandbox contract instead; every assertion below
 * must fail against current production.
 *
 * Frozen contract:
 *  - Always labels the region sandbox/no-real-money; never renders card
 *    number, expiry, or CVV/CVC inputs.
 *  - Accessible loading status.
 *  - state:'none' subscription -> honest no-active-plan reason, Start
 *    disabled, zero Iyzico session GET/POST.
 *  - Active subscription -> GET .../iyzico-sandbox/session with
 *    same-origin credentials + Accept; {state:'none'} session -> ready,
 *    Start enabled.
 *  - Explicit human click only: GET /sanctum/csrf-cookie then POST session
 *    with same-origin credentials, Accept/Content-Type JSON,
 *    X-XSRF-TOKEN, body exactly {}, Idempotency-Key UUID. No client
 *    amount/currency/plan/card values sent.
 *  - Pending POST: Start disabled, status initiated/processing, no
 *    duplicate POST.
 *  - 202 response may include state, conversation_id, amount_minor,
 *    currency, redirect_url; only server data + sandbox disclaimer shown;
 *    no auto navigation; explicit Continue link only for https
 *    redirect_url; javascript/non-https rejected as accessible failure.
 *  - processing/succeeded session states rendered; failure -> alert +
 *    Retry using a fresh idempotency UUID and repeating CSRF then POST.
 */

const WORKSPACE_ID = 34;
const SUBSCRIPTION_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/subscription`;
const PLANS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/plans`;
const IYZICO_SESSION_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/iyzico-sandbox/session`;
const CSRF_ENDPOINT = '/sanctum/csrf-cookie';

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function makePlans() {
    return [
        {
            id: 11,
            name: 'Growth',
            code: 'growth',
            entitlements: ['Unlimited menus'],
            amount_minor: 149900,
            currency: 'TRY',
        },
    ];
}

function makeNoneSubscription() {
    return { state: 'none' };
}

function makeActiveSubscription() {
    return {
        state: 'active',
        plan_code: 'growth',
        plan_name: 'Growth',
        plan_version: 3,
        ends_at: '2026-12-31',
    };
}

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

describe('BillingPage — Iyzico sandbox checkout (IYZICO_SANDBOX_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        fetchSpy = vi.fn(async (url: string) => {
            throw new Error(`Unhandled fetch in BillingPage iyzico sandbox test: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    });

    function iyzicoRegion() {
        return screen.getByRole('region', { name: /iyzico sandbox/i });
    }

    function baseFetchImpl(
        overrides: Record<string, (url: string, init?: RequestInit) => Promise<Response>>,
    ) {
        return async (url: string, init?: RequestInit) => {
            if (String(url) === PLANS_ENDPOINT) return jsonResponse(200, makePlans());
            const handler = overrides[String(url)];
            if (handler) return handler(String(url), init);
            throw new Error(`Unhandled fetch: ${String(url)}`);
        };
    }

    it('always shows a sandbox/no-real-money disclaimer and never renders card number, expiry, or CVV/CVC inputs', async () => {
        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeActiveSubscription()),
                [IYZICO_SESSION_ENDPOINT]: async () => jsonResponse(200, { state: 'none' }),
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(
                within(iyzicoRegion()).getByRole('heading', { name: /iyzico sandbox/i }),
            ).toBeInTheDocument();
        });

        expect(
            within(iyzicoRegion()).getByText(/no real money|not real money|no real payment/i),
        ).toBeInTheDocument();

        expect(within(iyzicoRegion()).queryByLabelText(/card number/i)).not.toBeInTheDocument();
        expect(
            within(iyzicoRegion()).queryByLabelText(/expiry|expiration/i),
        ).not.toBeInTheDocument();
        expect(within(iyzicoRegion()).queryByLabelText(/cvv|cvc/i)).not.toBeInTheDocument();
    });

    it('renders an accessible loading status for the Iyzico sandbox region before the subscription fetch resolves', () => {
        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => new Promise(() => {}),
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        expect(within(iyzicoRegion()).getByRole('status')).toHaveTextContent(/loading/i);
    });

    it('with no active subscription shows an honest no-active-plan reason, disabled Start, and issues zero Iyzico session requests', async () => {
        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeNoneSubscription()),
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(
                within(iyzicoRegion()).getByText(/no active (plan|subscription)/i),
            ).toBeInTheDocument();
        });

        const startButton = within(iyzicoRegion()).getByRole('button', {
            name: /start sandbox checkout/i,
        });
        expect(startButton).toBeDisabled();

        const iyzicoCalls = fetchSpy.mock.calls.filter(
            ([calledUrl]) => String(calledUrl) === IYZICO_SESSION_ENDPOINT,
        );
        expect(iyzicoCalls).toHaveLength(0);
    });

    it('with an active subscription fetches the Iyzico session with same-origin credentials and Accept application/json, then shows ready + enabled Start for state:none', async () => {
        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeActiveSubscription()),
                [IYZICO_SESSION_ENDPOINT]: async () => jsonResponse(200, { state: 'none' }),
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            const call = fetchSpy.mock.calls.find(
                ([calledUrl]) => String(calledUrl) === IYZICO_SESSION_ENDPOINT,
            );
            expect(call).toBeDefined();
        });

        const call = fetchSpy.mock.calls.find(
            ([calledUrl]) => String(calledUrl) === IYZICO_SESSION_ENDPOINT,
        );
        const init = call?.[1] as RequestInit | undefined;
        expect(init?.credentials).toBe('same-origin');
        expect(new Headers(init?.headers).get('Accept')).toBe('application/json');
        expect(init?.method === undefined || init?.method === 'GET').toBe(true);

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByText(/ready/i)).toBeInTheDocument();
        });

        expect(
            within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
        ).toBeEnabled();
    });

    it('starts checkout only on explicit human click: GET csrf-cookie then POST session with exact headers, empty body, and a UUID Idempotency-Key, sending no amount/currency/plan/card values', async () => {
        const user = userEvent.setup();
        let csrfCalls = 0;
        let postCalls = 0;
        let capturedInit: RequestInit | undefined;

        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeActiveSubscription()),
                [IYZICO_SESSION_ENDPOINT]: async (_url, init) => {
                    if (!init || (init.method ?? 'GET') === 'GET') {
                        return jsonResponse(200, { state: 'none' });
                    }
                    postCalls += 1;
                    capturedInit = init;
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(202, {
                        state: 'initiated',
                        conversation_id: 'conv-1',
                        amount_minor: 149900,
                        currency: 'TRY',
                    });
                },
                [CSRF_ENDPOINT]: async () => {
                    csrfCalls += 1;
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(204, {});
                },
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(
                within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
            ).toBeEnabled();
        });

        expect(fetchSpy.mock.calls.some(([calledUrl]) => String(calledUrl) === CSRF_ENDPOINT)).toBe(
            false,
        );
        expect(postCalls).toBe(0);

        await user.click(
            within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
        );

        await waitFor(() => {
            expect(postCalls).toBe(1);
        });

        expect(csrfCalls).toBe(1);

        const csrfCallIndex = fetchSpy.mock.calls.findIndex(
            ([calledUrl]) => String(calledUrl) === CSRF_ENDPOINT,
        );
        const postCallIndex = fetchSpy.mock.calls.findIndex(
            ([calledUrl, init]) =>
                String(calledUrl) === IYZICO_SESSION_ENDPOINT &&
                (init as RequestInit | undefined)?.method === 'POST',
        );
        expect(csrfCallIndex).toBeGreaterThanOrEqual(0);
        expect(postCallIndex).toBeGreaterThan(csrfCallIndex);

        expect(capturedInit?.method).toBe('POST');
        expect(capturedInit?.credentials).toBe('same-origin');
        expect(new Headers(capturedInit?.headers).get('Accept')).toBe('application/json');
        expect(new Headers(capturedInit?.headers).get('Content-Type')).toBe('application/json');

        const xsrfHeader = new Headers(capturedInit?.headers).get('X-XSRF-TOKEN');
        expect(xsrfHeader).toBeTruthy();

        expect(capturedInit?.body).toBe('{}');

        const idempotencyKey = new Headers(capturedInit?.headers).get('Idempotency-Key');
        expect(idempotencyKey).toMatch(UUID_RE);

        const bodyParsed = JSON.parse(String(capturedInit?.body));
        expect(bodyParsed).toEqual({});
        expect(Object.keys(bodyParsed)).toHaveLength(0);
    });

    it('disables Start and shows initiated/processing status while the POST is pending, without issuing a duplicate POST', async () => {
        const user = userEvent.setup();
        let resolvePost: (value: Response) => void = () => {};
        let postCalls = 0;

        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeActiveSubscription()),
                [IYZICO_SESSION_ENDPOINT]: async (_url, init) => {
                    if (!init || (init.method ?? 'GET') === 'GET') {
                        return jsonResponse(200, { state: 'none' });
                    }
                    postCalls += 1;
                    return new Promise<Response>((resolve) => {
                        resolvePost = resolve;
                    });
                },
                [CSRF_ENDPOINT]: async () => {
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(204, {});
                },
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(
                within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
            ).toBeEnabled();
        });

        const startButton = within(iyzicoRegion()).getByRole('button', {
            name: /start sandbox checkout/i,
        });
        await user.click(startButton);
        await user.click(startButton);

        await waitFor(() => {
            expect(
                within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
            ).toBeDisabled();
        });

        expect(within(iyzicoRegion()).getByText(/initiated|processing/i)).toBeInTheDocument();
        expect(postCalls).toBe(1);

        resolvePost(
            jsonResponse(202, {
                state: 'initiated',
                conversation_id: 'conv-2',
                amount_minor: 149900,
                currency: 'TRY',
            }),
        );

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByText(/conv-2/i)).toBeInTheDocument();
        });
    });

    it('renders only server-returned 202 transaction data plus the sandbox disclaimer, without auto navigating, and offers a Continue link only for an https redirect_url', async () => {
        const originalLocation = window.location.href;

        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeActiveSubscription()),
                [IYZICO_SESSION_ENDPOINT]: async (_url, init) => {
                    if (!init || (init.method ?? 'GET') === 'GET') {
                        return jsonResponse(200, { state: 'none' });
                    }
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(202, {
                        state: 'initiated',
                        conversation_id: 'conv-https',
                        amount_minor: 149900,
                        currency: 'TRY',
                        redirect_url: 'https://sandbox-iyzico.example.com/checkout/conv-https',
                    });
                },
                [CSRF_ENDPOINT]: async () => {
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(204, {});
                },
            }),
        );

        const user = userEvent.setup();
        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(
                within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
            ).toBeEnabled();
        });

        await user.click(
            within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
        );

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByText(/conv-https/i)).toBeInTheDocument();
        });

        expect(
            within(iyzicoRegion()).getByText(/149900|1[,.]?499(?:[.,]00)?/i),
        ).toBeInTheDocument();
        expect(within(iyzicoRegion()).getByText(/try/i)).toBeInTheDocument();
        expect(
            within(iyzicoRegion()).getByText(/no real money|not real money|no real payment/i),
        ).toBeInTheDocument();

        expect(window.location.href).toBe(originalLocation);

        const continueLink = within(iyzicoRegion()).getByRole('link', {
            name: /continue to iyzico sandbox/i,
        });
        expect(continueLink).toHaveAttribute(
            'href',
            'https://sandbox-iyzico.example.com/checkout/conv-https',
        );
    });

    it('rejects a non-https redirect_url as an accessible failure and renders no Continue link', async () => {
        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeActiveSubscription()),
                [IYZICO_SESSION_ENDPOINT]: async (_url, init) => {
                    if (!init || (init.method ?? 'GET') === 'GET') {
                        return jsonResponse(200, { state: 'none' });
                    }
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(202, {
                        state: 'initiated',
                        conversation_id: 'conv-js',
                        amount_minor: 149900,
                        currency: 'TRY',
                        redirect_url: "javascript:alert('x')",
                    });
                },
                [CSRF_ENDPOINT]: async () => {
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(204, {});
                },
            }),
        );

        const user = userEvent.setup();
        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(
                within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
            ).toBeEnabled();
        });

        await user.click(
            within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
        );

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByRole('alert')).toBeInTheDocument();
        });

        expect(
            within(iyzicoRegion()).queryByRole('link', { name: /continue to iyzico sandbox/i }),
        ).not.toBeInTheDocument();
    });

    it('renders existing processing and succeeded session GET states honestly', async () => {
        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeActiveSubscription()),
                [IYZICO_SESSION_ENDPOINT]: async () =>
                    jsonResponse(200, {
                        state: 'succeeded',
                        conversation_id: 'conv-old',
                        amount_minor: 149900,
                        currency: 'TRY',
                    }),
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByText(/succeeded/i)).toBeInTheDocument();
        });

        expect(within(iyzicoRegion()).getByText(/conv-old/i)).toBeInTheDocument();
    });

    it('on a session failure state renders an alert with Retry, and Retry uses a fresh idempotency UUID and repeats CSRF then POST', async () => {
        const user = userEvent.setup();
        const idempotencyKeys: string[] = [];
        let postCalls = 0;
        let csrfCalls = 0;

        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeActiveSubscription()),
                [IYZICO_SESSION_ENDPOINT]: async (_url, init) => {
                    if (!init || (init.method ?? 'GET') === 'GET') {
                        return jsonResponse(200, { state: 'none' });
                    }
                    postCalls += 1;
                    const key = new Headers(init.headers).get('Idempotency-Key');
                    if (key) idempotencyKeys.push(key);
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    if (postCalls === 1) {
                        return jsonResponse(200, { state: 'failed', conversation_id: 'conv-fail' });
                    }
                    return jsonResponse(202, { state: 'initiated', conversation_id: 'conv-retry' });
                },
                [CSRF_ENDPOINT]: async () => {
                    csrfCalls += 1;
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(204, {});
                },
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(
                within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
            ).toBeEnabled();
        });

        await user.click(
            within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
        );

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByRole('alert')).toBeInTheDocument();
        });

        const retryButton = within(iyzicoRegion()).getByRole('button', { name: /retry/i });
        await user.click(retryButton);

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByText(/conv-retry/i)).toBeInTheDocument();
        });

        expect(postCalls).toBe(2);
        expect(csrfCalls).toBe(2);
        expect(idempotencyKeys).toHaveLength(2);
        expect(idempotencyKeys[0]).toMatch(UUID_RE);
        expect(idempotencyKeys[1]).toMatch(UUID_RE);
        expect(idempotencyKeys[0]).not.toBe(idempotencyKeys[1]);
    });

    it('composes the sandbox checkout region as intrinsic w-full/fluid, with no responsive breakpoint classes', async () => {
        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeActiveSubscription()),
                [IYZICO_SESSION_ENDPOINT]: async () => jsonResponse(200, { state: 'none' }),
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(
                within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
            ).toBeEnabled();
        });

        const region = iyzicoRegion();
        expect(region.className).toMatch(/\bw-full\b/);

        const breakpointClassRegex = /\b(sm|md|lg|xl|2xl):/;
        const walk = (el: Element): boolean => {
            if (breakpointClassRegex.test(el.className ?? '')) return true;
            return Array.from(el.children).some((child) => walk(child));
        };
        expect(walk(region)).toBe(false);
    });

    /**
     * IYZICO_SANDBOX_REVIEW_CORRECTION_RED
     *
     * Reviewer-correction additions. Current production trusts the checkout
     * POST response body without checking response.ok, validates only
     * SessionData.state (not the other optional fields), degrades a failed
     * POST's Retry into a GET-only fetchSession, and conflates a
     * subscription transport/parse failure with the honest "no active
     * subscription" state. Every assertion below must fail against current
     * production.
     */

    it('treats a checkout POST that returns a non-2xx status as a transport failure even when the body looks like a valid transaction, and offers a retry instead of rendering it', async () => {
        const user = userEvent.setup();

        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeActiveSubscription()),
                [IYZICO_SESSION_ENDPOINT]: async (_url, init) => {
                    if (!init || (init.method ?? 'GET') === 'GET') {
                        return jsonResponse(200, { state: 'none' });
                    }
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(422, {
                        state: 'initiated',
                        conversation_id: 'conv-non-2xx',
                        amount_minor: 149900,
                        currency: 'TRY',
                    });
                },
                [CSRF_ENDPOINT]: async () => {
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(204, {});
                },
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(
                within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
            ).toBeEnabled();
        });

        await user.click(
            within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
        );

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByRole('alert')).toBeInTheDocument();
        });

        expect(within(iyzicoRegion()).queryByText(/conv-non-2xx/i)).not.toBeInTheDocument();
        expect(
            within(iyzicoRegion()).queryByRole('link', { name: /continue to iyzico sandbox/i }),
        ).not.toBeInTheDocument();
        expect(within(iyzicoRegion()).getByRole('button', { name: /retry/i })).toBeInTheDocument();
    });

    it('on a checkout POST network/non-JSON failure after an explicit Start click, Retry repeats CSRF then POST with a fresh Idempotency-Key rather than degrading into a session GET', async () => {
        const user = userEvent.setup();
        const idempotencyKeys: string[] = [];
        let postAttempts = 0;
        let csrfCalls = 0;

        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeActiveSubscription()),
                [IYZICO_SESSION_ENDPOINT]: async (_url, init) => {
                    if (!init || (init.method ?? 'GET') === 'GET') {
                        return jsonResponse(200, { state: 'none' });
                    }
                    postAttempts += 1;
                    const key = new Headers(init.headers).get('Idempotency-Key');
                    if (key) idempotencyKeys.push(key);
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    if (postAttempts === 1) {
                        throw new TypeError('Failed to fetch');
                    }
                    return jsonResponse(202, {
                        state: 'initiated',
                        conversation_id: 'conv-retry-post',
                    });
                },
                [CSRF_ENDPOINT]: async () => {
                    csrfCalls += 1;
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(204, {});
                },
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(
                within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
            ).toBeEnabled();
        });

        await user.click(
            within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
        );

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByRole('alert')).toBeInTheDocument();
        });

        expect(postAttempts).toBe(1);
        expect(csrfCalls).toBe(1);

        const retryButton = within(iyzicoRegion()).getByRole('button', { name: /retry/i });
        await user.click(retryButton);

        await waitFor(() => {
            expect(postAttempts).toBe(2);
        });

        expect(csrfCalls).toBe(2);
        expect(idempotencyKeys).toHaveLength(2);
        expect(idempotencyKeys[0]).toMatch(UUID_RE);
        expect(idempotencyKeys[1]).toMatch(UUID_RE);
        expect(idempotencyKeys[0]).not.toBe(idempotencyKeys[1]);
    });

    it('rejects a checkout session response with malformed optional fields (non-string conversation_id, non-numeric amount_minor, non-string currency) as an accessible alert without rendering the malformed values', async () => {
        const user = userEvent.setup();

        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => jsonResponse(200, makeActiveSubscription()),
                [IYZICO_SESSION_ENDPOINT]: async (_url, init) => {
                    if (!init || (init.method ?? 'GET') === 'GET') {
                        return jsonResponse(200, { state: 'none' });
                    }
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(202, {
                        state: 'initiated',
                        conversation_id: 918273645,
                        amount_minor: '149900',
                        currency: 7,
                    });
                },
                [CSRF_ENDPOINT]: async () => {
                    document.cookie = 'XSRF-TOKEN=test-token; path=/;';
                    return jsonResponse(204, {});
                },
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(
                within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
            ).toBeEnabled();
        });

        await user.click(
            within(iyzicoRegion()).getByRole('button', { name: /start sandbox checkout/i }),
        );

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByRole('alert')).toBeInTheDocument();
        });

        expect(within(iyzicoRegion()).queryByText(/918273645/)).not.toBeInTheDocument();
    });

    it('treats a subscription GET transport/non-OK/malformed failure as distinct from "no active subscription", shows an accessible failure with Retry, and issues zero Iyzico session calls until a valid active subscription is confirmed', async () => {
        const user = userEvent.setup();
        let subscriptionCalls = 0;

        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => {
                    subscriptionCalls += 1;
                    if (subscriptionCalls <= 2) {
                        throw new TypeError('Failed to fetch');
                    }
                    return jsonResponse(200, makeActiveSubscription());
                },
                [IYZICO_SESSION_ENDPOINT]: async () => jsonResponse(200, { state: 'none' }),
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByRole('alert')).toBeInTheDocument();
        });

        expect(
            fetchSpy.mock.calls.some(
                ([calledUrl]) => String(calledUrl) === IYZICO_SESSION_ENDPOINT,
            ),
        ).toBe(false);

        const retryButton = within(iyzicoRegion()).getByRole('button', { name: /retry/i });
        await user.click(retryButton);

        await waitFor(() => {
            expect(subscriptionCalls).toBe(3);
        });

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByText(/ready/i)).toBeInTheDocument();
        });
    });

    it('treats a subscription GET rejection with a non-TypeError Error as an accessible failure, not "no active subscription", and issues zero Iyzico session calls', async () => {
        fetchSpy.mockImplementation(
            baseFetchImpl({
                [SUBSCRIPTION_ENDPOINT]: async () => {
                    throw new Error('subscription service unavailable');
                },
                [IYZICO_SESSION_ENDPOINT]: async () => jsonResponse(200, { state: 'none' }),
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(iyzicoRegion()).getByRole('alert')).toBeInTheDocument();
        });

        const retryButton = within(iyzicoRegion()).getByRole('button', { name: /retry/i });
        expect(retryButton).toBeInTheDocument();

        expect(
            within(iyzicoRegion()).queryByText(/no active (plan|subscription)/i),
        ).not.toBeInTheDocument();

        const iyzicoCalls = fetchSpy.mock.calls.filter(
            ([calledUrl]) => String(calledUrl) === IYZICO_SESSION_ENDPOINT,
        );
        expect(iyzicoCalls).toHaveLength(0);
    });
});
