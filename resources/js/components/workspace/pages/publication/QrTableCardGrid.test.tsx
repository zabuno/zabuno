import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { QrTableCardGrid, type QrScreenCode } from './QrTableCardGrid';

/**
 * MASA KARTLARI IZGARASI — panel v3 kanonik kaynağı (`docs/109` §6.7).
 *
 * Kaynağın QR ekranı bir liste değil bir IZGARADIR ve her karenin üstünde üç
 * bilgi var: kodun önizlemesi, masanın adı ve TARAMA SAYISI. Sıralı bir satır
 * listesi bu işi yapmaz; sahip kırk masayı tek bakışta tarayamaz ve "hangi
 * masa hiç okutulmamış" sorusu ancak kırk satır okunarak yanıtlanır.
 *
 * Üç şey burada kilitleniyor:
 *
 *   1. **Taraması sıfır olan masa AYIRT EDİLİR — ve yalnız renkle değil.**
 *      Kaynak sıfırı kırmızıya boyuyor. Renk tek başına bir işaret değildir
 *      (WCAG 2.2 §1.4.1): kırmızı-yeşil ayırt edemeyen bir sahip — erkeklerin
 *      yaklaşık %8'i — o kırk kare arasında hiçbir fark görmez. İşaret METİN
 *      olmalı.
 *   2. **Sayı uydurulmaz.** Ölçüm plana bağlıdır ve kapalıyken sunucu `null`
 *      döner; ızgara o zaman sayı YAZMAZ. Sıfır yazmak "kod hiç taranmadı"
 *      demektir ve bu bir yalandır.
 *   3. **Seçili kart klavyeden ve ekran okuyucudan da bellidir.** Kaynak
 *      seçimi yalnız çerçeve rengiyle anlatıyor; `aria-pressed` olmadan
 *      ekran okuyucu kullanıcısı hangi masanın sağ panelde açık olduğunu
 *      bilemez.
 */

const CODES: QrScreenCode[] = [
    {
        id: 11,
        workspaceId: 71,
        locationId: 923,
        menuId: 42,
        token: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        resolverUrl: 'https://zabuno.test/q/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        destinationType: 'published_menu',
        state: 'active',
        tableName: 'Masa 1',
        scanCount: 31,
    },
    {
        id: 12,
        workspaceId: 71,
        locationId: 923,
        menuId: 42,
        token: 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        resolverUrl: 'https://zabuno.test/q/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        destinationType: 'published_menu',
        state: 'active',
        tableName: 'Masa 2',
        scanCount: 0,
    },
];

describe('QrTableCardGrid — masa kartları ızgarası', () => {
    it('hiç taranmamış masayı RENKTEN BAĞIMSIZ bir metinle ayırır', () => {
        render(<QrTableCardGrid codes={CODES} selectedId={11} onSelect={vi.fn()} />);

        const neverScanned = screen.getByRole('button', { name: /masa 2/i });

        expect(
            neverScanned.textContent ?? '',
            'Sıfır tarama METİNLE söylenmeli: renk tek başına bir işaret değildir (WCAG 1.4.1).',
        ).toMatch(/never scanned/i);
    });

    it('ölçüm kapalıyken sayı YAZMAZ — sıfır yazmak yalan olurdu', () => {
        const withoutCounts = CODES.map((code) => ({ ...code, scanCount: null }));

        render(<QrTableCardGrid codes={withoutCounts} selectedId={11} onSelect={vi.fn()} />);

        expect(
            screen.queryByText(/never scanned/i),
            'Ölçüm kapalıyken "hiç taranmadı" demek, bilmediğimizi bildiğimiz gibi göstermektir.',
        ).toBeNull();
        expect(screen.queryByText(/scans/i)).toBeNull();
    });

    it('seçili kart ekran okuyucuya da bildirilir ve tıklama seçimi taşır', async () => {
        const onSelect = vi.fn();
        render(<QrTableCardGrid codes={CODES} selectedId={11} onSelect={onSelect} />);

        expect(screen.getByRole('button', { name: /masa 1/i })).toHaveAttribute(
            'aria-pressed',
            'true',
        );

        await userEvent.click(screen.getByRole('button', { name: /masa 2/i }));

        expect(onSelect).toHaveBeenCalledWith(12);
    });

    it('sayaç tabular-nums taşır: rakam genişliği satır satır oynamaz', () => {
        render(<QrTableCardGrid codes={CODES} selectedId={11} onSelect={vi.fn()} />);

        const counter = screen.getByText(/31 scans/i);

        expect(counter).toHaveClass('tabular-nums');
        expect(counter).toHaveClass('text-meta');
    });

    it('ağırlık ölçeğinin dışına çıkmaz ve rounded-full kullanmaz', () => {
        const { container } = render(
            <QrTableCardGrid codes={CODES} selectedId={11} onSelect={vi.fn()} />,
        );

        expect(container.innerHTML).not.toMatch(/font-semibold/);
        expect(container.innerHTML).not.toMatch(/rounded-full/);
    });
});

/**
 * ÖNİZLEME ÜRETİLEMEZSE KIRIK RESİM SİMGESİ KALMAZ.
 *
 * Karekod görselinin ucu `qr.design.manage` yetkisi ister
 * (`ExportQrCodeSvgController`): yalnız `qr.view` yetkisi olan bir kullanıcı —
 * ör. mutfak rolü — kırk karenin kırkında da tarayıcının kırık resim simgesini
 * görürdü. Sayfadaki her şeyin bir hâli varken, sahibin buraya gelme sebebi
 * olan şeyin hâli yoktu.
 */
describe('QrTableCardGrid — önizleme üretilemediğinde', () => {
    it('kırık resim yerine bir durum yazar', () => {
        const { container } = render(
            <QrTableCardGrid codes={CODES} selectedId={11} onSelect={vi.fn()} />,
        );

        // `alt=""` bir görseli SÜS yapar; erişilebilirlik ağacında `img`
        // rolü taşımaz. Sorgu bu yüzden etiketten değil, düğümden geçer.
        const [first] = Array.from(container.querySelectorAll('img'));
        fireEvent.error(first);

        expect(screen.getAllByText(/the preview could not be produced/i).length).toBeGreaterThan(0);
    });
});
