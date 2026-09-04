import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaLibraryRegion } from './MediaLibraryRegion';
import type { MediaAsset } from '../MediaPage';

/**
 * KÜTÜPHANE ARAÇ ÇUBUĞU — kanonik kaynağın "Kütüphane" bölümü:
 * klasör hapları, Süz / Sırala / Görünüm, sonuç sayısı + "tümünü seç",
 * ızgara/liste ve çoklu seçim.
 *
 * Neden araç çubuğu: elli dosyalık bir kütüphanede sahibin sorusu tek
 * değildir. "Hangi fotoğraftı?" göze bakar (ızgara), "hangisi hâlâ
 * taranıyor?" okumaya bakar (liste), "geçen ayki kampanya" klasöre bakar,
 * "en büyük dosyalar hangileri?" sıralamaya bakar. Bunların hepsini tek bir
 * düz listeye sıkıştırmak, her seferinde gözle tarama yaptırır.
 *
 * Süzgeçler KAPALI başlar: kaynağın "Süz" düğmesi bir kapıdır. Üç açılır
 * kutuyu her açılışta ekrana sermek, kütüphaneyi bir form gibi gösterir ve
 * asıl iş olan dosyaları aşağı iter.
 */

const ASSETS: MediaAsset[] = [
    {
        id: 1,
        altText: 'Adana kebap',
        slot: 'itemImage',
        status: 'ready',
        originalName: 'adana.jpg',
        sizeBytes: 3 * 1048576,
        usageCount: 2,
        previewUrl: 'https://cdn.example/adana-thumb.webp',
        createdAt: '2026-01-05T10:00:00Z',
    },
    {
        id: 2,
        altText: 'Lahmacun',
        slot: 'itemImage',
        status: 'ready',
        originalName: 'lahmacun.jpg',
        sizeBytes: 9 * 1048576,
        usageCount: 0,
        previewUrl: 'https://cdn.example/lahmacun-thumb.webp',
        createdAt: '2026-02-05T10:00:00Z',
    },
    {
        id: 3,
        altText: 'Bekleyen logo',
        slot: 'logo',
        status: 'scanning',
        originalName: 'logo.png',
        sizeBytes: 1048576,
        usageCount: 0,
        createdAt: '2026-03-05T10:00:00Z',
    },
];

function mount(overrides: Partial<React.ComponentProps<typeof MediaLibraryRegion>> = {}) {
    const onDelete = overrides.onDelete ?? vi.fn();
    render(
        <MediaLibraryRegion assets={ASSETS} onDelete={onDelete} loadState="idle" {...overrides} />,
    );
    return { onDelete };
}

function rowNames(): string[] {
    return within(screen.getByRole('list', { name: 'Assets' }))
        .getAllByRole('listitem')
        .map((row) => row.querySelector('[data-media-asset-name]')?.textContent ?? '');
}

describe('Kütüphane araç çubuğu — Süz', () => {
    it('süzgeçler KAPALI başlar, Süz düğmesiyle açılır', async () => {
        const user = userEvent.setup();
        mount();

        expect(screen.queryByLabelText('Slot')).toBeNull();

        await user.click(screen.getByRole('button', { name: 'Filter' }));

        expect(screen.getByLabelText('Slot')).toBeInTheDocument();
        expect(screen.getByLabelText('Status')).toBeInTheDocument();
    });

    it('etkin süzgeç sayısı düğmenin üstünde okunur', async () => {
        const user = userEvent.setup();
        mount();

        await user.click(screen.getByRole('button', { name: 'Filter' }));
        await user.selectOptions(screen.getByLabelText('Slot'), 'logo');

        const filterButton = screen.getByRole('button', { name: /^Filter/ });
        expect(within(filterButton).getByText('1')).toHaveClass('tabular-nums');
    });
});

describe('Kütüphane araç çubuğu — Sırala', () => {
    it('sıralama gerçek alanlar arasında döner ve satır sırası değişir', async () => {
        const user = userEvent.setup();
        mount();

        // Varsayılan: en yeni önce (yükleme zamanı gerçek veridir).
        expect(rowNames()).toEqual(['Bekleyen logo', 'Lahmacun', 'Adana kebap']);

        await user.click(screen.getByRole('button', { name: 'Sort: Newest' }));
        expect(rowNames()).toEqual(['Adana kebap', 'Bekleyen logo', 'Lahmacun']);

        await user.click(screen.getByRole('button', { name: 'Sort: Name' }));
        expect(rowNames()).toEqual(['Lahmacun', 'Adana kebap', 'Bekleyen logo']);
    });
});

