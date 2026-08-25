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
    it('renders each mixed asset through its own real status, never hard-coding quarantine, and preserves alt text and delete', async () => {
        const user = userEvent.setup();
        const onDelete = vi.fn();

        render(<MediaLibraryRegion assets={MIXED_ASSETS} onDelete={onDelete} />);

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
