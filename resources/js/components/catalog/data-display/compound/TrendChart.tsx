export type TrendPoint = {
    label: string;
    /** Çubuk serisi — kaynağın grafiğinde TARAMA. */
    primary: number;
    /** Çizgi serisi — kaynağın grafiğinde MENÜ AÇILIŞI. */
    secondary: number;
};

export type TrendChartProps = {
    points: TrendPoint[];
    primaryLabel: string;
    secondaryLabel: string;
    /** Tablonun ilk sütun başlığı — "Gün". */
    columnLabel: string;
    /** Grafiğin adı; hem `figure` etiketi hem görünür altyazı. */
    description: string;
};

/*
    ÇİZİM ALANI KULLANICI BİRİMİNDEDİR, PİKSEL DEĞİL.

    SVG'nin `viewBox`'ı boyutsuz bir koordinat sistemidir: 640×240 burada
    "640 piksel" demek değil, "genişlik 640 birim" demektir. Kutu `width:100%`
    ile kabına oturur ve 320 piksellik bir telefonda da, geniş bir ekranda da
    aynı oranla çizilir. Yani bu sayılar bir ölçü kararı değil, bir ORAN
    kararıdır — jeton karşılıkları yoktur ve olamaz.
*/
const VIEW_WIDTH = 640;
const VIEW_HEIGHT = 240;
const BASELINE = VIEW_HEIGHT - 8;
const TOP = 12;
const MAX_BAR_WIDTH = 28;

/**
 * Compound: çubuk + çizgi grafiği, elle yazılmış SVG ile — `docs/109` §6.5.
 *
 * Kaynak (`panel.dc.html`, Insights) bu grafiği ECharts ile çiziyor. Depo
 * ECharts'ı EKLEMİYOR ve sebebi ölçülü: paket ~300 KB gzip'tir, bütçe ise
 * giriş başına 200 KB (`DS-BUNDLE-BUDGET-07`). Tek bir ekran için bütçeyi
 * ikiye katlamak, telefonla bakan bir restoran sahibinin her sayfa açılışını
 * yavaşlatırdı. Bu bir sapma değil, aynı TASARIMIN başka bir teslim biçimi.
 *
 * GRAFİK VE TABLO BİRLİKTE DOĞAR. Bir SVG ekran okuyucu için bir resimdir;
 * içindeki dikdörtgenlerin yükseklikleri okunmaz. Grafiği gören biri sayıya
 * ulaşırken görmeyen biri hiçbir şeye ulaşamazsa, ürünün bir kısmı o
 * kullanıcı için yoktur.
 */
