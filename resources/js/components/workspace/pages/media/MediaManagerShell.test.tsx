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

const QUEUE = {
    key: 'queue',
    label: 'Queue',
    icon: <span aria-hidden="true" data-testid="queue-icon" />,
    content: <p>queue content</p>,
};

const GOVERNANCE = {
    key: 'governance',
    label: 'Governance',
    icon: <span aria-hidden="true" data-testid="governance-icon" />,
    content: <p>governance content</p>,
};

const MATURITY = {
    key: 'maturity',
    label: 'Maturity',
    icon: <span aria-hidden="true" data-testid="maturity-icon" />,
    content: <p>maturity content</p>,
};

const ALL = [LIBRARY, UPLOAD, QUEUE, GOVERNANCE, MATURITY];

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

describe('MediaManagerShell — günlük iş öne çıkar', () => {
    /*
        Kabuğa on bir bölüme kadar veriliyor; sahibin GÜNLÜK işi üçünde
        geçer: dosyaya bakmak, dosya eklemek, eklediği işin ne olduğunu
        görmek. On bir sekme yan yana yazıldığında bu fark kayboluyordu —
        hepsi aynı boyda, telefonda üç satıra sarılı, her seferinde baştan
        okunan bir liste.
    */
    it('günlük üçlü öne çıkar, geri kalanı açılır bölümde durur — ama HEPSİ erişilebilir', async () => {
        const user = userEvent.setup();
        const { onSelect } = mount({ sections: ALL });

        const nav = screen.getByRole('navigation', { name: 'Media sections' });
        const disclosure = nav.querySelector('details') as HTMLDetailsElement;

        // Öne çıkanlar kapağın DIŞINDA: sahip onlara bir tık bile harcamaz.
        ['Library', 'Upload', 'Queue'].forEach((label) => {
            const tab = within(nav).getByRole('button', { name: label });

            expect(disclosure.contains(tab)).toBe(false);
            expect(tab).toHaveClass('px-[var(--space-4)]');
        });

        // Geri kalanı GİZLİ DEĞİL, bir tık uzakta — ve gittiği yer gerçek.
        const governance = within(disclosure).getByRole('button', { name: 'Governance' });
        expect(within(disclosure).getByRole('button', { name: 'Maturity' })).toBeInTheDocument();

        await user.click(governance);
        expect(onSelect).toHaveBeenCalledWith('governance');
    });

    it('aktif bölüm kapağın ARDINDAYSA kapak açık başlar', () => {
        /*
            Kapalı bir kapak, ekranda hiçbir yerde işaretli sekme bırakmaz:
            içerik görünür ama sahip nerede olduğunu göremez. "Yönetişim"i
            açan biri, geri dönerken hangi kutudan çıktığını görmelidir.
        */
        mount({ sections: ALL, activeKey: 'governance' });

        const nav = screen.getByRole('navigation', { name: 'Media sections' });
        const disclosure = nav.querySelector('details') as HTMLDetailsElement;

        expect(disclosure.open).toBe(true);
        expect(within(disclosure).getByRole('button', { name: 'Governance' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('günlük bölüm aktifken kapak kapalı başlar ve gezinti düzse hiç çizilmez', () => {
        mount({ sections: ALL });

        const nav = screen.getByRole('navigation', { name: 'Media sections' });
        expect((nav.querySelector('details') as HTMLDetailsElement).open).toBe(false);

        // Yalnız günlük bölümler verildiğinde saklanacak bir şey yoktur.
        mount({ sections: [LIBRARY, UPLOAD, QUEUE] });
        expect(
            screen
                .getAllByRole('navigation', { name: 'Media sections' })[1]
                .querySelector('details'),
        ).toBeNull();
    });
});

describe('MediaManagerShell — ana sütun önce', () => {
    it('şerit (klasör + kota) ana içerikten SONRA gelir', () => {
        /*
            Şerit belgede önde dururken telefonda sahip her açılışta önce
            "ne kadar yerim kaldı" tablosunu geçip sonra dosyalarına
            ulaşıyordu; oysa geldiği iş dosyalardı. Kota bir CEVAPTIR, bir
            kapı değil — ve bu sıra ekran okuyucuda da aynen okunur.
        */
        mount({ rail: <p>rail content</p> });

        const content = screen.getByText('library content');
        const rail = screen.getByTestId('media-manager-rail');

        expect(
            content.compareDocumentPosition(rail) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });
});

// Kullanılmayan yardımcıyı bırakmayalım.
void Sections;
