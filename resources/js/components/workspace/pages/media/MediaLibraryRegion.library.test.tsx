import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MediaLibraryRegion } from './MediaLibraryRegion';
import type { MediaAsset, MediaLibraryActions } from '../MediaPage';

/**
 * `docs/49` Faz 4-5 (`docs/98` FF-70): kütüphane arar/süzer, ızgaraya
 * geçer, detay çekmecesi kullanım ve sürümleri gösterir, kullanılan görsel
 * silinirken etki önizlemesi açılır, çöp sekmesi geri alır.
 */
const ASSETS: MediaAsset[] = [
    {
        id: 1,
        altText: 'Adana kebap',
        slot: 'itemImage',
        status: 'ready',
        previewUrl: '/media/r/11-abc.webp',
        usageCount: 2,
        versionCount: 2,
        originalName: 'kebap.jpg',
        sizeBytes: 2_400_000,
        createdAt: '2026-09-01T10:00:00Z',
    },
    {
        id: 2,
        altText: 'Lahmacun',
        slot: 'itemImage',
        status: 'ready',
        previewUrl: '/media/r/12-def.webp',
        usageCount: 0,
        versionCount: 1,
        originalName: 'lahmacun.png',
        sizeBytes: 800_000,
    },
    { id: 3, altText: 'Logo', slot: 'logo', status: 'quarantined', usageCount: 0 },
];

function actionsStub(overrides: Partial<MediaLibraryActions> = {}): MediaLibraryActions {
    return {
        loadUsages: vi.fn(async () => [
            {
                entityType: 'menu_item',
                entityId: 7,
                slot: 'itemImage',
                label: 'Adana Kebap',
                published: false,
            },
            {
                entityType: 'menu_item',
                entityId: 8,
                slot: 'itemImage',
                label: 'Urfa Kebap',
                published: false,
            },
        ]),
        loadVersions: vi.fn(async () => [
            {
                number: 2,
                id: 22,
                createdBy: 'reprocess',
                createdAt: '2026-09-02',
                renditionCount: 3,
            },
            { number: 1, id: 21, createdBy: 'upload', createdAt: '2026-09-01', renditionCount: 3 },
        ]),
        reprocess: vi.fn(async () => {}),
        restoreVersion: vi.fn(async () => {}),
        detach: vi.fn(async () => {}),
        loadTrash: vi.fn(async () => []),
        restoreFromTrash: vi.fn(async () => {}),
        downloadOriginal: vi.fn(async () => 'https://zabuno.test/media/original/1/1?signature=x'),
        updateAltText: vi.fn(async () => {}),
        ...overrides,
    };
}

