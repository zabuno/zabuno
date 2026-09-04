import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MediaUploadRegion, type UploadOptions } from './MediaUploadRegion';

/**
 * FF-76 (`docs/101` A5): 40 fotoğrafı tek tek seçtirmeyiz. Birden çok dosya
 * seçilince ilki bugünkü yoldan, kalanlar dosya adından türeyen adla ve aynı
 * yerle sırayla yüklenir.
 */
describe('çoklu yükleme (ACEMI-MEDIA-MULTI-01)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('üç dosya seçilince ikisi listede adıyla gelir; Upload üçünü de sırayla gönderir', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({
                ok: true,
                status: 200,
                json: async () => ({
                    slots: [
                        {
                            key: 'itemImage',
                            minWidth: 1,
                            minHeight: 1,
                            aspect: null,
                            formats: ['jpeg'],
                            altRequired: true,
                        },
                    ],
                    limits: { maxBytes: 31457280, maxMegapixels: 40 },
                }),
            })),
        );
        const onSubmit = vi.fn<(formData: FormData, options: UploadOptions) => Promise<void>>(
            async () => {},
        );
        const user = userEvent.setup();
        render(<MediaUploadRegion onSubmit={onSubmit} />);

        const region = screen.getByRole('region', { name: /media upload/i });
        await user.upload(within(region).getByLabelText(/choose a file/i) as HTMLInputElement, [
            new File(['a'], 'kebap.jpg', { type: 'image/jpeg' }),
            new File(['b'], 'IMG_8734.jpg', { type: 'image/jpeg' }),
            new File(['c'], 'ayran-buyuk.jpg', { type: 'image/jpeg' }),
        ]);

        const more = screen.getByRole('list', { name: 'More photos to upload' });
        expect(within(more).getByLabelText('Name for IMG_8734.jpg')).toHaveValue('IMG 8734');
        expect(within(more).getByLabelText('Name for ayran-buyuk.jpg')).toHaveValue('ayran buyuk');

        await user.clear(within(more).getByLabelText('Name for IMG_8734.jpg'));
        await user.type(within(more).getByLabelText('Name for IMG_8734.jpg'), 'Lahmacun');
        await user.type(within(region).getByLabelText(/alt text/i), 'Adana kebap');
        const slotField = within(region).getByLabelText(/where will this image be used/i);
        await waitFor(() =>
            expect(within(slotField).getAllByRole('option').length).toBeGreaterThan(1),
        );
        await user.selectOptions(slotField, 'itemImage');
        await user.click(within(region).getByRole('button', { name: /^upload$/i }));

        await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(3));
        const alts = onSubmit.mock.calls.map(([formData]) => formData.get('altText'));
        expect(alts).toEqual(['Adana kebap', 'Lahmacun', 'ayran buyuk']);
        const keys = new Set(onSubmit.mock.calls.map(([, options]) => options.idempotencyKey));
        expect(keys.size).toBe(3);
        expect(screen.queryByRole('list', { name: 'More photos to upload' })).toBeNull();
    });
});
