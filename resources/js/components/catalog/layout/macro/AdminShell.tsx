import type { ReactNode } from 'react';
import clsx from 'clsx';
import { SkipLink } from '../micro/SkipLink';
import { TopBar, type TopBarProps } from '../compound/TopBar';
import { AdminFooter } from '../compound/AdminFooter';

export type AdminShellProps = {
    brand: TopBarProps['brand'];
    /** Mobile drawer open state — externally controlled (docs/35 §9). */
    mobileMenuOpen: boolean;
    onToggleMobileMenu: () => void;
    /** Optional slot for search/global actions in the top bar. */
    topBarCenter?: ReactNode;
    /** Optional slot for profile menu / notifications / workspace switcher. */
    topBarEnd?: ReactNode;
    /**
     * Cihaza ÖZGÜ kabuk parçaları — yuva olarak alınır, burada ÜRETİLMEZ.
     *
     * Kabuğun içinde `deviceClass === 'desktop' ? <aside/> : null` yazmak
     * yetmezdi: o dal çalışmasa bile KOD pakette bulunur, indirilir ve
     * ayrıştırılır. Telefonun masaüstü kodunu indirmemesi ancak modül
     * sınırıyla sağlanır — bu yüzden parçalar giriş noktasından geçirilir
     * (docs/54).
     */
    persistentSidebar?: ReactNode;
    navigationDrawer?: ReactNode;
    /**
     * TELEFONA özgü alt gezinti — ekranın altına YAPIŞIR.
     *
     * Telefonda gezinti hedefi başparmağın altındadır; üst çubuktaki hamburger
     * ekranın en uzak köşesindeydi ve her gezinti iki adım (aç → seç)
     * gerektiriyordu (`docs/50` §20, `docs/101` A4). Bu yuva verildiğinde üst
     * çubuktaki hamburger de KALKAR: aynı iş iki yerde durmaz.
     */
    bottomBar?: ReactNode;
    /**
     * Kabuğun PERSONASI — `docs/102` §5h.
     *
     * `platform` verildiğinde kök öğeye `data-persona="platform"` yazılır ve
     * yüzey token'ları lacivert bandına geçer. Varsayılan (verilmezse)
     * restoran kabuğudur ve KROMASIZ kalır; bir kapı testi bu ayrımı
     * dondurur.
     */
    persona?: 'platform';
    /**
     * Sağ panel — masaüstünde ana içeriğin YANINDA duran çalışma alanı.
     *
     * WordPress'in yazı düzenleyicisindeki sağ panelin karşılığı: kategoriler,
     * görünürlük, yayın durumu gibi kayda AİT ama akışı bölmemesi gereken
     * işler oraya gider. Ana alan böylece tek bir işe ayrılır.
     *
     * Bu yuva YALNIZ masaüstü paketinde doldurulur; mobil paket bu kodu hiç
     * indirmez (docs/54, adaptive yükleme).
     */
    inspector?: ReactNode;
    /** Sağ panelin erişilebilir adı ve dar ekrandaki başlığı. */
    inspectorLabel?: string;

    /** id of the main landmark; also the SkipLink target. */
    mainId?: string;
    /**
     * Telif/yasal footer'ı gösterilsin mi? Varsayılan HAYIR.
     *
     * Tenant panelinde her ekranda görünen bir telif satırı hiçbir görevin
     * parçası değildir ve küçük ekranda dikey alan harcar. Public ve Auth
     * kabuklarında gerçekten ortak alt bilgi vardır; orada `true` geçilir.
     */
    showFooter?: boolean;
    children: ReactNode;
    className?: string;
};

/**
 * Macro: composes Micro/Layout/SkipLink, Compound/Layout/TopBar,
 * ve cihaza özgü kabuk yuvaları (`persistentSidebar`, `navigationDrawer`,
 * `inspector`) etrafında bir `main` içerik bölgesi kurar. Bu parçaları
 * KENDİSİ üretmez: hangi cihaza hizmet edildiği sunucuda belirlenir ve yalnız
 * o cihazın parçası geçirilir (docs/54). Kabuğun içinde bir cihaz dalı
 * bırakmak yetmezdi — dal çalışmasa bile kod her pakete girerdi. Route/fetch/business-rule
 * agnostic — nav data,
 * active key, and drawer open state are all props; a surface owns wiring
 * this to a real route and persona (docs/35 §2a macro boundary, §4 shared
 * shell for both Restaurant Admin and Superadmin personas).
 */
