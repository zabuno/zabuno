import type { ReactNode } from 'react';
import clsx from 'clsx';
import { Check } from '@phosphor-icons/react';

export type MenuItemRadioProps = {
    children: ReactNode;
    checked: boolean;
    onSelect: () => void;
    disabled?: boolean;
};

/**
 * Menü içinde TEK SEÇİMLİK bir satır — `role="menuitemradio"`.
 *
 * Tema tercihi menüye girerken gerekti. Üç seçeneği düz `menuitem` olarak
 * koymak, ekran okuyucuya "üç ayrı eylem" der; oysa bunlar tek bir ayarın
 * birbirini dışlayan değerleridir ve hangisinin AÇIK olduğu duyulmalıdır.
 *
 * Menünün içine ayrı bir `radiogroup` gömmek de yanlış olurdu: `menu`
 * çocukları `menuitem` ailesinden olmalıdır, `radiogroup` orada geçerli bir
 * çocuk değildir.
 */
export function MenuItemRadio({
    children,
    checked,
    onSelect,
    disabled = false,
}: MenuItemRadioProps) {
    return (
        <button
            type="button"
            role="menuitemradio"
            aria-checked={checked}
            onClick={onSelect}
            disabled={disabled}
            className={clsx(
                'flex min-h-[var(--density-hit-area-min)] w-full items-center gap-[var(--space-3)] rounded-[var(--radius-md)]',
                'px-[var(--space-3)] py-[var(--space-2)] text-start text-body',
                checked ? 'text-fg' : 'text-fg-secondary',
                'hover:bg-surface-hover',
                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus',
                'disabled:pointer-events-none disabled:opacity-50',
            )}
        >
            {/*
                İşaret YALNIZ renkle verilemez: seçili ile seçili olmayan
                arasındaki fark renk körlüğünde ve yüksek kontrast modunda
                kaybolur. Kalıcı genişlikte bir sütun, satırların da
                kaymamasını sağlar.
            */}
            {/*
                İşaret bir ONAY imidir, nokta değil: nokta hem küçüktü hem de
                satırın soluna kaymış görünüyordu (2026-09-04 ekran incelemesi).
                Sütun kalıcı genişlikte kalır ki satırlar kaymasın.
            */}
            <span aria-hidden="true" className="flex w-[1.25rem] shrink-0 justify-center">
                {checked ? <Check size={16} weight="bold" /> : null}
            </span>
            <span>{children}</span>
        </button>
    );
}
