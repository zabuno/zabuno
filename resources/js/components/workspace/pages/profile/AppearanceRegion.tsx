import { CheckCircle, Desktop, Moon, Sun } from '@phosphor-icons/react';
import clsx from 'clsx';

import { t } from '../../../../i18n/workspace';
import { AppearancePreview } from './AppearancePreview';
import { DensityRegion } from './DensityRegion';
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
/**
 * Her seçeneğin RENK ÖRNEĞİ — zemin, kart ve marka rengi.
 *
 * Değerler AEP ilkelleridir ve bilerek doğrudan yazılır: örnek, o an aktif
 * olan temayı değil KARŞI temayı göstermek zorundadır. Semantic jetonu
 * kullansaydı üç örnek de aynı renge çıkar ve seçenekler birbirinden
 * ayrılamazdı.
 */
const THEME_SWATCHES: Record<ThemePreference, readonly string[]> = {
    system: ['var(--aep-ink-50)', 'var(--aep-ink-900)', 'var(--aep-yellow-500)'],
    light: ['var(--aep-ink-50)', 'var(--aep-white)', 'var(--aep-yellow-500)'],
    dark: ['var(--aep-ink-950)', 'var(--aep-ink-900)', 'var(--aep-yellow-500)'],
};

const THEME_ICONS: Record<ThemePreference, typeof Sun> = {
    system: Desktop,
    light: Sun,
    dark: Moon,
};

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
                {/*
                    SEÇENEK, SONUCUNU GÖSTERİR (FF-131, teslim paketinin
                    kendi tasarımı).

                    Önceki hâl üç metin düğmesiydi: "Sistem / Açık / Koyu".
                    Kullanıcı hangisinin ne olduğunu ancak SEÇİP görerek
                    öğreniyordu — yani seçim bir denemeydi. Paket her
                    seçeneğe küçük bir renk örneği koyuyor: zemin, kart ve
                    marka rengi yan yana. Renkler o temanın GERÇEK AEP
                    ilkelleridir, çizilmiş bir taklit değil.
                */}
                <div className="flex flex-wrap gap-[var(--space-2)]">
                    {THEME_OPTION_ORDER.map((option) => {
                        const selected = option === theme.preference;
                        const OptionIcon = THEME_ICONS[option];

                        return (
                            <label
                                key={option}
                                className={clsx(
                                    'flex min-h-[var(--control-height)] cursor-pointer flex-col gap-[var(--space-2)]',
                                    'rounded-[var(--radius-lg)] border p-[var(--space-3)]',
                                    selected
                                        ? 'border-action bg-[var(--color-surface-active)]'
                                        : 'border-border hover:bg-surface-hover',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="appearance-theme"
                                    value={option}
                                    checked={selected}
                                    onChange={() => theme.choose(option as ThemePreference)}
                                    className="sr-only"
                                />

                                {/* Örnek DEKORATİFTİR: adı etiket söyler. */}
                                <span aria-hidden="true" className="flex gap-[var(--space-1)]">
                                    {THEME_SWATCHES[option].map((color, index) => (
                                        <span
                                            key={index}
                                            className="h-[var(--space-5)] w-[var(--space-5)] rounded-[var(--radius-sm)] border border-border"
                                            style={{ background: color }}
                                        />
                                    ))}
                                </span>

                                <span className="flex items-center gap-[var(--space-2)] text-body font-medium text-fg">
                                    <OptionIcon size={18} weight="regular" aria-hidden="true" />
                                    {themeOptionLabels[option]()}
                                    {/*
                                        SEÇİM RENKTEN BAŞKA BİR KANALDA DA
                                        GÖRÜNÜR: yüksek kontrast kipinde arka
                                        plan/metin çiftleri işletim sistemi
                                        paletine düşer ve yalnız renge dayanan
                                        her ayrım kaybolur (WCAG 1.4.1).
                                    */}
                                    {selected ? (
                                        <CheckCircle
                                            size={18}
                                            weight="fill"
                                            aria-hidden="true"
                                            /*
                                                İŞARETİN KENDİ TUTAMAĞI VAR.
                                                Testler önceden "etikette svg
                                                var mı" diye bakıyordu; her
                                                seçeneğe ikon eklenince o
                                                ölçüm anlamsızlaştı. Seçimin
                                                işareti ikondan AYRI bir
                                                şeydir ve ayrı adlandırılır.
                                            */
                                            data-selected-marker="true"
                                        />
                                    ) : null}
                                </span>
                            </label>
                        );
                    })}
                </div>
            </fieldset>

            {/*
                Yoğunluk ve önizleme AYNI bölümün içinde (FF-128). Kullanıcı
                "bu ekran bana nasıl görünsün" sorusunu bir kez sorar; temayı
                bir yerde, satır aralığını başka bir yerde aramak zorunda
                kalmamalı. Önizleme ikisinin de sonucunu aynı anda gösterir.
            */}
            <DensityRegion />
            <AppearancePreview />
        </section>
    );
}

export default AppearanceRegion;
