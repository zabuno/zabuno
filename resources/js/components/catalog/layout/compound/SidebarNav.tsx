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
    /**
     * Satırın sonunda duran kısa sayı — ör. yayınlanmamış değişiklik adedi.
     *
     * Kenar çubuğu bu sayıyı KENDİ HESAPLAMAZ; çağıran verir. Rozet, bir
     * hedefin "sende bekleyen bir şey var" demesinin en ucuz yoludur ve
     * kullanıcıyı o ekrana gitmeden önce uyarır.
     */
    badge?: string;
    /**
     * Rozetin ne anlama geldiğini söyleyen TAM CÜMLE.
     *
     * Ekran okuyucu kullanan biri "Menüler 3" duyduğunda neyin üçü olduğunu
     * bilemez; sayının anlamı görsel bağlamda (sarı hap = bekleyen iş) saklı
     * kalır. Verilmezse sayının kendisi okunur — eksik ama yanlış olmayan bir
     * geri düşüş.
     */
    badgeLabel?: string;
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
            /*
                Gruplar arası nefes 24px (`--space-5`).

                Önceki 32px, referans kabuğun ritmini bozuyordu: dokuz maddelik
                bir rayda iki grup arası boşluk, grup başlığının kendisinden
                daha çok yer kaplıyor ve ray gereksiz yere uzuyordu — dar bir
                dizüstü ekranında dipteki Ayarlar satırı ilk kaybolan yerdi.
                Ayrım zaten başlıkla kuruluyor; boşluğun tek işi onu
                desteklemek.
            */
            className={clsx('flex flex-col gap-[var(--space-5)]', className)}
        >
            {groups.map((group) => (
                <div key={group.key} className="flex flex-col gap-[var(--space-1)]">
                    {/*
                        Grup başlığı gezinti öğesinden bir kademe geride durur:
                        hiyerarşi boyutla değil TONLA kurulur (Flat 2.0). Ray
                        genişliği kadar içeriden başlar ki başlık ile öğeler
                        aynı optik hizada olsun.

                        ÖLÇÜ KÜÇÜLMEZ, AĞIRLIK ARTAR (AEP `TOKEN_MAP`): başlık
                        gövdeyle aynı 1rem'de kalır, 700'e çıkar ve rengi ikinci
                        plana düşer. Küçültülmüş bir grup başlığı, ekranı kol
                        mesafesinden okuyan bir restoran sahibi için gri bir
                        lekeye dönüşüyordu. Büyük harfe de çevrilmez: Türkçede
                        "İ/ı" dönüşümü tarayıcı diline göre bozulur ve "Yönetim"
                        ekranda "YÖNETIM" diye çıkabilir.
                    */}
                    {group.label ? (
                        <span
                            id={`${groupIdPrefix}-${group.key}`}
                            className="mb-[var(--space-2)] ps-[var(--space-3)] text-meta font-bold text-fg-secondary"
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
                                    {/*
                                        Etiket ve rozet TEK bir esnek satırda
                                        durur: `w-full` sayesinde bu kutu
                                        `NavLink`'in ikondan artan alanını
                                        kaplar, rozet de `ms-auto` ile o alanın
                                        sonuna yaslanır.

                                        Rozeti doğrudan etiketin yanına
                                        koymak daha kolaydı ama sayılar satır
                                        satır farklı yerlerde dururdu; göz,
                                        etiket sütununu tararken sayıyı her
                                        seferinde yeniden aramak zorunda kalır.
                                    */}
                                    <span className="flex w-full min-w-0 items-center gap-[var(--space-2)]">
                                        <span className="truncate">{item.label}</span>
                                        {item.badge !== undefined && item.badge !== '' ? (
                                            <span
                                                data-slot="sidebar-nav-badge"
                                                className={clsx(
                                                    'ms-auto shrink-0 rounded-pill bg-action px-[var(--space-2)]',
                                                    'text-meta font-bold text-action-fg tabular-nums',
                                                )}
                                            >
                                                {/*
                                                    Sayı GÖZE, cümle KULAĞA:
                                                    ikisi aynı rozetin iki
                                                    yüzüdür ve ekranda iki kez
                                                    görünmez.
                                                */}
                                                <span aria-hidden="true">{item.badge}</span>
                                                <span className="sr-only">
                                                    {item.badgeLabel ?? item.badge}
                                                </span>
                                            </span>
                                        ) : null}
                                    </span>
                                </NavLink>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </Container>
    );
}
