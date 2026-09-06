import { useEffect, useRef, useState } from 'react';
import clsx from 'clsx';

export type HeatRow = {
    label: string;
    /**
     * 24 saatin değeri. `null` "sıfır" DEĞİLDİR: sunucu tek ziyaretçiye
     * dayanan hücreleri yayımlamaz ("salı 03:00 · 1 tarama" bir istatistik
     * değil, bir kişinin o gece oraya girdiğinin kaydıdır). Sıfırla
     * karıştırmak, sahibe olmayan bir boşluk göstermek olurdu.
     */
    values: (number | null)[];
};

export type HeatGridProps = {
    rows: HeatRow[];
    description: string;
    /** Tablonun köşe başlığı — "Gün". */
    columnLabel: string;
    hourLabel: (hour: number) => string;
    /** Gizlenmiş hücrenin tablodaki karşılığı. */
    withheldLabel: string;
};

/*
    Hücre bir KARE birimdir; `viewBox` oran taşır, piksel değil (bkz.
    `TrendChart`). Kutu genişledikçe kareler birlikte büyür.
*/
const CELL = 10;
const GAP = 1.4;
const HOURS = 24;

/** Sönmüş bir hücre ile en yoğun hücre arasındaki en düşük görünür ton. */
const MIN_VISIBLE_OPACITY = 0.12;

/*
    ÇİZİMİN TABANI — hücre başına ölü alan ölçeğinin bir adımı (`--space-2`).

    Çizim `viewBox` ile kabına uyar ve dar ekranda küçülür; 320 pikselde
    hücre 11 piksel (ölçüldü, `docs/117` M8) ve bu okunur. Bunun bir SINIRI
    var: hücreler arasındaki boşluk hücrenin yüzde on dördüdür ve hücre bir
    adımın altına inince o boşluk bir pikselin altına düşer — ızgara bir
    lekeye döner, sahip "cumartesi 13:00" ile "cumartesi 14:00"i ayıramaz.
    Taban, 24 sütunun her birine bir adım yer bulmaktır; bulunamıyorsa
    ızgara küçülmez, KENDİ KABINDA kayar. Belge kaymaz.
*/
const DRAWING_MIN_WIDTH_CLASS = 'min-w-[calc(24*var(--space-2))]';

type Edges = { start: boolean; end: boolean };

/**
 * Compound: saat × gün ısı haritası, elle yazılmış SVG ile — `docs/109` §6.5.
 *
 * Sahibin bu haritadan çıkardığı karar somut: personeli hangi saate koyacağı,
 * mutfağın hangi saate hazırlanacağı. "Günde 30 tarama" bu kararı vermez;
 * "cumartesi 13:00'te 30 tarama" verir.
 */
