import type { AnchorHTMLAttributes } from 'react';
import clsx from 'clsx';

export type ActionLinkVariant = 'primary' | 'secondary';

export type ActionLinkProps = AnchorHTMLAttributes<HTMLAnchorElement> & {
    variant?: ActionLinkVariant;
};

/**
 * Micro: anlamı BAĞLANTI, görünüşü EYLEM olan öğe.
 *
 * Frontpage'in çağrı-butonları aslında `<a href>`'tir — bir sayfaya
 * götürürler, bir işlem yürütmezler. Onları `<button>` yapmak klavye ve ekran
 * okuyucu davranışını bozar (Enter/Space farkı, yeni sekmede açma). Bu yüzden
 * öğe bağlantı kalır ve yalnız görünümü eylem dilinden gelir.
 *
 * Bu bileşen var, çünkü frontpage kendi mavisini elle yazıyordu: tasarım
 * sistemi değiştiğinde admin paneli değişiyor, açılış sayfası aynı kalıyordu.
 * Artık ikisi de `--color-action` okur — token değişince ikisi birden değişir.
 * Bileşen hiçbir ham renk bilmez, yalnız semantic token tüketir.
 */
export function ActionLink({ variant = 'primary', className, ...rest }: ActionLinkProps) {
    return (
        <a
            className={clsx(
                'inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium',
                'min-h-[var(--density-hit-area-min)]',
                'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)]',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                variant === 'primary'
                    ? 'bg-action text-action-fg hover:brightness-95'
                    : 'border border-border text-fg hover:bg-surface-hover',
                'forced-colors:border forced-colors:border-[ButtonText]',
                className,
            )}
            {...rest}
        />
    );
}
