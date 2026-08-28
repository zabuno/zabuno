import { forwardRef, useEffect, useId, useRef, useState, type ReactNode } from 'react';
import clsx from 'clsx';
import { Select as FlowbiteSelect } from 'flowbite-react';
import type { SelectProps as FlowbiteSelectProps } from 'flowbite-react';
import { selectTokenTheme } from '../../../../design-system/flowbite-theme';

export type SelectProps = FlowbiteSelectProps & {
    invalid?: boolean;
};

type Option = { value: string; label: string; disabled: boolean };

/**
 * `<option>` çocuklarını okunabilir bir listeye çevirir.
 *
 * Çağıranların hepsi bugün düz `<option>` yazıyor; onları değiştirmemek
 * kasıtlı. Sözleşmeyi değiştirmek on iki çağrı yerini birden dokunmak
 * demekti ve bu paketin konusu GÖRÜNÜM.
 */
function readOptions(children: ReactNode): Option[] {
    const out: Option[] = [];

    const walk = (node: ReactNode): void => {
        if (Array.isArray(node)) {
            node.forEach(walk);

            return;
        }

        if (node === null || typeof node !== 'object' || !('props' in node)) {
            return;
        }

        const element = node as { type?: unknown; props?: Record<string, unknown> };

        if (element.type === 'optgroup') {
            walk(element.props?.children as ReactNode);

            return;
        }

        if (element.type !== 'option') {
            return;
        }

        const props = element.props ?? {};
        const label =
            typeof props.children === 'string' ? props.children : String(props.value ?? '');

        out.push({
            value: String(props.value ?? label),
            label,
            disabled: props.disabled === true,
        });
    };

    walk(children);

    return out;
}

/**
 * Micro building block: a bare select field.
 *
 * ## Neden yerli açılır liste kullanılmıyor
 *
 * `<select>`'in AÇILAN paneli işletim sistemi tarafından çizilir. Chrome
 * macOS'ta kendi koyu panelini, Safari macOS'un yerli menüsünü gösterir;
 * ikisi farklı görünür ve **CSS ile eşitlenemez** — panel sayfanın DOM'unda
 * değildir.
 *
 * Bu yüzden panel kendimiz çiziyoruz. Ama `<select>`'in kendisi DOM'da
 * KALIYOR ve kontrolün sahibi o:
 *
 * - Erişilebilir ad, `aria-invalid`, form gönderimi ve klavye onun.
 * - Ok tuşları, Home/End ve harfle arama tarayıcıdan gelir; taklit edilmez.
 * - Ekran okuyucu kullanıcısı yerli listeyi duyar.
 *
 * Bizim panelimiz yalnız İŞARETÇİ için bir görsel karşılıktır ve bu yüzden
 * `aria-hidden`: aynı seçenekler ekran okuyucuya iki kez okunmamalı.
 *
 * Yerli panelin açılması `mousedown`'da engellenir — tarayıcıların üçünde de
 * çalışan yol budur.
 */
export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
    { invalid = false, color, disabled = false, className, children, ...rest },
    ref,
) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const selectRef = useRef<HTMLSelectElement | null>(null);
    const listId = useId();

    const options = readOptions(children);
    const currentValue = String(rest.value ?? rest.defaultValue ?? '');

    useEffect(() => {
        if (!open) {
            return;
        }

        function onPointerDown(event: MouseEvent): void {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        function onKeyDown(event: KeyboardEvent): void {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    /**
     * Seçim, YERLİ öğe üzerinden yapılır.
     *
     * Doğrudan `onChange` çağırmak, kontrollü bir formda `value`'yu
     * güncellemeden olay göndermek olurdu; React'in beklediği yol öğenin
     * kendi değerini değiştirip `change` yaymaktır.
     */
    function choose(value: string): void {
        const element = selectRef.current;

        if (element === null) {
            return;
        }

        const setter = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value')?.set;

        setter?.call(element, value);
        element.dispatchEvent(new Event('change', { bubbles: true }));
        setOpen(false);
        element.focus();
    }

    return (
        <div ref={containerRef} className="relative">
            <FlowbiteSelect
                theme={selectTokenTheme}
                applyTheme="replace"
                ref={(node: HTMLSelectElement | null) => {
                    selectRef.current = node;

                    if (typeof ref === 'function') {
                        ref(node);
                    } else if (ref !== null) {
                        ref.current = node;
                    }
                }}
                disabled={disabled}
                aria-invalid={invalid || undefined}
                aria-disabled={disabled || undefined}
                aria-expanded={open || undefined}
                color={color ?? (invalid ? 'failure' : undefined)}
                className={className}
                onMouseDown={(event: React.MouseEvent<HTMLSelectElement>) => {
                    if (disabled) {
                        return;
                    }

                    // Yerli panel AÇILMAZ; yerine kendi panelimiz açılır.
                    event.preventDefault();
                    setOpen((wasOpen) => !wasOpen);
                }}
                {...rest}
            >
                {children}
            </FlowbiteSelect>

            {open ? (
                <ul
                    /*
                        `aria-hidden`: erişilebilir kontrol `<select>`'in
                        kendisidir ve ekran okuyucu onun yerli listesini
                        duyar. Bu panel yalnız işaretçi için bir görsel
                        karşılıktır; aynı seçenekleri ikinci kez duyurmak
                        kullanıcıyı iki listeyle baş başa bırakırdı.
                    */
                    aria-hidden="true"
                    id={listId}
                    className={clsx(
                        'absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-lg border',
                        'border-border bg-surface py-1 shadow-lg',
                    )}
                >
                    {options.map((option) => (
                        <li key={option.value}>
                            <button
                                type="button"
                                tabIndex={-1}
                                disabled={option.disabled}
                                onClick={() => choose(option.value)}
                                className={clsx(
                                    'flex min-h-[var(--density-hit-area-min)] w-full items-center gap-2',
                                    'px-3 text-start text-body',
                                    option.value === currentValue ? 'text-fg' : 'text-fg-secondary',
                                    'hover:bg-surface-hover disabled:pointer-events-none disabled:opacity-60',
                                )}
                            >
                                {/*
                                    Seçili olan RENKLE değil işaretle
                                    ayrılır: yüksek kontrast modunda ve renk
                                    körlüğünde renk kaybolur.
                                */}
                                <span aria-hidden="true" className="w-[2ch] shrink-0 text-center">
                                    {option.value === currentValue ? '•' : ''}
                                </span>
                                <span className="truncate">{option.label}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
});