export function HeatGrid({
    rows,
    description,
    columnLabel,
    hourLabel,
    withheldLabel,
}: HeatGridProps) {
    const scrollerRef = useRef<HTMLDivElement | null>(null);
    const [edges, setEdges] = useState<Edges>({ start: false, end: false });
    const empty = rows.length === 0;

    /*
        KAYDIRILABİLİRLİK DOKUNMADA DA ANLAŞILIR (`docs/117` M8).

        Dokunmada üzerine gelme yoktur ve iOS kaydırma çubuğunu ancak
        kaydırırken gösterir: taşan bir ızgara, taşmadığını sanan bir sahip
        demektir. İpucu bu yüzden kalıcı ve görsel — içeriğin devam ettiği
        kenarda bir solma. Hangi kenarın solacağı kaydırma konumundan
        okunur; sağdan-sola dillerde konum negatif gelir, mutlak değeri
        alınır. Ölçüm gerçek düzen motoruna aittir; jsdom'da gözlemci yoktur
        ve ipucu hiç doğmaz — bu bir hata değil, orada ölçülecek düzen
        olmamasıdır.
    */
    useEffect(() => {
        const node = scrollerRef.current;

        if (node === null || typeof ResizeObserver === 'undefined') {
            return undefined;
        }

        const update = () => {
            const offset = Math.abs(node.scrollLeft);
            const hidden = node.scrollWidth - node.clientWidth;
            const next: Edges = { start: offset > 1, end: hidden - offset > 1 };

            setEdges((current) =>
                current.start === next.start && current.end === next.end ? current : next,
            );
        };

        node.addEventListener('scroll', update, { passive: true });
        const observer = new ResizeObserver(update);
        observer.observe(node);

        return () => {
            node.removeEventListener('scroll', update);
            observer.disconnect();
        };
    }, [empty]);

    if (empty) {
        return null;
    }

    const max = Math.max(...rows.flatMap((row) => row.values.map((value) => value ?? 0)), 0);

    /*
        Ölçek sıfıra bölünmez. Menüsü yayında ama hiç taranmamış bir hafta
        gerçektir; 0/0 tüm haritayı `NaN` opaklıkla çizerdi — yani hiç
        çizmezdi ve sahip ekranın bozulduğunu sanardı.
    */
    const opacity = (value: number | null): number => {
        if (value === null || value <= 0 || max === 0) {
            return 0;
        }

        return MIN_VISIBLE_OPACITY + (value / max) * (1 - MIN_VISIBLE_OPACITY);
    };

    /*
        GÜN ADLARI YERİNDE KALIR. Izgara kayarken satırın hangi güne ait
        olduğu okunmaya devam etmeli; sütun yapışkandır ve yalnız
        kaydırılmışken zemin alır — kaymamış hâlde zemine ihtiyaç yok, kartın
        kendi zemini yeter.
    */
    const stickyColumn = clsx('sticky start-0 z-10', edges.start && 'bg-surface');

    const edgeClass = (visible: boolean) =>
        clsx(
            'pointer-events-none absolute inset-y-0 w-[var(--space-6)] to-transparent',
            visible ? 'opacity-100' : 'opacity-0',
        );

    return (
        <figure
            role="figure"
            aria-label={description}
            className="m-0 flex flex-col gap-[var(--space-2)]"
        >
            <div className="relative">
                <div
                    ref={scrollerRef}
                    data-role="heat-scroller"
                    className="overflow-x-auto overscroll-x-contain"
                >
                    <div className="grid grid-cols-[auto_1fr] gap-x-[var(--space-2)]">
                        {/*
                            Gün adları HTML'de: SVG metni kullanıcı birimiyle
                            ölçeklenir ve dar bir telefonda okunamaz. Aynı
                            ızgara satırında durdukları için etiketler kendi
                            satırlarına hizalanır — SVG
                            `preserveAspectRatio="none"` ile kabın
                            yüksekliğine uyar.
                        */}
                        <div
                            aria-hidden="true"
                            data-role="heat-days"
                            className={clsx('grid text-meta text-fg-muted', stickyColumn)}
                            style={{ gridTemplateRows: `repeat(${rows.length}, minmax(0, 1fr))` }}
                        >
                            {rows.map((row) => (
                                <span key={row.label} className="flex items-center">
                                    {row.label}
                                </span>
                            ))}
                        </div>

                        <svg
                            aria-hidden="true"
                            viewBox={`0 0 ${HOURS * CELL} ${rows.length * CELL}`}
                            preserveAspectRatio="none"
                            className={clsx('h-full w-full', DRAWING_MIN_WIDTH_CLASS)}
                        >
                            {rows.map((row, rowIndex) =>
                                row.values.map((value, hour) => (
                                    <rect
                                        key={`${row.label}-${String(hour)}`}
                                        data-role="heat-cell"
                                        data-value={value === null ? 'withheld' : String(value)}
                                        x={hour * CELL + GAP / 2}
                                        y={rowIndex * CELL + GAP / 2}
                                        width={CELL - GAP}
                                        height={CELL - GAP}
                                        rx="2"
                                        /*
                                            Sönmüş hücre de ÇİZİLİR: ızgaranın
                                            kendisi bir bilgidir. Boş bırakılsaydı
                                            "ölçülmedi" ile "hiç olmadı" aynı
                                            görünürdü.
                                        */
                                        fill={
                                            value === null
                                                ? 'var(--color-border-strong)'
                                                : 'var(--color-brand)'
                                        }
                                        fillOpacity={value === null ? 0.35 : opacity(value)}
                                        stroke="var(--color-border)"
                                        strokeWidth="0.4"
                                    />
                                )),
                            )}
                        </svg>

                        {/* Saat ekseninin altındaki köşe: gün sütunuyla birlikte yapışır. */}
                        <span aria-hidden="true" className={stickyColumn} />

                        {/*
                            Saat ekseni seyrekleştirilir: yirmi dört etiket
                            320 pikselde üst üste biner. Altı saatlik
                            işaretler sahibin kafasındaki bölümlemeye denk
                            gelir — sabah, öğle, akşam, gece.

                            ETİKET KESİLMEZ (`docs/117` M8). Önceki hâl her
                            etiketi kendi sütununa sığdırıp üç noktayla
                            kesiyordu; 320 pikselde sütun 11 piksel ve "00:00"
                            yerine "0." okunuyordu. Etiketin sağındaki beş
                            sütun boştur — etiket oraya taşar. Beş boş sütun
                            hiçbir genişlikte etiketten dar değildir: taban
                            (yukarıda) bunu da garanti eder.
                        */}
                        <div
                            aria-hidden="true"
                            data-role="heat-hours"
                            className="grid text-meta text-fg-muted"
                            style={{ gridTemplateColumns: `repeat(${HOURS}, minmax(0, 1fr))` }}
                        >
                            {Array.from({ length: HOURS }, (_, hour) => (
                                <span key={hour} className="whitespace-nowrap">
                                    {hour % 6 === 0 ? hourLabel(hour) : ''}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>

                <span
                    aria-hidden="true"
                    data-role="heat-scroll-edge"
                    data-edge="start"
                    className={clsx(
                        edgeClass(edges.start),
                        'start-0 bg-linear-to-r from-surface rtl:bg-linear-to-l',
                    )}
                />
                <span
                    aria-hidden="true"
                    data-role="heat-scroll-edge"
                    data-edge="end"
                    className={clsx(
                        edgeClass(edges.end),
                        'end-0 bg-linear-to-l from-surface rtl:bg-linear-to-r',
                    )}
                />
            </div>

            <figcaption className="text-meta text-fg-muted">{description}</figcaption>

            {/*
                EKRAN OKUYUCU TABLOSU BİR KAPTA GİZLENİR — `docs/117` M8.

                `sr-only` doğrudan tablonun üstündeydi. Bir tablo 1 piksele
                SIĞMAZ: en dar içeriğinin genişliğini alır, 25 sütun × "00:00"
                = 1132 piksel, ve mutlak konumlu olsa da belgeyi o kadar
                genişletir. 320 pikselde sayfa yana kayıyordu ve kayan şey
                görünmez bir tabloydu — dört hikâyede ölçüldü (K4). Bir `div`
                1 piksele sığar ve taşanı kırpar; tablo onun içinde, ekran
                okuyucu için aynı, düzen için yok.
            */}
            <div className="sr-only">
                <table>
                    <caption>{description}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{columnLabel}</th>
                            {Array.from({ length: HOURS }, (_, hour) => (
                                <th key={hour} scope="col">
                                    {hourLabel(hour)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.label}>
                                <th scope="row">{row.label}</th>
                                {row.values.map((value, hour) => (
                                    <td key={`${row.label}-${String(hour)}`}>
                                        {value === null ? withheldLabel : value}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </figure>
    );
}

export default HeatGrid;
