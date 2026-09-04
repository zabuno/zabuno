import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';

import { MediaPage } from './MediaPage';

/**
 * MEDIA_FRONTEND_RED
 *
 * RED suite for the S1-WP01A Media surface. Correction: MediaPage is
 * frozen to the real intake API contract — GET on mount, multipart POST
 * to upload, DELETE to remove an own item — instead of a disabled,
 * zero-fetch stub. It has no accessible upload region, no library
 * region, no fetch wiring, and no enabled file/alt/slot controls today,
 * so the assertions below must fail against current production. No
 * production, i18n, Storybook, backend or Git edits are made from this
 * file.
 */

const WORKSPACE_ID = 5;
const MEDIA_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/media`;

const FIXED_PIXEL_CLASS_PATTERN =
    /(^|[\s"'`])(w|h|min-w|max-w|min-h|max-h)-\[\d+px\]|(^|[\s"'`])(w|h)-(px|0\.5|1|2|3|4|5|6|7|8|9|10|11|12|14|16|20|24|28|32|36|40|44|48|52|56|60|64|72|80|96)(?=[\s"'`]|$)/;
const BREAKPOINT_CLASS_PATTERN = /(^|[\s"'`])(sm|md|lg|xl|2xl):/;

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

function collectClassLists(root: HTMLElement): string[] {
    const classLists: string[] = [];
    if (root.className) classLists.push(root.className);
    root.querySelectorAll<HTMLElement>('*').forEach((el) => {
        if (el.className && typeof el.className === 'string') classLists.push(el.className);
    });
    return classLists;
}

/**
 * Slot politikaları ayrı bir uç noktadan gelir ve HER fetch taklidinin
 * bunu karşılaması gerekir: slot listesi artık koda gömülü değil.
 *
 * Tek bir yardımcıda durur ki bir sonraki test onu unutmasın; unutulduğunda
 * belirti "seçenek bulunamadı" olur ve sebebi bulmak zaman alır.
 */
function slotPoliciesResponse(url: string): Response | null {
    if (String(url) !== '/api/media/slot-policies') {
        return null;
    }

    return {
        ok: true,
        status: 200,
        json: async () => ({
            slots: [
                {
                    key: 'itemImage',
                    minWidth: 1000,
                    minHeight: 1000,
                    aspect: '1:1',
                    formats: ['jpeg', 'png', 'webp'],
                    altRequired: true,
                },
            ],
            limits: { maxBytes: 31457280, maxMegapixels: 40 },
        }),
    } as Response;
}

/**
 * YÜKLEME ARTIK KENDİ BÖLÜMÜDÜR (FF-131, kanonik kaynak
 * `docs/reference/media-manager/`, gerekçe `docs/108` §1).
 *
 * Medya bu depoda düz bir sayfaydı: solda yükleme kartı, sağda kütüphane.
 * Kaynak ise kendi kabuğu olan bir UYGULAMA gösteriyor — bir menüyü
 * yönetmekle bir dosya deposunu yönetmek farklı işlerdir ve tek sayfaya
 * sıkıştırıldıklarında ikisi de yarım kalıyordu.
 *
 * Bu yüzden testler yükleme alanına artık GEZİNEREK gider; kullanıcı da
 * öyle gidiyor.
 */
async function openUploadSection(user: {
    click: (element: Element) => Promise<unknown>;
}): Promise<void> {
    const nav = screen.getByRole('navigation', { name: 'Media sections' });
    await user.click(within(nav).getByRole('button', { name: 'Upload' }));
}

/**
 * Yükleme bittikten sonra kütüphaneye DÖNMEK kullanıcının işidir.
 *
 * Sayfa bunu kendiliğinden yapmıyor ve bu bilinçli: yükleme bölümü işin
 * bittiğini kendi içinde söylüyor ("Media upload complete."). Ekranı
 * altından çekmek, o onayı okunmadan yok eder.
 */
async function openLibrarySection(user: {
    click: (element: Element) => Promise<unknown>;
}): Promise<void> {
    const nav = screen.getByRole('navigation', { name: 'Media sections' });
    await user.click(within(nav).getByRole('button', { name: 'Library' }));
}

type TestUser = ReturnType<typeof import('@testing-library/user-event').default.setup>;

/**
 * YÜKLEME ARTIK DÖRT ADIMLI BİR SİHİRBAZ (kanonik kaynak "Yükle"; bileşen
 * ayrı bir pakete ait).
 *
 * Sayfa seviyesindeki testler tek uzun forma değil, kullanıcının yürüdüğü
 * adımlara bakar: dosya → küçültme → yer ve çerçeve → alt metin. Adımların
 * KENDİ davranışı (hangi alan hangi adımda korunur) sihirbazın kendi test
 * dosyalarının işidir; burada sınanan şey, sayfanın sunucuyla konuşması ve
 * sonucun kütüphaneye düşmesidir.
 */
async function walkUploadWizard(
    user: TestUser,
    altText = 'A test image',
): Promise<{
    uploadRegion: HTMLElement;
    altField: HTMLElement;
    submitButton: HTMLElement;
}> {
    const uploadRegion = screen.getByRole('region', { name: /media upload/i });

    await user.upload(
        within(uploadRegion).getByLabelText(/choose a file/i) as HTMLInputElement,
        new File(['binary'], 'photo.png', { type: 'image/png' }),
    );

    // 2. adım (küçültme) → 3. adım (yer ve çerçeve).
    await user.click(await screen.findByRole('button', { name: /^continue$/i }));

    const slotField = within(uploadRegion).getByLabelText(/where will this image be used/i);
    // Slot listesi sunucudan geliyor; seçenek gelene kadar beklenir.
    await waitFor(() => {
        expect(
            within(slotField).getByRole('option', { name: /list.card.detail item/i }),
        ).toBeInTheDocument();
    });
    await user.selectOptions(slotField, 'itemImage');
    await user.click(screen.getByRole('button', { name: /^continue$/i }));

    const altField = within(uploadRegion).getByLabelText(/alt text/i);
    if (altText !== '') {
        await user.type(altField, altText);
    }

    return {
        uploadRegion,
        altField,
        submitButton: within(uploadRegion).getByRole('button', { name: /^upload$/i }),
    };
}

describe('MediaPage — S1-WP01A Media surface (MEDIA_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        setViewport(320, 480);
        fetchSpy = vi.fn(async (url: string) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT) {
                return {
                    ok: true,
                    status: 200,
                    json: async () => ({ assets: [] }),
                } as Response;
            }

            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('renders a real Media heading at a 320x480 viewport', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        expect(screen.getByRole('heading', { name: 'Media' })).toBeInTheDocument();
    });

    it('exposes an accessible Media upload region', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        await openUploadSection(user);

        expect(screen.getByRole('region', { name: /media upload/i })).toBeInTheDocument();
    });

    it('exposes an accessible Media library region', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        expect(screen.getByRole('region', { name: /media library/i })).toBeInTheDocument();
    });

    it('fetches the workspace media list on mount with credentials same-origin', async () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        // İlk çağrı olduğu VARSAYILMAZ: slot politikaları ayrı bir uç
        // noktadan geliyor ve sıra garanti değil.
        const mediaCall = fetchSpy.mock.calls.find(
            (call: unknown[]) => String(call[0]) === MEDIA_ENDPOINT,
        );
        expect(mediaCall).toBeDefined();
        const [url, init] = mediaCall as [string, RequestInit | undefined];
        expect(String(url)).toBe(MEDIA_ENDPOINT);
        expect(init).toMatchObject({ credentials: 'same-origin' });
    });

    it('enables file, alt text and asset slot fields; alt text is required', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());
        await openUploadSection(user);

        const uploadRegion = screen.getByRole('region', { name: /media upload/i });

        // 1. adım: dosya seçimi açıktır.
        const fileField = within(uploadRegion).getByLabelText(/choose a file/i);
        expect(fileField).not.toBeDisabled();

        // Sihirbazı yürüdüğümüzde yer seçimi ve alt metin de açıktır; alt
        // metin erişilebilirlik yükümlülüğü olduğu için ZORUNLUDUR.
        const { altField } = await walkUploadWizard(user);
        expect(altField).not.toBeDisabled();
        expect(altField).toBeRequired();
    });

    it('submits a multipart upload and renders the returned quarantined asset with an honest scan-pending status and no public preview', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT && (!init || init.method === undefined)) {
                return { ok: true, status: 200, json: async () => ({ assets: [] }) } as Response;
            }
            if (String(url) === MEDIA_ENDPOINT && init?.method === 'POST') {
                expect(init.body).toBeInstanceOf(FormData);
                expect(init.credentials).toBe('same-origin');
                return {
                    ok: true,
                    status: 201,
                    json: async () => ({
                        asset: {
                            id: 42,
                            altText: 'A test image',
                            slot: 'itemImage',
                            status: 'quarantined',
                        },
                    }),
                } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });

        render(<MediaPage workspaceId={WORKSPACE_ID} />);
        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());
        await openUploadSection(user);

        const { submitButton } = await walkUploadWizard(user);
        await user.click(submitButton);

        await waitFor(() => {
            const postCall = fetchSpy.mock.calls.find((call) => call[1]?.method === 'POST');
            expect(postCall).toBeDefined();
        });

        await openLibrarySection(user);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        await waitFor(() => {
            // Satır artık kullanıcının yazdığı alt metinle tanınır. Önceden
            // varlığın veritabanı kimliği (`#42`) birincil etiketti: kullanıcının
            // yüklediği fotoğraf, kendi verdiği adla değil bir tablo anahtarıyla
            // listeleniyordu.
            expect(within(libraryRegion).getByText('A test image')).toBeInTheDocument();
            expect(within(libraryRegion).queryByText(/#42/)).toBeNull();
        });
        expect(within(libraryRegion).getByText('A test image')).toBeInTheDocument();
        expect(within(libraryRegion).getByText(/scan pending|quarantined/i)).toBeInTheDocument();

        expect(within(libraryRegion).queryByRole('img')).toBeNull();
        expect(screen.queryByText(/\bReady\b/)).toBeNull();
        expect(screen.queryByText(/\bPublished\b/)).toBeNull();
    });

    it('deletes an own item and removes it from the library on click', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT && (!init || init.method === undefined)) {
                return {
                    ok: true,
                    status: 200,
                    json: async () => ({
                        assets: [
                            {
                                id: 7,
                                altText: 'Owned asset',
                                slot: 'itemImage',
                                status: 'quarantined',
                            },
                        ],
                    }),
                } as Response;
            }
            if (String(url) === `${MEDIA_ENDPOINT}/7` && init?.method === 'DELETE') {
                expect(init.credentials).toBe('same-origin');
                return { ok: true, status: 204, json: async () => ({}) } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });

        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        await waitFor(() =>
            expect(within(libraryRegion).getByText('Owned asset')).toBeInTheDocument(),
        );

        const deleteButton = within(libraryRegion).getByRole('button', { name: /delete/i });
        await user.click(deleteButton);

        await waitFor(() => {
            const deleteCall = fetchSpy.mock.calls.find((call) => call[1]?.method === 'DELETE');
            expect(deleteCall).toBeDefined();
        });

        await waitFor(() => expect(within(libraryRegion).queryByText('Owned asset')).toBeNull());
    });

    it('lists the media lifecycle concepts in order, with no Ready/Published implication', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const bodyText = document.body.textContent ?? '';
        const quarantineIdx = bodyText.indexOf('Quarantine');
        const validationIdx = bodyText.indexOf('Validation');
        const scanIdx = bodyText.indexOf('Security scan');
        const derivativesIdx = bodyText.indexOf('Derivatives');

        expect(quarantineIdx).toBeGreaterThan(-1);
        expect(validationIdx).toBeGreaterThan(quarantineIdx);
        expect(scanIdx).toBeGreaterThan(validationIdx);
        expect(derivativesIdx).toBeGreaterThan(scanIdx);

        expect(screen.queryByText(/\bReady\b/)).toBeNull();
        expect(screen.queryByText(/\bPublished\b/)).toBeNull();
    });

    // Bu test eskiden BUNUN TERSİNİ donduruyordu: her sayfada bir AI
    // yardımcı kartı olsun, alanları devre dışı olsun, "No real AI is
    // connected yet" yazsın.
    //
    // Sağlayıcı bağlı değilken bu yüzey kullanıcıya değer değil,
    // GELİŞTİRİLMEMİŞ ÖZELLİK gösterir. AI bağlandığında giriş noktası
    // görünür hâlde gelir (`docs/50` Faz 1 ve Faz 10).
    it('shows no AI panel while no provider is connected', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        expect(screen.getByText('Media')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /review and approve/i })).toBeNull();
        expect(screen.queryByText(/no real ai is connected/i)).toBeNull();
    });

    it('carries no fixed-pixel or breakpoint class on the batch-owned Media surface (excluding the shared AI panel)', () => {
        const { container } = render(<MediaPage workspaceId={WORKSPACE_ID} />);
        const root = container.querySelector('#section-media') as HTMLElement | null;

        expect(root).not.toBeNull();

        const aiPanel = (root as HTMLElement).querySelector('section[aria-label*="Media"]');
        if (aiPanel) aiPanel.remove();

        const classLists = collectClassLists(root as HTMLElement);
        const offenders = classLists.filter(
            (classList) =>
                FIXED_PIXEL_CLASS_PATTERN.test(classList) ||
                BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
    });

    // Bu test eskiden bunun TERSİNİ donduruyordu: haklar/lisans ve son
    // kullanma alanları görünür ama kalıcı olarak devre dışı olsun.
    //
    // `docs/44`'ün devre dışı kontrol standardı ve `docs/47` Kural 4 bunu
    // reddediyor: bir kontrol yalnız İLERİDE yapılacak diye devre dışı
    // gösterilmez. Kullanıcı onu nasıl etkinleştireceğini bilemez, çünkü
    // etkinleştirmenin bir yolu yoktur — ekranda kalıcı bir soru işareti
    // durur. O alanlar geldiklerinde çalışır hâlde gelirler.
    it('shows no control that exists only to be permanently disabled', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();
        render(<MediaPage workspaceId={WORKSPACE_ID} />);
        await openUploadSection(user);

        const uploadRegion = screen.getByRole('region', { name: /media upload/i });

        expect(within(uploadRegion).queryByLabelText(/rights/i)).toBeNull();
        expect(within(uploadRegion).queryByLabelText(/licen[sc]e/i)).toBeNull();
        expect(within(uploadRegion).queryByLabelText(/expiry/i)).toBeNull();

        const disabled = Array.from(
            uploadRegion.querySelectorAll('input, select, textarea'),
        ).filter((control) => (control as HTMLInputElement).disabled);
        expect(disabled).toHaveLength(0);
    });

    it('keeps the metadata intake form fluid at a 320x480 start with no fixed-width or breakpoint classes', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();
        const { container } = render(<MediaPage workspaceId={WORKSPACE_ID} />);
        await openUploadSection(user);

        const uploadRegion = screen.getByRole('region', { name: /media upload/i });
        expect(window.innerWidth).toBe(320);
        expect(window.innerHeight).toBe(480);

        const classLists = collectClassLists(uploadRegion as HTMLElement);
        const offenders = classLists.filter(
            (classList) =>
                FIXED_PIXEL_CLASS_PATTERN.test(classList) ||
                BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
        expect(container.querySelector('#section-media')).not.toBeNull();
    });

    /**
     * MEDIA_LIBRARY_SLOT_LIST_RED
     *
     * The library region has no per-category inventory today — only a single
     * "unavailable" status line. These assertions require an ordered set of
     * visible slot categories (Corporate site, Restaurant, Menu, Product, QR,
     * Email), each with its own honest status, no fake asset markers, and a
     * fluid subtree from a 320px viewport — so they fail against current
     * production.
     */
    it('lists the media slot categories in order, each with its own honest status and no fake asset', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        const bodyText = libraryRegion.textContent ?? '';

        const categories = ['Corporate site', 'Restaurant', 'Menu', 'Product', 'QR', 'Email'];
        const indices = categories.map((label) => bodyText.indexOf(label));

        indices.forEach((idx) => expect(idx).toBeGreaterThan(-1));
        for (let i = 1; i < indices.length; i += 1) {
            expect(indices[i]).toBeGreaterThan(indices[i - 1]);
        }

        const statuses = within(libraryRegion).getAllByRole('status');
        expect(statuses.length).toBeGreaterThanOrEqual(categories.length);

        categories.forEach((label) => {
            const categoryStatus = statuses.find((node) => node.textContent?.includes(label));
            expect(categoryStatus).toBeDefined();
            expect(categoryStatus?.textContent ?? '').toMatch(
                /not available|unavailable|no assets? yet/i,
            );
        });

        expect(within(libraryRegion).queryByText(/#\d+/)).toBeNull();
    });

    it('keeps the slot-category inventory subtree fluid from a 320 viewport with no fixed-pixel or breakpoint classes', () => {
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        expect(window.innerWidth).toBe(320);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        const inventory = libraryRegion.querySelector(
            '[data-testid="media-slot-category-inventory"]',
        );

        expect(inventory).not.toBeNull();

        const classLists = collectClassLists(inventory as HTMLElement);
        const offenders = classLists.filter(
            (classList) =>
                FIXED_PIXEL_CLASS_PATTERN.test(classList) ||
                BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
    });
});

