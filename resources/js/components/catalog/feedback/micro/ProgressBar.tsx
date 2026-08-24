import { Progress } from 'flowbite-react';

export type ProgressBarProps = {
    /** 0–100 completion value. */
    value: number;
    /** Accessible label; also shown as the visible text label. */
    label: string;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    className?: string;
};

/**
 * Micro building block: a determinate progress bar. Knows nothing about
 * what is progressing (upload, save, import) — only the 0–100 value.
 */
export function ProgressBar({ value, label, size = 'md', className }: ProgressBarProps) {
    const clamped = Math.min(100, Math.max(0, value));

    return (
        <Progress
            progress={clamped}
            size={size}
            textLabel={label}
            textLabelPosition="outside"
            labelProgress
            className={className}
            aria-valuenow={clamped}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label={label}
        />
    );
}
