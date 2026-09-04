import { Check } from '@phosphor-icons/react';
import clsx from 'clsx';

import { wizardText } from './uploadWizardCopy';

export type UploadStepKey = 'pick' | 'optimize' | 'frame' | 'send';

export type UploadStep = {
    key: UploadStepKey;
    label: string;
    /** Bu adıma bugün gidilebilir mi? */
    reachable: boolean;
};

type MediaUploadStepsProps = {
    steps: readonly UploadStep[];
    activeKey: UploadStepKey;
    onGo: (key: UploadStepKey) => void;
};

/**
 * Adım göstergesi (kanonik kaynak: "Yükle" ekranının üst şeridi).
 *
 * Bir süs değil, iki soruyu birden cevaplar: neredeyim, kaç adım kaldı.
 * Öncesinde bu ekran tek bir uzun formdu ve telefonda sahibin kaydırarak
 * aradığı beş ayrı karar içeriyordu; hangisinin önce yapılacağı hiçbir yerde
 * yazmıyordu.
 *
 * Ulaşılamayan adım GİZLENMEZ, devre dışı gösterilir. Gizlemek "kaç adım
 * kaldı" sorusunu geri getirirdi; devre dışı hâli ise sırayı öğretir —
 * küçültme, çerçeveleme ve gönderme hepsi seçilmiş bir dosyaya bağlıdır.
 */
export function MediaUploadSteps({ steps, activeKey, onGo }: MediaUploadStepsProps) {
    const activeIndex = steps.findIndex((step) => step.key === activeKey);

    return (
        <ol
            aria-label={wizardText('workspace.media.upload.steps.label')}
            className="flex gap-[var(--space-1)] overflow-x-auto"
        >
            {steps.map((step, index) => {
                const active = step.key === activeKey;
                const done = index < activeIndex;

                return (
                    <li key={step.key} className="flex-none">
                        <button
                            type="button"
                            disabled={!step.reachable}
                            aria-current={active ? 'step' : undefined}
                            onClick={() => onGo(step.key)}
                            className={clsx(
                                'flex min-h-[var(--control-height)] items-center gap-[var(--space-2)]',
                                'rounded-[var(--radius-md)] border px-[var(--space-3)] py-[var(--space-1)]',
                                'text-body whitespace-nowrap',
                                active
                                    ? 'border-border-strong bg-surface-active font-bold text-fg'
                                    : 'border-border font-medium text-fg-secondary',
                                'disabled:cursor-not-allowed disabled:opacity-60',
                                !step.reachable || active ? '' : 'hover:bg-surface-hover',
                            )}
                        >
                            {/*
                                Sıra numarası ERİŞİLEBİLİR ADDAN çıkarılır.
                                İçeride kalsaydı adımın adı "4 Send" olurdu ve
                                ekran okuyucu kullanıcısı her adımda önce bir
                                rakam duyardı; rakam zaten görsel bir konum
                                işareti, adın parçası değil.
                            */}
                            <span
                                aria-hidden="true"
                                className={clsx(
                                    'grid min-h-6 min-w-6 place-items-center rounded-pill',
                                    'text-meta font-bold tabular-nums',
                                    done
                                        ? 'bg-surface-success text-fg-success'
                                        : active
                                          ? 'bg-surface-active text-fg'
                                          : 'bg-surface-subtle text-fg-muted',
                                )}
                            >
                                {done ? <Check size={14} weight="bold" /> : index + 1}
                            </span>
                            {step.label}
                        </button>
                    </li>
                );
            })}
        </ol>
    );
}

export default MediaUploadSteps;
