import { t } from '../../../../i18n/workspace';
import { PanelCard } from '../shared/PanelCard';

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

/*
    Satır hücresinin ORTAK ölçüsü (FF-131, teslim paketi "Kart grameri").

    Yükseklik yoğunluk jetonundan gelir: sahip Ayarlar'dan "Sıkı / Standart /
    Ferah" seçtiğinde bu tablo da onunla değişmeli. Elle yazılmış bir `py-1`
    yoğunluk anahtarını sağır bırakırdı.

    Yatay dolgu kart başlığınınkiyle AYNI (`--space-5`): başlık 20 pikselden,
    satırlar 12 pikselden başlarsa sütun kenarı zikzak çizer ve göz her
    satırda hizayı yeniden bulmak zorunda kalır.
*/
const CELL = 'h-[var(--density-row-height)] px-[var(--space-5)]';

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
        /*
            TEK KART, İÇİNDE İNCE AYRAÇLI SATIRLAR (FF-131, teslim paketinin
            kart grameri).

            Tablo çıplak duruyordu: ne kart sınırı vardı, ne başlık satırının
            gövdeden ayrıldığı bir ton. Sayfada üst üste iki kırılım olduğunda
            (şube ve QR kodu) birinin nerede bitip diğerinin nerede başladığı
            belli olmuyor, sahip "Beşiktaş" satırını yanlış başlığın altında
            okuyabiliyordu.

            `padded={false}`: dolguyu hücreler yönetir, yoksa kartın kendi
            dolgusu tablonun ayraçlarını kenarlığa kadar götürmez ve çizgiler
            havada asılı kalır.
        */
        <PanelCard title={heading} padded={false} className="overflow-hidden">
            <table className="w-full text-start">
                <thead>
                    {/*
                        Başlık satırı SOLUK TONLA ayrışır (`DESIGN_SPEC` §2),
                        kalın bir çizgiyle değil: Flat 2.0 derinliği tonla
                        kurar. Cümle düzeni korunur — büyük harfe çevirme
                        AEP'te yok, çünkü Türkçede "i/İ" dönüşümü tarayıcı
                        diline göre bozulur ve okuma hızını düşürür.
                    */}
                    <tr className="bg-surface-subtle">
                        <th
                            scope="col"
                            className={`${CELL} text-start text-meta font-bold text-fg-muted`}
                        >
                            {t('workspace.analytics.breakdown.column.name')}
                        </th>
                        <th
                            scope="col"
                            className={`${CELL} text-end text-meta font-bold text-fg-muted`}
                        >
                            {t('workspace.analytics.metric.qrResolve')}
                        </th>
                        <th
                            scope="col"
                            className={`${CELL} text-end text-meta font-bold text-fg-muted`}
                        >
                            {t('workspace.analytics.metric.menuOpen')}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        /*
                            AYRAÇ ÜSTE konur. Alt ayraçlı bir listede son
                            satırın ayracını ayrıca susturmak gerekir;
                            unutulduğunda kartın kendi kenarlığıyla çakışan
                            ikinci bir çizgi belirir. Üstten ayraç, eklenen
                            her yeni satırı kendiliğinden doğru çizer.
                        */
                        <tr key={row.id} className="border-t border-border">
                            <td className={`${CELL} text-body text-fg`}>
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
                            {/*
                                `tabular-nums`: oransal rakamlarda "1" diğer
                                rakamlardan dardır; alt alta duran iki sayı
                                farklı genişlikte görünür ve sütun kayar.
                            */}
                            <td className={`${CELL} text-end text-body tabular-nums text-fg`}>
                                {row.qrResolveCount}
                            </td>
                            <td className={`${CELL} text-end text-body tabular-nums text-fg`}>
                                {row.menuOpenCount}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </PanelCard>
    );
}

export default AnalyticsBreakdown;
