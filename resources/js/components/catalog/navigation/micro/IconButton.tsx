import type { MouseEvent, ReactNode } from 'react';
import clsx from 'clsx';
import { VisuallyHidden } from './VisuallyHidden';

export type IconButtonProps = {
    /** Icon node; the caller owns the icon implementation. */
    icon: ReactNode;
    /** Required accessible name — the button has no visible text. */
    label: string;
    onClick?: (event: MouseEvent<HTMLButtonElement>) => void;
    disabled?: boolean;
    className?: string;
};

/**
 * Micro building block: an icon-only, square-ish button used for nav
 * affordances such as a mobile menu toggle or a sidebar collapse control.
 * Renders VisuallyHidden text for its accessible name rather than relying
 * on `aria-label` alone, so a browser/OS tooltip or find-in-page still
 * surfaces meaningful text. Knows nothing about routes or fetches.
 */
export function IconButton({ icon, label, onClick, disabled = false, className }: IconButtonProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={clsx(
                /*
                    DOKUNMA ALANI BÜYÜR, İKON BÜYÜMEZ (`docs/117` M3).

                    Ölçüldü (2026-09-05, 320×568): bu düğme 36×36 çiziliyordu
                    ve dokunma hedefi asgarisinin (44) altındaydı — üst
                    çubuktaki "menüyü aç" ve omnibox tetikleyicisi dahil.
                    36 ile 44 arasındaki fark bir stil tercihi değil: parmak
                    ucu 8-10 milimetredir, 44 CSS pikseli onun karşılığıdır ve
                    altına inen her hedef yanlış dokunma olasılığı taşır.

                    Büyüyen şey KUTUDUR, ikon değil: ikon çağıranın kararıdır
                    ve bu satır ona hiç dokunmaz — kutu büyür, ikon ortada
                    kalır, aradaki fark dolgu olur.

                    Ölçü ham 44 değil `--density-hit-area-min`: aynı taban
                    yoğunluk modlarında da değişmez ve tek yerden yönetilir.
                */
                'inline-flex size-[var(--density-hit-area-min)] items-center justify-center rounded-md',
                'text-fg-secondary hover:bg-surface-hover hover:text-fg',
                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                'disabled:pointer-events-none disabled:opacity-50',
                className,
            )}
        >
            <span aria-hidden="true">{icon}</span>
            <VisuallyHidden>{label}</VisuallyHidden>
        </button>
    );
}
