export type ShareSlice = {
    id: string | number;
    label: string;
    value: number;
    /** Yüzde; sunucu hesaplar, bileşen yeniden türetmez. */
    percent: number;
};

export type ShareRingProps = {
    slices: ShareSlice[];
    description: string;
    formatValue: (value: number) => string;
    formatPercent: (percent: number) => string;
};

/*
    Halka `viewBox` oranıyla çizilir (bkz. `TrendChart`). Yarıçap ve kalınlık
    bu koordinat sisteminin içindedir; kutu genişledikçe halka birlikte
    büyür.
*/
const VIEW = 100;
const RADIUS = 38;
const STROKE = 16;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

/**
 * Dilim tonları — jetondan okunur, ham renk yok.
 *
 * Üç ton sonrası liste başa döner. Bu bilinçli: dördüncü şubeden sonra
 * halkanın kendisi okunamaz hâle gelir ve sıralamayı taşıyan şey efsanedir,
 * renk değil. Renge dördüncü bir anlam yüklemek, renk körü bir kullanıcı
 * için hiçbir şey ifade etmezdi.
 */
const TONES = [
    'var(--color-brand)',
    'var(--color-fg-secondary)',
    'var(--color-border-strong)',
] as const;

/**
 * Compound: pay halkası, elle yazılmış SVG ile — `docs/109` §6.5.
 *
 * "Bu şube markanın ne kadarı?" iki şubesi olan bir işletmenin ilk
 * sorusudur ve toplam sayı onu tam olarak gizler: 214 taramanın 200'ü tek
 * şubeden geliyorsa diğerinin karekodu duvardan düşmüş olabilir.
 *
 * EFSANE GÖRÜNÜRDÜR ve halkanın metin karşılığıdır. Bir daire diliminin
 * yüzdesini gözle kestirmek zordur — %28 ile %34 aynı görünür. Efsane hem
 * görene hem görmeyene aynı sayıyı verir.
 */
export function ShareRing({ slices, description, formatValue, formatPercent }: ShareRingProps) {
    if (slices.length === 0) {
        return null;
    }

    const total = slices.reduce((sum, slice) => sum + slice.value, 0);

    /*
        BİRİKİM `reduce` İLE TAŞINIR, dışarıdaki bir değişkene yazılarak
        değil.

        Önceki hâl `map` içinde dış bir `consumed` değişkenini artırıyordu ve
        React derleyici kapısı bunu haklı olarak reddetti: çizim sırasında
        dışarıya yazan bir gövde, aynı bileşen iki kez değerlendirildiğinde
        (geliştirme kipi, eşzamanlı çizim) yayları ÜST ÜSTE bindirirdi —
        halka sessizce yanlış çıkardı.
    */
    const arcs = slices.reduce<
        {
            id: ShareSlice['id'];
            length: number;
            offset: number;
            tone: (typeof TONES)[number];
            consumed: number;
        }[]
    >((accumulated, slice, index) => {
        // Tek dilim varsa halka tamdır: sıfır uzunlukta bir yay, ekranda
        // hiç halka olmaması demekti.
        const fraction = total === 0 ? 1 / slices.length : slice.value / total;
        const consumed = accumulated[index - 1]?.consumed ?? 0;

        accumulated.push({
            id: slice.id,
            length: CIRCUMFERENCE * fraction,
            offset: -CIRCUMFERENCE * consumed,
            tone: TONES[index % TONES.length],
            consumed: consumed + fraction,
        });

        return accumulated;
    }, []);

    return (
        <figure
            role="figure"
            aria-label={description}
            className="m-0 flex flex-wrap items-center gap-[var(--space-4)]"
        >
            <svg
                aria-hidden="true"
                viewBox={`0 0 ${VIEW} ${VIEW}`}
                className="h-auto w-[8rem] max-w-full shrink-0"
            >
                {/*
                    Yay uzunluğu `stroke-dasharray` ile verilir: tek bir daire
                    ve bir dash deseni, `path` trigonometrisinin yarıçap
                    değiştiğinde bozulan matematiğini ortadan kaldırır.

                    -90° döndürme, ilk dilimi saat 12 hizasından başlatır —
                    okuma yönü bir daireye de aittir.
                */}
                <g transform={`rotate(-90 ${VIEW / 2} ${VIEW / 2})`}>
                    {arcs.map((arc) => (
                        <circle
                            key={arc.id}
                            data-role="arc"
                            cx={VIEW / 2}
                            cy={VIEW / 2}
                            r={RADIUS}
                            fill="none"
                            stroke={arc.tone}
                            strokeWidth={STROKE}
                            strokeDasharray={`${arc.length} ${CIRCUMFERENCE - arc.length}`}
                            strokeDashoffset={arc.offset}
                        />
                    ))}
                </g>
            </svg>

            <ul className="flex min-w-[12rem] flex-1 flex-col gap-[var(--space-2)]">
                {slices.map((slice, index) => (
                    <li
                        key={slice.id}
                        className="flex flex-wrap items-baseline gap-x-[var(--space-2)] text-body"
                    >
                        <span
                            aria-hidden="true"
                            className="inline-block size-[var(--space-2)] shrink-0 rounded-pill"
                            style={{ background: TONES[index % TONES.length] }}
                        />
                        <span className="font-medium text-fg">{slice.label}</span>
                        {/*
                            Yüzde ve ham sayı BİRLİKTE durur. Yalnız yüzde,
                            "%50" ile "iki taramanın biri"yi aynı gösterirdi;
                            yalnız sayı ise payı hiç söylemezdi.
                        */}
                        <span className="ms-auto tabular-nums text-fg-secondary">
                            {formatPercent(slice.percent)}
                        </span>
                        <span className="basis-full tabular-nums text-meta text-fg-muted">
                            {formatValue(slice.value)}
                        </span>
                    </li>
                ))}
            </ul>

            <figcaption className="basis-full text-meta text-fg-muted">{description}</figcaption>
        </figure>
    );
}

export default ShareRing;
