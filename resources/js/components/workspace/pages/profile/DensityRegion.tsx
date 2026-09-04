import { Check } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import { t as themeText } from '../../../../i18n/theme';
import {
    DENSITY_OPTION_ORDER,
    densityOptionLabels,
    useDensityControl,
    type DensityPreference,
} from '../../../theme/densityControl';

/**
 * Satır aralığı — ölü bir sabitten gerçek bir tercihe (FF-128).
 *
 * Üç yoğunluk modu CSS'te tanımlıydı, ölçülüydü, test ediliydi ve hiç kimse
 * değiştiremiyordu: `ThemeRoot` içinde tek bir değer yazılıydı. Bir ayarın
 * var olması, ona erişilebilmesi demek değildir.
 *
 * Görünüm'ün İÇİNDE durur, ayrı bir bölüm olarak değil: kullanıcı "bu ekran
 * bana nasıl görünsün" sorusunu bir kez sorar, iki farklı yerde değil.
 *
 * DOKUNMA HEDEFİ HİÇBİR MODDA KÜÇÜLMEZ. `--density-hit-area-min` üç modda da
 * 44 pikseldir; sıkışık mod satır arasını daraltır, parmağın basacağı alanı
 * değil. Yoğunluğu hedef küçülterek vermek, tabletle servis yapan birine
 * ürünü kullanılamaz hâle getirirdi.
 */
export function DensityRegion() {
    const density = useDensityControl();

    if (density === null) {
        return null;
    }

    return (
        <div className="flex flex-col gap-[var(--space-2)]">
            <h4 className="text-body font-bold text-fg">
                {t('workspace.profile.density.heading')}
            </h4>
            <p className="text-body text-fg-secondary">{t('workspace.profile.density.help')}</p>

            <fieldset className="flex flex-col gap-[var(--space-2)]">
                <legend className="sr-only">{themeText('density.group_label')}</legend>
                <div className="flex flex-wrap gap-[var(--space-2)]">
                    {DENSITY_OPTION_ORDER.map((option) => {
                        const selected = option === density.preference;

                        return (
                            <label
                                key={option}
                                className={
                                    selected
                                        ? 'flex min-h-[var(--density-hit-area-min)] cursor-pointer items-center gap-[var(--space-2)] rounded-md border border-action bg-action px-4 py-2 text-body font-bold text-action-fg'
                                        : 'flex min-h-[var(--density-hit-area-min)] cursor-pointer items-center gap-[var(--space-2)] rounded-md border border-border px-4 py-2 text-body font-medium text-fg-secondary hover:bg-surface-hover'
                                }
                            >
                                <input
                                    type="radio"
                                    name="appearance-density"
                                    value={option}
                                    checked={selected}
                                    onChange={() => density.choose(option as DensityPreference)}
                                    className="sr-only"
                                />
                                {/*
                                    Seçim renkten BAŞKA bir kanalda da görünür:
                                    yüksek kontrast kipinde arka plan/metin
                                    çiftleri işletim sistemi paletine düşer ve
                                    yalnız renge dayanan her ayrım kaybolur
                                    (WCAG 1.4.1). Tema seçicisiyle aynı kural.
                                */}
                                {selected ? (
                                    <Check size={16} weight="bold" aria-hidden="true" />
                                ) : null}
                                {densityOptionLabels[option]()}
                            </label>
                        );
                    })}
                </div>
            </fieldset>
        </div>
    );
}

export default DensityRegion;
