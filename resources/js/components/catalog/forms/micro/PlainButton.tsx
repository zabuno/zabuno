import type { ButtonHTMLAttributes } from 'react';
import clsx from 'clsx';

export type PlainButtonVariant = 'primary' | 'secondary';

export type PlainButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: PlainButtonVariant;
};

/**
 * Micro: yalnız token'lardan giyinen buton.
 *
 * `Button` (Flowbite tabanlı) ile neden ikisi birden var? Çünkü Flowbite'ın
 * varsayılan teması kendi ham palet sınıflarını ve sabit yüksekliğini
 * (`h-10`) getiriyor; bunların ikisi de bu depodaki yüzey kurallarını çiğniyor
 * — sabit piksel yükseklik, yoğunluk token'ını (`--density-*`) yok sayar.
 *
 * Doğru uzun vadeli çözüm Flowbite temasını token köküne bağlamaktır ve o
 * ayrı bir iştir. O gün geldiğinde bu bileşen kaldırılır. O güne kadar
 * buradaki kural nettir: yeni bir yüzey ham palet yazmaz, bunu kullanır.
 *
 * Görünüşü `SegmentedControl` ile aynı dili konuşur — ikisi de aynı
 * token'ları okur, bu yüzden yan yana durduklarında birbirine yabancı
 * görünmezler.
 */
export function PlainButton({ variant = 'secondary', className, ...rest }: PlainButtonProps) {
    return (
        <button
            className={clsx(
                'min-h-[var(--density-hit-area-min)] inline-flex items-center justify-center',
                'rounded-lg border px-4 py-2 text-sm font-medium',
                'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)]',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                'disabled:cursor-not-allowed disabled:text-fg-muted disabled:opacity-60',
                variant === 'primary'
                    ? 'border-action bg-action text-action-fg hover:brightness-95'
                    : 'border-border text-fg-secondary hover:bg-surface-hover hover:text-fg',
                className,
            )}
            {...rest}
        />
    );
}
