import { ArrowsLeftRight, Gear, SignOut, UserCircle } from '@phosphor-icons/react';
import { t } from '../../../i18n/workspace';
import { t as themeText } from '../../../i18n/theme';
import { ActionMenu } from '../../catalog/overlays/compound/ActionMenu';
import {
    THEME_OPTION_ORDER,
    themeOptionLabels,
    useThemeControl,
    type ThemePreference,
} from '../../theme/themeControl';

export type AccountMenuProps = {
    /**
     * Profil ekranına geçiş — sahibin isteği (2026-09-04). Kişi kendi adını,
     * fotoğrafını, temasını ve markasının renklerini oradan düzenler.
     */
    onOpenProfile?: () => void;
    /** Ayarlar ekranına geçiş — sahibin kararı: ayarlar sistem menüsünde (FF-84). */
    onOpenSettings?: () => void;
    email: string;
    /**
     * Profil fotoğrafının önizleme adresi (FF-90). Yoksa baş harf dairesi
     * çizilir — yükleyen kişi fotoğrafını burada görmeli, yoksa yükleme
     * hiçbir yere varmamış gibi durur.
     */
    avatarUrl?: string | null;
    onSwitchWorkspace: () => void;
    onLogout: () => void;
    loggingOut?: boolean;
    className?: string;
};

/**
 * Hesap menüsü — `docs/50` §7, `docs/63`.
 *
 * Burada YALNIZ kişisel ve oturumla ilgili işler bulunur. Plan bu sınırı
 * açıkça çiziyor: organizasyon yönetimi, plan, ekip, marka ve entegrasyonlar
 * buraya GİRMEZ. Girdikleri anda menü bir "her şey çekmecesi"ne döner ve
 * kullanıcı bir ayarı ararken önce menünün kendisini aramaya başlar.
 *
 * Görünüm tercihi buraya taşındı. Önceden uygulamanın dibinde, her sayfanın
 * altında yüzen bir çubuktu: 320×480'de ekranın kalıcı olarak yaklaşık
 * %12'sini kaplıyor ve içeriğin üstüne biniyordu. Tema hiçbir sayfanın görevi
 * değildir; kişisel bir tercihtir.
 */
export function AccountMenu({
    email,
    avatarUrl = null,
    onOpenProfile,
    onOpenSettings,
    onSwitchWorkspace,
    onLogout,
    loggingOut = false,
    className,
}: AccountMenuProps) {
    const theme = useThemeControl();

    return (
        <ActionMenu
            label={t('workspace.account.menu.label')}
            className={className}
            tone="quiet"
            triggerContent={
                <span className="flex w-full items-center gap-[var(--space-2)]">
                    {/* Fotoğraf varsa o, yoksa baş harf: e-posta uzun, göz önce kime ait olduğunu arar. */}
                    {avatarUrl === null ? (
                        <span
                            aria-hidden="true"
                            className="flex h-[1.75rem] w-[1.75rem] shrink-0 items-center justify-center rounded-pill bg-[var(--color-surface-active)] text-meta font-semibold text-fg"
                        >
                            {email.slice(0, 1).toLocaleUpperCase()}
                        </span>
                    ) : (
                        <img
                            src={avatarUrl}
                            alt=""
                            aria-hidden="true"
                            className="h-[1.75rem] w-[1.75rem] shrink-0 rounded-pill object-cover"
                        />
                    )}
                    <span className="max-w-[18ch] truncate text-meta text-fg-secondary">
                        {email}
                    </span>
                </span>
            }
            header={
                <span className="flex items-center gap-[var(--space-3)]">
                    {avatarUrl === null ? (
                        <span
                            aria-hidden="true"
                            className="flex h-[2rem] w-[2rem] shrink-0 items-center justify-center rounded-pill bg-[var(--color-surface-active)] text-body font-semibold text-fg"
                        >
                            {email.slice(0, 1).toLocaleUpperCase()}
                        </span>
                    ) : (
                        <img
                            src={avatarUrl}
                            alt=""
                            aria-hidden="true"
                            className="h-[2rem] w-[2rem] shrink-0 rounded-pill object-cover"
                        />
                    )}
                    <span className="flex min-w-0 flex-col">
                        <span className="truncate text-body font-medium text-fg">{email}</span>
                        <span className="text-caption uppercase tracking-[0.08em] text-fg-muted">
                            {t('workspace.account.menu.label')}
                        </span>
                    </span>
                </span>
            }
            /*
                Görünüm bölümü YALNIZ tema sağlayıcısı varsa çizilir. Yoksa
                tercih hiçbir yere yazılamaz ve düğme tıklanır ama hiçbir şey
                olmaz — çalışmayan bir kontrol, olmayan bir kontroldan kötüdür.
            */
            radioGroup={
                theme === null
                    ? undefined
                    : {
                          label: themeText('theme.group_label'),
                          value: theme.preference,
                          options: THEME_OPTION_ORDER.map((option) => ({
                              key: option,
                              label: themeOptionLabels[option](),
                          })),
                          onSelect: (key) => theme.choose(key as ThemePreference),
                      }
            }
            items={[
                /*
                    PROFİL en üstte: menünün başlığı zaten kişinin kendisidir
                    (baş harf + e-posta), ve oradan devam eden ilk madde de
                    kişiye ait olmalıdır. Ayarlar çalışma alanına aittir ve
                    onun altında durur.
                */
                ...(onOpenProfile
                    ? [
                          {
                              key: 'profile',
                              label: t('workspace.profile.title'),
                              icon: <UserCircle size={18} />,
                              onSelect: onOpenProfile,
                          },
                      ]
                    : []),
                /*
                    AYARLAR sistem menüsünde — sahibin kararı (2026-09-04).
                    `docs/50` §8 onu kenar çubuğunun "utility" grubuna
                    koyuyordu; ayrı bir grup başlığı altında tek bir madde,
                    her ekranda dikey alan harcayan bir bölüm üretiyordu.
                    Kayıttaki adres değişmedi: `/app/{ws}/settings` aynı yer.
                */
                ...(onOpenSettings
                    ? [
                          {
                              key: 'settings',
                              label: t('workspace.shell.nav.settings'),
                              icon: <Gear size={18} />,
                              onSelect: onOpenSettings,
                          },
                      ]
                    : []),
                {
                    key: 'switch-workspace',
                    label: t('workspace.current.switch'),
                    icon: <ArrowsLeftRight size={18} />,
                    onSelect: onSwitchWorkspace,
                },
                {
                    key: 'logout',
                    label: t('workspace.current.logout'),
                    icon: <SignOut size={18} />,
                    onSelect: onLogout,
                    disabled: loggingOut,
                },
            ]}
        />
    );
}
