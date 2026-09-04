import { useId } from 'react';
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
    /**
     * `<nav>` landmark'ı olarak render edilsin mi? Zaten adlandırılmış bir
     * diyalog/çekmece İÇİNDE kullanıldığında `false` verilir: kapsayıcı adı
     * bağlamı sağlar ve aynı adı taşıyan ikinci bir landmark, ekran okuyucu
     * landmark listesinde ayırt edilemeyen bir çift üretir.
     */
    asLandmark?: boolean;
    className?: string;
};

/**
 * Compound: composes Micro/Navigation/NavLink for every item, grouped under
 * optional headings. Renders whatever `groups`/`activeKey` it is given — it
 * does not know which persona (restaurant-admin/superadmin) it belongs to,
 * does not fetch a nav tree, and does not decide routing (docs/35 §4).
 */
export function SidebarNav({
    groups,
    activeKey,
    label = 'Primary',
    asLandmark = true,
    className,
}: SidebarNavProps) {
    /*
        Örneğe özgü kimlik öneki.

        `AdminShell` bu bileşeni İKİ KEZ render eder: kalıcı ray ve mobil
        çekmece. Sabit bir `id` kullanılsaydı DOM'da yinelenen kimlikler
        oluşur ve `aria-labelledby` her zaman İLK kopyaya bağlanırdı — yani
        çekmeceden gezinen kullanıcı, ekranda görmediği bir listenin adını
        duyardı.
    */
    const groupIdPrefix = useId();

    const Container = asLandmark ? 'nav' : 'div';

    return (
        <Container
            aria-label={asLandmark ? label : undefined}
            className={clsx('flex flex-col gap-[var(--space-6)]', className)}
        >
            {groups.map((group) => (
                <div key={group.key} className="flex flex-col gap-[var(--space-1)]">
                    {/*
                        Grup başlığı gezinti öğesinden bir kademe geride durur:
                        hiyerarşi boyutla değil TONLA kurulur (Flat 2.0). Ray
                        genişliği kadar içeriden başlar ki başlık ile öğeler
                        aynı optik hizada olsun.
                    */}
                    {group.label ? (
                        <span
                            id={`${groupIdPrefix}-${group.key}`}
                            className="mb-[var(--space-2)] ps-[var(--space-3)] text-meta font-semibold text-fg-subtle"
                        >
                            {group.label}
                        </span>
                    ) : null}
                    {/*
                        Başlık, listeye BAĞLANIR (`aria-labelledby`).

                        Önceden yalnız başlık gibi görünen bir `<span>`di ve
                        hiçbir şeyi etiketlemiyordu: ekran okuyucu kullanan biri
                        kenar çubuğunda birbirinin aynı, adsız listeler duyuyordu
                        — gruplamanın getirdiği bütün bilgi görsel katmanda
                        kalıyor, onlara hiç ulaşmıyordu.
                    */}
                    <ul
                        aria-labelledby={group.label ? `${groupIdPrefix}-${group.key}` : undefined}
                        className="flex flex-col gap-[var(--space-1)]"
                    >
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
        </Container>
    );
}
