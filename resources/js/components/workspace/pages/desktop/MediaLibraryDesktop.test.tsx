import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaLibraryDesktop } from './MediaLibraryDesktop';
import type { MediaAsset, MediaLibraryActions } from '../MediaPage';

/**
 * MASAÜSTÜ KÜTÜPHANESİ — `docs/153` §6.
 *
 * Burada donan şey KÜTÜPHANENİN VERİSİ DEĞİL: onu `MediaPage.test.tsx` ve
 * dokunmalı kütüphanenin testleri zaten donduruyor ve iki yüzey de aynı
 * `mediaLibraryQuery` süzgecini ve aynı `onDelete` yolunu kullanıyor.
 *
 * Bu dosyanın İKİ sorusu var:
 *
 * 1. Masaüstüne özgü GİRİŞ KİPİ gerçekten var mı? (oklarla gezinme, çoklu
 *    seçim, sağ tıkın klavye karşılığı, kalıcı ayrıntı bölmesi) Bunlar
 *    dokunmada yoktur; biri sessizce kaybolursa ekran yine çizilir ve kimse
 *    fark etmez — masadaki kişi yalnız işini yavaş yapar.
 * 2. Hız, GÜVENLİK KURALINI kısaltmış mı? Kullanımda olan bir dosyanın tek
 *    tuşla gitmesi, misafirin gördüğü menüyü sahibin haberi olmadan
 *    değiştirmek olurdu. Kısayol işi hızlandırır, kapıyı kaldırmaz.
 */

function asset(id: number, altText: string, extra: Partial<MediaAsset> = {}): MediaAsset {
    return {
        id,
        altText,
        slot: 'product',
        status: 'ready',
        previewUrl: `https://example.test/preview/${id}.jpg`,
        usageCount: 0,
        originalName: `${altText}.jpg`,
        sizeBytes: 120_000,
        createdAt: '2026-09-01T10:00:00Z',
        ...extra,
    };
}

function actionsStub(): MediaLibraryActions {
    return {
        loadUsages: vi.fn(async () => []),
        loadVersions: vi.fn(async () => []),
        reprocess: vi.fn(async () => undefined),
        restoreVersion: vi.fn(async () => undefined),
        detach: vi.fn(async () => undefined),
        loadTrash: vi.fn(async () => []),
        restoreFromTrash: vi.fn(async () => undefined),
        downloadOriginal: vi.fn(async () => 'https://example.test/original'),
        updateAltText: vi.fn(async () => undefined),
    };
}

function renderLibrary(
    assets: MediaAsset[],
    onDelete: (id: number) => void = () => undefined,
    withActions = true,
) {
    return render(
        <MediaLibraryDesktop
            assets={assets}
            onDelete={onDelete}
            loadState="idle"
            {...(withActions ? { actions: actionsStub() } : {})}
        />,
    );
}

function cells() {
    return within(screen.getByRole('listbox')).getAllByRole('option');
}

const THREE = [asset(11, 'Adana'), asset(12, 'Urfa'), asset(13, 'Lahmacun')];

const FIVE = [
    asset(11, 'Adana'),
    asset(12, 'Urfa'),
    asset(13, 'Lahmacun'),
    asset(14, 'Künefe'),
    asset(15, 'Ayran'),
];

/** Görünümü LİSTEYE çevirir: tek sütun, tek satırlık adım. */
async function switchToList(user: ReturnType<typeof userEvent.setup>) {
    await user.click(screen.getByRole('button', { name: 'List' }));
}

