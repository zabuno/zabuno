import clsx from 'clsx';

export type SkipLinkProps = {
    /** id (without `#`) of the landmark this link jumps to, e.g. the main content region. */
    targetId: string;
    children?: string;
    className?: string;
};

/**
 * Micro building block: a "skip to main content" link. Visually hidden
 * until it receives keyboard focus, per the standard WCAG 2.2 bypass-blocks
 * pattern. Knows nothing about the shell layout it sits in — the caller
 * supplies the id of the landmark it jumps to.
 */
export function SkipLink({
    targetId,
    children = 'Skip to main content',
    className,
}: SkipLinkProps) {
    return (
        <a
            href={`#${targetId}`}
            className={clsx(
                'sr-only focus:not-sr-only',
                'focus:fixed focus:start-4 focus:top-4 focus:z-50',
                /*
                    Marka sarısı iki temada da AYNI kalır ve üstündeki tek
                    doğru mürekkep `--color-action-fg`'dir. Eskiden açık
                    temada beyaz kart, koyu temada sarı üstüne BEYAZ metin
                    (~1.75:1) çiziliyordu; klavye kullanıcısının ilk gördüğü
                    kontrol okunmuyordu.
                */
                'focus:rounded-md focus:bg-action focus:px-4 focus:py-2 focus:text-body focus:font-medium focus:text-action-fg focus:shadow-lg',
                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                className,
            )}
        >
            {children}
        </a>
    );
}
