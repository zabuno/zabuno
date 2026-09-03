import '@testing-library/jest-dom/vitest';

/*
    XHR → fetch KÖPRÜSÜ (FF-68, `docs/49` Faz 2).

    Yükleme artık `XMLHttpRequest` ile gidiyor — `fetch` gönderim
    ilerlemesini bildiremez. Testlerin tamamı ise ağı `fetch`'i sahteleyerek
    kesiyor; XHR gerçek ağa çıksaydı 40 test birden kırılırdı. Bu köprü
    XHR'ı `globalThis.fetch`'e yönlendirir: mevcut sahte cevaplar aynen
    çalışır, üretim kodu değişmez.
*/
class FetchBridgedXMLHttpRequest {
    public upload: { onprogress: ((event: ProgressEvent) => void) | null } = { onprogress: null };
    public onload: (() => void) | null = null;
    public onerror: (() => void) | null = null;
    public onabort: (() => void) | null = null;
    public withCredentials = false;
    public status = 0;
    public responseText = '';
    private method = 'GET';
    private url = '';
    private headers: Record<string, string> = {};
    private responseHeaders: Record<string, string> = {};

    open(method: string, url: string): void {
        this.method = method;
        this.url = url;
    }

    setRequestHeader(name: string, value: string): void {
        this.headers[name] = value;
    }

    getResponseHeader(name: string): string | null {
        return this.responseHeaders[name.toLowerCase()] ?? null;
    }

    abort(): void {
        this.onabort?.();
    }

    send(body?: Document | XMLHttpRequestBodyInit | null): void {
        void (async () => {
            try {
                const response = (await globalThis.fetch(this.url, {
                    method: this.method,
                    headers: this.headers,
                    body: body as BodyInit,
                    credentials: this.withCredentials ? 'include' : 'same-origin',
                })) as Response & { text?: () => Promise<string> };

                this.status = response.status;
                const contentType =
                    typeof response.headers?.get === 'function'
                        ? (response.headers.get('Content-Type') ?? 'application/json')
                        : 'application/json';
                this.responseHeaders['content-type'] = contentType;

                if (typeof response.text === 'function') {
                    this.responseText = await response.text();
                } else {
                    // Sahte cevaplar çoğu zaman yalnız `json()` taşır.
                    this.responseText = JSON.stringify(await response.json());
                }

                this.onload?.();
            } catch {
                this.onerror?.();
            }
        })();
    }
}

Object.defineProperty(globalThis, 'XMLHttpRequest', {
    configurable: true,
    writable: true,
    value: FetchBridgedXMLHttpRequest,
});
