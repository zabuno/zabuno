import type { ReactNode } from 'react';
import { Dropdown, Button as FlowbiteButton } from 'flowbite-react';
import { MenuItem } from '../micro/MenuItem';
import { MenuItemRadio } from '../micro/MenuItemRadio';

export type ActionMenuItem = {
    key: string;
    label: string;
    onSelect: () => void;
    icon?: ReactNode;
    disabled?: boolean;
    destructive?: boolean;
};

export type ActionMenuProps = {
    /** Accessible name for the trigger button (e.g. "Row actions"). */
    label: string;
    /**
     * Tetikleyicide GÖRÜNEN içerik; verilmezse `label` yazılır.
     *
     * İkisinin ayrılması gerekiyordu: hesap menüsünün üzerinde kullanıcının
     * e-postası görünmeli ama erişilebilir adı "Account" olmalı. Tek alanla
     * bunlar aynı şey olmak zorunda kalıyordu ve ekran okuyucu, menünün ne
     * olduğunu değil yalnız bir e-posta adresi duyuyordu.
     */
    triggerContent?: ReactNode;
    /**
     * Menünün en üstünde duran, SEÇİLEMEYEN bilgi satırı.
     *
     * Hangi hesapta olduğun bir eylem değildir; bir menü maddesi olarak
     * sunulsaydı tıklanabilir görünür ve hiçbir şey yapmazdı.
     */
    header?: ReactNode;
    items: ActionMenuItem[];
    /**
     * Menü içindeki TEK SEÇİMLİK ayar — örneğin görünüm tercihi.
     *
     * Ayrı bir prop, çünkü bunlar `items` ile aynı şey değildir: `items`
     * eylemlerdir ve seçildiklerinde menü bir iş yapar; bunlar bir AYARIN
     * değerleridir ve hangisinin açık olduğu duyulmalıdır.
     */
    radioGroup?: {
        label: string;
        options: Array<{ key: string; label: string }>;
        value: string;
        onSelect: (key: string) => void;
    };
    className?: string;
};

/**
 * Compound: composes Flowbite's Dropdown (which owns the floating-ui
 * positioning, Escape handling, outside-click dismissal, and focus return to
 * the trigger on close) with Micro/Overlays/MenuItem for each action row.
 * Does not reimplement Dropdown's trigger or floating-panel markup.
 */
export function ActionMenu({
    label,
    triggerContent,
    header,
    items,
    radioGroup,
    className,
}: ActionMenuProps) {
    return (
        <Dropdown
            renderTrigger={() => (
                <FlowbiteButton aria-label={label} className={className}>
                    {triggerContent ?? label}
                </FlowbiteButton>
            )}
        >
            {header ? (
                <div className="border-b border-border px-4 py-2 text-meta text-fg-muted">
                    {header}
                </div>
            ) : null}
            {radioGroup ? (
                <>
                    <div
                        className="border-t border-border px-4 pt-2 pb-1 text-meta text-fg-muted"
                        id={`${label}-appearance`}
                    >
                        {radioGroup.label}
                    </div>
                    {radioGroup.options.map((option) => (
                        <MenuItemRadio
                            key={option.key}
                            checked={radioGroup.value === option.key}
                            onSelect={() => radioGroup.onSelect(option.key)}
                        >
                            {option.label}
                        </MenuItemRadio>
                    ))}
                </>
            ) : null}
            {items.map((item) => (
                <MenuItem
                    key={item.key}
                    icon={item.icon}
                    disabled={item.disabled}
                    destructive={item.destructive}
                    onSelect={item.onSelect}
                >
                    {item.label}
                </MenuItem>
            ))}
        </Dropdown>
    );
}
