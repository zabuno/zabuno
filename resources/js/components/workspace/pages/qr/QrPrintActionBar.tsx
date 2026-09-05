import { FileZip, DownloadSimple, Printer } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import { ActionLink } from '../../../catalog/navigation/micro/ActionLink';
import type { QrScreenCode } from '../publication/QrTableCardGrid';
import {
    CARDS_PER_REQUEST,
    cardUrl,
    cardsZipUrl,
    planSummary,
    type QrPrintPlan,
} from './qrPrintPlan';

type QrPrintActionBarProps = {
    workspaceId: number;
    locationId: number;
    plan: QrPrintPlan;
    /** Plana göre gerçekten basılacak kodlar. */
    selected: readonly QrScreenCode[];
    /** Kapsamın adı: "Tüm masalar", bölge adı ya da masa adı. */
    scopeName: string;
};

/**
 * SABİT EYLEM ÇUBUĞU — panel v3.1 kanonik kaynağı: tek cümle özet + tek tık.
 *
 * Kaynağın en güçlü aygıtı bu cümle. Üç adımın on kontrolünü tek satıra
 * indirir ("12 kart · A6 dikey · sade") ve seçili durumu KELİMEYLE anlatır:
 * seçenekler yalnız kenarlık rengiyle işaretlenseydi kırmızı-yeşil ayırt
 * edemeyen bir sahip hiçbir farkı göremezdi (WCAG 2.2 §1.4.1).
 *
 * "YAZDIR" YALNIZ TEK KARTTA ÇİZİLİR ve bu bir eksiklik değil bir dürüstlük.
 * Tarayıcıya yazdırılabilecek şey bir PDF'tir; çok kartlı bir seçimin PDF'i
 * yoktur — çıktı bir ZIP arşividir ve matbaaya gider. Depoda çok kartlı tek
 * basılabilir kâğıt "kesilecek tabaka"dır (sayfa başına on iki kart) ama onun
 * KENDİ yerleşimi vardır: seçilen ölçüyü ve tasarımı taşımaz. "Yazdır" adıyla
 * onu açmak, sahibin eline seçtiğinden başka bir kâğıt vermek olurdu. Tabaka
 * bu yüzden kendi adıyla ve kendi cümlesiyle, ayrıca sunuluyor.
 *
 * ÇUBUK YAPIŞKANDIR (`sticky bottom-0`): sahip üçüncü adımdayken bile özeti ve
 * indirmeyi görür. 320 pikselde de aynı yerde durur, çünkü konum piksel
 * ölçüsüne değil kaydırmaya bağlıdır.
 */
export function QrPrintActionBar({
    workspaceId,
    locationId,
    plan,
    selected,
    scopeName,
}: QrPrintActionBarProps) {
    const count = selected.length;
    const single = count === 1 ? selected[0] : null;
    const capped = count > CARDS_PER_REQUEST;

    /*
        KENARLIK VE DOLU ZEMİN, GÖLGE DEĞİL. Kaynak burada bir yükselti gölgesi
        kullanıyor; bu deponun jeton kümesinde yükselti jetonu YOK ve ham bir
        `box-shadow` yazmak, tema değiştiğinde jetonlarla birlikte değişmeyen
        bir renk bırakırdı. Çubuğun altındaki içerikten ayrılması için dolu
        zemin ile kalın kenarlık yeter.
    */
    return (
        <div className="sticky bottom-0 z-10 flex flex-wrap items-center gap-[var(--space-3)] rounded-[var(--radius-lg)] border-2 border-border bg-surface p-[var(--space-3)]">
            <div className="flex min-w-[12rem] flex-1 flex-col">
                <span className="text-body font-bold text-fg">{planSummary(plan, count)}</span>
                <span className="text-body text-fg-secondary">
                    {t(
                        count > 1
                            ? 'workspace.publication.qrScreen.summarySub.zip'
                            : 'workspace.publication.qrScreen.summarySub',
                        { scope: scopeName, format: plan.format.toUpperCase() },
                    )}
                </span>
                {/*
                    SINIR SÖYLENİR. Sunucu tek istekte en fazla 48 kart basar ve
                    fazlasını sessizce düşürür; söylemeyen bir ekran, sahibin
                    eksik bir arşivi tam sanıp matbaaya göndermesi demektir.
                */}
                {capped ? (
                    <span role="status" className="text-meta text-fg-warning">
                        {t('workspace.publication.qrScreen.capped', {
                            cap: String(CARDS_PER_REQUEST),
                        })}
                    </span>
                ) : null}
            </div>

            {/*
                YAZDIR VE İNDİR YAN YANA — sahibin 2026-09-05 kararı.

                Önce kap sarabiliyordu (`flex-wrap`) ve dar ekranda iki düğme
                alt alta düşüyordu. Alt alta dizilen iki düğme birbirinin
                DEVAMI gibi okunuyor, oysa bir SEÇİM sunuyorlar: aynı kâğıdın
                iki farklı çıkışı.

                Sarma artık gerekli de değil, çünkü indirme etiketi kısaldı
                (aşağıda): "Print" ve "Download" 320 pikselde yan yana sığar.
                Uzun etiketle sarmayı kaldırmak taşmaya yol açardı — bu yüzden
                iki değişiklik tek pakette.
            */}
            <div className="flex gap-[var(--space-2)]">
                {single ? (
                    <ActionLink
                        variant="secondary"
                        href={cardUrl(single, plan, 'pdf', false)}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Printer size={20} weight="regular" aria-hidden="true" />
                        <span className="ps-[var(--space-1)]">
                            {t('workspace.publication.qrScreen.print')}
                        </span>
                    </ActionLink>
                ) : null}

                {count === 0 ? null : single ? (
                    <ActionLink href={cardUrl(single, plan, plan.format, true)}>
                        <DownloadSimple size={20} weight="regular" aria-hidden="true" />
                        {/*
                            ETİKET YALNIZ "İNDİR" — kodun adı DEĞİL.

                            Ad zaten iki satır yukarıda, özet cümlesinin
                            içinde ("Entrance code · PDF"). Düğmede tekrar
                            etmek bilgi eklemiyor; yalnız düğmeyi uzatıyor ve
                            uzayan düğme yanındakini alt satıra itiyor.

                            Bağlam kaybolmuyor: düğme özetin hemen altında ve
                            hangi kâğıdı indireceği o cümlede yazılı.
                        */}
                        <span className="ps-[var(--space-1)]">
                            {t('workspace.publication.qrScreen.downloadShort')}
                        </span>
                    </ActionLink>
                ) : (
                    <ActionLink href={cardsZipUrl(workspaceId, locationId, plan)}>
                        <FileZip size={20} weight="regular" aria-hidden="true" />
                        <span className="ps-[var(--space-1)]">
                            {t('workspace.publication.qrScreen.downloadZip', {
                                count: String(count),
                            })}
                        </span>
                    </ActionLink>
                )}
            </div>
        </div>
    );
}
