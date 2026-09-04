import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaManagerShell } from './MediaManagerShell';

/**
 * MEDYA KENDİ KABUĞU OLAN BİR UYGULAMADIR (kanonik kaynak:
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, ekran etiketi
 * "Medya yönetimi"; gerekçe `docs/108` §1).
 *
 * Depoda medya bugüne kadar Ayarlar'ın yanında DÜZ BİR SAYFAYDI: solda
 * yükleme kartı, sağda kütüphane. Bir menüyü yönetmekle bir dosya deposunu
 * yönetmek aynı iş değildir — birinde ürün ve fiyat, diğerinde biçim,
 * boyut, sürüm, kota ve kuyruk vardır. İkisi tek sayfaya sıkışınca ikisi de
 * yarım kalıyordu.
 *
 * Kabuğun taşıdığı şey bu yüzden bir süs değil: kendi başlığı, kendi arama
 * alanı, bölüm gezintisi ve solda klasör şeridi. Bu dosya kabuğun
 * SÖZLEŞMESİNİ çiviler — özellikle de DÜRÜSTLÜK tarafını: kabuk, verisi
 * olmayan hiçbir kontrolü çizmez.
 */

function Sections() {
    return null;
}

const LIBRARY = {
    key: 'library',
    label: 'Library',
    icon: <span aria-hidden="true" data-testid="library-icon" />,
    content: <p>library content</p>,
};

const UPLOAD = {
    key: 'upload',
    label: 'Upload',
    icon: <span aria-hidden="true" data-testid="upload-icon" />,
    content: <p>upload content</p>,
};

type ShellOverrides = Partial<React.ComponentProps<typeof MediaManagerShell>>;

function mount(overrides: ShellOverrides = {}) {
    const onSelect = overrides.onSelect ?? vi.fn();
    const onQueryChange = overrides.onQueryChange ?? vi.fn();

    render(
        <MediaManagerShell
            title="Media"
            sections={[LIBRARY, UPLOAD]}
            activeKey="library"
            onSelect={onSelect}
            query=""
            onQueryChange={onQueryChange}
            uploadKey="upload"
            {...overrides}
        />,
    );

    return { onSelect, onQueryChange };
}

describe('MediaManagerShell — kendi başlığı', () => {
    it('uygulamanın adı bir BAŞLIKTIR ve ikon dekoratiftir', () => {
        mount();

        const heading = screen.getByRole('heading', { name: 'Media' });

        expect(heading).toHaveClass('font-bold');
        // İkon bilgi taşımaz; ekran okuyucuya "resim resim resim" diye
        // okunması gezintiyi yavaşlatmaktan başka iş görmez.
        expect(heading.closest('header')?.querySelector('svg')).toHaveAttribute(
            'aria-hidden',
            'true',
        );
    });

    it('Yükle düğmesi gerçek bir bölüme götürür; bölüm yoksa düğme ÇİZİLMEZ', async () => {
        const user = userEvent.setup();
        const { onSelect } = mount();

        const header = screen.getByRole('heading', { name: 'Media' }).closest('header');
        const uploadButton = within(header as HTMLElement).getByRole('button', { name: 'Upload' });

        expect(uploadButton).toHaveClass('min-h-[var(--control-height)]');
        await user.click(uploadButton);
        expect(onSelect).toHaveBeenCalledWith('upload');
    });

    it('kuyruk rozeti YALNIZ gerçek bir sayı geldiğinde çizilir', () => {
        /*
            Kanonik kaynakta başlıkta "2" yazan bir kuyruk rozeti var. Bizde
            kuyruk sayısını verecek bir yer henüz YOK. Uydurulmuş bir "0" ya
            da hep sıfır gösteren bir rozet, sahibe "kuyruk boş" diye YANLIŞ
            bilgi verir; bir işi takıldığında da aynı sıfırı gösterir.
        */
        mount();

        expect(screen.queryByRole('button', { name: /queue/i })).toBeNull();
    });
});

describe('MediaManagerShell — kendi arama alanı', () => {
    it('yazılan metin dışarı bildirilir', async () => {
        const user = userEvent.setup();
        const { onQueryChange } = mount();

        await user.type(screen.getByRole('searchbox', { name: 'Search media' }), 'k');

        expect(onQueryChange).toHaveBeenCalledWith('k');
    });

    it('temizle düğmesi yalnız arama DOLUYKEN vardır', async () => {
        const user = userEvent.setup();
        mount({ query: '' });

        expect(screen.queryByRole('button', { name: 'Clear search' })).toBeNull();

        const onQueryChange = vi.fn();
        mount({ query: 'kebap', onQueryChange });

        await user.click(screen.getAllByRole('button', { name: 'Clear search' })[0]);
        expect(onQueryChange).toHaveBeenCalledWith('');
    });
});

describe('MediaManagerShell — bölüm gezintisi', () => {
    it('yalnız AKTİF bölümün içeriği çizilir', () => {
        mount();

        expect(screen.getByText('library content')).toBeInTheDocument();
        expect(screen.queryByText('upload content')).toBeNull();
    });

    it('aktif bölüm aria-current taşır ve tıklama bölümü değiştirir', async () => {
        const user = userEvent.setup();
        const { onSelect } = mount();

        const nav = screen.getByRole('navigation', { name: 'Media sections' });
        const library = within(nav).getByRole('button', { name: 'Library' });
        const upload = within(nav).getByRole('button', { name: 'Upload' });

        expect(library).toHaveAttribute('aria-current', 'page');
        expect(upload).not.toHaveAttribute('aria-current');

        await user.click(upload);
        expect(onSelect).toHaveBeenCalledWith('upload');
    });

    it('TEK bölüm varsa gezinti çizilmez — gidilecek başka yer yok', () => {
        mount({ sections: [LIBRARY], uploadKey: undefined });

        expect(screen.queryByRole('navigation', { name: 'Media sections' })).toBeNull();
    });
});

describe('MediaManagerShell — klasör şeridi ve ölçek disiplini', () => {
    it('şerit verilmezse yan sütun hiç çizilmez', () => {
        mount();

        expect(screen.queryByTestId('media-manager-rail')).toBeNull();
    });

    it('şerit verilirse yan sütunda durur', () => {
        mount({ rail: <p>rail content</p> });

        const rail = screen.getByTestId('media-manager-rail');
        expect(within(rail).getByText('rail content')).toBeInTheDocument();
    });

    it('hiçbir yerde 600 ağırlık, büyük harf, rounded-full ya da sabit piksel yoktur', () => {
        mount({ rail: <p>rail content</p> });

        const shell = screen.getByTestId('media-manager-shell');
        const classLists: string[] = [shell.className];
        shell.querySelectorAll<HTMLElement>('*').forEach((element) => {
            if (typeof element.className === 'string') classLists.push(element.className);
        });

        expect(classLists.filter((list) => /font-semibold/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /uppercase/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /rounded-full/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /\[\d+px\]/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /(^|\s)(sm|md|lg|xl|2xl):/.test(list))).toEqual([]);
    });
});

// Kullanılmayan yardımcıyı bırakmayalım.
void Sections;
