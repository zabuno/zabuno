import { useState, type ReactNode } from 'react';
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
    /** İkincil şerit (klasörler + depolama); verilmezse yan sütun yoktur. */
    rail?: ReactNode;
};

/**
 * GÜNLÜK ÜÇLÜ. Kabuğa on bir bölüm verilebiliyor; sahibin GÜNLÜK işi bunların
 * üçünde geçer: dosyaya bakmak (kütüphane), dosya eklemek (yükle) ve eklediği
 * işin ne olduğunu görmek (kuyruk). Boyut motoru, dönüştürme, yönetişim ya da
 * olgunluk günde bir kez bile açılmaz — bir şey ters gittiğinde ya da bir soru
 * sorulduğunda açılır.
 *
 * On bir sekme yan yana yazıldığında bu fark KAYBOLUYORDU: hepsi aynı boyda,
 * aynı ağırlıkta, telefonda üç satıra sarılıyor ve sahip her seferinde
 * "kütüphane neredeydi" diye listeyi baştan okuyordu. Sık kullanılanı öne
 * almak bir süs değil, aramayı ortadan kaldırmaktır.
 *
 * Anahtarlar burada yazılı, çünkü hangi üçünün günlük olduğu bir VERİ değil bir
 * ÜRÜN kararıdır ve `MediaPage` bunu her çağrıda tekrar bildirseydi karar iki
 * yere dağılırdı. Tanınmayan anahtar sessizce ikincil olur; kabuk hiçbir bölümü
 * yutmaz.
 */
const DAILY_SECTION_KEYS: ReadonlySet<string> = new Set(['library', 'upload', 'queue']);

