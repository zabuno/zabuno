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
            <span className="text-2xl font-semibold text-fg">{value}</span>
            {trend ? (
                <span className={clsx('text-meta font-medium', TREND_COLOR[trend])}>
                    <span aria-hidden="true">{TREND_GLYPH[trend]}</span>
                    <span className="sr-only"> ({TREND_LABEL[trend]})</span>
                </span>
            ) : null}
        </span>
    );
}
