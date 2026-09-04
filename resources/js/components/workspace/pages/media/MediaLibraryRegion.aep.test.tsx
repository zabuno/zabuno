import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaLibraryRegion } from './MediaLibraryRegion';
import type { MediaAsset } from '../MediaPage';

/**
 * KÜTÜPHANENİN İKİ GRAMERİ — kanonik teslim paketi (`DESIGN_SPEC.md` §7
 * "Kütüphane": `auto-fill minmax(150px,1fr)` KARTLAR).
 *
 * Restoran sahibinin yolculuğu iki farklı sorudur ve iki farklı düzen ister:
 *
 * IZGARA — "hangi fotoğraftı?" Cevabı gözle verilir. Burada kutu ANLAMLIDIR:
 * her kutu bir görselin sınırıdır, kart o sınırı çizer. Kartsız bir ızgarada
 * iki fotoğrafın nerede bitip nerede başladığı belirsizleşir.
 *
 * LİSTE — "hangisi kullanılmıyor, hangisi hâlâ taranıyor?" Cevabı okumayla
 * verilir, alt alta, tek ritimde. Burada kutu bilgi TAŞIMAZ; satır kart
 * değildir, ayraç 1 piksellik çizgidir. Bu ayrım zaten uygulandı; bu dosya
 * onu ÇİVİLER — sonraki bir dokunuş listeyi yeniden kartlaştırmasın diye.
 *
 * Bir de sessiz bir bozukluk vardı: liste `ul`'i `gap-2` taşıyorken satırlar
 * `border-t` çiziyordu. Boşluk + çizgi birlikte, çizgiyi satırdan koparıp
 * havada bırakır — kart grameri tam tersini söyler: dış çerçeve bir kez
 * çizilir, içerideki ayrım tek çizgidir, aralarında boşluk olmaz.
 */
const ASSETS: MediaAsset[] = [
    {
        id: 101,
        altText: 'Kapak fotoğrafı',
        slot: 'menu',
        status: 'ready',
        originalName: 'kapak.jpg',
        sizeBytes: 2 * 1048576,
        usageCount: 2,
    },
    {
        id: 102,
        altText: 'Salon fotoğrafı',
        slot: 'restaurant',
        // Tarama BİTMEMİŞTİR ve bu ekranda öyle görünür (aşağıdaki dürüstlük
        // testi). Bu ortamda tarama kapalı olabilir; kapalı tarama "hazır"
        // demek değildir.
        status: 'scanning',
        originalName: 'salon.jpg',
        sizeBytes: 3 * 1048576,
        usageCount: 0,
    },
];

function mount() {
    render(<MediaLibraryRegion assets={ASSETS} onDelete={vi.fn()} loadState="idle" />);
}

describe('MediaLibraryRegion — ızgara karttır, liste satırdır', () => {
    it('LİSTEDE satır karta ait kenarlığı ve yarıçapı TAŞIMAZ', () => {
        mount();

        const row = screen.getByText('Kapak fotoğrafı').closest('li');

        expect(row).toHaveClass('border-t');
        expect(row).toHaveClass('first:border-t-0');
        expect(row?.className ?? '').not.toMatch(/rounded-\[var\(--radius-lg\)\]/);
        expect(row?.className ?? '').not.toMatch(/(^|\s)bg-surface(\s|$)/);
    });

    it('LİSTEDE satırlar arasında boşluk yoktur', () => {
        mount();

        const list = screen.getByText('Kapak fotoğrafı').closest('ul');

        expect(list?.className ?? '').not.toMatch(/(^|\s)gap-/);
    });

    it('IZGARADA kutu bir görselin sınırıdır: kart kenarlığı ve yarıçapı kalır', async () => {
        const user = userEvent.setup();
        mount();

        await user.click(screen.getByRole('button', { name: 'Grid' }));

        const card = screen.getByText('Kapak fotoğrafı').closest('li');

        expect(card).toHaveClass('rounded-[var(--radius-lg)]');
        expect(card).toHaveClass('border');
        expect(card).toHaveClass('bg-surface');
        // Izgarada kutular ARASINDA boşluk olur; onlar ayrı sınırlardır.
        expect(screen.getByText('Kapak fotoğrafı').closest('ul')?.className ?? '').toMatch(
            /(^|\s)gap-/,
        );
    });
});

describe('MediaLibraryRegion — ölçek ve ağırlık disiplini', () => {
    it('süzgeç etiketleri gövde metnidir', () => {
        /*
            "Search", "Slot", "Status" birer ETİKETTİR. `app.css` meta rolünü
            açıkça zaman damgası ve sayaçla sınırlar; etiketi oraya koymak,
            ölçeğin bir gün küçülmesi hâlinde formu okunamaz bırakır.
        */
        mount();

        const label = screen.getByText('Search').closest('label');

        expect(label).toHaveClass('text-body');
        expect(label?.className ?? '').not.toMatch(/text-meta/);
    });

    it('bölüm başlığı meta rolüne düşürülmez', () => {
        mount();

        const heading = screen.getByRole('heading', { name: 'Assets' });

        expect(heading).toHaveClass('text-body');
        expect(heading).toHaveClass('font-bold');
        expect(heading.className).not.toMatch(/text-meta/);
    });

    it('süzgeç sayacı tabular-nums taşır', async () => {
        const user = userEvent.setup();
        mount();

        // Süzgeçler kapalı başlar; "Süz" onları açan kapıdır (FF-131).
        await user.click(screen.getByRole('button', { name: 'Filter' }));
        await user.click(screen.getByRole('checkbox', { name: 'Unused only' }));

        const count = screen.getByText('Showing 1 of 2.');

        expect(count).toHaveClass('tabular-nums');
        // Sayaç, `text-meta`nın MEŞRU kullanımıdır.
        expect(count).toHaveClass('text-meta');
    });

    it('hiçbir yerde 600 ağırlık, büyük harf ya da rounded-full yoktur', () => {
        mount();

        const region = screen.getByRole('region', { name: 'Media library' });
        const classLists: string[] = [region.className];
        region.querySelectorAll<HTMLElement>('*').forEach((element) => {
            if (typeof element.className === 'string') classLists.push(element.className);
        });

        expect(classLists.filter((list) => /font-semibold/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /uppercase/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /rounded-full/.test(list))).toEqual([]);
    });
});

describe('MediaLibraryRegion — dürüstlük', () => {
    it('taranmakta olan dosya "taranıyor" kalır ve ona sahte görsel çizilmez', async () => {
        /*
            Bu ortamda virüs taraması kapalı olabilir. Kapalı tarama, dosyanın
            TARANDIĞI anlamına gelmez; rozet olduğu gibi kalır.

            Önizlemesi olmayan varlık için uydurma bir görsel de çizilmez:
            karantinadaki dosyanın herkese açık adresi yoktur. Izgarada onun
            yerinde "henüz önizleme yok" yazan boş bir çerçeve durur — bu bir
            fotoğraf DEĞİL, bir cümledir ve o yüzden gövde ölçeğindedir.
        */
        const user = userEvent.setup();
        mount();

        const row = screen.getByText('Salon fotoğrafı').closest('li');
        expect(row?.textContent).toContain('Scanning in progress');
        expect(row?.querySelector('img')).toBeNull();

        await user.click(screen.getByRole('button', { name: 'Grid' }));

        const card = screen.getByText('Salon fotoğrafı').closest('li');
        expect(card?.querySelector('img')).toBeNull();

        const placeholder = screen.getAllByText('No preview yet')[0];
        expect(placeholder).toHaveClass('text-body');
        expect(placeholder.className).not.toMatch(/text-meta/);
    });
});
