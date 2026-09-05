import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import { MediaUploadRegion } from './MediaUploadRegion';

/**
 * MEDIA-SCANNER-HONEST-AT-UPLOAD-01 — sahip, dosyayı YÜKLEDİĞİ yerde
 * ne olduğunu öğrenir.
 *
 * Bu ekran daha önce her başarılı gönderimde tek bir cümle yazıyordu:
 * "Media upload complete." Sunucuda virüs tarayıcı kurulu değilken bu
 * cümle DOĞRU AMA EKSİKTİ: dosya gerçekten ulaşmıştı, ama karantinada
 * bekliyordu ve menüde kullanılamıyordu. Sahip ekrandan ayrılıyor, bir
 * hafta sonra fotoğrafın menüde olmadığını görüyor ve ürünün bozuk
 * olduğunu düşünüyordu.
 *
 * Altındaki sabit cümle bunu daha da kötü yapıyordu: "Her görsel taranır"
 * diyordu — oysa o ortamda hiçbir şey taranmıyordu. İki cümle aynı anda
 * ekranda durursa biri mutlaka yalandır.
 *
 * MEDIA-SCANNER-PROMISE-HONEST-01 (FF-151) o cümleyi bir adım geriye
 * taşır: vaat, HİÇ YÜKLEME YAPILMADAN ÖNCE de okunuyor. Sahip ilk
 * fotoğrafını seçmeden önce "her görsel taranır" yazısını görüyor ve
 * kararını ona göre veriyor; tarayıcı bağlı değilken o cümle daha
 * okunduğu anda yanlıştır.
 *
 * NOT: burada test edilen tek şey GÖRÜNÜRLÜKTÜR. Taranmamış dosyanın
 * kuralı değişmiyor; yalnız sebebi okunabiliyor.
 */
async function fillAndSubmit(): Promise<void> {
    const user = (await import('@testing-library/user-event')).default.setup();
    const region = screen.getByRole('region', { name: /media upload/i });

    await user.upload(
        within(region).getByLabelText(/choose a file/i) as HTMLInputElement,
        new File(['binary'], 'kebap.png', { type: 'image/png' }),
    );

    await user.click(await screen.findByRole('button', { name: /^continue$/i }));

    const slotField = within(region).getByLabelText(/where will this image be used/i);

    await waitFor(() => {
        expect(within(slotField).getAllByRole('option').length).toBeGreaterThan(1);
    });

    await user.selectOptions(slotField, 'itemImage');
    await user.click(screen.getByRole('button', { name: /^continue$/i }));

    await user.type(within(region).getByLabelText(/alt text/i), 'Adana kebap');
    await user.click(within(region).getByRole('button', { name: /^upload$/i }));
}

/** Sunucunun gerçekten kaydettiği cümle (`ScanQuarantinedMediaAsset`). */
const HELD_REASON = 'Virüs taraması bu ortamda çalışmıyor; dosya taranmadan yayına alınmaz.';

const SLOT_POLICIES = {
    slots: [
        {
            key: 'itemImage',
            minWidth: 1,
            minHeight: 1,
            aspect: null,
            formats: ['png'],
            altRequired: true,
        },
    ],
    limits: { maxBytes: 31457280, maxMegapixels: 40 },
};

/**
 * Ekranın iki ucu var ve İKİSİ DE taklit edilmeli: slot politikaları ve
 * medya ayarları. `settings` bir söz değil bir SEÇENEKTİR — `null`
 * verildiğinde uç hiç cevap vermez ve ekranın "durumu bilmiyorum" hâli
 * ölçülebilir.
 */
function stubFetch(settings: { virusScan: string } | null | 'never'): void {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string) => {
            const target = String(url);

            if (target === '/api/media/slot-policies') {
                return {
                    ok: true,
                    status: 200,
                    json: async () => SLOT_POLICIES,
                } as unknown as Response;
            }

            if (target.endsWith('/media/settings')) {
                // Hiç bitmeyen istek: durum BİLİNMİYOR ve öyle kalıyor.
                if (settings === 'never') {
                    return await new Promise<Response>(() => {});
                }

                // Uç düştü. Kütüphane çalışmaya devam eder, vaat susar.
                if (settings === null) {
                    return { ok: false, status: 500 } as unknown as Response;
                }

                return {
                    ok: true,
                    status: 200,
                    json: async () => ({
                        patterns: [],
                        security: [
                            { key: 'virusScan', state: settings.virusScan, switchable: false },
                            { key: 'signedLink', state: 'on', switchable: false },
                        ],
                    }),
                } as unknown as Response;
            }

            throw new Error(`Unhandled fetch: ${target}`);
        }),
    );
}

