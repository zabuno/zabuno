import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaFolderRail, type MediaFolder } from './MediaFolderRail';

/**
 * KLASÖR ŞERİDİ — kanonik kaynağın sol sütunu ("Klasörler" + "Depolama").
 *
 * `docs/108` §3 madde 1'in gerekçesi: bugün elli fotoğraf tek düz listede
 * duruyor ve arama, ADINI HATIRLAMADIĞIN dosyayı bulmaz. Klasör, "geçen
 * yazki kampanya görselleri" diye hatırlanan şeyin tek adresidir.
 *
 * Şeridin en sert kuralı DÜRÜSTLÜKTÜR: klasör ucu bu depoda HENÜZ YOK
 * (başka bir ajan yazıyor). Uç yokken şeritte uydurma bir "Genel" klasörü
 * göstermek, sahibi olmayan bir yere tıklatmaktır — bu yüzden veri yoksa
 * şerit HİÇ çizilmez.
 */

const FOLDERS: MediaFolder[] = [
    { id: 1, name: 'Kampanyalar', assetCount: 12 },
    { id: 2, name: 'Menü', assetCount: 3 },
];

function manyFolders(count: number): MediaFolder[] {
    // Sayaç bilerek YOK: sunucu vermediğinde satırda hiç yazmaz ve
    // erişilebilir ad yalnız klasör adından oluşur.
    return Array.from({ length: count }, (_, index) => ({
        id: index + 1,
        name: `Klasör ${index + 1}`,
    }));
}

describe('MediaFolderRail — veri yoksa şerit yok', () => {
    it('klasör de ek içerik de yoksa hiçbir şey çizilmez', () => {
        const { container } = render(
            <MediaFolderRail folders={[]} activeFolderId={null} onSelect={vi.fn()} />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    it('klasör yokken bile depolama kutusu varsa şerit çizilir', () => {
        render(
            <MediaFolderRail folders={[]} activeFolderId={null} onSelect={vi.fn()}>
                <p>storage</p>
            </MediaFolderRail>,
        );

        expect(screen.getByText('storage')).toBeInTheDocument();
        // Başlık, altında klasör olmadığı hâlde yazılmaz.
        expect(screen.queryByText('Folders')).toBeNull();
    });
});

describe('MediaFolderRail — klasörler', () => {
    it('her klasör adı ve sayısı ile durur; sayı tabular-nums', async () => {
        const user = userEvent.setup();
        const onSelect = vi.fn();

        render(<MediaFolderRail folders={FOLDERS} activeFolderId={null} onSelect={onSelect} />);

        const list = screen.getByRole('list', { name: 'Folders' });
        const kampanyalar = within(list).getByRole('button', { name: /Kampanyalar/ });

        expect(within(kampanyalar).getByText('12')).toHaveClass('tabular-nums');
        expect(kampanyalar).toHaveClass('min-h-[var(--density-row-height)]');

        await user.click(kampanyalar);
        expect(onSelect).toHaveBeenCalledWith(1);
    });

    it('seçili klasör aria-current taşır; "Tüm dosyalar" seçimi kaldırır', async () => {
        const user = userEvent.setup();
        const onSelect = vi.fn();

        render(<MediaFolderRail folders={FOLDERS} activeFolderId={2} onSelect={onSelect} />);

        const list = screen.getByRole('list', { name: 'Folders' });
        expect(within(list).getByRole('button', { name: /Menü/ })).toHaveAttribute(
            'aria-current',
            'true',
        );

        await user.click(within(list).getByRole('button', { name: 'All files' }));
        expect(onSelect).toHaveBeenCalledWith(null);
    });

    it('klasörler sayfalanır: sığdığında sayfa kontrolü YOKTUR', () => {
        render(
            <MediaFolderRail folders={manyFolders(6)} activeFolderId={null} onSelect={vi.fn()} />,
        );

        expect(screen.queryByRole('button', { name: 'More folders' })).toBeNull();
    });

    it('klasörler sayfalanır: taşınca sonraki sayfa açılır', async () => {
        const user = userEvent.setup();

        render(
            <MediaFolderRail folders={manyFolders(14)} activeFolderId={null} onSelect={vi.fn()} />,
        );

        expect(screen.getByRole('button', { name: /Klasör 1\b/ })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Klasör 14\b/ })).toBeNull();
        expect(screen.getByText('Page 1 of 2')).toHaveClass('tabular-nums');

        await user.click(screen.getByRole('button', { name: 'More folders' }));

        expect(screen.getByRole('button', { name: /Klasör 14\b/ })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Klasör 1\b/ })).toBeNull();
    });
});
