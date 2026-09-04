import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { QrCodesPage } from './QrCodesPage';

/**
 * QR KODLAR EKRANI — AEP ağırlık ölçeği (kanonik teslim paketi,
 * `Restoran Paneli v2.dc.html`, `DESIGN_SPEC.md` §4).
 *
 * AEP ağırlık ölçeği ÜÇ basamaklıdır: 400 gövde, 500 vurgulu satır, 700
 * başlık ve birincil eylem. 600 (`font-semibold`) ölçekte YOKTUR.
 *
 * Neden bu bir tercih değil: 600 yazıldığında tarayıcı, yüklü yazı tipinin
 * 500 ve 700 kesimleri arasından birini seçer ya da sentetik bir kalınlaştırma
 * uydurur. Aynı ekran iki makinede iki farklı ağırlıkta çizilir ve hiçbir
 * ekran görüntüsü diğerini doğrulamaz. Ölçeğin üç basamağı, ürünün her yerde
 * aynı görünmesinin şartıdır.
 *
 * Restoran sahibinin yolculuğu: menüsü olmayan yeni bir sahip bu ekrana
 * girer ve tek bir eylem görür — "Menüye git". O eylem sayfanın en kalın
 * yazısıdır; kalınlığı gerçekten 700 olmalıdır ki sayfadaki tek çıkış yolu
 * olduğu okunsun.
 */
describe('QrCodesPage — ön koşul durumu', () => {
    it('menü yokken çıkış eylemi ölçeğin 700 basamağını taşır', () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={null} />);

        const action = screen.getByRole('button', {
            name: 'Go to your menu',
        });

        expect(action).toHaveClass('font-bold');
        expect(action).not.toHaveClass('font-semibold');
    });
});