describe('MediaUploadRegion — beklemede kalan dosya (MEDIA-SCANNER-HONEST-AT-UPLOAD-01)', () => {
    beforeEach(() => {
        stubFetch({ virusScan: 'on' });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('sunucunun kaydettiği sebebi yükleme ekranında yazar ve bunun sahip hatası olmadığını söyler', async () => {
        const onSubmit = vi.fn(async () => ({
            status: 'scanning',
            statusReason: HELD_REASON,
        }));

        render(<MediaUploadRegion workspaceId={7} onSubmit={onSubmit} />);
        await fillAndSubmit();

        await waitFor(() => {
            expect(screen.getByText(HELD_REASON)).toBeInTheDocument();
        });

        // "Tamamlandı" tek başına yanıltıcıydı: dosya ulaştı ama kullanılamıyor.
        expect(screen.getByText(/cannot be used in your menu yet/i)).toBeInTheDocument();
        expect(screen.queryByText(/^Media upload complete\.$/)).not.toBeInTheDocument();

        // Suç dosyada ya da sahipte değil, ORTAMDA.
        expect(screen.getByText(/did not do anything wrong/i)).toBeInTheDocument();

        // Ve çelişen vaat ekranda kalmaz.
        expect(screen.queryByText(/every image is scanned/i)).not.toBeInTheDocument();
    });

    it('dosya sorunsuz ilerlediğinde eski cümle aynen kalır', async () => {
        const onSubmit = vi.fn(async () => ({ status: 'ready', statusReason: null }));

        render(<MediaUploadRegion workspaceId={7} onSubmit={onSubmit} />);
        await fillAndSubmit();

        await waitFor(() => {
            expect(screen.getByText('Media upload complete.')).toBeInTheDocument();
        });

        expect(screen.getByText(/every image is scanned/i)).toBeInTheDocument();
        expect(screen.queryByText(/cannot be used in your menu yet/i)).not.toBeInTheDocument();
    });
});

/**
 * MEDIA-SCANNER-PROMISE-HONEST-01 — vaat, YÜKLEMEDEN ÖNCE de dürüst.
 *
 * FF-150 yalnız yüklemenin ARDINDAN çelişen vaadi susturmuştu. Ama sahip o
 * cümleyi henüz hiçbir şey yüklemeden okuyor: ekranı açıyor, "her görsel
 * taranır ve bir kişi kontrol eder" yazısını görüyor ve müşteri
 * fotoğraflarını buna güvenerek yüklüyor. Tarayıcının bağlı olmadığı bir
 * ortamda bu cümle, okunduğu ilk saniyede yanlıştır.
 *
 * Durum İKİNCİ bir gerçek kaynağından değil, ayarlar ekranının kullandığı
 * AYNI uçtan okunur (`/api/workspaces/{id}/media/settings`). İki kaynak bir
 * gün ayrışır ve sahip aynı soruya iki farklı cevap alır.
 */
describe('MediaUploadRegion — yüklemeden ÖNCEKİ vaat (MEDIA-SCANNER-PROMISE-HONEST-01)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('tarayıcı çalışıyorken bugünkü vaat aynen durur', async () => {
        stubFetch({ virusScan: 'on' });

        render(<MediaUploadRegion workspaceId={7} onSubmit={vi.fn(async () => {})} />);

        expect(await screen.findByText(/every image is scanned/i)).toBeInTheDocument();
    });

    it('tarayıcı yokken vaat yerine ortamın gerçeğini yazar ve bunun sahip hatası olmadığını söyler', async () => {
        stubFetch({ virusScan: 'unavailable' });

        render(<MediaUploadRegion workspaceId={7} onSubmit={vi.fn(async () => {})} />);

        // Ortamın gerçeği: taranmıyor, o yüzden menüde de görünmüyor.
        expect(await screen.findByText(/no virus scanner is connected/i)).toBeInTheDocument();

        // Ve bu bir SAHİP HATASI değil; panelden açılacak bir anahtar da yok.
        expect(screen.getByText(/did not do anything wrong/i)).toBeInTheDocument();

        // Yanlış vaat hiç yazılmaz.
        expect(screen.queryByText(/every image is scanned/i)).not.toBeInTheDocument();
    });

    it('durum henüz bilinmiyorken hiçbir iddia yazmaz', async () => {
        stubFetch('never');

        render(<MediaUploadRegion workspaceId={7} onSubmit={vi.fn(async () => {})} />);

        // Dosya seçme adımı çizilmiş olmalı — ekran çalışıyor.
        expect(await screen.findByText(/drop an image here/i)).toBeInTheDocument();

        /*
            Ama vaat kutusu BOŞ. Yanlış cümleyi bir an gösterip düzeltmek,
            hiç göstermemekten kötüdür: sahip ilk okuduğuna inanır ve
            düzeltmeyi görmez.
        */
        expect(screen.queryByText(/every image is scanned/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/no virus scanner is connected/i)).not.toBeInTheDocument();
    });

    it('ayarlar ucu düşerse sessiz kalır; ekranın geri kalanı çalışmaya devam eder', async () => {
        stubFetch(null);

        render(<MediaUploadRegion workspaceId={7} onSubmit={vi.fn(async () => {})} />);

        expect(await screen.findByText(/drop an image here/i)).toBeInTheDocument();
        expect(screen.queryByText(/every image is scanned/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/no virus scanner is connected/i)).not.toBeInTheDocument();
    });
});
