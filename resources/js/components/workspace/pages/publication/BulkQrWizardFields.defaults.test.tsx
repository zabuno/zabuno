import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { BulkQrWizardFields } from './BulkQrWizardFields';

/**
 * "GERİSİ VARSAYILAN" — panel v3 kanonik kaynağı (`docs/109` §6.7).
 *
 * Kaynağın toplu kod bölümü tek soru sorar ("Kaç masa?") ve yanında,
 * daha hiçbir şey yazılmadan, VARSAYILANLARIN CÜMLESİNİ gösterir:
 * *"Gerisi varsayılan: 1 bölge, 4 koltuk, 'Masa 13'ten başlar."*
 *
 * Ürünün buradaki eski cümlesi "Fill in the table layout below, then create
 * the codes." idi — yani sahibe ne yapacağını söylüyor, ne OLACAĞINI
 * söylemiyordu. Fark somut: kırk masası olan bir kebapçı "Kodları oluştur"a
 * bastığında ürün kırk masayı TEK bölgeye koyar, her masaya dört koltuk yazar
 * ve masaları T1'den itibaren adlandırır. Sahip bunu ancak kırk masa
 * yaratıldıktan sonra öğreniyordu; salonu iki katlı olan bir işletme için bu,
 * geri alınması gereken kırk kayıt demekti.
 *
 * Sayılar UYDURULMAZ: bölge ve koltuk formun kendi canlı değerleridir, ad
 * öneki ve başlangıç numarası ise sunucunun gerçek varsayılanlarıdır
 * (`StoreBulkQrCodesController`: `$namingPrefix = 'T'`, `$start = 1`).
 */

describe('BulkQrWizardFields — varsayılanların cümlesi', () => {
    it('hiçbir şey yazılmadan ne olacağını SÖYLER', () => {
        render(<BulkQrWizardFields workspaceId={7} locationId={923} menuId={42} />);

        const defaults = screen.getByText(/the rest is default/i);

        expect(defaults.textContent ?? '').toMatch(/1 area/i);
        expect(defaults.textContent ?? '').toMatch(/4 seats/i);
        expect(
            defaults.textContent ?? '',
            'Ad öneki ve başlangıç numarası sunucunun gerçek varsayılanıdır (T1), uydurulmuş bir örnek değil.',
        ).toMatch(/T1/);
    });

    it('ileri ayarlar değişince cümle o değeri yazar — sabit bir metin değildir', async () => {
        const user = userEvent.setup();
        render(<BulkQrWizardFields workspaceId={7} locationId={923} menuId={42} />);

        const seats = screen.getByLabelText(/seat count per table/i);
        await user.clear(seats);
        await user.type(seats, '2');

        expect(screen.getByText(/the rest is default/i).textContent ?? '').toMatch(/2 seats/i);
    });
});