describe('MediaLibraryDesktop', () => {
    /*
        Izgara bir SEÇİLEBİLİR listedir, bir kutu yığını değil. Ekran
        okuyucunun "3 öğeden 1'i, seçili değil" diyebilmesi bu rollere
        bağlı; `div` yığınında o cümle hiç kurulmaz.
    */
    it('kütüphaneyi çoklu seçilebilir bir ızgara olarak sunar', () => {
        renderLibrary(THREE);

        const grid = screen.getByRole('listbox');

        expect(grid).toHaveAttribute('aria-multiselectable', 'true');
        expect(within(grid).getAllByRole('option')).toHaveLength(3);
    });

    /*
        ROVING TABINDEX: ızgaraya Tab ile BİR KEZ girilir. Her kutu
        odaklanabilir olsaydı, kırk dosyalık bir kütüphane kırk Tab demekti —
        ve bu ekranın asıl kullanıcısı klavyeyle çalışıyor.
    */
    it('ok tuşlarıyla kutu değiştirir ve odağı taşır', async () => {
        const user = userEvent.setup();

        renderLibrary(THREE);

        const all = cells();

        expect(all[0]).toHaveAttribute('tabindex', '0');
        expect(all[1]).toHaveAttribute('tabindex', '-1');

        all[0]?.focus();
        await user.keyboard('{ArrowRight}');

        await waitFor(() => {
            expect(document.activeElement).toBe(screen.getAllByRole('option')[1]);
        });
    });

    /*
        KALICI AYRINTI BÖLMESİ: telefonda ikinci sütun yoktur ve aynı bilgi
        çekmeceye sığar. Burada ayrı durması, kutuların tarama için KISA
        kalmasını sağlar — ve üzerinde durulan dosya değişince bölme de
        değişir, tıklamak gerekmez.
    */
    it('üzerinde durulan dosyanın ayrıntısını yan bölmede gösterir', async () => {
        const user = userEvent.setup();

        renderLibrary(THREE);

        const detail = screen.getByRole('complementary', { name: 'Selected file' });

        expect(within(detail).getByRole('heading', { name: 'Adana' })).toBeInTheDocument();

        cells()[0]?.focus();
        await user.keyboard('{ArrowRight}');

        await waitFor(() => {
            expect(
                within(screen.getByRole('complementary', { name: 'Selected file' })).getByRole(
                    'heading',
                    { name: 'Urfa' },
                ),
            ).toBeInTheDocument();
        });
    });

    /*
        ÇOKLU SEÇİM bu ekranın var oluş sebebi: otuz kullanılmayan dosyayı
        tek tek çöpe atmak, sahibin akşamıdır.
    */
    it('boşlukla seçer ve seçilenleri tek işlemde çöpe atar', async () => {
        const user = userEvent.setup();
        const deleted: number[] = [];

        renderLibrary(THREE, (id) => deleted.push(id));

        cells()[0]?.focus();
        await user.keyboard(' ');
        await user.keyboard('{ArrowRight}');
        await user.keyboard(' ');

        await waitFor(() => {
            expect(screen.getByText('2 selected')).toBeInTheDocument();
        });

        await user.click(screen.getByRole('button', { name: 'Delete selected' }));

        await waitFor(() => {
            expect(deleted).toEqual([11, 12]);
        });
    });

    /*
        SAĞ TIKIN KLAVYE KARŞILIĞI — WCAG 2.2 AA (`docs/153` §7).

        Menü yalnız fareyle açılabilseydi, oradaki eylemler klavye kullanıcısı
        için HİÇ yok olurdu. Shift+F10 her masaüstü tarayıcısında bu işi
        yapar ve sağ tıkla AYNI menüyü açar — ayrı bir "klavye menüsü", iki
        menünün zamanla ayrışması demekti.
    */
    it('Shift+F10 ile ve sağ tıkla aynı bağlam menüsünü açar', async () => {
        const user = userEvent.setup();

        renderLibrary(THREE);

        expect(screen.queryByRole('menu')).not.toBeInTheDocument();

        cells()[0]?.focus();
        await user.keyboard('{Shift>}{F10}{/Shift}');

        const menu = await screen.findByRole('menu', { name: 'File actions' });

        expect(within(menu).getAllByRole('menuitem')).toHaveLength(2);

        await user.keyboard('{Escape}');

        await waitFor(() => {
            expect(screen.queryByRole('menu')).not.toBeInTheDocument();
        });

        await user.pointer({ keys: '[MouseRight]', target: cells()[1] as Element });

        expect(await screen.findByRole('menu', { name: 'File actions' })).toBeInTheDocument();
    });

    /*
        HIZ, GÜVENLİK KURALINI KISALTMAZ (`docs/49` Faz 5 madde 2).

        Delete tuşu kullanılmayan dosyayı doğrudan çöpe atar — çöp geri
        alınabilir. Ama KULLANILAN dosyada aynı tuş silmez: önce nerede
        kullanıldığı gösterilir. Menüde duran bir fotoğrafın tek tuşla
        gitmesi, misafirin gördüğü menüyü sahibin haberi olmadan
        değiştirmek olurdu.
    */
    it('Delete tuşu kullanımdaki dosyayı silmez, etki önizlemesini açar', async () => {
        const user = userEvent.setup();
        const deleted: number[] = [];

        renderLibrary([asset(21, 'Kullanımda', { usageCount: 2 }), asset(22, 'Boşta')], (id) =>
            deleted.push(id),
        );

        cells()[0]?.focus();
        await user.keyboard('{Delete}');

        expect(await screen.findByText('Delete Kullanımda?')).toBeInTheDocument();
        expect(deleted).toEqual([]);
    });

    /*
        LİSTEDE AŞAĞI OK BİR SATIRDIR — ölçülmüş bir kusurun testi.

        Aşağı ok "bir satır aşağı" demektir ve satırın kaç kutu ettiği
        GÖRÜNÜME bağlıdır. Sütun sayısı sabitken (üç) liste görünümü de üçer
        üçer atlıyordu: sahip ikinci dosyaya inmek isterken dördüncüye
        düşüyor, aradaki iki dosyayı hiç göremiyordu. Adım artık ızgaranın
        gerçekten çizdiği sütundan okunuyor; listede o sayı birdir.
    */
    it('liste görünümünde aşağı ok tam bir satır iner', async () => {
        const user = userEvent.setup();

        renderLibrary(FIVE);
        await switchToList(user);

        cells()[0]?.focus();
        await user.keyboard('{ArrowDown}');

        await waitFor(() => {
            expect(document.activeElement).toBe(screen.getAllByRole('option')[1]);
        });

        await user.keyboard('{ArrowDown}');

        await waitFor(() => {
            expect(document.activeElement).toBe(screen.getAllByRole('option')[2]);
        });
    });

    /*
        SHIFT+AŞAĞI, ARADAKİ HER ŞEYİ DEĞİL, TAM ARALIĞI ALIR.

        Yanlış adımın en pahalı sonucu buydu: listede Shift+aşağı bir satır
        yerine üç satır atlayınca, sahibin hiç bakmadığı iki dosya da seçime
        giriyordu — ve seçimin karşılığı "seçilenleri sil" düğmesiydi.
    */
    it('liste görünümünde Shift+aşağı yalnız komşu satırı seçime katar', async () => {
        const user = userEvent.setup();

        renderLibrary(FIVE);
        await switchToList(user);

        cells()[0]?.focus();
        await user.keyboard('{Shift>}{ArrowDown}{/Shift}');

        await waitFor(() => {
            expect(screen.getByText('2 selected')).toBeInTheDocument();
        });

        const selected = cells().filter((cell) => cell.getAttribute('aria-selected') === 'true');

        expect(selected.map((cell) => cell.getAttribute('aria-label'))).toEqual(['Adana', 'Urfa']);
    });

    /*
        IZGARADA ADIM HÂLÂ BİR SATIRDIR, BİR KUTU DEĞİL.

        Liste düzeltilirken ızgaranın kendi davranışının sessizce tek kutuya
        inmediğini de dondurmak gerekiyor; yoksa masadaki kişi kırk dosyalık
        bir ızgarada satır satır değil kutu kutu ilerlerdi. Düzen motoru
        olmayan bu ortamda ölçüm boş döner ve ızgara belgelenmiş
        varsayılanına (üç sütun) düşer — gerçek genişliklerdeki sayı
        tarayıcıda ölçülür.
    */
    it('ızgara görünümünde aşağı ok bir kutu değil bir satır iner', async () => {
        const user = userEvent.setup();

        renderLibrary(FIVE);

        cells()[0]?.focus();
        await user.keyboard('{ArrowDown}');

        await waitFor(() => {
            expect(document.activeElement).toBe(screen.getAllByRole('option')[3]);
        });
    });

    /*
        Hazır bir türevi olmayan dosyanın herkese açık adresi YOKTUR
        (MEDIA-INTAKE-NO-PUBLIC-URL-01) — bu bir gecikme değil, bir güvenlik
        kararıdır. Uydurma bir görsel çizmek, taranmamış bir dosyayı
        taranmış gibi gösterirdi; ekran onun yerine DURUMU ve erişim
        kısıtını yazar.
    */
    it('taranmayı bekleyen dosyaya görsel çizmez, kısıtı yazar', () => {
        renderLibrary([
            asset(31, 'Karantinada', { status: 'quarantined', previewUrl: null }),
            asset(32, 'Boşta'),
        ]);

        const quarantined = cells()[0] as HTMLElement;

        expect(within(quarantined).queryByRole('img')).not.toBeInTheDocument();
        expect(within(quarantined).getByText('Scan pending (quarantined)')).toBeInTheDocument();
        expect(
            within(quarantined).getAllByText('Not publicly available yet').length,
        ).toBeGreaterThan(0);
    });
});
