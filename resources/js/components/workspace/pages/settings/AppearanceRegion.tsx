import { Check } from '@phosphor-icons/react';

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
 * TEK EV BURASI (FF-119, sahibin bildirimi 2026-09-04). Önceden aynı kontrol
 * hem hesap menüsünün içinde hem profil ekranındaydı; "biri hızlı çevir, biri
 * anlat" diye savunulmuştu ama sonuç, aynı ayarın iki değeri varmış gibi
 * görünmesiydi. Bir ayarın tek bir evi olur.
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
                                    name="appearance-theme"
                                    value={option}
                                    checked={selected}
                                    onChange={() => theme.choose(option as ThemePreference)}
                                    className="sr-only"
                                />
                                {/*
                                    SEÇİM RENKTEN BAŞKA BİR KANALDA DA GÖRÜNÜR.
                                    Windows Yüksek Kontrast kipinde arka
                                    plan/metin çiftleri işletim sistemi
                                    paletine düşer ve yalnız renge dayanan her
                                    ayrım kaybolur (WCAG 1.4.1). İşaret, hesap
                                    menüsündeki hâlinde de vardı; kontrol
                                    taşınırken kaybolmamalı.
                                */}
                                {selected ? (
                                    <Check size={16} weight="bold" aria-hidden="true" />
                                ) : null}
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
