import { Spinner as FlowbiteSpinner } from 'flowbite-react';

export type SpinnerSize = 'sm' | 'md' | 'lg';

export type SpinnerProps = {
    size?: SpinnerSize;
    /** Accessible label announced to assistive tech while loading. */
    label?: string;
    className?: string;
};

/**
 * Micro building block: a loading indicator. Announces itself via a
 * status live-region so screen readers pick up the loading state without
 * a separate wrapper having to wire aria-live itself.
 */
export function Spinner({ size = 'md', label = 'Loading…', className }: SpinnerProps) {
    return (
        <div role="status" className={className}>
            <FlowbiteSpinner size={size} aria-hidden="true" />
            <span className="sr-only">{label}</span>
        </div>
    );
}