describe('Kütüphane araç çubuğu — sonuç sayısı ve çoklu seçim', () => {
    it('sonuç sayısı okunur ve tümünü seç bütün görünen dosyaları seçer', async () => {
        const user = userEvent.setup();
        mount();

        expect(screen.getByText('3 file(s)')).toHaveClass('tabular-nums');

        await user.click(screen.getByRole('button', { name: 'Select all' }));

        expect(screen.getByText('3 selected')).toBeInTheDocument();
        expect(screen.getByRole('checkbox', { name: 'Select Lahmacun' })).toBeChecked();

        await user.click(screen.getByRole('button', { name: 'Clear selection' }));
        expect(screen.queryByText('3 selected')).toBeNull();
    });

    it('seçilenleri sil: kullanılmayan gider, KULLANILAN korunur ve sebebi yazar', async () => {
        /*
            Toplu silmede sessizce atlamak en kötüsüdür: sahip üç dosya seçer,
            ikisi silinir ve üçüncüsünün neden durduğunu hiçbir yerde yazmaz.
            Menüde duran bir fotoğraf toplu silmeyle GİTMEZ — nerede
            kullanıldığı tek tek gösterilir.
        */
        const user = userEvent.setup();
        const { onDelete } = mount();

        await user.click(screen.getByRole('button', { name: 'Select all' }));
        await user.click(screen.getByRole('button', { name: 'Delete selected' }));

        expect(onDelete).toHaveBeenCalledWith(2);
        expect(onDelete).toHaveBeenCalledWith(3);
        expect(onDelete).not.toHaveBeenCalledWith(1);
        expect(
            screen.getByText('1 in use were kept. Open each one to see where it is used.'),
        ).toBeInTheDocument();
    });
});

describe('Kütüphane — erişim işareti', () => {
    it('herkese açık adresi olmayan dosya kilitli görünür', async () => {
        const user = userEvent.setup();
        mount();

        const pending = screen.getByText('Bekleyen logo').closest('li') as HTMLElement;
        expect(within(pending).getByText('Not publicly available yet')).toBeInTheDocument();

        const ready = screen.getByText('Adana kebap').closest('li') as HTMLElement;
        expect(within(ready).queryByText('Not publicly available yet')).toBeNull();

        // Izgarada da aynı işaret durur.
        await user.click(screen.getByRole('button', { name: 'Grid' }));
        const pendingCard = screen.getByText('Bekleyen logo').closest('li') as HTMLElement;
        expect(within(pendingCard).getByText('Not publicly available yet')).toBeInTheDocument();
    });
});

describe('Kütüphane — klasör hapları', () => {
    it('klasör yoksa hap da yoktur', () => {
        mount();

        expect(screen.queryByRole('button', { name: 'All files' })).toBeNull();
    });

    it('klasör hapı seçimi dışarı bildirir ve seçili hap işaretlenir', async () => {
        const user = userEvent.setup();
        const onFolderChange = vi.fn();

        mount({
            folders: [
                { id: 4, name: 'Kampanyalar', assetCount: 1 },
                { id: 5, name: 'Menü', assetCount: 2 },
            ],
            activeFolderId: 4,
            onFolderChange,
        });

        const chip = screen.getByRole('button', { name: /Kampanyalar/ });
        expect(chip).toHaveAttribute('aria-pressed', 'true');
        expect(chip).toHaveClass('rounded-pill');

        await user.click(screen.getByRole('button', { name: 'All files' }));
        expect(onFolderChange).toHaveBeenCalledWith(null);
    });

    it('klasör seçiliyken yalnız o klasörün dosyaları listelenir', () => {
        mount({
            assets: [
                { ...ASSETS[0], folderId: 4 },
                { ...ASSETS[1], folderId: 5 },
            ],
            folders: [{ id: 4, name: 'Kampanyalar', assetCount: 1 }],
            activeFolderId: 4,
            onFolderChange: vi.fn(),
        });

        expect(screen.getByText('Adana kebap')).toBeInTheDocument();
        expect(screen.queryByText('Lahmacun')).toBeNull();
    });
});

describe('Kütüphane — arama kabuktan gelebilir', () => {
    it('dışarıdan arama verildiğinde bölge KENDİ arama kutusunu çizmez', () => {
        mount({ query: 'kebap' });

        expect(screen.queryByRole('searchbox')).toBeNull();
        expect(screen.getByText('Adana kebap')).toBeInTheDocument();
        expect(screen.queryByText('Lahmacun')).toBeNull();
    });
});