/**
 * MEDIA_LOAD_STATE_RED
 *
 * The library region has no truthful loading/error/retry lifecycle today —
 * it renders a single static "unavailable" line regardless of fetch
 * outcome, and there is no Retry control. These assertions require the
 * frozen accessible copy and roles from the MEDIA-LOAD-STATE-01 scope, so
 * they fail against current production.
 */
describe('MediaPage — media library load state (MEDIA_LOAD_STATE_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('shows an accessible loading status before the initial GET resolves, with no empty or error copy present', async () => {
        let resolveGet!: (response: Response) => void;
        const pendingGet = new Promise<Response>((resolve) => {
            resolveGet = resolve;
        });
        fetchSpy = vi.fn(async (url: string) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT) {
                return pendingGet;
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);

        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        const loadingNotice = within(libraryRegion).getByText('Loading media library…');
        expect(loadingNotice).toHaveAttribute('role', 'status');
        expect(within(libraryRegion).queryByText('No media assets yet.')).toBeNull();
        expect(within(libraryRegion).queryByRole('alert')).toBeNull();

        resolveGet({ ok: true, status: 200, json: async () => ({ assets: [] }) } as Response);
        await waitFor(() => {
            const emptyNotice = within(libraryRegion).getByText('No media assets yet.');
            expect(emptyNotice).toHaveAttribute('role', 'status');
        });
    });

    it('resolves an HTTP 200 empty response to the accessible successful-empty status', async () => {
        fetchSpy = vi.fn(async (url: string) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT) {
                return { ok: true, status: 200, json: async () => ({ assets: [] }) } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);

        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        await waitFor(() => {
            const emptyNotice = within(libraryRegion).getByText('No media assets yet.');
            expect(emptyNotice).toHaveAttribute('role', 'status');
        });
        expect(within(libraryRegion).queryByRole('alert')).toBeNull();
    });

    it('resolves a thrown GET to an accessible error with a real Retry button', async () => {
        fetchSpy = vi.fn(async (url: string) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT) {
                throw new Error('Network failure');
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);

        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        await waitFor(() => {
            const alert = within(libraryRegion).getByRole('alert');
            expect(alert.textContent).toBe('Media library could not be loaded.');
        });
        expect(within(libraryRegion).queryByText('No media assets yet.')).toBeNull();

        const retryButton = within(libraryRegion).getByRole('button', { name: 'Retry' });
        expect(retryButton.tagName).toBe('BUTTON');
    });

    it('Retry issues another GET and recovers from the error state to the successful-empty state', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        let getCallCount = 0;
        fetchSpy = vi.fn(async (url: string) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT) {
                getCallCount += 1;
                if (getCallCount === 1) {
                    return { ok: false, status: 500, json: async () => ({}) } as Response;
                }
                return { ok: true, status: 200, json: async () => ({ assets: [] }) } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);

        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        await waitFor(() => expect(within(libraryRegion).getByRole('alert')).toBeInTheDocument());

        const retryButton = within(libraryRegion).getByRole('button', { name: 'Retry' });
        await user.click(retryButton);

        await waitFor(() => expect(getCallCount).toBe(2));
        await waitFor(() => {
            const emptyNotice = within(libraryRegion).getByText('No media assets yet.');
            expect(emptyNotice).toHaveAttribute('role', 'status');
        });
        expect(within(libraryRegion).queryByRole('alert')).toBeNull();
    });

    it('a successful upload after an initial GET error renders the new asset and clears the library error', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT && (!init || init.method === undefined)) {
                return { ok: false, status: 500, json: async () => ({}) } as Response;
            }
            if (String(url) === MEDIA_ENDPOINT && init?.method === 'POST') {
                expect(init.body).toBeInstanceOf(FormData);
                expect(init.credentials).toBe('same-origin');
                return {
                    ok: true,
                    status: 201,
                    json: async () => ({
                        asset: {
                            id: 42,
                            altText: 'A test image',
                            slot: 'itemImage',
                            status: 'quarantined',
                        },
                    }),
                } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });
        vi.stubGlobal('fetch', fetchSpy);

        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() =>
            expect(
                within(screen.getByRole('region', { name: /media library/i })).getByRole('alert'),
            ).toBeInTheDocument(),
        );

        await openUploadSection(user);

        const { submitButton } = await walkUploadWizard(user);
        await user.click(submitButton);

        await waitFor(() => {
            const postCall = fetchSpy.mock.calls.find((call) => call[1]?.method === 'POST');
            expect(postCall).toBeDefined();
        });

        await openLibrarySection(user);
        const libraryRegion = screen.getByRole('region', { name: /media library/i });

        await waitFor(() => {
            // Satır artık kullanıcının yazdığı alt metinle tanınır. Önceden
            // varlığın veritabanı kimliği (`#42`) birincil etiketti: kullanıcının
            // yüklediği fotoğraf, kendi verdiği adla değil bir tablo anahtarıyla
            // listeleniyordu.
            expect(within(libraryRegion).getByText('A test image')).toBeInTheDocument();
            expect(within(libraryRegion).queryByText(/#42/)).toBeNull();
        });
        expect(within(libraryRegion).getByText('A test image')).toBeInTheDocument();
        expect(within(libraryRegion).getByText(/scan pending|quarantined/i)).toBeInTheDocument();
        expect(within(libraryRegion).queryByRole('alert')).toBeNull();
    });
});

