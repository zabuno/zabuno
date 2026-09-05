import type { KeyboardEvent, ReactNode } from 'react';
import { useId, useRef } from 'react';
import clsx from 'clsx';

export type TabItem = {
    key: string;
    label: string;
    /** Optional leading icon node; the caller owns the icon implementation. */
    icon?: ReactNode;
    disabled?: boolean;
    panel: ReactNode;
};

export type TabsProps = {
    items: TabItem[];
    /** Key of the currently selected tab — Tabs is fully controlled. */
    selectedKey: string;
    onChange: (key: string) => void;
    /** Accessible name for the tablist. */
    label: string;
    className?: string;
};

/**
 * Compound: a tablist + tabpanel pair following the WAI-ARIA Tabs pattern
 * (manual activation would also be valid, but selection-follows-focus with
 * roving tabindex is used here since panel switches are cheap/local — no
 * fetch is triggered by moving focus). Arrow Left/Right move focus and
 * selection; Home/End jump to the first/last enabled tab. Left/Right are
 * swapped automatically in RTL via the container's `dir` inheritance —
 * ArrowRight always moves in reading-order-forward, ArrowLeft backward,
 * so behavior is correct without inspecting `dir` explicitly.
 */
export function Tabs({ items, selectedKey, onChange, label, className }: TabsProps) {
    const baseId = useId();
    const tabRefs = useRef<Record<string, HTMLButtonElement | null>>({});

    const enabledItems = items.filter((item) => !item.disabled);
    const selectedItem = items.find((item) => item.key === selectedKey) ?? items[0];

    function focusAndSelect(key: string) {
        onChange(key);
        tabRefs.current[key]?.focus();
    }

    function handleKeyDown(event: KeyboardEvent<HTMLButtonElement>, currentKey: string) {
        const currentIndex = enabledItems.findIndex((item) => item.key === currentKey);
        if (currentIndex === -1) {
            return;
        }

        let nextIndex: number;
        switch (event.key) {
            case 'ArrowRight':
                nextIndex = (currentIndex + 1) % enabledItems.length;
                break;
            case 'ArrowLeft':
                nextIndex = (currentIndex - 1 + enabledItems.length) % enabledItems.length;
                break;
            case 'Home':
                nextIndex = 0;
                break;
            case 'End':
                nextIndex = enabledItems.length - 1;
                break;
            default:
                return;
        }

        event.preventDefault();
        const nextItem = enabledItems[nextIndex];
        if (nextItem) {
            focusAndSelect(nextItem.key);
        }
    }

    return (
        <div className={className}>
            <div role="tablist" aria-label={label} className="flex gap-1 border-b border-border">
                {items.map((item) => {
                    const isSelected = item.key === selectedItem?.key;

                    return (
                        <button
                            key={item.key}
                            ref={(node) => {
                                tabRefs.current[item.key] = node;
                            }}
                            type="button"
                            role="tab"
                            id={`${baseId}-tab-${item.key}`}
                            aria-selected={isSelected}
                            aria-controls={`${baseId}-panel-${item.key}`}
                            aria-disabled={item.disabled || undefined}
                            tabIndex={isSelected ? 0 : -1}
                            disabled={item.disabled}
                            onClick={() => focusAndSelect(item.key)}
                            onKeyDown={(event) => handleKeyDown(event, item.key)}
                            className={clsx(
                                /*
                                    SEKME YÜKSEKLİĞİ JETONDAN GELİR
                                    (`docs/117` M2).

                                    Ölçüldü (320×568): sekme 75×42 çiziliyordu.
                                    42 bir karar değildi, bir TOPLAMDI —
                                    `py-2` + satır yüksekliği + alt çizgi. Yani
                                    hedef boyu hiç seçilmemişti; ortaya çıkmıştı.

                                    `min-h` ile bağlanınca yükseklik bir daha
                                    dolgunun yan ürünü olmaz ve yoğunluk modu
                                    değiştiğinde birlikte hareket eder.
                                */
                                'inline-flex min-h-[var(--control-height)] items-center gap-2 border-b-2 px-3 py-2 text-body font-medium',
                                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                                isSelected
                                    ? 'border-focus text-fg-link'
                                    : 'border-transparent text-fg-secondary hover:text-fg',
                                item.disabled && 'pointer-events-none opacity-50',
                            )}
                        >
                            {item.icon ? (
                                <span aria-hidden="true" className="shrink-0">
                                    {item.icon}
                                </span>
                            ) : null}
                            <span>{item.label}</span>
                        </button>
                    );
                })}
            </div>
            {items.map((item) => (
                <div
                    key={item.key}
                    role="tabpanel"
                    id={`${baseId}-panel-${item.key}`}
                    aria-labelledby={`${baseId}-tab-${item.key}`}
                    hidden={item.key !== selectedItem?.key}
                    tabIndex={0}
                    className="py-4"
                >
                    {item.panel}
                </div>
            ))}
        </div>
    );
}
