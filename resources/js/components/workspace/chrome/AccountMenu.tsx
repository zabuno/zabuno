import { t } from '../../../i18n/workspace';
import { t as themeText } from '../../../i18n/theme';
import { ActionMenu } from '../../catalog/overlays/compound/ActionMenu';
import {
    THEME_OPTION_ORDER,
    themeOptionLabels,
    useThemeControl,
    type ThemePreference,
} from '../../theme/ThemeRoot';

export type AccountMenuProps = {
    email: string;
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
            triggerContent={<span className="max-w-[18ch] truncate text-meta">{email}</span>}
            header={email}
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
                {
                    key: 'switch-workspace',
                    label: t('workspace.current.switch'),
                    onSelect: onSwitchWorkspace,
                },
                {
                    key: 'logout',
                    label: t('workspace.current.logout'),
                    onSelect: onLogout,
                    disabled: loggingOut,
                },
            ]}
        />
    );
}
