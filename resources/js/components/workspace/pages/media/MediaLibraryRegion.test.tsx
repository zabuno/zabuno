import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MediaLibraryRegion } from './MediaLibraryRegion';
import type { MediaAsset } from '../MediaPage';

const MIXED_ASSETS: MediaAsset[] = [
    { id: 101, altText: 'Front dining room photo', slot: 'restaurant', status: 'ready' },
    { id: 102, altText: 'Chef portrait', slot: 'corporateSite', status: 'processing' },
    { id: 103, altText: 'Menu cover scan', slot: 'menu', status: 'rejected' },
    { id: 104, altText: 'Product label photo', slot: 'product', status: 'quarantined' },
];

describe('MediaLibraryRegion — real status per asset (MEDIA-UI-STATUS-01)', () => {
    it('renders each mixed asset through its own real status, never hard-coding quarantine, preserves alt text and delete, and hides loading/empty/error notices', async () => {
        const user = userEvent.setup();
        const onDelete = vi.fn();

        render(<MediaLibraryRegion assets={MIXED_ASSETS} onDelete={onDelete} loadState="idle" />);

        expect(screen.queryByText('Loading media library…')).toBeNull();
        expect(screen.queryByText('No media assets yet.')).toBeNull();
        expect(screen.queryByRole('alert')).toBeNull();

        const list = screen.getAllByRole('list')[0];
        const items = within(list).getAllByRole('listitem');
        expect(items).toHaveLength(MIXED_ASSETS.length);

        const readyItem = items[0];
        expect(within(readyItem).getByText('Front dining room photo')).toBeInTheDocument();
        expect(within(readyItem).getByRole('status').textContent).toBe('Ready');

        const processingItem = items[1];
        expect(within(processingItem).getByText('Chef portrait')).toBeInTheDocument();
        expect(within(processingItem).getByRole('status').textContent).toBe('Processing');

        const rejectedItem = items[2];
        expect(within(rejectedItem).getByText('Menu cover scan')).toBeInTheDocument();
        expect(within(rejectedItem).getByRole('status').textContent).toBe(
            'Rejected — failed security scan',
        );

        const quarantinedItem = items[3];
        expect(within(quarantinedItem).getByText('Product label photo')).toBeInTheDocument();
        expect(within(quarantinedItem).getByRole('status').textContent).toBe(
            'Scan pending (quarantined)',
        );

        const nonQuarantinedStatuses = [readyItem, processingItem, rejectedItem].map(
            (item) => within(item).getByRole('status').textContent,
        );
        nonQuarantinedStatuses.forEach((text) => {
            expect(text).not.toBe('Scan pending (quarantined)');
        });

        await user.click(within(rejectedItem).getByRole('button', { name: /delete/i }));
        expect(onDelete).toHaveBeenCalledWith(103);
        expect(onDelete).toHaveBeenCalledTimes(1);
    });
});

/**
 * `docs/76` — rozet DURUMU söyler, sebebi söylemez.
 *
 * Virüs tarayıcı çalışmayan bir ortamda dosya "Scanning" rozetiyle sonsuza
 * kadar bekliyordu ve sahip ne olduğunu hiçbir yerde okuyamıyordu. Ürün
 * yapamadığı şeyi sessizce saklamaz.
 */
describe('durumun sebebi (docs/76)', () => {
    it('sebep varsa okunur', () => {
        render(
            <MediaLibraryRegion
                assets={[
                    {
                        id: 4,
                        altText: 'Adana kebap',
                        slot: 'itemImage',
                        status: 'scanning',
                        statusReason:
                            'Virüs taraması bu ortamda çalışmıyor; dosya taranmadan yayına alınmaz.',
                    },
                ]}
                loadState="idle"
                onDelete={() => {}}
            />,
        );

        expect(
            screen.getByText(
                'Virüs taraması bu ortamda çalışmıyor; dosya taranmadan yayına alınmaz.',
            ),
        ).toBeInTheDocument();
    });

    it('sorunsuz bir dosyaya sebep yazılmaz', () => {
        render(
            <MediaLibraryRegion
                assets={[{ id: 5, altText: 'Lahmacun', slot: 'itemImage', status: 'ready' }]}
                loadState="idle"
                onDelete={() => {}}
            />,
        );

        // Listenin ALTINDAKİ sayaç da `role="status"` taşır; iddia
        // SATIRIN içine bakar, yoksa her zaman doğru çıkardı.
        // Rozet her satırda var; iddia rozetin METNİNE bakar. Sorunsuz bir
        // dosyanın rozeti yalnız durumu söyler, fazladan bir cümle taşımaz.
        const row = screen.getByText('Lahmacun').closest('li');

        expect(row).not.toBeNull();
        expect(within(row as HTMLElement).getByRole('status')).toHaveTextContent(/^Ready$/);
    });
});
