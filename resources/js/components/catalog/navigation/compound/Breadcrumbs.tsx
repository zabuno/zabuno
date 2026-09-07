import type { MouseEvent } from 'react';
import clsx from 'clsx';
import { NavLink } from '../micro/NavLink';
import { VisuallyHidden } from '../micro/VisuallyHidden';

export type BreadcrumbItem = {
    key: string;
    label: string;
    href?: string;
    onSelect?: (event: MouseEvent<HTMLAnchorElement | HTMLButtonElement>) => void;
};

export type BreadcrumbsProps = {
    items: BreadcrumbItem[];
    /**
     * Accessible name for the `<nav>` landmark. REQUIRED — `docs/121` Ö1.
     *
     * Varsayılanı `'Breadcrumb'` idi: kodda gömülü, katalogda görünmeyen,
     * hiçbir gün çevrilemeyecek bir kelime. Ürünün İKİ çağıranı da (sayfa
     * başlığı ve kabuk) varsayılana düşüyordu, yani bu metin ekranda
     * her zaman İngilizceydi.
     */
    label: string;
    /**
     * İz BOŞKEN ekran okuyucuya söylenen cümle. ZORUNLU — aynı sebeple.
     *
     * `'Empty breadcrumb trail'` de kodda gömülüydü. Adı olan ama içi boş
     * bir gezinti bölgesi sessiz kalamaz; ne söyleyeceğini ise yalnız
     * çağıranın kataloğu bilir.
     */
    emptyLabel: string;
    className?: string;
};

/**
 * Compound: composes Micro/Navigation/NavLink for every non-terminal
 * crumb. Does not reimplement NavLink's markup or focus/current-page
 * handling — the final item is rendered as plain text with
 * `aria-current="page"` per WAI-ARIA breadcrumb pattern, since the current
 * page is not itself a navigable link.
 */
export function Breadcrumbs({ items, label, emptyLabel, className }: BreadcrumbsProps) {
    return (
        <nav aria-label={label} className={className}>
            <ol className="flex flex-wrap items-center gap-1 text-body">
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <li key={item.key} className="flex items-center gap-1">
                            {index > 0 ? (
                                <span aria-hidden="true" className="text-fg-muted rtl:-scale-x-100">
                                    /
                                </span>
                            ) : null}
                            {isLast ? (
                                <span
                                    aria-current="page"
                                    className={clsx('px-3 py-2 font-bold text-fg')}
                                >
                                    {item.label}
                                </span>
                            ) : item.href === undefined && item.onSelect === undefined ? (
                                /*
                                    Gidilecek yeri olmayan kırıntı DÜZ METİNDİR.
                                    Öncesinde bu durumda hiçbir şey yapmayan bir
                                    düğme çiziliyordu: tıklanabilir görünen ama
                                    tıklanınca hiçbir şey olmayan bir kontrol,
                                    kullanıcıya ürünün bozuk olduğunu öğretir.
                                */
                                <span className="px-3 py-2 text-fg-secondary">{item.label}</span>
                            ) : (
                                <NavLink href={item.href} onSelect={item.onSelect}>
                                    {item.label}
                                </NavLink>
                            )}
                        </li>
                    );
                })}
            </ol>
            {items.length === 0 ? <VisuallyHidden>{emptyLabel}</VisuallyHidden> : null}
        </nav>
    );
}
