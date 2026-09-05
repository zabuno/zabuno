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
    if (rows.length === 0) {
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

    return (
        <figure
            role="figure"
            aria-label={description}
            className="m-0 flex flex-col gap-[var(--space-2)]"
        >
            <div className="grid grid-cols-[auto_1fr] gap-x-[var(--space-2)]">
                {/*
                    Gün adları HTML'de: SVG metni kullanıcı birimiyle
                    ölçeklenir ve dar bir telefonda okunamaz. Aynı ızgara
                    satırında durdukları için etiketler kendi satırlarına
                    hizalanır — SVG `preserveAspectRatio="none"` ile kabın
                    yüksekliğine uyar.
                */}
                <div
                    aria-hidden="true"
                    className="grid text-meta text-fg-muted"
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
                    className="h-full w-full"
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
            </div>

            {/*
                Saat ekseni seyrekleştirilir: yirmi dört etiket 320 pikselde
                üst üste biner. Altı saatlik işaretler sahibin kafasındaki
                bölümlemeye denk gelir — sabah, öğle, akşam, gece.
            */}
            <div
                aria-hidden="true"
                className="grid text-meta text-fg-muted"
                style={{ gridTemplateColumns: `repeat(${HOURS}, minmax(0, 1fr))` }}
            >
                {Array.from({ length: HOURS }, (_, hour) => (
                    <span key={hour} className="truncate">
                        {hour % 6 === 0 ? hourLabel(hour) : ''}
                    </span>
                ))}
            </div>

            <figcaption className="text-meta text-fg-muted">{description}</figcaption>

            <table className="sr-only">
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
        </figure>
    );
}

export default HeatGrid;
