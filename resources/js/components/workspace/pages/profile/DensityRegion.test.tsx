import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { ThemeRoot } from '../../../theme/ThemeRoot';
import { DensityRegion } from './DensityRegion';

/**
 * YOĞUNLUK AÇIKLAMASI — kanonik kaynak (`panel.dc.html` > "Profil" >
 * "Görünüm"), birebir cümle: "Satır yüksekliği değişir; yazı boyutu ve
 * dokunma hedefi değişmez."
 *
 * NEDEN BU TEST: depodaki cümle ("nefes alma payı", "düğmeler aynı boyutta
 * kalır") kullanıcıya NE DEĞİŞTİĞİNİ değil neyin değişmediğini anlatıyordu.
 * Tabletle servis yapan bir garson için asıl soru şudur: "sıkışık" seçersem
 * parmağımın basacağı yer küçülür mü? Kaynağın cümlesi ikisini de tek
 * satırda cevaplıyor — satır yüksekliği değişir, YAZI BOYUTU ve DOKUNMA
 * HEDEFİ değişmez. Yazı boyutu, depodaki cümlede hiç geçmiyordu.
 *
 * `ThemeRoot` ile sarılır: sağlayıcı yoksa bölüm hiç çizilmez.
 */
describe('DensityRegion — kaynağın cümlesi (docs/109)', () => {
    it('satır yüksekliğinin değiştiğini, yazı boyutu ve dokunma hedefinin değişmediğini yazar', () => {
        render(
            <ThemeRoot>
                <DensityRegion />
            </ThemeRoot>,
        );

        expect(
            screen.getByText('Row height changes. Text size and touch targets stay the same.'),
        ).toBeInTheDocument();
    });
});