/**
 * MEDIA_UPLOAD_STATE_RED
 *
 * The upload region has no truthful pending/failure/retry lifecycle today —
 * the Upload button stays enabled during the POST, a non-2xx or rejected
 * POST is silent (no accessible alert, no field preservation), and there is
 * no honest completion announcement. These assertions require the frozen
 * accessible copy from the MEDIA-UPLOAD-STATE-01 scope, so they fail
 * against current production.
 */
describe('MediaPage — media upload state (MEDIA_UPLOAD_STATE_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('a pending POST announces Uploading via role=status and disables Upload', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        let resolvePost!: (response: Response) => void;
        const pendingPost = new Promise<Response>((resolve) => {
            resolvePost = resolve;
        });
        fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT && (!init || init.method === undefined)) {
                return { ok: true, status: 200, json: async () => ({ assets: [] }) } as Response;
            }
            if (String(url) === MEDIA_ENDPOINT && init?.method === 'POST') {
                return pendingPost;
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });
        vi.stubGlobal('fetch', fetchSpy);

        render(<MediaPage workspaceId={WORKSPACE_ID} />);
        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());
        await openUploadSection(user);

        const { uploadRegion, submitButton } = await walkUploadWizard(user);
        await user.click(submitButton);

        await waitFor(() => {
            const uploadingNotice = within(uploadRegion).getByText('Uploading media…');
            expect(uploadingNotice).toHaveAttribute('role', 'status');
        });
        expect(submitButton).toBeDisabled();

        resolvePost({
            ok: true,
            status: 201,
            json: async () => ({
                asset: {
                    id: 99,
                    altText: 'A test image',
                    slot: 'itemImage',
                    status: 'quarantined',
                },
            }),
        } as Response);
    });

    it('a non-2xx POST shows one accessible upload alert, adds no asset, preserves file/alt/slot, and re-enables Upload', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT && (!init || init.method === undefined)) {
                return { ok: true, status: 200, json: async () => ({ assets: [] }) } as Response;
            }
            if (String(url) === MEDIA_ENDPOINT && init?.method === 'POST') {
                return { ok: false, status: 422, json: async () => ({}) } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });
        vi.stubGlobal('fetch', fetchSpy);

        render(<MediaPage workspaceId={WORKSPACE_ID} />);
        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());
        await openUploadSection(user);

        const { uploadRegion, altField, submitButton } = await walkUploadWizard(user);
        await user.click(submitButton);

        await waitFor(() => {
            const alerts = within(uploadRegion).getAllByRole('alert');
            expect(alerts).toHaveLength(1);
            expect(alerts[0].textContent).toBe('Media upload failed. Your selection was kept.');
        });

        /*
            Kullanıcının YAZDIĞI kaybolmaz: sunucu reddettiğinde ekran son
            adımda kalır ve alt metin yerinde durur. Dosya ve yer seçimi
            sihirbazın önceki adımlarında yaşar; onların korunduğunu
            sihirbazın kendi test dosyaları kanıtlar.
        */
        expect((altField as HTMLInputElement).value).toBe('A test image');
        expect(submitButton).not.toBeDisabled();

        // Başarısız yükleme kütüphaneye SAHTE bir satır eklemez.
        await openLibrarySection(user);
        expect(
            within(screen.getByRole('region', { name: /media library/i })).getByText(
                'No media assets yet.',
            ),
        ).toBeInTheDocument();
    });

    it('a rejected POST shows the same alert and preserves the same fields without an unhandled rejection', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT && (!init || init.method === undefined)) {
                return { ok: true, status: 200, json: async () => ({ assets: [] }) } as Response;
            }
            if (String(url) === MEDIA_ENDPOINT && init?.method === 'POST') {
                throw new Error('Network failure');
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });
        vi.stubGlobal('fetch', fetchSpy);

        render(<MediaPage workspaceId={WORKSPACE_ID} />);
        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());
        await openUploadSection(user);

        const { uploadRegion, altField, submitButton } = await walkUploadWizard(user);
        await user.click(submitButton);

        await waitFor(() => {
            const alerts = within(uploadRegion).getAllByRole('alert');
            expect(alerts).toHaveLength(1);
            expect(alerts[0].textContent).toBe('Media upload failed. Your selection was kept.');
        });

        expect((altField as HTMLInputElement).value).toBe('A test image');
        expect(submitButton).not.toBeDisabled();
    });

    it('retry after one failed POST then succeeds: alert clears, completion is announced, file/alt/slot clear, and exactly the returned quarantined asset appears', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        let postCallCount = 0;
        fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT && (!init || init.method === undefined)) {
                return { ok: true, status: 200, json: async () => ({ assets: [] }) } as Response;
            }
            if (String(url) === MEDIA_ENDPOINT && init?.method === 'POST') {
                postCallCount += 1;
                if (postCallCount === 1) {
                    return { ok: false, status: 500, json: async () => ({}) } as Response;
                }
                return {
                    ok: true,
                    status: 201,
                    json: async () => ({
                        asset: {
                            id: 77,
                            altText: 'A test image',
                            slot: 'itemImage',
                            status: 'quarantined',
                        },
                    }),
                } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });
        vi.stubGlobal('fetch', fetchSpy);

        render(<MediaPage workspaceId={WORKSPACE_ID} />);
        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());
        await openUploadSection(user);

        const { uploadRegion, submitButton } = await walkUploadWizard(user);
        await user.click(submitButton);

        await waitFor(() => expect(within(uploadRegion).getByRole('alert')).toBeInTheDocument());

        await user.click(submitButton);

        await waitFor(() => expect(postCallCount).toBe(2));

        await waitFor(() => {
            const completionNotice = within(uploadRegion).getByText('Media upload complete.');
            expect(completionNotice).toHaveAttribute('role', 'status');
        });
        expect(within(uploadRegion).queryByRole('alert')).toBeNull();

        /*
            Başarılı yüklemeden sonra sihirbaz BAŞA döner: ekranda yeniden
            dosya seçimi durur ve önceki fotoğrafın alt metni yeni yüklemeye
            sızmaz. Eskiden bu, üç alanın tek tek boşaldığı iddiasıydı;
            adımlara bölününce aynı şeyin görünür kanıtı, birinci adımın geri
            gelmesidir.
        */
        await waitFor(() =>
            expect(within(uploadRegion).getByLabelText(/choose a file/i)).toBeInTheDocument(),
        );
        expect(within(uploadRegion).queryByLabelText(/alt text/i)).toBeNull();

        await openLibrarySection(user);
        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        await waitFor(() => {
            expect(within(libraryRegion).getByText('A test image')).toBeInTheDocument();
            expect(within(libraryRegion).queryByText(/#77/)).toBeNull();
        });
        // "Tam olarak bir varlık göründü" iddiası, önceden ekrandaki `#N`
        // etiketlerini sayarak kuruluyordu. O etiketler kaldırıldı; sayım artık
        // liste öğeleri üzerinden yapılıyor — ki asıl ölçülmek istenen de oydu.
        expect(
            within(within(libraryRegion).getByRole('list', { name: /assets/i })).getAllByRole(
                'listitem',
            ),
        ).toHaveLength(1);
        // Ve hiçbir veritabanı kimliği ekrana çıkmıyor.
        expect(within(libraryRegion).queryAllByText(/#\d+/)).toHaveLength(0);
    });
});

