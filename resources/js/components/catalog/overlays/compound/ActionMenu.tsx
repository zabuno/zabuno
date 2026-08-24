import type { ReactNode } from 'react';
import { Dropdown, Button as FlowbiteButton } from 'flowbite-react';
import { MenuItem } from '../micro/MenuItem';

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
    items: ActionMenuItem[];
    className?: string;
};

/**
 * Compound: composes Flowbite's Dropdown (which owns the floating-ui
 * positioning, Escape handling, outside-click dismissal, and focus return to
 * the trigger on close) with Micro/Overlays/MenuItem for each action row.
 * Does not reimplement Dropdown's trigger or floating-panel markup.
 */
export function ActionMenu({ label, items, className }: ActionMenuProps) {
    return (
        <Dropdown
            renderTrigger={() => (
                <FlowbiteButton aria-label={label} className={className}>
                    {label}
                </FlowbiteButton>
            )}
        >
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
