import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { MediaTrashList } from './MediaTrashList';
import type { MediaAsset } from '../MediaPage';

/**
 * ÇÖPÜN KART GRAMERİ — kanonik teslim paketi (`DESIGN_SPEC.md` §7 "Çöp" ve
 * "Kart grameri": TEK kart, içinde İNCE AYRAÇLI satırlar).
 *
 * Restoran sahibinin yolculuğu: yanlışlıkla sildiği kapak fotoğrafını geri
 * almak istiyor. Sorduğu soru bir KARŞILAŞTIRMA sorusudur — "hangisiydi?" —
 * ve üç beş dosya alt alta, aynı hizada, aynı ritimde okunmalıdır.
 *
 * Önceki hâl bunu yapamıyordu: her satır `gap-2` ile ayrılmış, üstüne bir de
 * `border-b` çizilmiş bağımsız bir kutuydu. Boşluk + çizgi birlikte, çizgiyi
 * satırdan koparıp havada bırakır; göz her satırda "bu neydi?" diye yeniden
 * başlar. Ayraç ÜSTE konur (`first:border-t-0`), çünkü alttan ayraçta son
 * satırın çizgisini ayrıca susturmak gerekir ve o susturma unutulduğunda
 * kartın kendi kenarlığıyla çakışan ikinci bir çizgi belirir.
 *
 * Ritmin kaynağı YOĞUNLUK jetonlarıdır: sahip Ayarlar'dan "Sıkı / Standart /
 * Ferah" seçtiğinde çöp listesi de onunla değişmelidir; elle yazılmış bir
 * `pb-2` o anahtarı sağır bırakır.
 */
const TRASHED: MediaAsset[] = [
    {
        id: 501,
        altText: 'Kapak fotoğrafı',
        slot: 'menu',
        status: 'ready',
        originalName: 'kapak.jpg',
        sizeBytes: 2 * 1048576,
    },
    {
        id: 502,
        altText: 'Salon',
        slot: 'restaurant',
        status: 'ready',
        originalName: 'salon.jpg',
        sizeBytes: 3 * 1048576,
    },
];

function mount() {
    render(
        <MediaTrashList
            loadTrash={vi.fn(async () => TRASHED)}
            restore={vi.fn(async () => {})}
            onRestored={() => {}}
            retentionDays={30}
        />,
    );
}

describe('MediaTrashList — satır kart değildir, ayraç üsttedir', () => {
    it('satırlar ÜSTTEN ayraçlıdır ve ilk satırda ayraç yoktur', async () => {
        mount();

        const row = (await screen.findByText('Kapak fotoğrafı')).closest('li');

        expect(row).toHaveClass('border-t');
        expect(row).toHaveClass('first:border-t-0');
        expect(row?.className ?? '').not.toMatch(/(^|\s)border-b\b/);
    });

    it('satır yüksekliği ve yatay dolgusu yoğunluk jetonundan gelir', async () => {
        mount();

        const row = (await screen.findByText('Kapak fotoğrafı')).closest('li');

        expect(row).toHaveClass('min-h-[var(--density-row-height)]');
        expect(row).toHaveClass('px-[var(--density-padding-inline)]');
    });

    it('satırlar arasında boşluk YOKTUR: liste tek bir kartın içidir', async () => {
        mount();

        const list = (await screen.findByText('Kapak fotoğrafı')).closest('ul');

        expect(list?.className ?? '').not.toMatch(/(^|\s)gap-/);
    });
});

describe('MediaTrashList — ölçek disiplini', () => {
    it('saklama cümlesi gövde metnidir', async () => {
        /*
            "Silinen dosyalar 30 gün burada bekler" bir sayaç değil, bir
            CÜMLEDİR ve sahibin kararını değiştiren tek bilgidir. `text-meta`
            rolü yalnız zaman damgası ve sayaç içindir (`app.css`).
        */
        mount();

        const lead = await screen.findByText(/30 days/);

        expect(lead).toHaveClass('text-body');
        expect(lead.className).not.toMatch(/text-meta/);
    });

    it('dosya boyutu tabular-nums taşır', async () => {
        /*
            Boyutlar alt alta okunur; orantılı rakamda "2 MB" ile "3 MB"
            farklı genişlikte çizilir ve sütun titrer.
        */
        mount();

        const meta = await screen.findByText('kapak.jpg · 2.0 MB');

        expect(meta).toHaveClass('tabular-nums');
        expect(meta).toHaveClass('text-meta');
    });

    it('geri al düğmesinin metni meta rolüne düşürülmez', async () => {
        mount();

        const button = await screen.findByRole('button', { name: 'Restore Kapak fotoğrafı' });

        expect(button.className).not.toMatch(/text-meta/);
    });
});
