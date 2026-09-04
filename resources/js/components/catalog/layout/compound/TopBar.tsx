import type { ReactNode } from 'react';
import clsx from 'clsx';
import { IconButton } from '../../navigation/micro/IconButton';
import { BrandMark, type BrandMarkProps } from '../micro/BrandMark';

export type TopBarProps = {
    brand: BrandMarkProps;
    /** Toggles the mobile drawer (docs/35 §9). Omit to hide the control entirely. */
    onToggleMenu?: () => void;
    menuOpen?: boolean;
    /** Optional slot for search/global actions, rendered between the brand and the profile area. */
    center?: ReactNode;
    /** Optional slot for profile menu / notifications / workspace switcher. */
    end?: ReactNode;
    className?: string;
};

/**
 * Compound: composes Micro/Navigation/IconButton (mobile menu toggle) and
 * Micro/Layout/BrandMark. The `center`/`end` slots are opaque ReactNode —
 * TopBar renders whatever it is given and does not know about search,
 * profile menus, or notifications itself (docs/35 §4 shared shell).
 */
export function TopBar({
    brand,
    onToggleMenu,
    menuOpen = false,
    center,
    end,
    className,
}: TopBarProps) {
    return (
        <header
            className={clsx(
                /*
                    Cam yüzey KALIR (`docs/06` §11 sözleşmesi, `TopBar.test`):
                    solid fallback'i ve ölçülmüş kontrastı var. Değişen şey
                    RİTİM: dikey dolgu `space-3`'e indi, boşluklar 8pt ızgaraya
                    oturdu — çubuk artık gri bir şerit değil, ince bir başlık.
                */
                'surface-glass flex flex-wrap items-center gap-[var(--space-3)] border-b',
                'px-[var(--space-fluid-md)] py-[var(--space-3)]',
                className,
            )}
        >
            {onToggleMenu ? (
                <IconButton
                    icon={
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            className="h-5 w-5"
                        >
                            <path
                                fillRule="evenodd"
                                d="M3 5a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Zm0 5a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Zm1 4a1 1 0 1 0 0 2h12a1 1 0 1 0 0-2H4Z"
                                clipRule="evenodd"
                            />
                        </svg>
                    }
                    label={menuOpen ? 'Close menu' : 'Open menu'}
                    onClick={onToggleMenu}
                />
            ) : null}
            <BrandMark {...brand} />
            {center ? (
                <div className="min-w-[12rem] flex-[1_1_12rem]">{center}</div>
            ) : (
                <div className="flex-[1_1_0%]" />
            )}
            {end ? <div className="flex items-center gap-2">{end}</div> : null}
        </header>
    );
}