describe('kütüphane: arama, süzgeç, ızgara (FAZ4-LIBRARY-UI-01)', () => {
    it('arama ve "kullanılmayanlar" süzgeci listeyi daraltır; ızgara görünümü açılır', async () => {
        const user = userEvent.setup();
        render(
            <MediaLibraryRegion
                assets={ASSETS}
                onDelete={() => {}}
                loadState="idle"
                actions={actionsStub()}
            />,
        );

        const list = () => screen.getByRole('list', { name: 'Assets' });
        expect(within(list()).getAllByRole('listitem')).toHaveLength(3);

        await user.type(screen.getByRole('searchbox'), 'kebap');
        expect(within(list()).getAllByRole('listitem')).toHaveLength(1);
        expect(screen.getByText('Showing 1 of 3.')).toBeInTheDocument();
        await user.clear(screen.getByRole('searchbox'));

        await user.click(screen.getByRole('checkbox', { name: 'Unused only' }));
        expect(within(list()).getAllByRole('listitem')).toHaveLength(2);
        expect(within(list()).queryByText('Adana kebap')).toBeNull();
        await user.click(screen.getByRole('checkbox', { name: 'Unused only' }));

        await user.selectOptions(screen.getByLabelText('Slot'), 'logo');
        expect(within(list()).getAllByRole('listitem')).toHaveLength(1);
        await user.selectOptions(screen.getByLabelText('Slot'), '');

        await user.click(screen.getByRole('button', { name: 'Grid' }));
        expect(screen.getByRole('button', { name: 'Grid' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(list().className).toContain('grid');

        // Hazır varlık küçük resimle, karantinadaki resimsiz gelir.
        const images = within(list()).getAllByRole('presentation', { hidden: true });
        expect(images).toHaveLength(2);
    });

    it('satır meta bilgisi dosya adı, boyut ve kullanım sayısını taşır', () => {
        render(
            <MediaLibraryRegion
                assets={ASSETS}
                onDelete={() => {}}
                loadState="idle"
                actions={actionsStub()}
            />,
        );

        expect(screen.getByText('kebap.jpg · 2.3 MB · used in 2')).toBeInTheDocument();
    });
});

describe('detay çekmecesi (FAZ4-ASSET-DETAIL-01)', () => {
    it('adı tıklanınca kullanım ve sürümler gelir; eski sürüm geri alınır', async () => {
        const user = userEvent.setup();
        const actions = actionsStub();
        const onRetry = vi.fn();
        render(
            <MediaLibraryRegion
                assets={ASSETS}
                onDelete={() => {}}
                loadState="idle"
                actions={actions}
                onRetry={onRetry}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Open details for Adana kebap' }));

        const drawer = await screen.findByRole('dialog');
        expect(await within(drawer).findByText('Adana Kebap')).toBeInTheDocument();
        expect(within(drawer).getByText('Urfa Kebap')).toBeInTheDocument();
        expect(within(drawer).getByText('v2 · reprocess · 3 sizes')).toBeInTheDocument();
        expect(within(drawer).getByText('current')).toBeInTheDocument();
        expect(within(drawer).getByText('kebap.jpg')).toBeInTheDocument();
        expect(actions.loadUsages).toHaveBeenCalledWith(1);

        await user.click(within(drawer).getByRole('button', { name: 'Restore v1' }));
        expect(actions.restoreVersion).toHaveBeenCalledWith(1, 1);
        expect(
            await within(drawer).findByText('v1 restored as a new version.'),
        ).toBeInTheDocument();
        expect(onRetry).toHaveBeenCalled();
    });

    it('"Download original" imzalı adresi alır ve yeni sekmede açar', async () => {
        const user = userEvent.setup();
        const actions = actionsStub();
        const open = vi.fn();
        vi.stubGlobal('open', open);
        render(
            <MediaLibraryRegion
                assets={ASSETS}
                onDelete={() => {}}
                loadState="idle"
                actions={actions}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Open details for Adana kebap' }));
        const drawer = await screen.findByRole('dialog');
        await user.click(within(drawer).getByRole('button', { name: 'Download original' }));

        expect(actions.downloadOriginal).toHaveBeenCalledWith(1);
        expect(open).toHaveBeenCalledWith(
            'https://zabuno.test/media/original/1/1?signature=x',
            '_blank',
            'noopener',
        );
        expect(await within(drawer).findByText(/stays valid for 10 minutes/)).toBeInTheDocument();
        vi.unstubAllGlobals();
    });

    it('hazır varlıkta "Regenerate sizes" var, karantinadakinde yok', async () => {
        const user = userEvent.setup();
        const actions = actionsStub();
        render(
            <MediaLibraryRegion
                assets={ASSETS}
                onDelete={() => {}}
                loadState="idle"
                actions={actions}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Open details for Logo' }));
        const drawer = await screen.findByRole('dialog');
        expect(within(drawer).queryByRole('button', { name: 'Regenerate sizes' })).toBeNull();
        expect(within(drawer).getByText('No preview yet')).toBeInTheDocument();
    });
});

describe('silme etki önizlemesi (FAZ5-DELETE-IMPACT-01)', () => {
    it('kullanılmayan görsel doğrudan çöpe gider — diyalog yok', async () => {
        const user = userEvent.setup();
        const onDelete = vi.fn();
        render(
            <MediaLibraryRegion
                assets={ASSETS}
                onDelete={onDelete}
                loadState="idle"
                actions={actionsStub()}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Delete Lahmacun' }));

        expect(onDelete).toHaveBeenCalledWith(2);
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('kullanılan görselde önce nerede kullanıldığı gösterilir; onayda bağlar koparılıp çöpe atılır', async () => {
        const user = userEvent.setup();
        const onDelete = vi.fn();
        const actions = actionsStub();
        render(
            <MediaLibraryRegion
                assets={ASSETS}
                onDelete={onDelete}
                loadState="idle"
                actions={actions}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Delete Adana kebap' }));

        const dialog = await screen.findByRole('dialog');
        expect(await within(dialog).findByText('Adana Kebap')).toBeInTheDocument();
        expect(onDelete).not.toHaveBeenCalled();

        await user.click(
            within(dialog).getByRole('button', { name: 'Detach from 2 and move to trash' }),
        );

        expect(actions.detach).toHaveBeenCalledWith(1);
        expect(onDelete).toHaveBeenCalledWith(1);
    });

    it('yayındaki menüde kullanılan görsel silinemez — diyalog bunu söyler', async () => {
        const user = userEvent.setup();
        const onDelete = vi.fn();
        const actions = actionsStub({
            loadUsages: vi.fn(async () => [
                {
                    entityType: 'menu_item',
                    entityId: 7,
                    slot: 'itemImage',
                    label: 'Adana Kebap',
                    published: true,
                },
            ]),
        });
        render(
            <MediaLibraryRegion
                assets={ASSETS}
                onDelete={onDelete}
                loadState="idle"
                actions={actions}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Delete Adana kebap' }));
        const dialog = await screen.findByRole('dialog');

        expect(await within(dialog).findByRole('alert')).toHaveTextContent(/live menu/);
        await user.click(
            within(dialog).getByRole('button', { name: 'Detach from 0 and move to trash' }),
        );
        expect(actions.detach).not.toHaveBeenCalled();
        expect(onDelete).not.toHaveBeenCalled();
    });
});

describe('çöp sekmesi (FAZ5-TRASH-RESTORE-01)', () => {
    it('sekme açılınca çöp çekilir; geri al listeyi tazeler', async () => {
        const user = userEvent.setup();
        const onRetry = vi.fn();
        const actions = actionsStub({
            loadTrash: vi.fn(async () => [
                {
                    id: 9,
                    altText: 'Eski logo',
                    slot: 'logo',
                    status: 'ready',
                    lifecycle: 'trashed',
                },
            ]),
        });
        render(
            <MediaLibraryRegion
                assets={ASSETS}
                onDelete={() => {}}
                loadState="idle"
                actions={actions}
                onRetry={onRetry}
                trashRetentionDays={30}
            />,
        );

        expect(actions.loadTrash).not.toHaveBeenCalled();
        await user.click(screen.getByRole('tab', { name: 'Trash' }));

        expect(await screen.findByText('Eski logo')).toBeInTheDocument();
        expect(screen.getByText(/stay here for 30 days/)).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Restore Eski logo' }));

        expect(actions.restoreFromTrash).toHaveBeenCalledWith(9);
        expect(await screen.findByText('Trash is empty.')).toBeInTheDocument();
        expect(onRetry).toHaveBeenCalled();
    });
});
