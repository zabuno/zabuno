import { type ReactNode } from 'react';
import { Images, MagnifyingGlass, Queue, UploadSimple, X } from '@phosphor-icons/react';
import { t } from '../../../../i18n/workspace';

export type MediaManagerSection = {
    key: string;
    label: string;
    /** Bölüm ikonu — DEKORATİFTİR, adı ikon değil etiket taşır. */
    icon: ReactNode;
    content: ReactNode;
};

type MediaManagerShellProps = {
    title: string;
    sections: MediaManagerSection[];
    activeKey: string;
    onSelect: (key: string) => void;
    query: string;
    onQueryChange: (value: string) => void;
    /** Başlıktaki "Yükle" düğmesinin götürdüğü bölüm; yoksa düğme çizilmez. */
    uploadKey?: string;
    /** Kuyruktaki iş sayısı — GERÇEK sayı yoksa rozet hiç çizilmez. */
    queueCount?: number;
    /** Kuyruk rozetinin götürdüğü bölüm. */
    queueKey?: string;
    /** Sol şerit (klasörler + depolama); verilmezse yan sütun yoktur. */
    rail?: ReactNode;
};

/**
 * MEDYA YÖNETİCİSİNİN KABUĞU (kanonik kaynak: `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html`, ekran etiketi "Medya yönetimi"; gerekçe
 * `docs/108` §1).
 *
 * Medya, Ayarlar'ın yanındaki düz bir sayfa değil KENDİ UYGULAMASIDIR:
 * kendi başlığı, kendi arama alanı, kendi bölüm gezintisi ve solda klasör
 * şeridi. Ayrım keyfi değil — bir menüyü yönetmekle bir dosya deposunu
 * yönetmek farklı işlerdir: birinde ürün ve fiyat, diğerinde biçim, boyut,
 * sürüm, kota ve kuyruk vardır. Aynı sayfaya sıkıştırıldığında ikisi de
 * yarım kalıyordu.
 *
 * Kabuk BOŞ BİR ÇERÇEVEDİR: veri çekmez, hangi bölümlerin var olduğunu
 * bilmez. Kaynak dokuz bölüm gösteriyor; depoda bugün ikisi gerçek. Kabuk
 * yalnız kendisine VERİLEN bölümleri çizer — var olmayan bir bölüme giden
 * bir sekme, kullanıcıyı boş bir odaya sokar.
 */
