import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import { MediaUploadRegion } from './MediaUploadRegion';
import { ServerRejectedError } from '../../../../lib/validationErrors';

/**
 * UPLOAD-REASON — yükleme neden başarısız oldu, ekranda yazsın.
 *
 * Yükleme yolu sunucunun yanıtını durum koduna çevirip atıyordu:
 * `throw new Error('Upload failed with status 413')`. Ekranda ise sabit bir
 * "yükleme başarısız" cümlesi görünüyordu. Oysa 50 MB sınırını aşan bir
 * dosyada sunucu tam olarak neyin fazla geldiğini söylüyor.
 *
 * İkinci yarısı da önemli ve bir test yakaladı: ağ koptuğunda `catch`
 * bloğuna düşen şey ham bir JavaScript hatasıdır ("Network failure"). Onu
 * ekrana basmak kullanıcıya iç detay sızdırmaktır ve hiçbir şey anlatmaz.
 * Bu yüzden yalnız `ServerRejectedError` görünür.
 */

async function fillAndSubmit(): Promise<void> {
    const user = (await import('@testing-library/user-event')).default.setup();
    const region = screen.getByRole('region', { name: /media upload/i });

    await user.upload(
        within(region).getByLabelText(/file/i) as HTMLInputElement,
        new File(['binary'], 'photo.png', { type: 'image/png' }),
    );
    await user.type(within(region).getByLabelText(/alt text/i), 'Bir tabak');
    const slotField = within(region).getByLabelText(/where will this image be used/i);

    // Slot listesi sunucudan geliyor; seçenek gelene kadar beklenir.
    await waitFor(() => {
        expect(
            within(slotField).getByRole('option', { name: /list.card.detail item/i }),
        ).toBeInTheDocument();
    });

    await user.selectOptions(slotField, 'itemImage');
    await user.click(within(region).getByRole('button', { name: /upload/i }));
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

describe('MediaUploadRegion — başarısızlığın sebebi', () => {
    // Bileşen slot politikalarını KENDİ okur; bu dosya `MediaPage` olmadan
    // doğrudan onu render ediyor, dolayısıyla fetch burada da taklit edilir.
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string) => {
                const policies = slotPoliciesResponse(url);

                if (policies) return policies;

                throw new Error(`Unhandled fetch: ${String(url)}`);
            }),
        );
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('sunucunun söylediği sebebi gösterir', async () => {
        const onSubmit = vi.fn(async () => {
            throw new ServerRejectedError(
                'The upload is larger than this server accepts (52M). Choose a smaller file.',
            );
        });

        render(<MediaUploadRegion onSubmit={onSubmit} />);
        await fillAndSubmit();

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent(/larger than this server accepts/i);
        });
    });

    it('ağ hatasında ham JavaScript metnini SIZDIRMAZ', async () => {
        const onSubmit = vi.fn(async () => {
            throw new Error('Network failure');
        });

        render(<MediaUploadRegion onSubmit={onSubmit} />);
        await fillAndSubmit();

        await waitFor(() => {
            expect(screen.getByRole('alert')).toBeInTheDocument();
        });

        expect(screen.getByRole('alert')).not.toHaveTextContent(/network failure/i);
    });
});