/**
 * MEDIA_DELETE_STATE_RED
 *
 * The delete control has no truthful pending/failure/retry lifecycle today —
 * the per-asset Delete button stays enabled during the DELETE request (so a
 * second click fires a duplicate request), a non-2xx or rejected DELETE is
 * silent (no accessible alert, the asset stays but nothing explains why),
 * and there is no honest completion announcement on success. These
 * assertions require a truthful per-asset delete lifecycle, so they fail
 * against current production. No production, i18n, Storybook, backend or
 * Git edits are made from this file.
 */
describe('MediaPage — media delete state (MEDIA_DELETE_STATE_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    function stubInitialGet(handleOther: (url: string, init?: RequestInit) => Promise<Response>) {
        fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === MEDIA_ENDPOINT && (!init || init.method === undefined)) {
                return {
                    ok: true,
                    status: 200,
                    json: async () => ({
                        assets: [
                            {
                                id: 7,
                                altText: 'Owned asset',
                                slot: 'itemImage',
                                status: 'quarantined',
                            },
                            { id: 8, altText: 'Other asset', slot: 'menu', status: 'quarantined' },
                        ],
                    }),
                } as Response;
            }
            return handleOther(url, init);
        });
        vi.stubGlobal('fetch', fetchSpy);
    }

    it('a pending DELETE disables only the targeted asset delete control and prevents a duplicate request', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        let resolveDelete!: (response: Response) => void;
        const pendingDelete = new Promise<Response>((resolve) => {
            resolveDelete = resolve;
        });

        stubInitialGet(async (url, init) => {
            if (String(url) === `${MEDIA_ENDPOINT}/7` && init?.method === 'DELETE') {
                return pendingDelete;
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });

        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        await waitFor(() =>
            expect(within(libraryRegion).getByText('Owned asset')).toBeInTheDocument(),
        );

        const items = within(libraryRegion).getAllByRole('listitem');
        const targetItem = items.find((item) =>
            item.textContent?.includes('Owned asset'),
        ) as HTMLElement;
        const otherItem = items.find((item) =>
            item.textContent?.includes('Other asset'),
        ) as HTMLElement;

        const targetDeleteButton = within(targetItem).getByRole('button', { name: /delete/i });
        const otherDeleteButton = within(otherItem).getByRole('button', { name: /delete/i });

        await user.click(targetDeleteButton);

        await waitFor(() => expect(targetDeleteButton).toBeDisabled());
        expect(otherDeleteButton).not.toBeDisabled();

        await user.click(targetDeleteButton);

        const deleteCalls = fetchSpy.mock.calls.filter((call) => call[1]?.method === 'DELETE');
        expect(deleteCalls).toHaveLength(1);

        resolveDelete({ ok: true, status: 204, json: async () => ({}) } as Response);
        await waitFor(() => expect(within(libraryRegion).queryByText('Owned asset')).toBeNull());
    });

    it('a non-2xx DELETE keeps the asset, shows an accessible error, and permits retry', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        stubInitialGet(async (url, init) => {
            if (String(url) === `${MEDIA_ENDPOINT}/7` && init?.method === 'DELETE') {
                return { ok: false, status: 500, json: async () => ({}) } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });

        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        await waitFor(() =>
            expect(within(libraryRegion).getByText('Owned asset')).toBeInTheDocument(),
        );

        const items = within(libraryRegion).getAllByRole('listitem');
        const targetItem = items.find((item) =>
            item.textContent?.includes('Owned asset'),
        ) as HTMLElement;
        const targetDeleteButton = within(targetItem).getByRole('button', { name: /delete/i });

        await user.click(targetDeleteButton);

        await waitFor(() => {
            const alert = within(targetItem).getByRole('alert');
            expect(alert.textContent).toBe('Media asset deletion failed. Your item was kept.');
        });

        expect(within(libraryRegion).getByText('Owned asset')).toBeInTheDocument();
        expect(targetDeleteButton).not.toBeDisabled();

        await user.click(targetDeleteButton);
        const deleteCalls = fetchSpy.mock.calls.filter((call) => call[1]?.method === 'DELETE');
        expect(deleteCalls).toHaveLength(2);
    });

    it('a thrown DELETE request keeps the asset, shows the same accessible error, and permits retry', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        stubInitialGet(async (url, init) => {
            if (String(url) === `${MEDIA_ENDPOINT}/7` && init?.method === 'DELETE') {
                throw new Error('Network failure');
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });

        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        await waitFor(() =>
            expect(within(libraryRegion).getByText('Owned asset')).toBeInTheDocument(),
        );

        const items = within(libraryRegion).getAllByRole('listitem');
        const targetItem = items.find((item) =>
            item.textContent?.includes('Owned asset'),
        ) as HTMLElement;
        const targetDeleteButton = within(targetItem).getByRole('button', { name: /delete/i });

        await user.click(targetDeleteButton);

        await waitFor(() => {
            const alert = within(targetItem).getByRole('alert');
            expect(alert.textContent).toBe('Media asset deletion failed. Your item was kept.');
        });

        expect(within(libraryRegion).getByText('Owned asset')).toBeInTheDocument();
        expect(targetDeleteButton).not.toBeDisabled();

        await user.click(targetDeleteButton);
        const deleteCalls = fetchSpy.mock.calls.filter((call) => call[1]?.method === 'DELETE');
        expect(deleteCalls).toHaveLength(2);
    });

    it('a successful DELETE removes the asset and announces concise accessible completion', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        stubInitialGet(async (url, init) => {
            if (String(url) === `${MEDIA_ENDPOINT}/7` && init?.method === 'DELETE') {
                return { ok: true, status: 204, json: async () => ({}) } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });

        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        await waitFor(() =>
            expect(within(libraryRegion).getByText('Owned asset')).toBeInTheDocument(),
        );

        const items = within(libraryRegion).getAllByRole('listitem');
        const targetItem = items.find((item) =>
            item.textContent?.includes('Owned asset'),
        ) as HTMLElement;
        const targetDeleteButton = within(targetItem).getByRole('button', { name: /delete/i });

        await user.click(targetDeleteButton);

        await waitFor(() => expect(within(libraryRegion).queryByText('Owned asset')).toBeNull());

        const completionNotice = within(libraryRegion).getByText(
            'Moved to trash. You can restore it from the Trash tab.',
        );
        expect(completionNotice).toHaveAttribute('role', 'status');
        expect(within(libraryRegion).queryByRole('alert')).toBeNull();
        expect(within(libraryRegion).getByText('Other asset')).toBeInTheDocument();
    });
});