/**
 * MEDYA YÖNETİCİSİNİN KABUĞU (kanonik kaynak: `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html`, ekran etiketi "Medya yönetimi"; gerekçe
 * `docs/108` §1).
 *
 * Medya, Ayarlar'ın yanındaki düz bir sayfa değil KENDİ UYGULAMASIDIR:
 * kendi başlığı, kendi arama alanı, kendi bölüm gezintisi ve ikincil bir
 * klasör şeridi. Ayrım keyfi değil — bir menüyü yönetmekle bir dosya deposunu
 * yönetmek farklı işlerdir: birinde ürün ve fiyat, diğerinde biçim, boyut,
 * sürüm, kota ve kuyruk vardır. Aynı sayfaya sıkıştırıldığında ikisi de
 * yarım kalıyordu.
 *
 * Kabuk hangi bölümlerin GERÇEK olduğunu bilmez: yalnız kendisine VERİLEN
 * bölümleri çizer — var olmayan bir bölüme giden bir sekme, kullanıcıyı boş
 * bir odaya sokar. Bildiği tek şey, verilenler arasında hangilerinin günlük
 * iş olduğudur (bkz. `DAILY_SECTION_KEYS`); geri kalanı yerli bir `<details>`
 * içinde durur — GİZLİ DEĞİL, bir tık uzakta ve klavyeyle gezilebilir.
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

    const daily = sections.filter((section) => DAILY_SECTION_KEYS.has(section.key));
    /*
        AÇILIR BÖLÜM YALNIZ İKİSİ DE DOLUYKEN VARDIR. Günlük hiçbir bölüm
        verilmemişse (kiracı adresi yokken, ya da bambaşka anahtarlarla) her
        şeyi "Daha fazla"nın arkasına koymak, ekranı tek bir kapalı satıra
        indirirdi — o durumda hiyerarşi diye bir şey yoktur, gezinti düzdür.
    */
    const hasDisclosure = daily.length > 0 && daily.length < sections.length;
    const primary = hasDisclosure ? daily : sections;
    const secondary = hasDisclosure
        ? sections.filter((section) => !DAILY_SECTION_KEYS.has(section.key))
        : [];

    const activeIsSecondary = secondary.some((section) => section.key === active?.key);

    /*
        AÇIK/KAPALI SAHİBİNDİR, ama aktif bölüm asla saklanmaz. Bölüm
        "Daha fazla"nın içindeyken kapalı bir kapak, ekranda hiçbir yerde
        işaretli sekme bırakmaz: içerik görünür, sahip nerede olduğunu
        göremez.

        Bu yüzden kapak yalnız GEZİNTİ ANINDA açılır (aktif anahtar
        değiştiğinde), sonra kararı sahip verir — açık bir kapağı sahip
        kapatabilir ve bir sonraki tıklamaya kadar kapalı kalır. Kalıcılık
        yok: kabuk hiçbir tercihi kaydetmez, sayfa yenilendiğinde aktif
        bölüm neredeyse ona göre başlar.
    */
    const [moreOpen, setMoreOpen] = useState(activeIsSecondary);
    const [seenKey, setSeenKey] = useState(activeKey);

    if (seenKey !== activeKey) {
        setSeenKey(activeKey);

        if (activeIsSecondary) {
            setMoreOpen(true);
        }
    }

    function renderTab(section: MediaManagerSection, prominent: boolean) {
        const isActive = section.key === active?.key;

        return (
            <button
                key={section.key}
                type="button"
                aria-current={isActive ? 'page' : undefined}
                onClick={() => onSelect(section.key)}
                className={[
                    'flex min-h-[var(--control-height)] items-center gap-[var(--space-2)]',
                    prominent
                        ? 'rounded-[var(--radius-lg)] border px-[var(--space-4)] text-body'
                        : 'rounded-[var(--radius-md)] px-[var(--space-3)] text-body',
                    isActive
                        ? 'bg-surface-active font-bold text-fg'
                        : 'font-medium text-fg-secondary',
                    prominent && isActive ? 'border-border-strong' : '',
                    prominent && !isActive ? 'border-border bg-surface' : '',
                ]
                    .filter((part) => part !== '')
                    .join(' ')}
            >
                {section.icon}
                {section.label}
            </button>
        );
    }

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
                    className="flex flex-col gap-[var(--space-2)]"
                >
                    <div className="flex flex-wrap gap-[var(--space-2)]">
                        {primary.map((section) => renderTab(section, true))}
                    </div>

                    {secondary.length === 0 ? null : (
                        /*
                            YERLİ `<details>`, taklit bir açılır menü değil:
                            klavye, ekran okuyucu, sayfa içi arama ve
                            "yazdır" onu bedava doğru çalıştırır. Bir
                            düğme + durum ile yeniden yazılan her açılır
                            menü bu dördünü tek tek geri kazanmak zorunda
                            kalır ve genelde kazanamaz.

                            Sayı UYDURMA DEĞİLDİR: kapağın arkasındaki
                            gerçek bölüm sayısıdır. "Daha fazla" tek başına
                            kaç şey sakladığını söylemez; iki bölümle on
                            bölüm aynı görünür.
                        */
                        <details
                            open={moreOpen}
                            onToggle={(event) => setMoreOpen(event.currentTarget.open)}
                            className="rounded-[var(--radius-lg)] border border-border"
                        >
                            <summary className="flex min-h-[var(--control-height)] cursor-pointer items-center gap-[var(--space-2)] px-[var(--space-3)] text-body font-medium text-fg-secondary">
                                {t('workspace.shell.nav.more')}
                                <span className="rounded-pill border border-border px-[var(--space-2)] text-meta text-fg-muted tabular-nums">
                                    {String(secondary.length)}
                                </span>
                            </summary>

                            <div className="flex flex-wrap gap-[var(--space-1)] px-[var(--space-3)] pb-[var(--space-3)]">
                                {secondary.map((section) => renderTab(section, false))}
                            </div>
                        </details>
                    )}
                </nav>
            ) : null}

            {/*
                ANA SÜTUN ÖNCE GELİR — hem belgede hem ekranda. Şerit
                (klasörler + kota) telefonda yukarıda dururken, sahip her
                açılışta önce "ne kadar yerim kaldı" tablosunu geçip sonra
                dosyalarına ulaşıyordu; oysa geldiği iş dosyalardı. Kota bir
                CEVAPTIR, bir kapı değil.

                Şerit ile ana sütun arasında KIRILMA NOKTASI YOK: ikisi de
                esner, sığmadığında alt alta geçer. `999` büyüme katsayısı
                geniş ekranda ana sütunu doldurur, şeridi 15rem'de bırakır —
                yani geniş ekranda ana sütun ferah, şerit ikincil kalır;
                dar ekranda ana sütun tek başına en üsttedir.
            */}
            <div className="flex flex-wrap items-start gap-[var(--space-5)]">
                <div className="flex min-w-0 flex-[999_1_min(100%,24rem)] flex-col gap-[var(--space-4)]">
                    {active?.content}
                </div>
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
            </div>
        </div>
    );
}

export default MediaManagerShell;
