import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaUploadRegion } from './MediaUploadRegion';

/**
 * BIRAKMA ALANININ ÖLÇEK DİSİPLİNİ — kanonik teslim paketi (`DESIGN_SPEC.md`
 * §7 "Bırakma alanı": yardım metni bırakma alanının GÖVDESİDİR, dipnot değil).
 *
 * Restoran sahibinin yolculuğu: ürün fotoğrafını yüklemeye çalışıyor, sunucu
 * "en az 1200 × 400 piksel" diyor. Bu cümle onun kararını değiştiren TEK
 * bilgidir — telefondan çektiği fotoğraf yeter mi, yetmez mi. Böyle bir cümle
 * dipnot ölçeğine düşürülemez.
 *
 * `app.css` bu ayrımı token yorumunda açıkça yazar: `text-meta` yalnız zaman
 * damgası, sayaç ve birim eki içindir; "etiket, gövde, buton metni veya hata
 * mesajı için KULLANILMAZ".
 *
 * Bugün `--text-meta` ile `--text-body` aynı 1rem'e bağlı, yani bu kural
 * ekranda henüz bir fark üretmiyor. Tam da bu yüzden test şart: ölçek bir gün
 * yeniden ayrışırsa, yanlış rol taşıyan cümleler sessizce küçülür ve kimse
 * bunu bir değişiklikle ilişkilendiremez.
 */
function mount() {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({
            ok: true,
            status: 200,
            json: async () => ({
                slots: [
                    {
                        key: 'itemImage',
                        minWidth: 1200,
                        minHeight: 400,
                        aspect: null,
                        formats: ['jpeg'],
                        altRequired: true,
                    },
                ],
                limits: { maxBytes: 31457280, maxMegapixels: 40 },
            }),
        })),
    );

    render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('MediaUploadRegion — cümleler gövde, sayılar hizalı', () => {
    it('alternatif metin ipucu gövde metnidir', async () => {
        mount();

        const hint = await screen.findByText(/Describe the image for people/i);

        expect(hint).toHaveClass('text-body');
        expect(hint.className).not.toMatch(/text-meta/);
    });

    it('güvenlik açıklaması gövde metnidir', async () => {
        mount();

        const explanation = await screen.findByText(/Every image is scanned/i);

        expect(explanation).toHaveClass('text-body');
        expect(explanation.className).not.toMatch(/text-meta/);
    });

    it('slot gereksinimi gövde metnidir ve rakamları hizalanır', async () => {
        /*
            "En az 1200 × 400 piksel" hem bir CÜMLE hem de bir ÖLÇÜDÜR: slot
            değiştikçe sayılar değişir, o yüzden `tabular-nums`.
        */
        const user = userEvent.setup();
        mount();

        await user.selectOptions(
            await screen.findByLabelText(/Where will this image be used/i),
            'itemImage',
        );

        const requirement = screen.getByText('At least 1200 × 400 pixels').closest('ul');

        expect(requirement).toHaveClass('text-body');
        expect(requirement).toHaveClass('tabular-nums');
        expect(requirement?.className ?? '').not.toMatch(/text-meta/);
    });
});
