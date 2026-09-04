import { t } from '../../../../i18n/workspace';
import { t as themeText } from '../../../../i18n/theme';
import {
    THEME_OPTION_ORDER,
    themeOptionLabels,
    useThemeControl,
    type ThemePreference,
} from '../../../theme/themeControl';

/**
 * Görünüm — sahibin isteği (2026-09-04): "tokens (renk, theme) buradan
 * değiştirebilsin".
 *
 * Tema KİŞİSELDİR ve markaya ait değildir: aynı restoranın gündüz çalışan
 * müdürü açık temayı, gece kapanışı yapan kişi koyu temayı seçebilir ve
 * ikisi de aynı menüyü aynı renklerde yayınlar. Bu yüzden burada kayıtlı
 * marka rengi bölümünden AYRI durur.
 *
 * Aynı kontrol hesap menüsünde de vardır. Bu tekrar kasıtlı: menüdeki hâli
 * "hızlı çevir" içindir, buradaki hâli ise seçeneğin ne anlama geldiğini
 * anlatır. "Sistem" seçeneğinin cihazın ayarını izlediğini bilmeyen biri onu
 * menüde asla anlamaz.
 */
export function AppearanceRegion() {
    const theme = useThemeControl();

    if (theme === null) {
        return null;
    }

    return (
        <section
            aria-labelledby="profile-appearance-heading"
            className="flex flex-col gap-[var(--space-3)]"
        >
            <h3 id="profile-appearance-heading" className="text-body font-semibold text-fg">
                {t('workspace.profile.appearance.heading')}
            </h3>
            <p className="text-body text-fg-secondary">{t('workspace.profile.appearance.help')}</p>

            <fieldset className="flex flex-col gap-[var(--space-2)]">
                <legend className="sr-only">{themeText('theme.group_label')}</legend>
                <div className="flex flex-wrap gap-[var(--space-2)]">
                    {THEME_OPTION_ORDER.map((option) => {
                        const selected = option === theme.preference;

                        return (
                            <label
                                key={option}
                                className={
                                    selected
                                        ? 'flex min-h-[var(--density-hit-area-min)] cursor-pointer items-center gap-[var(--space-2)] rounded-md border border-action bg-action px-4 py-2 text-body font-semibold text-action-fg'
                                        : 'flex min-h-[var(--density-hit-area-min)] cursor-pointer items-center gap-[var(--space-2)] rounded-md border border-border px-4 py-2 text-body font-medium text-fg-secondary hover:bg-surface-hover'
                                }
                            >
                                <input
                                    type="radio"
                                    name="profile-theme"
                                    value={option}
                                    checked={selected}
                                    onChange={() => theme.choose(option as ThemePreference)}
                                    className="sr-only"
                                />
                                {themeOptionLabels[option]()}
                            </label>
                        );
                    })}
                </div>
            </fieldset>
        </section>
    );
}

export default AppearanceRegion;
