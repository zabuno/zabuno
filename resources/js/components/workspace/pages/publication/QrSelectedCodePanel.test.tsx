import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { QrSelectedCodePanel } from './QrSelectedCodePanel';
import type { QrScreenCode } from './QrTableCardGrid';

/**
 * SEÇİLİ KODUN PANELİ — panel v3 kanonik kaynağı (`docs/109` §6.7).
 *
 * Kaynağın sağ paneli: durum rozeti, büyük önizleme, TAM ADRES, tema, boyut,
 * "PDF indir"/"Yazdır" ve **ÖLÇÜLMÜŞ KONTRAST**.
 *
 * Kontrast satırı bu ekranın en kolay yalan söylenecek yeridir. Kaynak
 * "18,7:1 · tarayıcı testi geçti" yazıyor; bu iki ayrı iddiadır ve yalnız
 * biri doğrulanabilir:
 *
 *   - **Oran HESAPLANIR.** Kart her zaman siyah kod / beyaz kâğıt basar
 *     (`ExportQrCardController`: "Kodun kendisi HER ZAMAN klasik"), yani oran
 *     WCAG bağıl parlaklıktan tam olarak 21,0:1 çıkar. Ekranda yazan sayı bu
 *     hesabın çıktısıdır; elle yazılmış bir sabit, bir gün renk değiştiğinde
 *     sessizce yalan söylerdi.
 *   - **"Tarayıcı testi geçti" YAZILMAZ.** Ürün hiçbir telefonda hiçbir
 *     tarama testi çalıştırmıyor. Çalıştırmadığı bir testin geçtiğini yazmak,
 *     sahibin kırk kart bastırıp masaya koymasını sağlayan cümledir.
 *
 * Tema listesi de gerçek: kart tasarımı `CardTheme` (dört tasarım) ve marka
 * rengi KARTIN çerçevesine uygulanır, kodun modüllerine değil. "Koyu" (açık
 * kod / koyu zemin) SUNULMAZ ve sebebi ekranda yazar — ters kontrastlı bir
 * karekod birçok telefonda hiç okunmaz (ISO/IEC 18004 koyu-üstüne-açık
 * varsayar).
 */

const CODE: QrScreenCode = {
    id: 11,
    workspaceId: 71,
    locationId: 923,
    menuId: 42,
    token: 'yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
    resolverUrl: 'https://zabuno.test/q/yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
    destinationType: 'published_menu',
    state: 'active',
    tableName: 'Masa 3',
    scanCount: 12,
};

describe('QrSelectedCodePanel — seçili kodun paneli', () => {
    it('kontrastı HESAPLAR ve doğrulanmamış bir tarayıcı testi iddiası taşımaz', () => {
        render(<QrSelectedCodePanel code={CODE} />);

        const contrast = screen.getByText(/contrast measured/i);

        expect(
            contrast.textContent ?? '',
            'Siyah kod / beyaz kâğıt WCAG bağıl parlaklıkla tam 21,0:1 verir; sayı hesaptan gelmeli.',
        ).toMatch(/21\.0:1/);

        expect(
            document.body.textContent ?? '',
            'Çalıştırılmamış bir tarayıcı testinin geçtiği YAZILMAZ.',
        ).not.toMatch(/scanner test/i);
    });

    it('kodun TAM adresini gösterir ve kopyalanabilir kılar', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText },
            configurable: true,
        });

        render(<QrSelectedCodePanel code={CODE} />);

        expect(screen.getByText(CODE.resolverUrl)).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: /copy address/i }));

        expect(writeText).toHaveBeenCalledWith(CODE.resolverUrl);
    });

    it('hiç taranmamış kodu "çalışıyor" diye sunmaz', () => {
        render(<QrSelectedCodePanel code={{ ...CODE, scanCount: 0 }} />);

        expect(screen.getByText(/never scanned/i)).toBeInTheDocument();
        expect(screen.queryByText(/^working$/i)).toBeNull();
    });

    it('koyu (ters kontrastlı) kodun neden sunulmadığını SÖYLER', () => {
        render(<QrSelectedCodePanel code={CODE} />);

        expect(
            screen.getByText(/dark-on-light/i),
            'Kaynakta olan bir seçenek çizilmiyorsa sebebi ekranda yazar; sessizce eksiltmek eksiklik gibi okunur.',
        ).toBeInTheDocument();
    });

    it('boyut değiştiğinde indirme adresi GERÇEKTEN değişir', async () => {
        render(<QrSelectedCodePanel code={CODE} />);

        const before = screen.getByRole('link', { name: /download pdf/i }).getAttribute('href');
        expect(before).toMatch(/size=A6/);

        await userEvent.click(screen.getByRole('radio', { name: /148 × 210/i }));

        expect(screen.getByRole('link', { name: /download pdf/i }).getAttribute('href')).toMatch(
            /size=A5/,
        );
    });

    it('ağırlık ölçeğinin dışına çıkmaz ve rounded-full kullanmaz', () => {
        const { container } = render(<QrSelectedCodePanel code={CODE} />);

        expect(container.innerHTML).not.toMatch(/font-semibold/);
        expect(container.innerHTML).not.toMatch(/rounded-full/);
    });
});

/**
 * TESLİMATIN DA BİR DURUMU OLMALI.
 *
 * Kart görselinin ucu `qr.design.manage` yetkisi ister
 * (`ExportQrCardController`). Yetkisi olmayan bir kullanıcıda panelin ortasında
 * tarayıcının kırık resim simgesi kalırdı — sayfadaki her şeyin bir hâli
 * varken, sahibin buraya gelme sebebi olan şeyin hâli yoktu.
 */
describe('QrSelectedCodePanel — önizleme üretilemediğinde', () => {
    it('kırık resim yerine bir durum yazar ve indirme bağlantısı durur', () => {
        render(<QrSelectedCodePanel code={CODE} />);

        fireEvent.error(screen.getByRole('img', { name: /printable card preview/i }));

        expect(screen.getByText(/the preview could not be produced/i)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /download pdf/i })).toBeInTheDocument();
    });
});
