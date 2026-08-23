import type { MouseEvent, ReactNode } from 'react';
import clsx from 'clsx';
import { NavLink } from '../../navigation/micro/NavLink';

export type SidebarNavNode = {
    key: string;
    label: string;
    href?: string;
    icon?: ReactNode;
    onSelect?: (event: MouseEvent<HTMLAnchorElement | HTMLButtonElement>) => void;
    disabled?: boolean;
};

export type SidebarNavGroup = {
    key: string;
    /** Optional group heading, e.g. "Menu" / "Ayarlar". Omit for an ungrouped list. */
    label?: string;
    items: SidebarNavNode[];
};

export type SidebarNavProps = {
    groups: SidebarNavGroup[];
    /** Currently active item's `key`, used to set `aria-current` on the matching NavLink. */
    activeKey?: string;
    /** Accessible name for the `<nav>` landmark; defaults to "Primary". */
    label?: string;
    className?: string;
};

/**
 * Compound: composes Micro/Navigation/NavLink for every item, grouped under
 * optional headings. Renders whatever `groups`/`activeKey` it is given — it
 * does not know which persona (restaurant-admin/superadmin) it belongs to,
 * does not fetch a nav tree, and does not decide routing (docs/35 §4).
 */
export function SidebarNav({ groups, activeKey, label = 'Primary', className }: SidebarNavProps) {
    return (
        <nav aria-label={label} className={clsx('flex flex-col gap-4', className)}>
            {groups.map((group) => (
                <div key={group.key} className="flex flex-col gap-1">
                    {group.label ? (
                        <span className="px-3 text-xs font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">
                            {group.label}
                        </span>
                    ) : null}
                    <ul className="flex flex-col gap-0.5">
                        {group.items.map((item) => (
                            <li key={item.key}>
                                <NavLink
                                    href={item.href}
                                    onSelect={item.onSelect}
                                    icon={item.icon}
                                    disabled={item.disabled}
                                    current={item.key === activeKey}
                                    className="w-full"
                                >
                                    {item.label}
                                </NavLink>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </nav>
    );
}
