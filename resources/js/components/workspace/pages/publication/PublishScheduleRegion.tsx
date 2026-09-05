import { useCallback, useEffect, useState } from 'react';
import { CalendarBlank } from '@phosphor-icons/react';

import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { currentLocaleTag } from '../../../../money/format';
import { t } from '../../../../i18n/workspace';

type ScheduleOption = {
    key: string;
    scheduledFor: string;
};

type PendingSchedule = {
    id: number;
    scheduledFor: string;
    state: string;
};

type SchedulePayload = {
    timeZone: string;
    pending: PendingSchedule | null;
    options: ScheduleOption[];
};

const OPTION_LABEL_KEYS = {
    tonight: 'workspace.publication.schedule.option.tonight',
    tomorrowMorning: 'workspace.publication.schedule.option.tomorrowMorning',
    nextMonday: 'workspace.publication.schedule.option.nextMonday',
} as const;

/**
 * Sunucudan gelen ANI, İstanbul saatiyle okunabilir hâle çevirir.
 *
 * Burada HESAP YAPILMAZ, yalnız biçimlendirilir. Saatin kendisini tarayıcı
 * hesaplasaydı, Berlin'den panele giren bir ortak "bu gece 03:00" dediğinde
 * menü Türkiye'de 04:00'te değişirdi ve sahip menüsünün ne zaman
 * değiştiğini bilemezdi.
 */
