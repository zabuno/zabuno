import { useId } from 'react';
import { Badge, type BadgeStatus } from '../../../catalog/feedback/micro/Badge';
import { currentLocaleTag } from '../../../../money/format';
import { t } from '../../../../i18n/workspace';
import type { SupportRequestRow } from './supportApi';

export type SupportRequestListStatus = 'loading' | 'error' | 'success';

type SupportRequestListProps = {
    status: SupportRequestListStatus;
    requests: SupportRequestRow[];
};

/**
 * Durum → rozet rengi. Rengin yanında KELİME de var (`status.*`), çünkü
 * rozet yalnız renkle anlatmaz.
 *
 * `received` bilgi (mavi), `answered` başarı (yeşil), `closed` yine bilgi:
 * kapanmış bir talep bir uyarı değildir. Tanınmayan bir durum da `info`dur
 * ve KELİMESİ olduğu gibi yazılır — sunucu dördüncü bir durumu bir gün
 * eklediğinde ekran sessizce yanlış renk basmaz, dürüstçe adını yazar.
 */
function badgeStatusFor(status: string): BadgeStatus {
    if (status === 'answered') {
        return 'success';
    }

    return 'info';
}

function statusLabel(status: string): string {
    switch (status) {
        case 'received':
            return t('workspace.support.status.received');
        case 'answered':
            return t('workspace.support.status.answered');
        case 'closed':
            return t('workspace.support.status.closed');
        default:
            return status;
    }
}

/**
 * Tarih okuyanın dilinde ve kendi saatinde; saat yazılmaz. Bir destek
 * talebinde "hangi gün" yeter — "18:41" hiçbir karar değiştirmez ve dar
 * ekranda satırı ikiye böler.
 */
function dayOf(iso: string): string {
    const value = new Date(iso);

    if (Number.isNaN(value.getTime())) {
        return iso;
    }

    return new Intl.DateTimeFormat(currentLocaleTag(), {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(value);
}

/**
 * Sahibin talepleri — sunum bileşeni. Fetch, rota, çalışma alanı bilmez;
 * `SupportPage` çağırır. Satırlar ETKİLEŞİMSİZDİR: talebi açacak bir ekran
 * yok (cevap e-postayla gelir), o yüzden tıklanır gibi görünen bir satır
 * çizmek, olmayan bir kapıyı göstermek olurdu.
 */
export function SupportRequestList({ status, requests }: SupportRequestListProps) {
    const headingId = useId();

    return (
        <div role="region" aria-labelledby={headingId} className="flex flex-col gap-3">
            <h2 id={headingId} className="text-body font-bold text-fg">
                {t('workspace.support.list.heading')}
            </h2>

            {status === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.support.list.loading')}
                </p>
            )}

            {status === 'error' && (
                <p role="alert" className="text-body font-medium text-fg-danger">
                    {t('workspace.support.list.error')}
                </p>
            )}

            {status === 'success' && requests.length === 0 && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.support.list.empty')}
                </p>
            )}

            {status === 'success' && requests.length > 0 && (
                <ul className="flex flex-col">
                    {requests.map((request) => (
                        <li
                            key={request.id}
                            /*
                                Ekip listeleriyle AYNI gramer (FF-131): kart
                                değil, ayraçla bölünmüş satır. Dar ekranda
                                referans + rozet ilk satıra, konu ikinci
                                satıra iner — `flex-wrap` ile, breakpoint'le
                                değil.
                            */
                            className="flex min-h-[var(--density-row-height)] flex-col gap-y-1 border-t border-border px-[var(--density-padding-inline)] py-[var(--space-2)] text-body first:border-t-0"
                        >
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                {/*
                                    REFERANS TEK ARALIKLI: telefonda okunurken
                                    karakterler tek tek sayılır ve orantılı
                                    yazıda "ZB-3F7K2" ile "ZB-3F7KZ" aynı
                                    genişlikte değildir.
                                */}
                                <span className="font-mono font-bold text-fg">
                                    {request.reference}
                                </span>
                                <Badge status={badgeStatusFor(request.status)}>
                                    {statusLabel(request.status)}
                                </Badge>
                            </div>
                            <span className="text-fg">{request.subject}</span>
                            <span className="text-meta text-fg-muted">
                                {request.first_response_at !== null
                                    ? t('workspace.support.list.answered', {
                                          date: dayOf(request.first_response_at),
                                      })
                                    : t('workspace.support.list.received', {
                                          date: dayOf(request.received_at),
                                      })}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default SupportRequestList;
