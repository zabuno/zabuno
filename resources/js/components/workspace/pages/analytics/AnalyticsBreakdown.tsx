import { t } from '../../../../i18n/workspace';

export type AnalyticsBreakdownRow = {
    id: number;
    label: string;
    qrResolveCount: number;
    menuOpenCount: number;
};

export type AnalyticsBreakdownProps = {
    heading: string;
    rows: AnalyticsBreakdownRow[];
};

/**
 * Bir boyuta göre kırılım — `docs/68`.
 *
 * Toplam sayı, iki şubesi olan bir işletmede birinin HİÇ taranmadığını
 * gizler. Kırılım o gizlenen şeyi görünür kılar.
 *
 * **Tek satırlık kırılım çizilmez.** Tek şubesi olan bir işletmede "Kadıköy:
 * 12" satırı, hemen üstündeki toplamın kelimesi kelimesine tekrarıdır ve
 * ekranda yalnız yer kaplar. Kırılımın değeri KARŞILAŞTIRMADIR; karşılaştıracak
 * ikinci bir şey yoksa değeri de yoktur.
 */
export function AnalyticsBreakdown({ heading, rows }: AnalyticsBreakdownProps) {
    if (rows.length < 2) {
        return null;
    }

    return (
        <section className="flex flex-col gap-2">
            <h3 className="text-body font-semibold text-fg">{heading}</h3>

            <table className="w-full text-start">
                <thead>
                    <tr className="border-b border-border">
                        <th scope="col" className="py-1 text-start text-meta text-fg-muted">
                            {t('workspace.analytics.breakdown.column.name')}
                        </th>
                        <th scope="col" className="py-1 text-end text-meta text-fg-muted">
                            {t('workspace.analytics.metric.qrResolve')}
                        </th>
                        <th scope="col" className="py-1 text-end text-meta text-fg-muted">
                            {t('workspace.analytics.metric.menuOpen')}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id} className="border-b border-border">
                            <td className="py-1.5 text-body text-fg">
                                {/*
                                    Uzun bir jeton satırı taşırmasın diye
                                    kırpılır — ama BAŞLIKTA tamamı durur:
                                    kullanıcı basılı koddaki adresle
                                    eşleştirebilmeli.
                                */}
                                <span className="block max-w-[24ch] truncate" title={row.label}>
                                    {row.label}
                                </span>
                            </td>
                            <td className="py-1.5 text-end text-body tabular-nums text-fg">
                                {row.qrResolveCount}
                            </td>
                            <td className="py-1.5 text-end text-body tabular-nums text-fg">
                                {row.menuOpenCount}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    );
}

export default AnalyticsBreakdown;
