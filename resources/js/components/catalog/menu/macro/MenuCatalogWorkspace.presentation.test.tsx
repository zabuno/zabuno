import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * SUNUM: fotoğraf ve açıklama — `docs/78` (FF-20, P0-04'ün panel yarısı).
 *
 * Uçlar `docs/77` ile açıldı ama sahip onları PANELDEN kullanamıyordu.
 * Bir ürünün nasıl görüneceğine karar vermek sahibin en sık yapacağı
 * işlerden biri; API üzerinden yürünen bir yol, olmayan bir yoldur.
 */
const WORKSPACE_ID = 7;
const LOCATION_ID = 3;
const MENU_ID = 42;
const MEDIA_HREF = '/app/zeytin-restoranlari/media';

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

function tree(overrides: Record<string, unknown> = {}) {
    return {
        id: MENU_ID,
        workspaceId: WORKSPACE_ID,
        locationId: LOCATION_ID,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 5,
                menuId: MENU_ID,
                name: 'Kebaplar',
                position: 1,
                menuItems: [
                    {
                        id: 11,
                        categoryId: 5,
                        productId: 9,
                        productName: 'Adana Kebap',
                        priceMinorAmount: 38000,
                        currencyCode: 'TRY',
                        position: 1,
                        isVisible: true,
                        allergens: [],
                        description: null,
                        imageMediaAssetId: null,
                        ...overrides,
                    },
                ],
            },
        ],
    };
}

type Call = { url: string; method: string; body: unknown };

/**
 * YUVA POLİTİKALARI — `GET /api/media/slot-policies`, kaynağı
 * `config/media-slots.php`. Ekran ölçüyü BURADAN okur; sahte yanıt gerçek
 * yapılandırmadaki `itemImage` satırını taşır (1000×1000, 1:1).
 */
function slotPolicies() {
    return {
        slots: [
            { key: 'itemImage', minWidth: 1000, minHeight: 1000, aspect: '1:1' },
            { key: 'menuImportSource', minWidth: 600, minHeight: 600, aspect: null },
        ],
    };
}

async function renderWorkspace(
    options: {
        media?: unknown[];
        imageStatus?: number;
        draftResponse?: Response;
        /** `undefined` = politika okunabiliyor; `null` = uç cevap vermedi. */
        policies?: unknown | null;
        can?: (permission: string) => boolean;
        mediaHref?: string;
        onNavigateToSection?: (section: string) => void;
    } = {},
) {
    const calls: Call[] = [];

    const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();
        calls.push({
            url: String(url),
            method,
            body: init?.body ? JSON.parse(String(init.body)) : null,
        });

        if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
        if (String(url) === '/api/media/slot-policies' && method === 'GET') {
            return options.policies === null
                ? jsonResponse(503, { message: 'unavailable' })
                : jsonResponse(200, options.policies ?? slotPolicies());
        }
        if (String(url).endsWith('/brand') && method === 'GET') {
            return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
        }
        if (String(url).endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
            return jsonResponse(200, tree());
        }
        if (String(url).endsWith('/media') && method === 'GET') {
            return jsonResponse(200, {
                data: options.media ?? [
                    { id: 91, altText: 'Kömürde Adana kebap', slot: 'itemImage', status: 'ready' },
                    // İşlenmesi bitmemiş ve başka slota ait olanlar
                    // SEÇİLEMEZ: hazır olmayan bir görseli seçtirmek,
                    // menüye kırık bir kutu koymaya davettir.
                    { id: 92, altText: 'Hâlâ işleniyor', slot: 'itemImage', status: 'processing' },
                    { id: 93, altText: 'Marka logosu', slot: 'logo', status: 'ready' },
                ],
            });
        }
        if (String(url).endsWith('/image') && method === 'PUT') {
            return jsonResponse(options.imageStatus ?? 200, { ok: true });
        }
        if (String(url).endsWith('/description-drafts') && method === 'POST') {
            return (
                options.draftResponse ??
                jsonResponse(201, {
                    id: 999,
                    description: '',
                    confidence: 0,
                    uncertainFieldCount: 0,
                    usedFallback: false,
                })
            );
        }
        if (String(url).includes('/description-drafts/') && String(url).endsWith('/apply')) {
            return jsonResponse(200, { applied: true, alreadyApplied: false, reason: null });
        }

        return jsonResponse(200, { ok: true });
    });

    vi.stubGlobal('fetch', fetchMock);

    const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
        MenuCatalogWorkspace: React.ComponentType<{
            workspaceId: number;
            locationId: number;
            can?: (permission: string) => boolean;
            mediaHref?: string;
            onNavigateToSection?: (section: string) => void;
        }>;
    };

    render(
        <MenuCatalogWorkspace
            workspaceId={WORKSPACE_ID}
            locationId={LOCATION_ID}
            can={options.can}
            mediaHref={options.mediaHref ?? MEDIA_HREF}
            onNavigateToSection={options.onNavigateToSection}
        />,
    );
    await screen.findByRole('heading', { name: 'Kebaplar' });

    return { calls, user: userEvent.setup() };
}

