import type { ReactNode } from 'react';
import clsx from 'clsx';

export type StatValueTrend = 'up' | 'down' | 'flat';

export type StatValueProps = {
    value: ReactNode;
    trend?: StatValueTrend;
    className?: string;
};

const TREND_COLOR: Record<StatValueTrend, string> = {
    up: 'text-fg-success ',
    down: 'text-fg-danger',
    flat: 'text-fg-muted',
};

const TREND_GLYPH: Record<StatValueTrend, string> = {
    up: '▲',
    down: '▼',
    flat: '▬',
};

const TREND_LABEL: Record<StatValueTrend, string> = {
    up: 'trending up',
    down: 'trending down',
    flat: 'flat',
};

/**
 * Micro building block: a single numeric/text value with an optional trend
 * glyph. Knows nothing about the surrounding stat card or its label.
 */
export function StatValue({ value, trend, className }: StatValueProps) {
    return (
        <span className={clsx('inline-flex items-baseline gap-1.5', className)}>
            {/*
                RAKAM METRİK ÖLÇEKTE (FF-131, AEP `DESIGN_SPEC` §2).

                Önceki hâli `text-title` taşıyordu; o ölçek SAYFA BAŞLIĞININ
                ölçeğidir. Yani bir ölçüm, bir sayfa adı ve bir kart başlığı
                birbirine yakın büyüklükteydi ve gözün "burada bir SAYI var"
                diyebileceği tek bir işaret kalmıyordu.

                AEP bu iş için ayrı bir basamak yayınlıyor: `--aep-text-metric`.
                Jeton `app.css` @theme'inde bir rol adı olarak yayımlanmadığı
                için satır içi okunuyor — `WorkspacePageFrame`'in
                `--space-fluid-lg`'yi okuduğu desenin aynısı. Bileşen ham bir
                piksel değil, ölçeğin ADINI biliyor: ölçek değişirse burası
                değişmez.

                `tabular-nums` ayrı bir şart: oransal rakamlarda "1" diğer
                rakamlardan dardır, o yüzden alt alta duran iki kartın sayıları
                farklı genişlikte görünür ve sütun kayar.

                Ağırlık 700 — AEP yalnız 400/500/700 yayınlıyor. `font-semibold`
                (600) o merdivenin dışındaydı ve yazı tipinde karşılığı
                olmadığında tarayıcı onu SENTEZLER: rakamlar kalınlaşırken
                biçimleri hafifçe bozulur.
            */}
            <span
                className="font-bold tracking-tight tabular-nums text-fg"
                style={{
                    fontSize: 'var(--aep-text-metric)',
                    lineHeight: 'var(--aep-text-metric-lh)',
                }}
            >
                {value}
            </span>
            {trend ? (
                <span className={clsx('text-meta font-medium', TREND_COLOR[trend])}>
                    <span aria-hidden="true">{TREND_GLYPH[trend]}</span>
                    <span className="sr-only"> ({TREND_LABEL[trend]})</span>
                </span>
            ) : null}
        </span>
    );
}
