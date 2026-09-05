import { Broadcast, CheckCircle, Eye, PencilSimple } from '@phosphor-icons/react';
import type { Icon } from '@phosphor-icons/react';

import { cn } from '../../../../lib/utils';
import { t } from '../../../../i18n/workspace';

/**
 * Göreli zaman — "2 gün önce".
 *
 * Ham zaman damgası sahibe hiçbir şey anlatmaz; "2 gün önce" ise
 * "menüm bayat mı?" sorusunun doğrudan cevabıdır. Gelecekteki bir an
 * (sunucu ile tarayıcı saatinin birkaç saniyelik farkı) "az önce" sayılır:
 * "-1 dakika önce" yazan bir ekran, kullanıcıya kendi verisinden şüphe
 * ettirir.
 */
export function relativePublishTime(publishedAt: string, now: Date): string {
    const then = new Date(publishedAt).getTime();

    if (Number.isNaN(then)) {
        return publishedAt;
    }

    const minutes = Math.floor((now.getTime() - then) / 60000);

    if (minutes < 1) {
        return t('workspace.publication.relative.justNow');
    }

    if (minutes < 60) {
        return t('workspace.publication.relative.minutesAgo', { count: String(minutes) });
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return t('workspace.publication.relative.hoursAgo', { count: String(hours) });
    }

    return t('workspace.publication.relative.daysAgo', { count: String(Math.floor(hours / 24)) });
}

type PublishStepperProps = {
    pendingChangeCount: number;
    previewOpen: boolean;
    liveVersion: number | null;
    publishedAt: string | null;
    /** Testin zamanı sabitleyebilmesi için; üretimde şimdiki an. */
    now?: Date;
};

type Step = {
    key: string;
    label: string;
    sub: string;
    icon: Icon;
};

export function PublishStepper({
    pendingChangeCount,
    previewOpen,
    liveVersion,
    publishedAt,
    now = new Date(),
}: PublishStepperProps) {
    /*
        ŞU ANKİ ADIM VERİDEN ÇIKAR, bir düğmeden değil. Bekleyen değişiklik
        varsa sahip taslaktadır — kendini nerede sansa da. Önizlemeyi açtıysa
        kontrol adımındadır. Bekleyen bir şey yoksa ve yayın varsa iş
        bitmiştir. Bu sıra kaynağın kendi sırasıdır.
    */
    const activeIndex = liveVersion !== null && pendingChangeCount === 0 ? 2 : previewOpen ? 1 : 0;

    const steps: Step[] = [
        {
            key: 'draft',
            label: t('workspace.publication.stepper.draft'),
            sub:
                pendingChangeCount > 0
                    ? t('workspace.publication.stepper.draft.changes', {
                          count: String(pendingChangeCount),
                      })
                    : t('workspace.publication.stepper.draft.noChanges'),
            icon: PencilSimple,
        },
        {
            key: 'preview',
            label: t('workspace.publication.stepper.preview'),
            sub: t('workspace.publication.stepper.preview.sub'),
            icon: Eye,
        },
        {
            key: 'live',
            label: t('workspace.publication.stepper.live'),
            sub:
                liveVersion === null
                    ? t('workspace.publication.stepper.live.never')
                    : t('workspace.publication.stepper.live.sub', {
                          version: String(liveVersion),
                          when:
                              publishedAt === null
                                  ? t('workspace.publication.relative.justNow')
                                  : relativePublishTime(publishedAt, now),
                      }),
            icon: Broadcast,
        },
    ];

    return (
        <section
            role="region"
            aria-label={t('workspace.publication.stepper.region')}
            data-publish-stepper="true"
            className="rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--density-padding-inline)]"
        >
            {/*
                320 pikselde TEK SÜTUN, geniş ekranda üç. Kırılım noktası
                sınıfı yok: `auto-fit` + `minmax(min(100%, …))` düzeni
                kapsayıcının kendi genişliğine göre kurar, ekranın değil —
                bu bölge bir gün dar bir sütunun içine girse de doğru kalır.
            */}
            <ol className="grid list-none grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))] gap-[var(--space-3)]">
                {steps.map((step, index) => {
                    const isCurrent = index === activeIndex;
                    const isDone = index < activeIndex;
                    const StepIcon = isDone ? CheckCircle : step.icon;

                    return (
                        <li
                            key={step.key}
                            {...(isCurrent ? { 'aria-current': 'step' as const } : {})}
                            className="flex items-start gap-[var(--space-3)]"
                        >
                            {/*
                                İşaret ÜÇ KANALIN BİRİNCİSİDİR: biten adım
                                dolu onay dairesi, şu anki adım kendi
                                simgesi kalın, gelecek adım aynı simge ince.
                                İkincisi metin ağırlığı, üçüncüsü ekran
                                okuyucuya yazılan durum cümlesi. Renk
                                dördüncüdür ve tek başına hiçbir şey
                                anlatmaz.
                            */}
                            <StepIcon
                                aria-hidden="true"
                                size={24}
                                weight={isDone ? 'fill' : isCurrent ? 'bold' : 'regular'}
                                className={cn(
                                    'shrink-0',
                                    isDone
                                        ? 'text-fg-success'
                                        : isCurrent
                                          ? 'text-fg'
                                          : 'text-fg-muted',
                                )}
                            />

                            <div className="flex min-w-0 flex-col">
                                <span
                                    className={cn(
                                        'text-body',
                                        isCurrent ? 'font-bold text-fg' : 'font-medium text-fg',
                                    )}
                                >
                                    {step.label}
                                </span>
                                {/*
                                    Alt satır bir CÜMLEDİR, bir ölçü değil:
                                    gövde tabanında kalır. `text-meta` yalnız
                                    zaman damgası, sayaç ve ölçü içindir.
                                */}
                                <span className="text-body tabular-nums text-fg-secondary">
                                    {step.sub}
                                </span>
                                <span className="sr-only">
                                    {isCurrent
                                        ? t('workspace.publication.stepper.state.current')
                                        : isDone
                                          ? t('workspace.publication.stepper.state.done')
                                          : t('workspace.publication.stepper.state.upcoming')}
                                </span>
                            </div>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

export default PublishStepper;
