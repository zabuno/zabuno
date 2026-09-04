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
     * Tetikleyicinin AĞIRLIĞI.
     *
     * `primary` marka rengiyle çizer ve sayfanın ana eylemi için ayrılmıştır
     * (global "Create"). `quiet` sessiz bir yüzey düğmesidir: hesap menüsü
     * gibi YARDIMCI girişler marka rengini tüketmemeli — 2026-09-04'te sol
     * alttaki hesap düğmesi parlak sarı bir çağrı gibi duruyordu ve
     * sayfadaki gerçek birincil eylemle yarışıyordu (`docs/102` §1).
     */
    tone?: 'primary' | 'quiet';
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
    tone = 'primary',
    className,
}: ActionMenuProps) {
    return (
        <Dropdown
            // Panelin kendi dolgusu: satırlar kenara yapışmaz, hover vurgusu
            // içeriden yuvarlanır (`docs/102` §5f).
            // Yüzey token'dan: Flowbite'ın mavi tonlu varsayılan grisi değil (`docs/102` §5h).
            className="min-w-[16rem] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-1)] text-fg-secondary"
            renderTrigger={() => (
                <FlowbiteButton
                    aria-label={label}
                    color={tone === 'quiet' ? 'light' : undefined}
                    className={className}
                >
                    {triggerContent ?? label}
                </FlowbiteButton>
            )}
        >
            {header ? (
                <div className="border-b border-border px-[var(--space-3)] py-[var(--space-3)]">
                    {header}
                </div>
            ) : null}
            {radioGroup ? (
                <>
                    <div
                        className="px-[var(--space-3)] pt-[var(--space-3)] pb-[var(--space-1)] text-caption font-semibold uppercase tracking-[0.08em] text-fg-muted"
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
                    <div className="my-[var(--space-1)] border-t border-border" />
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