async function openEditor(user: ReturnType<typeof userEvent.setup>) {
    /*
        GÜNCELLENDİ (FF-102): "fotoğraf ve metin" satırda kalıcı bir düğme
        değil, taşma menüsünde ADIYLA duran bir madde. Satır dokuz kontrol
        taşıyordu ve hepsi aynı ağırlıktaydı.
    */
    await user.click(screen.getByRole('button', { name: 'More actions for Adana Kebap' }));
    await user.click(await screen.findByRole('menuitem', { name: 'Photo & text' }));

    return screen.findByLabelText('Description');
}

describe('sunum düzenleyicisi (docs/78)', () => {
    it('açıklama ve fotoğraf tek düzenleyicide kaydedilir', async () => {
        const { calls, user } = await renderWorkspace();

        const description = await openEditor(user);
        await user.type(description, 'Kömür ateşinde, acılı.');

        // Yalnız HAZIR ve bu slota ait görsel seçilebilir.
        const select = screen.getByLabelText('Photo');
        expect(within(select).queryByText('Hâlâ işleniyor')).toBeNull();
        expect(within(select).queryByText('Marka logosu')).toBeNull();

        await user.selectOptions(select, '91');
        await user.click(screen.getByRole('button', { name: 'Save presentation' }));

        await waitFor(() => {
            expect(calls.some((call) => call.method === 'PUT' && call.url.endsWith('/image'))).toBe(
                true,
            );
        });

        const details = calls.find(
            (call) => call.method === 'PUT' && call.url.endsWith('/menu-items/11'),
        );
        expect(details?.body).toEqual({
            productName: 'Adana Kebap',
            description: 'Kömür ateşinde, acılı.',
        });

        const image = calls.find((call) => call.method === 'PUT' && call.url.endsWith('/image'));
        expect(image?.url).toBe(`/api/workspaces/${WORKSPACE_ID}/menu-items/11/image`);
        expect(image?.body).toEqual({ mediaAssetId: 91 });

        vi.unstubAllGlobals();
    });

    it('fotoğrafı kaldırmak boş seçimle olur', async () => {
        const { calls, user } = await renderWorkspace();

        await openEditor(user);
        await user.click(screen.getByRole('button', { name: 'Save presentation' }));

        await waitFor(() => {
            expect(calls.some((call) => call.method === 'PUT' && call.url.endsWith('/image'))).toBe(
                true,
            );
        });

        const image = calls.find((call) => call.method === 'PUT' && call.url.endsWith('/image'));
        expect(image?.body).toEqual({ mediaAssetId: null });

        vi.unstubAllGlobals();
    });

    it('açıklama kaydedilip fotoğraf kaydedilemezse ikisi tek cümleye sıkıştırılmaz', async () => {
        const { user } = await renderWorkspace({ imageStatus: 422 });

        const description = await openEditor(user);
        await user.type(description, 'Kömür ateşinde.');
        await user.click(screen.getByRole('button', { name: 'Save presentation' }));

        expect(
            await screen.findByText('The description was saved, but the photo was not attached.'),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});

/**
 * ÇIKMAZ SOKAK — sahibin 2026-09-08'de bulduğu kusur (FF-224).
 *
 * Sahip "Photo" başlığı altında tek seçenek gördü ("No photo") ve sordu:
 * *"Ürünlere, menülere resim yükleme alanı yok?"* Yükleme VARDI — Medya
 * ekranında. Panel ise hiçbir şey söylemiyordu: ne "önce yükleyin", ne
 * hangi yuva, ne oraya giden bir yol.
 */
describe('seçilecek fotoğraf yokken panel konuşur (FF-224)', () => {
    it('sebebini, yuvasını, ölçüsünü ve Medya ekranına giden gerçek bağlantıyı verir', async () => {
        const { user } = await renderWorkspace({ media: [] });

        await openEditor(user);

        // 1. NE OLDU ve NEREYE — yuva adı cümlenin içinde.
        expect(
            await screen.findByText(
                'No processed photo is available yet. Upload one on the Media page ' +
                    '(slot: List/card/detail item) first.',
            ),
        ).toBeInTheDocument();

        // 2. O YUVA NE İSTİYOR — rakamlar `config/media-slots.php`'den.
        expect(
            await screen.findByText('That slot needs at least 1000×1000 px and a 1:1 frame.'),
        ).toBeInTheDocument();

        // 3. GERÇEK BİR ADRES — düğme değil, `<a href>`; kopyalanabilir,
        //    yeni sekmede açılabilir, yer imine eklenebilir.
        const link = screen.getByRole('link', { name: 'Open the Media page' });
        expect(link).toHaveAttribute('href', MEDIA_HREF);

        vi.unstubAllGlobals();
    });

    it('süslenmemiş sol tıklama tam sayfa yenilemeden Medya bölümüne geçer', async () => {
        const onNavigateToSection = vi.fn();
        const { user } = await renderWorkspace({ media: [], onNavigateToSection });

        await openEditor(user);
        await user.click(await screen.findByRole('link', { name: 'Open the Media page' }));

        expect(onNavigateToSection).toHaveBeenCalledWith('media');

        vi.unstubAllGlobals();
    });

    it('yuva politikası okunamazsa ölçü UYDURULMAZ; yol tarifi yine durur', async () => {
        const { user } = await renderWorkspace({ media: [], policies: null });

        await openEditor(user);

        expect(
            await screen.findByText(
                'No processed photo is available yet. Upload one on the Media page ' +
                    '(slot: List/card/detail item) first.',
            ),
        ).toBeInTheDocument();
        expect(screen.queryByText(/That slot needs at least/)).toBeNull();
        expect(screen.getByRole('link', { name: 'Open the Media page' })).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    /*
        Medya kütüphanesi `media.manage` ister (`MediaPage.section`). Mutfak
        rolüne "yükleyin" deyip bir bağlantı vermek, kapattığımız çıkmaz
        sokağın yerine yenisini koymak olurdu.
    */
    it('medyayı yönetemeyen rol bağlantı görmez, kimin yükleyeceğini okur', async () => {
        const { user } = await renderWorkspace({
            media: [],
            can: (permission) => permission !== 'media.manage',
        });

        await openEditor(user);

        expect(
            await screen.findByText(
                'No processed photo is available yet. Someone with media access has to ' +
                    'upload one on the Media page (slot: List/card/detail item).',
            ),
        ).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Open the Media page' })).toBeNull();

        vi.unstubAllGlobals();
    });

    /*
        DOLU HÂL BOZULMADI: hazır bir görsel varken yol tarifi ÇİZİLMEZ —
        yoksa panel, sahibin zaten yaptığı işi tarif ederdi.
    */
    it('seçilebilecek fotoğraf varken yol tarifi çizilmez', async () => {
        const { user } = await renderWorkspace();

        await openEditor(user);

        expect(screen.queryByRole('link', { name: 'Open the Media page' })).toBeNull();
        expect(screen.queryByText(/No processed photo is available yet/)).toBeNull();

        vi.unstubAllGlobals();
    });

    /*
        AYNI ÇIKMAZ SOKAK, İKİNCİ EKRAN: fotoğraftan içe aktarma. Cümle
        yuvayı zaten söylüyordu; eksik olan bağlantıydı — ve söylediği yuva
        adı ("Import source") Medya ekranının açılır listesinde HİÇ
        yazmıyordu (`SlotNameIsReadableTest`).
    */
    it('fotoğraftan içe aktarma boş hâli de aynı yolu gösterir', async () => {
        const { user } = await renderWorkspace({ media: [] });

        await user.click(screen.getByRole('button', { name: 'Import from a photo (AI)' }));

        expect(
            await screen.findByText(
                'No processed photo is available yet. Upload one on the Media page ' +
                    '(slot: Import source) first.',
            ),
        ).toBeInTheDocument();
        expect(await screen.findByText('That slot needs at least 600×600 px.')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Open the Media page' })).toHaveAttribute(
            'href',
            MEDIA_HREF,
        );

        vi.unstubAllGlobals();
    });
});

/**
 * AI ÖNERİSİ — `docs/97` Yolculuk B, R4-R5. Backend zaten vardı (FF-46);
 * bu ekran o backend'i ilk kez bir düğmeye bağlıyor.
 */
describe('AI açıklama önerisi (docs/97 Yolculuk B)', () => {
    it('öneri kutuya yazılır ve Kaydet, düzenlenmiş metinle onay uç noktasına gider', async () => {
        const { calls, user } = await renderWorkspace({
            draftResponse: jsonResponse(201, {
                id: 501,
                description: 'Kömürde, acılı.',
                confidence: 0.92,
                uncertainFieldCount: 0,
                usedFallback: false,
            }),
        });

        const description = await openEditor(user);
        await user.click(screen.getByRole('button', { name: 'Suggest with AI' }));

        await waitFor(() => expect(description).toHaveValue('Kömürde, acılı.'));
        expect(
            screen.getByText('AI suggestion — feel free to edit it before saving.'),
        ).toBeInTheDocument();

        // Kullanıcı öneriyi düzenliyor — kutu gerçekten düzenlenebilir olmalı.
        await user.clear(description);
        await user.type(description, 'Kömürde, hafif acılı — elle düzeltildi.');
        await user.click(screen.getByRole('button', { name: 'Save presentation' }));

        await waitFor(() => {
            expect(calls.some((call) => call.url.endsWith('/description-drafts/501/apply'))).toBe(
                true,
            );
        });

        const apply = calls.find((call) => call.url.endsWith('/description-drafts/501/apply'));
        expect(apply?.body).toEqual({ description: 'Kömürde, hafif acılı — elle düzeltildi.' });

        // Düz PUT yoluna GİTMEMELİ — taslak varken onay yolu tektir.
        expect(
            calls.some((call) => call.method === 'PUT' && call.url.endsWith('/menu-items/11')),
        ).toBe(false);

        vi.unstubAllGlobals();
    });

    it('AI kapalıyken (503) hata değil, kısa bir mesaj gösterir; elle yazma bozulmaz', async () => {
        const { calls, user } = await renderWorkspace({
            draftResponse: jsonResponse(503, { message: 'off', reason: 'kill_switch' }),
        });

        const description = await openEditor(user);
        await user.click(screen.getByRole('button', { name: 'Suggest with AI' }));

        expect(
            await screen.findByText('AI suggestions are not available right now.'),
        ).toBeInTheDocument();

        // Elle yazma hâlâ çalışır — düz PUT yoluna gider.
        await user.type(description, 'Elle yazıldı.');
        await user.click(screen.getByRole('button', { name: 'Save presentation' }));

        await waitFor(() => {
            expect(
                calls.some((call) => call.method === 'PUT' && call.url.endsWith('/menu-items/11')),
            ).toBe(true);
        });

        vi.unstubAllGlobals();
    });

    it('belirsiz alan taşıyan öneri açıkça uyarır', async () => {
        const { user } = await renderWorkspace({
            draftResponse: jsonResponse(201, {
                id: 502,
                description: 'Belirsiz bir öneri.',
                confidence: 0.3,
                uncertainFieldCount: 1,
                usedFallback: false,
            }),
        });

        await openEditor(user);
        await user.click(screen.getByRole('button', { name: 'Suggest with AI' }));

        expect(
            await screen.findByText(
                'The AI was not confident about this one. Read it carefully before saving.',
            ),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('yedek sağlayıcıdan gelen öneri sessizce geçmez, ayrı etiketlenir', async () => {
        const { user } = await renderWorkspace({
            draftResponse: jsonResponse(201, {
                id: 503,
                description: 'Yedekten gelen öneri.',
                confidence: 0.8,
                uncertainFieldCount: 0,
                usedFallback: true,
            }),
        });

        await openEditor(user);
        await user.click(screen.getByRole('button', { name: 'Suggest with AI' }));

        expect(
            await screen.findByText(
                'AI suggestion (from a backup provider) — feel free to edit it before saving.',
            ),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});
