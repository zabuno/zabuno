import clsx from 'clsx';

export type SegmentedOption<Value extends string> = {
    value: Value;
    label: string;
};

export type SegmentedControlProps<Value extends string> = {
    label: string;
    value: Value;
    options: readonly SegmentedOption<Value>[];
    onChange: (value: Value) => void;
    disabled?: boolean;
    className?: string;
};

/**
 * Compound: birkaç seçenek arasından tek seçim — hepsi aynı anda görünür.
 *
 * Neden `radiogroup`? Görünüşü buton dizisi olsa da anlamı tek-seçimdir.
 * Ekran okuyucu kullanıcısı "3 seçenekten 2." bilgisini ancak bu rolle alır;
 * bir dizi `<button>` ona yalnız birbirinden bağımsız üç buton gibi görünür.
 *
 * Seçili durum RENKLE anlatılmaz: `aria-checked` taşınır ve yüksek kontrast
 * modunda kenarlıkla da ayrışır (WCAG 2.2 §1.4.1).
 */
export function SegmentedControl<Value extends string>({
    label,
    value,
    options,
    onChange,
    disabled = false,
    className,
}: SegmentedControlProps<Value>) {
    return (
        <div
            role="radiogroup"
            aria-label={label}
            className={clsx('flex flex-wrap gap-2', className)}
        >
            {options.map((option) => {
                const selected = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        disabled={disabled}
                        onClick={() => onChange(option.value)}
                        className={clsx(
                            'min-h-[var(--density-hit-area-min)] rounded-lg border px-4 py-2 text-body font-medium',
                            'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)]',
                            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                            'disabled:cursor-not-allowed disabled:opacity-60',
                            selected
                                ? 'border-action bg-action text-action-fg'
                                : 'border-border text-fg-secondary hover:bg-surface-hover hover:text-fg',
                            'aria-checked:forced-colors:outline aria-checked:forced-colors:outline-2 aria-checked:forced-colors:outline-offset-2',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
