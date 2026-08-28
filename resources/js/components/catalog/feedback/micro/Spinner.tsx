import { Spinner as FlowbiteSpinner } from 'flowbite-react';

export type SpinnerSize = 'sm' | 'md' | 'lg';

export type SpinnerProps = {
    size?: SpinnerSize;
    /** Accessible label announced to assistive tech while loading. */
    label?: string;
    /**
     * Zaten duyurulan bir bölgenin İÇİNDE mi?
     *
     * Bu bileşen kendi canlı bölgesini kurar. Kendisi de bir `role="status"`
     * kabının içine konduğunda aynı metin iki kez duyurulur — ve tekrar eden
     * duyurular gerçek olanları da bastırır. O durumda gösterge yalnız
     * GÖRSELDİR; anlatımı kap üstlenir.
     */
    decorative?: boolean;
    className?: string;
};

/**
 * Micro building block: a loading indicator. Announces itself via a
 * status live-region so screen readers pick up the loading state without
 * a separate wrapper having to wire aria-live itself.
 */
export function Spinner({
    size = 'md',
    label = 'Loading…',
    decorative = false,
    className,
}: SpinnerProps) {
    if (decorative) {
        return (
            <div aria-hidden="true" className={className}>
                <FlowbiteSpinner size={size} aria-hidden="true" />
            </div>
        );
    }

    return (
        <div role="status" className={className}>
            <FlowbiteSpinner size={size} aria-hidden="true" />
            <span className="sr-only">{label}</span>
        </div>
    );
}
