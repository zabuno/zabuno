import clsx from 'clsx';
import type { ReactNode } from 'react';
import { t } from '../../../i18n/workspace';
import type { RailSection } from './railSection';
import { SidebarNav, type SidebarNavGroup } from '../../catalog/layout/compound/SidebarNav';
import { WorkspaceSwitcherTrigger, type WorkspaceSwitcherOption } from './WorkspaceSwitcherTrigger';

/**
 * MASAÜSTÜNE ÖZGÜ kabuk parçaları.
 *
 * Bu modül YALNIZ `workspace.desktop.tsx` girişinden içeri alınır. Telefonla
 * açan kullanıcı bu dosyanın hiçbir baytını indirmez — kabuğun içinde
 * `deviceClass === 'desktop'` diye bir dal bırakmak yetmezdi: o dal
 * çalışmasa bile KOD pakette bulunur, indirilir ve ayrıştırılır.
 *
 * Ayrımın tek anlamlı yeri modül sınırıdır.
 */
export type DesktopChromeProps = {
    navGroups: SidebarNavGroup[];
    activeNavKey?: string;
    navLabel?: string;
    workspaceName?: string;
    /** Seçilebilir çalışma alanları; tek taneyse seçici menü açılmaz. */
    workspaces?: WorkspaceSwitcherOption[];
    currentWorkspaceId?: number;
    onSelectWorkspace?: (workspaceId: number) => void;
    accountMenu?: ReactNode;
    /**
     * Rayın dibindeki sabit blokta duran hedefler — Profil ve Ayarlar
     * (FF-127).
     *
     * Kabuk bu listeyi KENDİ ÜRETMEZ: bölüm kaydından türetilip verilir.
     * İkinci bir liste tutulsaydı bir bölümün izni değiştiğinde ray onu
     * göstermeye devam eder ve kullanıcı 403 görürdü.
     */
    railSections?: readonly RailSection[];
};

export type { RailSection };

/** Kalıcı birincil kenar çubuğu — `docs/50` §4: 248–272 px. */
export function DesktopSidebar({
    navGroups,
    activeNavKey,
    navLabel,
    workspaceName,
    workspaces,
    currentWorkspaceId,
    onSelectWorkspace,
    accountMenu,
    railSections,
}: DesktopChromeProps): ReactNode {
    return (
        <aside
            className={clsx(
                // Genişlik SABİT (`docs/102`): sayfa bağlam paneli açsa da ray daralmaz.
                'admin-shell-sidebar flex shrink-0 grow-0 basis-[17rem] flex-col border-e bg-[var(--color-surface)]',
                'border-[var(--color-border)] px-[var(--space-fluid-md)] py-[var(--space-fluid-md)]',
                /*
                    RAY GÖRÜNTÜ ALANI KADAR (sahibin bildirimi, 2026-09-04).

                    Kabuk artık tam ekran yüksekliğinde ve kaydırma ana alanda
                    (`AdminShell`). Ray da kendi içinde kayar: gezinti uzarsa
                    ray kayar, ama dibindeki hesap düğmesi HER ZAMAN ekranda
                    kalır — çünkü ray sayfa kadar değil, ekran kadar uzun.
                */
                'min-h-0 overflow-y-auto',
            )}
        >
            <WorkspaceSwitcherTrigger
                workspaceName={workspaceName}
                workspaces={workspaces}
                currentWorkspaceId={currentWorkspaceId}
                onSelectWorkspace={onSelectWorkspace}
            />
            <SidebarNav groups={navGroups} activeKey={activeNavKey} label={navLabel} />

            {/*
                Hesap tetikleyicisi kenar çubuğunun DİBİNDE — `docs/50` §7.

                `mt-auto` ile aşağı itilir, gezintinin arasına karışmaz.
                Buraya YALNIZ yardımcı işler konur: günlük kritik bir görev
                burada saklanmamalıdır, çünkü dar pencerelerde kenar çubuğunun
                alt kısmı ilk kaybolan yerdir.
            */}
            {(accountMenu !== undefined && accountMenu !== null) ||
            (railSections !== undefined && railSections.length > 0) ? (
                /*
                    YAPIŞKAN (sahibin isteği, 2026-09-04).

                    `mt-auto` düğmeyi rayın dibine iter, ama gezinti uzayıp ray
                    kendi içinde kaydığında düğme yukarı kayıp ekrandan
                    çıkardı. `sticky bottom-0` onu rayın görünen alanının
                    dibine çiviler; gezinti altından akar.

                    Zemin ŞART: yapışkan bir öğe saydam olsaydı, altından
                    geçen gezinti maddeleri düğmenin içinden okunurdu. Üst
                    kenarlık, kaydırılacak içerik olduğunu söyleyen ince bir
                    işarettir.
                */
                <div className="sticky bottom-0 mt-auto border-t border-[var(--color-border)] bg-[var(--color-surface)] pt-[var(--space-fluid-sm)]">
                    {railSections !== undefined && railSections.length > 0 ? (
                        /*
                            PROFİL VE AYARLAR AÇIKTA (FF-127).

                            İkisi de kayıtta grupsuzdur, yani gruplu listede
                            çizilmezler; tek yolları hesap menüsünün İÇİYDİ.
                            Günlük olmayan ama sık aranan hedefler bir açılır
                            menünün ardında durunca, kullanıcı "nerede?"
                            sorusunu her seferinde yeniden sorar.

                            Kendi gezinti adı vardır: adsız ikinci bir gezinti
                            bölgesi ekran okuyucuda iki kez "gezinti" diye
                            okunur ve hangisinde olunduğu anlaşılmaz.
                        */
                        <nav
                            aria-label={t('workspace.shell.nav.account')}
                            className="mb-[var(--space-2)] flex flex-col gap-[var(--space-1)]"
                        >
                            {railSections.map((section) => (
                                <a
                                    key={section.key}
                                    href={section.href}
                                    onClick={section.onSelect}
                                    aria-current={section.active ? 'page' : undefined}
                                    className={clsx(
                                        'inline-flex w-full items-center gap-[var(--space-3)] rounded-[var(--radius-md)]',
                                        'min-h-[var(--control-height)] px-[var(--space-3)] py-[var(--space-2)]',
                                        'text-body whitespace-nowrap',
                                        'hover:bg-[var(--color-surface-hover)]',
                                        'focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                                        section.active
                                            ? 'bg-[var(--color-surface-active)] font-medium text-fg'
                                            : 'text-fg-secondary',
                                    )}
                                >
                                    {section.icon}
                                    {section.label}
                                </a>
                            ))}
                        </nav>
                    ) : null}
                    {accountMenu}
                </div>
            ) : null}
        </aside>
    );
}
