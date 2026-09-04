import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { WorkspaceSwitcherTrigger } from './WorkspaceSwitcherTrigger';

/**
 * Çalışma alanı seçici — teslim paketinin `DESIGN_SPEC.md` §1 "Kenar çubuğu":
 * "36px marka karesi (`brand` zemin, `action-fg` harf) + işletme adı".
 *
 * Bu kutu kenar çubuğunun İLK şeyidir ve sorusu şudur: "hangi restorandayım?"
 * Depodaki hâl baş harfi soluk bir gri kareye koyuyordu; aynı grinin
 * hemen altında gezinti maddelerinin aktif zemini de duruyordu, yani ekranın
 * en üst kimlik işareti ile "buradasın" işareti aynı renkti. İki restoran
 * arasında gün içinde gidip gelen bir işletmeci için ayırt edici tek işaret
 * bu karedir — marka rengini taşıması gereken yer burasıdır.
 */
describe('çalışma alanı seçici — AEP grameri (FF-131)', () => {
    it('baş harf marka karesinde durur ve yazısı ölçülmüş mürekkeptir', () => {
        render(<WorkspaceSwitcherTrigger workspaceName="Paşa Döner" />);

        const trigger = screen.getByRole('button', { name: /Paşa Döner/ });
        const square = trigger.querySelector('[data-slot="workspace-initial"]') as HTMLElement;

        expect(square).not.toBeNull();
        expect(square.textContent).toBe('P');
        expect(square.className).toContain('bg-action');
        expect(square.className).toContain('text-action-fg');
        /*
            Marka sarısı üstündeki tek meşru yazı rengi `--color-action-fg`
            (ölçülmüş 11.63:1). `text-fg` yazsaydık koyu temada beyaza döner
            ve sarı zeminde okunmazdı — jeton zaten temaya göre değişmez.
        */
        expect(square.className).not.toContain('text-fg');
        // Kart/panel yarıçapı tavanı 8px: kare, gezinti satırlarıyla aynı dil.
        expect(square.className).toContain('rounded-[var(--radius-lg)]');
    });

    /**
     * Ağırlık ölçeği yalnız 400/500/700'dür (TOKEN_MAP "Tipografi").
     *
     * 600, Roboto'da gerçek bir kesim değildir; tarayıcı onu 500'den sentezler
     * ve sonuç işletim sistemine göre farklı kalınlıkta çıkar. Kabuğun en üst
     * satırı, aynı ürünün iki bilgisayarda farklı görünmesinin en görünür yeri.
     */
    it('işletme adı 700 ağırlıkta yazılır', () => {
        render(<WorkspaceSwitcherTrigger workspaceName="Paşa Döner" />);

        const name = screen.getByText('Paşa Döner');

        expect(name.className).toContain('font-bold');
        expect(name.className).not.toContain('font-semibold');
    });

    it('çalışma alanı adı yoksa hiç çizilmez', () => {
        const { container } = render(<WorkspaceSwitcherTrigger />);

        expect(container.firstChild).toBeNull();
    });
});
