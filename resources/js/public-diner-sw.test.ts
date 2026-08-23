import { describe, it, expect, beforeEach } from 'vitest';
import { readFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import vm from 'node:vm';

/**
 * S1-WP04b11 RED — classic public-diner service worker offline contract.
 *
 * public/public-diner-sw.js does not exist yet. This is the one canonical,
 * served worker source — no duplicate copy under resources/. This suite
 * loads that real classic worker source (no ES module syntax — a plain
 * `importScripts` style script, as registered by the Blade view via
 * `navigator.serviceWorker.register('/public-diner-sw.js', { scope })`)
 * into an in-memory `self`/`caches`/`fetch` sandbox and exercises its
 * `fetch` event listener directly, without a browser or a real network.
 *
 * Contract under test:
 *   - network-first for same-origin GET navigation requests, only under
 *     /menu/ and /q/
 *   - only successful (2xx) HTML responses are cached
 *   - a fresh /menu/{token} response replaces the exact prior cache entry
 *     for that same URL
 *   - offline, GET /menu/{token} returns the last cached successful
 *     response for that token
 *   - offline, GET /q/{token} is served from the cached /menu/{token}
 *     response for the same token (no redirect chase over the network)
 *   - offline on a first visit (nothing cached yet) returns an honest
 *     read-only 503 HTML response, not fabricated menu content
 *   - /app, /api, auth, payment, and any other path are never intercepted
 *     or cached
 *   - the cache name is a fixed, deterministic string
 *
 * The sandbox `fetch` only accepts a `FakeRequest` instance — the same
 * object the worker received on the `fetch` event — and throws on any
 * fabricated plain object such as `{ url, method }`. This proves the
 * worker must forward the original `event.request` into `fetch(...)`
 * rather than rebuild a lookalike request, which is exactly what a real
 * browser's `Request` object requires (headers, credentials, redirect
 * mode, etc. are not reproducible by hand).
 *
 * A dedicated case also covers the QR-first path: a browser navigating
 * `GET /q/{token}` follows the redirect itself before the worker's fetch
 * handler ever sees a response, so the network fetch for `/q/{token}`
 * already resolves to the final 200 text/html menu document. That
 * response must still be cached under the exact `/menu/{token}` key so a
 * QR-first diner who never visited `/menu/{token}` directly still has an
 * offline snapshot for it.
 *
 * Every test below fails RED because the source file is absent.
 *
 * Requirement IDs: PWA-SW-NETWORK-FIRST-01, PWA-SW-CACHE-SUCCESS-ONLY-01,
 * PWA-SW-CACHE-REPLACE-01, PWA-SW-OFFLINE-MENU-01, PWA-SW-OFFLINE-Q-MAP-01,
 * PWA-SW-OFFLINE-FIRST-VISIT-503-01, PWA-SW-SCOPE-GUARD-01,
 * PWA-SW-CACHE-NAME-DETERMINISTIC-01, PWA-SW-FORWARD-ORIGINAL-REQUEST-01,
 * PWA-SW-ONLINE-Q-CACHE-POPULATION-01.
 */

const SW_SOURCE_PATH = path.join(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
    'public',
    'public-diner-sw.js'
);

type StoredResponse = {
    status: number;
    headers: Record<string, string>;
    body: string;
};

class FakeRequest {
    readonly url: string;
    readonly method: string;
    readonly mode: string;

    constructor(url: string, method = 'GET', mode = 'navigate') {
        this.url = url;
        this.method = method;
        this.mode = mode;
    }
}

class FakeResponse {
    readonly status: number;
    readonly ok: boolean;
    readonly headers: { get(name: string): string | null };
    private readonly body: string;

    constructor(body: string, init: { status?: number; headers?: Record<string, string> } = {}) {
        this.body = body;
        this.status = init.status ?? 200;
        this.ok = this.status >= 200 && this.status < 300;
        const headers = init.headers ?? {};
        this.headers = {
            get: (name: string) => headers[name.toLowerCase()] ?? headers[name] ?? null,
        };
    }

    clone(): FakeResponse {
        return new FakeResponse(this.body, {
            status: this.status,
            headers: { 'content-type': this.headers.get('content-type') ?? 'text/html' },
        });
    }

    async text(): Promise<string> {
        return this.body;
    }
}

type CacheKey = FakeRequest | string;

class FakeCache {
    private store = new Map<string, StoredResponse>();

    async match(request: CacheKey): Promise<FakeResponse | undefined> {
        const url = typeof request === 'string' ? request : request.url;
        const stored = this.store.get(url);
        if (!stored) return undefined;
        return new FakeResponse(stored.body, { status: stored.status, headers: stored.headers });
    }

    async put(request: CacheKey, response: FakeResponse): Promise<void> {
        const url = typeof request === 'string' ? request : request.url;
        this.store.set(url, {
            status: response.status,
            headers: { 'content-type': response.headers.get('content-type') ?? 'text/html' },
            body: await response.clone().text(),
        });
    }

    async has(url: string): Promise<boolean> {
        return this.store.has(url);
    }
}

class FakeCacheStorage {
    private caches = new Map<string, FakeCache>();

    async open(name: string): Promise<FakeCache> {
        if (!this.caches.get(name)) {
            this.caches.set(name, new FakeCache());
        }
        return this.caches.get(name) as FakeCache;
    }

    names(): string[] {
        return Array.from(this.caches.keys());
    }
}

function loadSwSource(): string {
    if (!existsSync(SW_SOURCE_PATH)) {
        throw new Error(`expected public/public-diner-sw.js to exist at ${SW_SOURCE_PATH}`);
    }
    return readFileSync(SW_SOURCE_PATH, 'utf8');
}

type FetchImpl = (request: FakeRequest) => Promise<FakeResponse>;

type FetchEventLike = {
    request: FakeRequest;
    respondWith(promise: Promise<FakeResponse>): void;
};

type SandboxSelf = {
    addEventListener(type: string, handler: (event: FetchEventLike) => void): void;
    location: { origin: string };
    skipWaiting: () => void;
    clients: { claim: () => void };
};

type SandboxHandle = {
    caches: FakeCacheStorage;
    triggerFetch: (url: string, opts?: { method?: string }) => Promise<FakeResponse>;
    setNetworkImpl: (impl: FetchImpl) => void;
};

function buildSandbox(networkImpl: FetchImpl): SandboxHandle {
    const cacheStorage = new FakeCacheStorage();
    let currentNetworkImpl = networkImpl;

    const listeners: Record<string, Array<(event: FetchEventLike) => void>> = {};

    const selfObj: SandboxSelf = {
        addEventListener(type: string, handler: (event: FetchEventLike) => void) {
            listeners[type] = listeners[type] ?? [];
            listeners[type].push(handler);
        },
        location: { origin: 'https://diner.example.test' },
        skipWaiting: () => {},
        clients: { claim: () => {} },
    };

    // Only a real FakeRequest instance (the same object the worker's
    // "fetch" listener received on event.request) is accepted here. A
    // worker that fabricates its own plain { url, method } object instead
    // of forwarding the original request must fail this check.
    const sandboxFetch = (request: unknown): Promise<FakeResponse> => {
        if (!(request instanceof FakeRequest)) {
            throw new TypeError(
                'sandbox fetch() received a fabricated request — the worker must forward the original event.request, not rebuild a plain object'
            );
        }
        return currentNetworkImpl(request);
    };

    const context = vm.createContext({
        self: selfObj,
        caches: cacheStorage,
        fetch: sandboxFetch,
        console,
        Response: FakeResponse,
        URL,
    });

    vm.runInContext(loadSwSource(), context, { filename: 'public-diner-sw.js' });

    async function triggerFetch(url: string, opts: { method?: string } = {}): Promise<FakeResponse> {
        const fetchListeners = listeners['fetch'] ?? [];
        if (fetchListeners.length === 0) {
            throw new Error('service worker registered no "fetch" event listener');
        }

        const request = new FakeRequest(url, opts.method ?? 'GET');
        let respondedWith: Promise<FakeResponse> | null = null;

        const event: FetchEventLike = {
            request,
            respondWith(promise: Promise<FakeResponse>) {
                respondedWith = promise;
            },
        };

        for (const handler of fetchListeners) {
            handler(event);
        }

        if (!respondedWith) {
            throw new Error(`fetch listener did not call respondWith() for ${url}`);
        }

        return respondedWith;
    }

    return {
        caches: cacheStorage,
        triggerFetch,
        setNetworkImpl: (impl: FetchImpl) => {
            currentNetworkImpl = impl;
        },
    };
}

describe('public-diner-sw.js classic service worker', () => {
    let online: SandboxHandle;

    beforeEach(() => {
        online = buildSandbox(async (request) => {
            if (request.url.includes('/menu/valid-token')) {
                return new FakeResponse('<html><body>Menu valid-token v1</body></html>', {
                    status: 200,
                    headers: { 'content-type': 'text/html' },
                });
            }
            if (request.url.includes('/q/valid-token')) {
                return new FakeResponse('', { status: 302, headers: { location: '/menu/valid-token' } });
            }
            return new FakeResponse('not found', { status: 404, headers: { 'content-type': 'text/html' } });
        });
    });

    it('caches a successful same-origin /menu/{token} navigation response', async () => {
        const response = await online.triggerFetch('https://diner.example.test/menu/valid-token');
        expect(response.status).toBe(200);

        const cacheNames = online.caches.names();
        expect(cacheNames).toHaveLength(1);

        const cache = await online.caches.open(cacheNames[0]);
        const cached = await cache.match('https://diner.example.test/menu/valid-token');
        expect(cached).toBeDefined();
        expect(await cached!.text()).toContain('Menu valid-token v1');
    });

    it('uses a fixed, deterministic cache name', async () => {
        await online.triggerFetch('https://diner.example.test/menu/valid-token');
        const cacheNames = online.caches.names();
        expect(cacheNames).toEqual(['public-diner-menu-v1']);
    });

    it('replaces the exact prior cache entry when a fresh menu response arrives', async () => {
        await online.triggerFetch('https://diner.example.test/menu/valid-token');

        online.setNetworkImpl(async (request) => {
            if (request.url.includes('/menu/valid-token')) {
                return new FakeResponse('<html><body>Menu valid-token v2</body></html>', {
                    status: 200,
                    headers: { 'content-type': 'text/html' },
                });
            }
            return new FakeResponse('not found', { status: 404 });
        });

        await online.triggerFetch('https://diner.example.test/menu/valid-token');

        const cache = await online.caches.open('public-diner-menu-v1');
        const cached = await cache.match('https://diner.example.test/menu/valid-token');
        expect(await cached!.text()).toContain('Menu valid-token v2');
        expect(await cached!.text()).not.toContain('v1');
    });

    it('does not cache a non-2xx response', async () => {
        await online.triggerFetch('https://diner.example.test/menu/unknown-token');

        const cache = await online.caches.open('public-diner-menu-v1');
        const cached = await cache.match('https://diner.example.test/menu/unknown-token');
        expect(cached).toBeUndefined();
    });

    it('serves the last cached menu response when offline', async () => {
        await online.triggerFetch('https://diner.example.test/menu/valid-token');

        const offline = buildSandbox(async () => {
            throw new TypeError('network unavailable');
        });

        const cache = await offline.caches.open('public-diner-menu-v1');
        const onlineCache = await online.caches.open('public-diner-menu-v1');
        const cached = await onlineCache.match('https://diner.example.test/menu/valid-token');
        await cache.put('https://diner.example.test/menu/valid-token', cached!);

        const response = await offline.triggerFetch('https://diner.example.test/menu/valid-token');
        expect(response.status).toBe(200);
        expect(await response.text()).toContain('Menu valid-token v1');
    });

    it('maps offline /q/{token} to the cached /menu/{token} response', async () => {
        await online.triggerFetch('https://diner.example.test/menu/valid-token');

        const offline = buildSandbox(async () => {
            throw new TypeError('network unavailable');
        });

        const onlineCache = await online.caches.open('public-diner-menu-v1');
        const cached = await onlineCache.match('https://diner.example.test/menu/valid-token');
        const offlineCache = await offline.caches.open('public-diner-menu-v1');
        await offlineCache.put('https://diner.example.test/menu/valid-token', cached!);

        const response = await offline.triggerFetch('https://diner.example.test/q/valid-token');
        expect(response.status).toBe(200);
        expect(await response.text()).toContain('Menu valid-token v1');
    });

    it('returns an honest read-only 503 HTML response on a first-visit offline request', async () => {
        const offline = buildSandbox(async () => {
            throw new TypeError('network unavailable');
        });

        const response = await offline.triggerFetch('https://diner.example.test/menu/never-seen-token');
        expect(response.status).toBe(503);
        const body = await response.text();
        expect(body.toLowerCase()).toContain('html');
        expect(body).not.toContain('Menu valid-token');
    });

    it('never intercepts /app paths', async () => {
        const sandbox = buildSandbox(async () => new FakeResponse('app shell', { status: 200 }));

        await expect(
            sandbox.triggerFetch('https://diner.example.test/app/dashboard')
        ).rejects.toThrow();
    });

    it('never intercepts /api paths', async () => {
        const sandbox = buildSandbox(async () => new FakeResponse('{}', { status: 200 }));

        await expect(
            sandbox.triggerFetch('https://diner.example.test/api/workspaces')
        ).rejects.toThrow();
    });

    it('never intercepts auth or payment paths', async () => {
        const sandbox = buildSandbox(async () => new FakeResponse('ok', { status: 200 }));

        await expect(
            sandbox.triggerFetch('https://diner.example.test/login')
        ).rejects.toThrow();
        await expect(
            sandbox.triggerFetch('https://diner.example.test/payments/checkout')
        ).rejects.toThrow();
    });

    it('forwards the original request instance to fetch instead of a fabricated {url, method} object', async () => {
        let capturedRequest: FakeRequest | null = null;

        const sandbox = buildSandbox(async (request) => {
            capturedRequest = request;
            return new FakeResponse('<html><body>Menu valid-token v1</body></html>', {
                status: 200,
                headers: { 'content-type': 'text/html' },
            });
        });

        await sandbox.triggerFetch('https://diner.example.test/menu/valid-token');

        expect(capturedRequest).toBeInstanceOf(FakeRequest);
    });

    it('caches the browser-followed final 200 response for /q/{token} under the exact /menu/{token} key', async () => {
        // A browser navigating GET /q/{token} follows the redirect itself
        // before the worker's fetch handler observes a response, so the
        // network fetch for /q/{token} already resolves to the final
        // 200 text/html menu document.
        const qOnline = buildSandbox(async (request) => {
            if (request.url.includes('/q/valid-token')) {
                return new FakeResponse('<html><body>Menu valid-token v1</body></html>', {
                    status: 200,
                    headers: { 'content-type': 'text/html' },
                });
            }
            return new FakeResponse('not found', { status: 404, headers: { 'content-type': 'text/html' } });
        });

        const response = await qOnline.triggerFetch('https://diner.example.test/q/valid-token');
        expect(response.status).toBe(200);

        const cache = await qOnline.caches.open('public-diner-menu-v1');
        const cached = await cache.match('https://diner.example.test/menu/valid-token');
        expect(cached).toBeDefined();
        expect(await cached!.text()).toContain('Menu valid-token v1');
    });
});