function istanbulMoment(iso: string): string {
    const value = new Date(iso);

    if (Number.isNaN(value.getTime())) {
        return iso;
    }

    return new Intl.DateTimeFormat(currentLocaleTag(), {
        timeZone: 'Europe/Istanbul',
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(value);
}

function optionLabel(key: string): string {
    const labelKey = OPTION_LABEL_KEYS[key as keyof typeof OPTION_LABEL_KEYS];

    return labelKey === undefined ? key : t(labelKey);
}

type PublishScheduleRegionProps = {
    workspaceId: number;
    menuId: number;
    /** Hazırlık listesi tamamlandı mı? Plan o anki içeriği dondurur. */
    draftReady: boolean;
};

/**
 * "Planla" — yayını ileri bir zamana kurar.
 *
 * Restoran sahibinin yolculuğu: zam kararı öğlen alınır ama akşam servisi
 * sürerken masadaki misafirin menüsünün fiyatı değişsin istemez. "Bu gece
 * 03:00" der ve uyur; sabah menü yeni fiyatlarla açılmıştır. QR aynı, kart
 * aynı, yalnız sürüm numarası bir artmıştır.
 */
export function PublishScheduleRegion({
    workspaceId,
    menuId,
    draftReady,
}: PublishScheduleRegionProps) {
    const [payload, setPayload] = useState<SchedulePayload | null>(null);
    const [busy, setBusy] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [reloadToken, setReloadToken] = useState(0);

    const scheduleUrl = `/api/workspaces/${workspaceId}/menu/${menuId}/publications/schedule`;

    useEffect(() => {
        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(scheduleUrl, {
                    credentials: 'include',
                    headers: { Accept: 'application/json' },
                });

                if (cancelled || !response.ok) return;

                const body = (await response.json()) as SchedulePayload;

                if (cancelled) return;

                setPayload(body);
            } catch {
                // Okunamadıysa bölüm seçeneksiz kalır. Hemen yayınlamak her
                // zaman açıktır; planlama onu engellemez.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [scheduleUrl, reloadToken]);

    const schedule = useCallback(
        async (scheduledFor: string) => {
            setBusy(true);
            setErrorMessage(null);

            try {
                await bootstrapCsrfCookie();

                const response = await fetch(
                    scheduleUrl,
                    buildAuthRequestInit({
                        method: 'POST',
                        body: JSON.stringify({ scheduledFor }),
                    }),
                );

                if (!response.ok) {
                    setErrorMessage(t('workspace.publication.schedule.error'));

                    return;
                }

                const created = (await response.json()) as PendingSchedule;

                setPayload((previous) =>
                    previous === null ? previous : { ...previous, pending: created },
                );
            } catch {
                setErrorMessage(t('workspace.publication.schedule.error'));
            } finally {
                setBusy(false);
            }
        },
        [scheduleUrl],
    );

    const cancel = useCallback(
        async (scheduleId: number) => {
            setBusy(true);
            setErrorMessage(null);

            try {
                await bootstrapCsrfCookie();

                const response = await fetch(
                    `${scheduleUrl}/${scheduleId}`,
                    buildAuthRequestInit({ method: 'DELETE' }),
                );

                if (!response.ok) {
                    setErrorMessage(t('workspace.publication.schedule.cancelError'));

                    return;
                }

                setPayload((previous) =>
                    previous === null ? previous : { ...previous, pending: null },
                );
                setReloadToken((token) => token + 1);
            } catch {
                setErrorMessage(t('workspace.publication.schedule.cancelError'));
            } finally {
                setBusy(false);
            }
        },
        [scheduleUrl],
    );

    const pending = payload?.pending ?? null;

    return (
        <section
            role="region"
            aria-label={t('workspace.publication.schedule.region')}
            data-publish-schedule="true"
            className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--density-padding-inline)]"
        >
            <h3 className="flex items-center gap-[var(--space-2)] text-body font-bold text-fg">
                <CalendarBlank aria-hidden="true" size={22} weight="bold" className="shrink-0" />
                {t('workspace.publication.schedule.region')}
            </h3>

            {/*
                KURALIN KENDİSİ EKRANDA. "Zamanlanmış yayın da bir yayındır:
                yeni sürüm numarası alır, QR aynı kalır." Sahibin en büyük
                korkusu basılı kartların ölmesidir ve bu cümle o korkuyu tam
                da karar anında karşılar.
            */}
            <p className="max-w-[60ch] text-body text-fg-secondary">
                {t('workspace.publication.schedule.help')}
            </p>

            {pending !== null ? (
                <div className="flex flex-wrap items-center gap-[var(--space-3)]">
                    <p role="status" className="text-body tabular-nums text-fg">
                        {t('workspace.publication.schedule.pending', {
                            moment: istanbulMoment(pending.scheduledFor),
                        })}
                    </p>
                    {/*
                        İPTAL, PLANIN KENDİSİ KADAR ÖNEMLİDİR: zam kararından
                        vazgeçen sahip gece 03:00'e kadar beklemek zorunda
                        kalmamalı.
                    */}
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => void cancel(pending.id)}
                        className="min-h-[var(--control-height)] rounded-[var(--radius-md)] border border-border px-[var(--space-3)] py-[var(--space-1)] text-body font-medium text-fg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        {t('workspace.publication.schedule.cancel')}
                    </button>
                </div>
            ) : !draftReady ? (
                <p role="status" className="text-body text-fg-secondary">
                    {t('workspace.publication.schedule.unready')}
                </p>
            ) : (
                <ul className="flex list-none flex-wrap gap-[var(--space-2)]">
                    {(payload?.options ?? []).map((option) => (
                        <li key={option.key}>
                            <button
                                type="button"
                                disabled={busy}
                                onClick={() => void schedule(option.scheduledFor)}
                                /*
                                    Hap biçim (`rounded-pill`): bu bir seçim
                                    çipidir, bir birincil eylem değil.
                                    Yayınlama düğmesiyle aynı ağırlıkta
                                    çizilseydi, sahip hangisinin menüyü ŞU AN
                                    değiştirdiğini ayırt edemezdi.
                                */
                                className="min-h-[var(--control-height)] rounded-pill border border-border px-[var(--space-3)] py-[var(--space-1)] text-body font-medium tabular-nums text-fg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                            >
                                {t('workspace.publication.schedule.optionAt', {
                                    label: optionLabel(option.key),
                                    moment: istanbulMoment(option.scheduledFor),
                                })}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {errorMessage !== null ? (
                <p role="alert" className="text-body text-fg-danger">
                    {errorMessage}
                </p>
            ) : null}
        </section>
    );
}

export default PublishScheduleRegion;
