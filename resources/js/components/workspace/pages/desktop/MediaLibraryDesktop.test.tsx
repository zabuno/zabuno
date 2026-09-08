import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaLibraryDesktop } from './MediaLibraryDesktop';
import type { MediaAsset } from '../MediaPage';

/**
 * MEDYA KÜTÜPHANESİ — MASAÜSTÜ (`docs/151` §B).
 *
 * Burada donan şey KÜTÜPHANENİN VERİSİ DEĞİL: onu dokunmalı bölgenin
 * testleri zaten donduruyor ve iki yüzey aynı `MediaPage` okumasını, aynı
 * `selectVisibleAssets` süzgecini kullanıyor.
 *
 * Bu dosyanın sorusu tek: **masaüstüne özgü GİRİŞ KİPİ gerçekten var mı?**
 * Klavyeyle gezinme, çoklu seçim, toplu silme ve sağ tıkın KLAVYE
 * KARŞILIĞI. Bunlar dokunmada yoktur; biri sessizce kaybolursa ekran yine
 * çizilir ve kimse fark etmez — sahip yalnız işini yavaş yapar.
 */

function asset(id: number, altText: string, extra: Partial<MediaAsset> = {}): MediaAsset {
    return {
        id,
        altText,
        slot: 'product',
        status: 'ready',
        usageCount: 0,
        sizeBytes: 120_000,
        /*
            Varsayılan sıralama "en yeni"dir; kurgu bu yüzden id sırasını
            KORUYACAK biçimde tarihlenir. Aksi hâlde test, ölçmek istediği
            klavye davranışını değil sıralamayı ölçerdi.
        */
        createdAt: `2026-09-0${String(9 - id)}T10:00:00Z`,
        originalName: `${altText}.jpg`,
        ...extra,
    };
}

function renderLibrary(overrides: Partial<Parameters<typeof MediaLibraryDesktop>[0]> = {}) {
    const onDelete = vi.fn();

    const result = render(
        <MediaLibraryDesktop
            assets={[asset(1, 'Adana'), asset(2, 'Baklava'), asset(3, 'Cacik')]}
            onDelete={onDelete}
            loadState="idle"
            {...overrides}
        />,
    );

    return { ...result, onDelete };
}

async function tiles() {
    return within(await screen.findByRole('listbox')).findAllByRole('option');
}

describe('MediaLibraryDesktop', () => {
    it('ızgaraya bir kez Tab ile girilir ve içinde oklarla gezilir', async () => {
        const user = userEvent.setup();
        renderLibrary();

        const options = await tiles();

        // ROVING TABINDEX: yalnız etkin kutu odaklanabilir. Elli dosyalık bir
        // kütüphanede her kutu odaklanabilir olsaydı listeyi geçmek elli Tab
        // demekti.
        expect(options[0]).toHaveAttribute('tabindex', '0');
        expect(options[1]).toHaveAttribute('tabindex', '-1');

        options[0]?.focus();
        await user.keyboard('{ArrowRight}');

        expect(options[1]).toHaveFocus();
    });

    it('Boşluk seçer, Shift+ok aralık büyütür, Ctrl+A hepsini seçer', async () => {
        const user = userEvent.setup();
        renderLibrary();

        const options = await tiles();
        options[0]?.focus();

        await user.keyboard(' ');
        expect(options[0]).toHaveAttribute('aria-selected', 'true');

        await user.keyboard('{Shift>}{ArrowRight}{/Shift}');
        expect(options[1]).toHaveAttribute('aria-selected', 'true');

        await user.keyboard('{Control>}a{/Control}');
        for (const option of options) {
            expect(option).toHaveAttribute('aria-selected', 'true');
        }
    });

    it('toplu silme seçilen her dosya için tek bir silme yolu çağırır', async () => {
        const user = userEvent.setup();
        const { onDelete } = renderLibrary();

        const options = await tiles();
        options[0]?.focus();
        await user.keyboard('{Control>}a{/Control}');

        await user.click(screen.getByRole('button', { name: 'Delete selected' }));

        expect(onDelete.mock.calls.map((call) => call[0])).toEqual([1, 2, 3]);
    });

    it('kullanımdaki dosya toplu silmede ATLANIR ve atlandığı yazılır', async () => {
        const user = userEvent.setup();
        const { onDelete } = renderLibrary({
            assets: [asset(1, 'Adana'), asset(2, 'Baklava', { usageCount: 2 })],
        });

        const options = await tiles();
        options[0]?.focus();
        await user.keyboard('{Control>}a{/Control}');
        await user.click(screen.getByRole('button', { name: 'Delete selected' }));

        // Menüde duran fotoğraf GİTMEZ; ve sessizce atlanmaz, çünkü sahip
        // onu da silinmiş sanardı.
        expect(onDelete.mock.calls.map((call) => call[0])).toEqual([1]);
        expect(
            screen.getByText(/1 in use were kept/, { selector: '[role="status"]' }),
        ).toBeInTheDocument();
    });

    it('sağ tıkın klavye karşılığı vardır (Shift+F10) ve Escape satıra döner', async () => {
        const user = userEvent.setup();
        renderLibrary();

        const options = await tiles();
        options[0]?.focus();

        // WCAG 2.2 AA: fareyle ulaşılan her eylemin klavye yolu olmalı.
        await user.keyboard('{Shift>}{F10}{/Shift}');

        const menu = await screen.findByRole('menu', { name: 'File actions' });
        expect(within(menu).getByRole('menuitem')).toBeInTheDocument();

        await user.keyboard('{Escape}');
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
        expect(options[0]).toHaveFocus();
    });

    it('kalıcı ayrıntı bölmesi seçili dosyayı gösterir ve seçimle değişir', async () => {
        const user = userEvent.setup();
        renderLibrary();

        const pane = screen.getByRole('complementary', { name: 'Selected file' });
        const options = await tiles();

        // Telefonda ikinci sütun YOKTUR ve aynı bilgi bir çekmecededir.
        expect(within(pane).getByRole('heading')).toHaveTextContent('Adana');

        options[0]?.focus();
        await user.keyboard('{ArrowRight}');

        expect(within(pane).getByRole('heading')).toHaveTextContent('Baklava');
    });

    it('Delete tuşu üzerinde durulan dosyayı siler — fare olmadan da', async () => {
        const user = userEvent.setup();
        const { onDelete } = renderLibrary();

        const options = await tiles();
        options[0]?.focus();
        await user.keyboard('{Delete}');

        expect(onDelete).toHaveBeenCalledWith(1);
    });
});
