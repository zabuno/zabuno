/* global self, caches, fetch, Response, URL */

/**
 * S1-WP04b11 public-diner classic service worker.
 *
 * Network-first, same-origin GET navigation only, scoped to /menu/{token}
 * and /q/{token}. Only successful (2xx) HTML responses — from either
 * /menu/{token} or /q/{token} — are cached, always keyed by the exact
 * /menu/{token} URL, under a fixed cache name. Offline, /q/{token} is
 * served from that cached /menu/{token} entry for the same token. Nothing
 * else is intercepted, cached, or mutated.
 */

var CACHE_NAME = 'public-diner-menu-v1';

function tokenFromPath(pathname, prefix) {
    if (pathname.indexOf(prefix) !== 0) {
        return null;
    }

    var rest = pathname.slice(prefix.length);
    var token = rest.split('/')[0];

    return token ? token : null;
}

function isHtmlResponse(response) {
    var contentType =
        response.headers && response.headers.get ? response.headers.get('content-type') : null;

    return typeof contentType === 'string' && contentType.indexOf('html') !== -1;
}

function offlineResponse() {
    return new Response(
        '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>Çevrimdışı</title></head>' +
            '<body><p>Bu menü şu anda çevrimdışı ve daha önce hiç görüntülenmemiş. ' +
            'Lütfen bağlantınızı kontrol edip tekrar deneyin.</p></body></html>',
        { status: 503, headers: { 'content-type': 'text/html' } },
    );
}

async function handlePublicDinerRequest(request, url, menuToken, qToken) {
    var cache = await caches.open(CACHE_NAME);
    var token = menuToken !== null ? menuToken : qToken;
    var menuUrl = url.origin + '/menu/' + token;

    try {
        var networkResponse = await fetch(request);

        if (networkResponse.ok && isHtmlResponse(networkResponse)) {
            await cache.put(menuUrl, networkResponse.clone());
        }

        return networkResponse;
    } catch {
        var cached = await cache.match(menuUrl);

        if (cached) {
            return cached;
        }

        return offlineResponse();
    }
}

self.addEventListener('fetch', function (event) {
    var request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    if (request.mode && request.mode !== 'navigate') {
        return;
    }

    var url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    var menuToken = tokenFromPath(url.pathname, '/menu/');
    var qToken = tokenFromPath(url.pathname, '/q/');

    if (menuToken === null && qToken === null) {
        return;
    }

    event.respondWith(handlePublicDinerRequest(request, url, menuToken, qToken));
});