export function TrendChart({
    points,
    primaryLabel,
    secondaryLabel,
    columnLabel,
    description,
}: TrendChartProps) {
    if (points.length === 0) {
        /*
            Boşluğun ne anlama geldiğini SAYFA anlatır (`docs/66`): menü yok,
            yayınlanmadı, hiç taranmadı ve seçili aralıkta yok — dört ayrı
            durum, dört ayrı çıkış yolu. Bir grafik hangisinde olduğunu
            bilemez, o yüzden hiçbir şey söylemez.
        */
        return null;
    }

    const max = Math.max(...points.map((point) => Math.max(point.primary, point.secondary)), 0);
    const slot = VIEW_WIDTH / points.length;
    const barWidth = Math.min(slot * 0.5, MAX_BAR_WIDTH);
    const plotHeight = BASELINE - TOP;

    // Ölçek sıfıra bölünmez: her sayı sıfırken çizim tabana yapışır.
    const scale = (value: number): number => (max === 0 ? 0 : (value / max) * plotHeight);

    const linePoints = points
        .map((point, index) => `${slot * index + slot / 2},${BASELINE - scale(point.secondary)}`)
        .join(' ');

    return (
        <figure
            role="figure"
            aria-label={description}
            className="m-0 flex flex-col gap-[var(--space-3)]"
        >
            {/*
                Efsane GÖRÜNÜR metindir. İki seriyi yalnız renkle ayırmak,
                renk körü bir kullanıcı için grafiği tek seriye indirger
                (WCAG 2.2 §1.4.1).
            */}
            <div className="flex flex-wrap items-center gap-x-[var(--space-4)] gap-y-[var(--space-1)] text-meta text-fg-muted">
                <span className="inline-flex items-center gap-[var(--space-2)]">
                    <span
                        aria-hidden="true"
                        className="inline-block size-[var(--space-2)] rounded-pill"
                        style={{ background: 'var(--color-brand)' }}
                    />
                    {primaryLabel}
                </span>
                <span className="inline-flex items-center gap-[var(--space-2)]">
                    <span
                        aria-hidden="true"
                        className="inline-block h-[var(--space-1)] w-[var(--space-4)]"
                        style={{ background: 'var(--color-fg-secondary)' }}
                    />
                    {secondaryLabel}
                </span>
            </div>

            <svg
                aria-hidden="true"
                viewBox={`0 0 ${VIEW_WIDTH} ${VIEW_HEIGHT}`}
                className="h-auto w-full"
            >
                {/*
                    Taban çizgisi sıfırın nerede olduğunu söyler. Onsuz kısa
                    bir çubuk ile hiç olmayan bir çubuk aynı görünür.
                */}
                <line
                    x1="0"
                    y1={BASELINE}
                    x2={VIEW_WIDTH}
                    y2={BASELINE}
                    stroke="var(--color-border)"
                    strokeWidth="1"
                />

                {points.map((point, index) => {
                    const height = scale(point.primary);

                    return (
                        <rect
                            key={point.label}
                            data-role="bar"
                            x={slot * index + (slot - barWidth) / 2}
                            y={BASELINE - height}
                            width={barWidth}
                            height={height}
                            rx="4"
                            fill="var(--color-brand)"
                        />
                    );
                })}

                <polyline
                    points={linePoints}
                    fill="none"
                    stroke="var(--color-fg-secondary)"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>

            {/*
                Gün adları HTML'de durur, SVG'de değil: SVG metni kullanıcı
                birimiyle ölçeklenir, yani dar bir telefonda okunamayacak
                kadar küçülür. Aynı ızgara sütun sayısı çubuklarınkiyle
                birebir aynı olduğu için etiketler çubukların altına oturur.
            */}
            <div
                aria-hidden="true"
                className="grid gap-[var(--space-1)] text-meta text-fg-muted"
                style={{ gridTemplateColumns: `repeat(${points.length}, minmax(0, 1fr))` }}
            >
                {points.map((point, index) => (
                    <span key={point.label} className="truncate text-center">
                        {/*
                            Otuz günlük bir seride her etiket okunamaz hâle
                            gelirdi; seyrekleştirme etiketi siler, çubuğu
                            değil. Tablo zaten hepsini taşıyor.
                        */}
                        {points.length > 10 && index % 5 !== 0 ? '' : point.label}
                    </span>
                ))}
            </div>

            <figcaption className="text-meta text-fg-muted">{description}</figcaption>

            {/*
                Tablo GÖRSEL OLARAK gizlidir ama ağaçta durur: grafiğin
                söylediği her sayı buradan okunabilir. Görünür bir tablo
                aynı bilgiyi iki kez göstererek ekranı doldururdu.
            */}
            <table className="sr-only">
                <caption>{description}</caption>
                <thead>
                    <tr>
                        <th scope="col">{columnLabel}</th>
                        <th scope="col">{primaryLabel}</th>
                        <th scope="col">{secondaryLabel}</th>
                    </tr>
                </thead>
                <tbody>
                    {points.map((point) => (
                        <tr key={point.label}>
                            <th scope="row">{point.label}</th>
                            <td>{point.primary}</td>
                            <td>{point.secondary}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}

export default TrendChart;