/**
 * MEDIA_FOLDERS_HONESTY — klasör ucu YOKSA klasör de yok.
 *
 * Klasör uçları bu depoya henüz inmedi (`docs/108` §2: "Kütüphane … klasör
 * yok"). Kabuk yine de klasör soruyor, çünkü uç indiği gün ekranın
 * değişmesi gerekmesin diye — ama uç 404 dönerse ekranda ne kırmızı bir
 * satır ne de uydurma bir "Genel" klasörü belirir.
 *
 * Sebep sahibin yolculuğunda görünür: elli fotoğrafı olan bir restoran
 * sahibi, tıkladığında hiçbir şey yapmayan bir klasör görürse ürünün geri
 * kalanına da güvenmez. Var olmayan yetenek, kırık bir yetenekten daha az
 * zarar verir.
 */
describe('MediaPage — klasörler (MEDIA_FOLDERS_HONESTY)', () => {
    const ASSET_ROWS = [
        { id: 11, altText: 'Adana kebap', slot: 'itemImage', status: 'ready', folderId: 3 },
        { id: 12, altText: 'Lahmacun', slot: 'itemImage', status: 'ready', folderId: 4 },
    ];

    function stubFetch(foldersResponse: () => Response) {
        const fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const policies = slotPoliciesResponse(url);
            if (policies) return policies;

            if (String(url) === `${MEDIA_ENDPOINT}/folders`) {
                return foldersResponse();
            }
            if (String(url) === MEDIA_ENDPOINT && (!init || init.method === undefined)) {
                return {
                    ok: true,
                    status: 200,
                    json: async () => ({ assets: ASSET_ROWS }),
                } as Response;
            }
            if (String(url) === `${MEDIA_ENDPOINT}/quota`) {
                return { ok: false, status: 404, json: async () => ({}) } as Response;
            }
            if (String(url).startsWith(`${MEDIA_ENDPOINT}/audit`)) {
                return { ok: false, status: 404, json: async () => ({}) } as Response;
            }
            throw new Error(`Unhandled fetch: ${String(url)} ${init?.method ?? 'GET'}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
        return fetchSpy;
    }

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('uç 404 dönerse klasör listesi de hap şeridi de çizilmez, hata da gösterilmez', async () => {
        stubFetch(() => ({ ok: false, status: 404, json: async () => ({}) }) as Response);

        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(screen.getByText('Adana kebap')).toBeInTheDocument());

        expect(screen.queryByRole('list', { name: 'Folders' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'All files' })).toBeNull();
        expect(screen.queryByRole('alert')).toBeNull();
    });

    it('uç klasör döndürünce şerit ve haplar gerçek adlarla çizilir', async () => {
        stubFetch(
            () =>
                ({
                    ok: true,
                    status: 200,
                    json: async () => ({
                        folders: [
                            { id: 3, name: 'Kampanyalar', assetCount: 1 },
                            { id: 4, name: 'Menü', assetCount: 1 },
                        ],
                    }),
                }) as Response,
        );

        const user = (await import('@testing-library/user-event')).default.setup();
        render(<MediaPage workspaceId={WORKSPACE_ID} />);

        const rail = await screen.findByRole('list', { name: 'Folders' });
        expect(within(rail).getByRole('button', { name: /Kampanyalar/ })).toBeInTheDocument();

        // Klasör seçimi listeyi daraltır: seçim şeritten yapılır, sonuç
        // kütüphanede görünür.
        await user.click(within(rail).getByRole('button', { name: /Kampanyalar/ }));

        const libraryRegion = screen.getByRole('region', { name: /media library/i });
        expect(within(libraryRegion).getByText('Adana kebap')).toBeInTheDocument();
        expect(within(libraryRegion).queryByText('Lahmacun')).toBeNull();
    });
});