export function MediaManagerShell({
    title,
    sections,
    activeKey,
    onSelect,
    query,
    onQueryChange,
    uploadKey,
    queueCount,
    queueKey,
    rail,
}: MediaManagerShellProps) {
    const active = sections.find((section) => section.key === activeKey) ?? sections[0];

    return (
        <div data-testid="media-manager-shell" className="flex flex-col gap-[var(--space-4)]">
            <header className="flex flex-col gap-[var(--space-2)] border-b border-border pb-[var(--space-3)]">
                <div className="flex flex-wrap items-center gap-[var(--space-2)]">
                    <span className="grid size-[2rem] place-items-center rounded-[var(--radius-md)] bg-action text-action-fg">
                        <Images aria-hidden="true" size={18} weight="fill" />
                    </span>
                    {/*
                        Ad bir BAŞLIKTIR: ekran okuyucu kullanan biri sayfada
                        nerede olduğunu başlık listesinden bulur. Kalın ama
                        büyük harfe çevrilmez — büyük harf okuma hızını
                        düşürür ve marka sesi vermez.
                    */}
                    <h2 className="min-w-0 flex-1 truncate text-subsection font-bold text-fg">
                        {title}
                    </h2>

                    {/*
                        Kuyruk rozeti kaynakta "2" yazıyor. Bizde kuyruğu
                        sayacak bir yer HENÜZ YOK; uydurulmuş bir sıfır,
                        sahibe "kuyruk boş" diye yanlış bilgi verir ve iş
                        takıldığında da aynı sıfırı gösterir.
                    */}
                    {queueCount !== undefined && queueKey !== undefined ? (
                        <button
                            type="button"
                            onClick={() => onSelect(queueKey)}
                            className="flex min-h-[var(--control-height)] items-center gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border px-[var(--space-3)] text-body font-medium text-fg-secondary"
                        >
                            <Queue aria-hidden="true" size={18} />
                            {t('workspace.media.shell.queue')}
                            <span className="rounded-pill bg-action px-[var(--space-2)] text-meta font-bold text-action-fg tabular-nums">
                                {String(queueCount)}
                            </span>
                        </button>
                    ) : null}

                    {uploadKey === undefined ? null : (
                        <button
                            type="button"
                            onClick={() => onSelect(uploadKey)}
                            className="flex min-h-[var(--control-height)] items-center gap-[var(--space-2)] rounded-[var(--radius-lg)] bg-action px-[var(--space-3)] text-body font-bold text-action-fg"
                        >
                            <UploadSimple aria-hidden="true" size={18} />
                            {t('workspace.media.upload.button')}
                        </button>
                    )}
                </div>

                {/*
                    ARAMA KABUĞUN İŞİDİR, bölümün değil: sahip "adana" yazıp
                    bölüm değiştirdiğinde aradığı şeyi kaybetmemeli.
                */}
                <div className="flex min-h-[var(--control-height)] items-center gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface-subtle px-[var(--space-3)] text-fg-muted">
                    <MagnifyingGlass aria-hidden="true" size={18} />
                    <input
                        type="search"
                        value={query}
                        aria-label={t('workspace.media.shell.search')}
                        placeholder={t('workspace.media.shell.search.placeholder')}
                        onChange={(event) => onQueryChange(event.target.value)}
                        className="min-w-0 flex-1 border-0 bg-transparent py-[var(--space-2)] text-body text-fg outline-none"
                    />
                    {query === '' ? null : (
                        <button
                            type="button"
                            aria-label={t('workspace.media.shell.search.clear')}
                            onClick={() => onQueryChange('')}
                            className="grid min-h-[var(--control-height)] min-w-[var(--control-height)] place-items-center rounded-[var(--radius-md)] text-fg-secondary"
                        >
                            <X aria-hidden="true" size={16} />
                        </button>
                    )}
                </div>
            </header>

            {/*
                Gezinti BAŞLIĞIN DIŞINDA: başlıktaki "Yükle" düğmesiyle
                gezintideki "Yükle" sekmesi aynı adı taşır ve iç içe
                olduklarında hangisinin nerede durduğu — hem ekran
                okuyucuda hem testte — belirsizleşir.

                Tek bölümlü bir gezinti ise hiç çizilmez: gidilecek başka
                yer olmadığını gizler ve ekranda kalıcı bir soru işareti
                bırakır.
            */}
            {sections.length > 1 ? (
                <nav
                    aria-label={t('workspace.media.shell.sections')}
                    className="flex flex-wrap gap-[var(--space-1)]"
                >
                    {sections.map((section) => {
                        const isActive = section.key === active?.key;

                        return (
                            <button
                                key={section.key}
                                type="button"
                                aria-current={isActive ? 'page' : undefined}
                                onClick={() => onSelect(section.key)}
                                className={[
                                    'flex min-h-[var(--control-height)] items-center gap-[var(--space-2)]',
                                    'rounded-[var(--radius-lg)] px-[var(--space-3)] text-body',
                                    isActive
                                        ? 'bg-surface-active font-bold text-fg'
                                        : 'font-medium text-fg-secondary',
                                ].join(' ')}
                            >
                                {section.icon}
                                {section.label}
                            </button>
                        );
                    })}
                </nav>
            ) : null}

            {/*
                Şerit ile ana sütun arasında KIRILMA NOKTASI YOK: ikisi de
                esner, sığmadığında alt alta geçer. `999` büyüme katsayısı
                geniş ekranda ana sütunu doldurur, şeridi 15rem'de bırakır.
            */}
            <div className="flex flex-wrap items-start gap-[var(--space-4)]">
                {rail ? (
                    <aside
                        data-testid="media-manager-rail"
                        /*
                            `empty:hidden`: şerit hiçbir şey çizmediğinde
                            (klasör yok, kota gelmedi) sütun tamamen kapanır
                            — yoksa geniş ekranda boş bir 15rem sütun
                            durur ve dosya ızgarasını sebepsiz daraltır.
                        */
                        className="flex min-w-0 flex-[1_1_15rem] flex-col gap-[var(--space-3)] empty:hidden"
                    >
                        {rail}
                    </aside>
                ) : null}
                <div className="flex min-w-0 flex-[999_1_min(100%,24rem)] flex-col gap-[var(--space-3)]">
                    {active?.content}
                </div>
            </div>
        </div>
    );
}

export default MediaManagerShell;
