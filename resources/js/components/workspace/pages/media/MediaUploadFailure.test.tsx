import { describe, expect, it, vi } from 'vitest';
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
    await user.selectOptions(within(region).getByLabelText(/asset slot/i), 'hero');
    await user.click(within(region).getByRole('button', { name: /upload/i }));
}

describe('MediaUploadRegion — başarısızlığın sebebi', () => {
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
