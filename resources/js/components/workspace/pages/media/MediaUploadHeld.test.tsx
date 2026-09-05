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

describe('MediaUploadRegion — beklemede kalan dosya (MEDIA-SCANNER-HONEST-AT-UPLOAD-01)', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string) => {
                if (String(url) !== '/api/media/slot-policies') {
                    throw new Error(`Unhandled fetch: ${String(url)}`);
                }

                return {
                    ok: true,
                    status: 200,
                    json: async () => ({
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
                    }),
                } as unknown as Response;
            }),
        );
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('sunucunun kaydettiği sebebi yükleme ekranında yazar ve bunun sahip hatası olmadığını söyler', async () => {
        const onSubmit = vi.fn(async () => ({
            status: 'scanning',
            statusReason: HELD_REASON,
        }));

        render(<MediaUploadRegion onSubmit={onSubmit} />);
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

        render(<MediaUploadRegion onSubmit={onSubmit} />);
        await fillAndSubmit();

        await waitFor(() => {
            expect(screen.getByText('Media upload complete.')).toBeInTheDocument();
        });

        expect(screen.getByText(/every image is scanned/i)).toBeInTheDocument();
        expect(screen.queryByText(/cannot be used in your menu yet/i)).not.toBeInTheDocument();
    });
});
