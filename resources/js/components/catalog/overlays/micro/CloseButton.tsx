import type { MouseEvent } from 'react';
import clsx from 'clsx';

export type CloseButtonProps = {
    onClick: (event: MouseEvent<HTMLButtonElement>) => void;
    /** Accessible name; defaults to "Close" since the icon carries no text. */
    label?: string;
    className?: string;
    disabled?: boolean;
};

/**
 * Micro building block: the small "x" control used in the corner of an
 * overlay (dialog/drawer/popover) to dismiss it. Knows nothing about which
 * overlay it lives in, focus return, or Escape handling — the compound that
 * composes it owns that behavior.
 */
export function CloseButton({
    onClick,
    label = 'Close',
    className,
    disabled = false,
}: CloseButtonProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            className={clsx(
                /*
                    32 → 44 (`docs/117` M3). Ölçüldü: telefon gezinti
                    çekmecesinin kapatma düğmesi 32×32 idi — asgari hedefin
                    yarısından biraz fazlası. Kapatamadığı için içinde
                    kalınan bir çekmece, açılmamış bir çekmeceden kötüdür.

                    Çarpı işareti (`size-4`) DEĞİŞMEDİ: büyüyen tek şey
                    dokunulabilir kutudur.
                */
                'inline-flex size-[var(--density-hit-area-min)] items-center justify-center rounded-md',
                'text-fg-muted hover:bg-surface-hover hover:text-fg',
                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                'disabled:pointer-events-none disabled:opacity-50',
                className,
            )}
        >
            <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" className="size-4">
                <path
                    fillRule="evenodd"
                    d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z"
                    clipRule="evenodd"
                />
            </svg>
        </button>
    );
}