export function AdminShell({
    brand,
    mobileMenuOpen,
    onToggleMobileMenu,
    topBarCenter,
    topBarEnd,
    persistentSidebar,
    navigationDrawer,
    bottomBar,
    persona,
    inspector,
    inspectorLabel = 'Details',
    mainId = 'main-content',
    showFooter = false,
    children,
    className,
}: AdminShellProps) {
    return (
        <div data-persona={persona} className={clsx('flex min-h-screen flex-col', className)}>
            <SkipLink targetId={mainId} />
            <TopBar
                brand={brand}
                /*
                    HAMBURGER yalnız gezintiye BAŞKA yol yokken çizilir.

                    Kalıcı kenar çubuğu zaten ekrandayken (masaüstü) hamburger
                    hiçbir şey açmıyordu: aynı gezinti bir tık ötede, açık
                    hâlde duruyordu. Sahibin isteği (2026-09-04) bunu
                    kaldırmaktı. Telefonda alt çubuk varsa da kalkar — aynı iş
                    iki yerde durmaz. Geriye tek meşru durum kalır: ne kalıcı
                    ray ne alt çubuk varken açılan çekmece.
                */
                onToggleMenu={bottomBar || persistentSidebar ? undefined : onToggleMobileMenu}
                menuOpen={mobileMenuOpen}
                center={topBarCenter}
                end={topBarEnd}
            />
            <div className="admin-shell-layout flex min-w-0 flex-1 flex-wrap">
                {persistentSidebar}
                {navigationDrawer}
                <main
                    id={mainId}
                    tabIndex={-1}
                    // Tonal SaaS Shell (`docs/06` §10, `docs/102` §1): soluk zemin, üstünde kartlar.
                    /*
                        Ana alan BÜYÜR, raylar büyümez. Öncesinde üçü de esnek
                        büyüme oranı taşıyordu (`4_1_32rem` / `1_1_17rem` /
                        `1_1_21rem`): bağlam paneli olan sayfada oranlar
                        yeniden dağılıyor ve KENAR ÇUBUĞU DARALIYORDU. Aynı
                        kabuk, sayfadan sayfaya farklı genişlikte görünüyordu
                        (2026-09-04 ekran incelemesi).
                    */
                    className="admin-shell-main min-w-0 flex-1 basis-[32rem] bg-[var(--color-canvas)] p-[var(--space-fluid-lg)] outline-none"
                >
                    {children}
                </main>
                {inspector ? (
                    /*
                        Bağlam paneli — YALNIZ masaüstü paketinde bulunur.

                        Gizlenmiyor, SARIYOR. Kalıcı ray için yer kalmadığında
                        (dar bir masaüstü penceresi) panel ana içeriğin altına
                        geçer ve okunmaya devam eder. `display: none` ile
                        gizlemek daha kolay olurdu ama içeriği ulaşılamaz
                        kılardı; onu geri getirmek için bir "paneli aç" düğmesi
                        gerekirdi ve o düğme, panel zaten görünürken ölü
                        kontrole dönüşürdü.

                        `flex-basis` 21rem: `docs/50` §4'teki 336–400 px
                        aralığının içinde.
                    */
                    <aside
                        aria-label={inspectorLabel}
                        className={clsx(
                            'admin-shell-inspector min-w-0 shrink-0 grow-0 basis-[21rem] flex-col gap-[var(--space-fluid-sm)] border-s',
                            'border-[var(--color-border)] px-[var(--space-fluid-md)] py-[var(--space-fluid-md)]',
                        )}
                    >
                        {inspector}
                    </aside>
                ) : null}
            </div>
            {/*
                Tenant uygulamasında kalıcı telif footer'ı YOKTUR.

                Her ekranda görünen bir telif satırı, restoran sahibinin
                hiçbir görevine ait değildir ve 320×480'de dikey alan
                harcar. Yasal bağlantılar ve sürüm bilgisi Account → About
                altına aittir (`docs/50` Faz 1).

                `contentinfo` landmark'ı Public ve Auth kabuklarında kalır —
                orada gerçekten ortak alt bilgi vardır.
            */}
            {/*
                Alt gezinti ana içeriğin DIŞINDA ve `main`'den sonra: ekran
                okuyucu önce içeriği okur. `sticky` değil `fixed` de değil —
                kabuk zaten tam yükseklikte; alt çubuk normal akışta durur ve
                içeriğin üstüne binmez (`docs/50` §20: hiçbir kontrol içeriğin
                üstüne kalıcı olarak binmez).
            */}
            {bottomBar}

            {showFooter ? (
                <AdminFooter productName={brand.name} currentYear={new Date().getFullYear()} />
            ) : null}
        </div>
    );
}
