const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';

function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Fetches the Sanctum CSRF cookie before a state-changing auth request.
 * Must be awaited immediately before the mutation; throws on failure so the
 * caller can suppress the mutation (S1WP02A-R1-CSRF-01).
 */
export async function bootstrapCsrfCookie(): Promise<void> {
    const response = await fetch(CSRF_COOKIE_URL, {
        credentials: 'include',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error('Failed to bootstrap CSRF cookie');
    }
}

/**
 * Builds a fetch() RequestInit for state-changing auth requests: stateful
 * cookie session (credentials: include) plus the Sanctum SPA XSRF header
 * round-trip. Never attaches an Authorization bearer header and never
 * touches localStorage (S1WP02A-CSRF-01/SESSION-01 — no bearer token).
 */
export function buildAuthRequestInit(init: RequestInit = {}): RequestInit {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');

    const xsrfToken = readCookie('XSRF-TOKEN');
    if (xsrfToken) {
        headers.set('X-XSRF-TOKEN', xsrfToken);
    }

    return {
        ...init,
        credentials: 'include',
        headers,
    };
}
