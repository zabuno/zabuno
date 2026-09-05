import { ForkKnife, FrameCorners, Storefront, SlidersHorizontal } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import { QrOptionCard, QrOptionChip } from './QrPrintControls';
import { QrStepSection } from './QrStepSection';
import { CARD_GEOMETRY_MM, type CardGeometrySizeKey } from '../../../../lib/qrCardGeometry';
import {
    QR_PAPER_SIZES,
    QR_PRINT_PRESETS,
    QR_RATIO_SIZES,
    QR_RATIO_USE_KEYS,
    type QrPrintFormat,
    type QrPrintPlan,
    type QrPrintPresetKey,
} from './qrPrintPlan';

const PRESET_ICONS: Record<QrPrintPresetKey, React.ReactNode> = {
    table: <ForkKnife size={16} weight="regular" />,
    large: <ForkKnife size={18} weight="regular" />,
    wall: <FrameCorners size={18} weight="regular" />,
    window: <Storefront size={20} weight="regular" />,
};

type QrPrintTargetStepProps = {
    plan: QrPrintPlan;
    onChange: (patch: Partial<QrPrintPlan>) => void;
};

/**
 * 1 · NE BASACAKSIN? — panel v3.1 kanonik kaynağı.
 *
 * Kaynağın ilk sorusu bir ayar sorusu değil: *"masaya mı, duvara mı, vitrine
 * mi?"* Kâğıt boyutu bunun SONUCUDUR. Eski ekran tam tersini yapıyordu — en
 * üstte sekiz kâğıt boyu ve üç oran duruyordu ve restoran sahibinin "A6 mı B5
 * mi" diye bir sorusu hiç olmadı.
 *
 * Sekiz kâğıt, üç oran, yön ve biçim silinmedi: kapalı bir "başka bir ölçü
 * gerekiyor" bölümüne indi. Varsayılanı olan her şey sorulmaz, DEĞİŞTİRİLEBİLİR
 * durur.
 */
export function QrPrintTargetStep({ plan, onChange }: QrPrintTargetStepProps) {
    const formats: {
        key: QrPrintFormat;
        labelKey: Parameters<typeof t>[0];
        useKey: Parameters<typeof t>[0];
    }[] = [
        {
            key: 'pdf',
            labelKey: 'workspace.publication.qrScreen.custom.format.pdf',
            useKey: 'workspace.publication.qrScreen.custom.format.pdf.use',
        },
        {
            key: 'svg',
            labelKey: 'workspace.publication.qrScreen.custom.format.svg',
            useKey: 'workspace.publication.qrScreen.custom.format.svg.use',
        },
    ];

    function chooseSize(size: CardGeometrySizeKey) {
        onChange({ custom: true, size });
    }

    return (
        <QrStepSection step={1} title={t('workspace.publication.qrScreen.step1')}>
            <div
                className="grid gap-[var(--space-2)]"
                style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(13.5rem, 1fr))' }}
            >
                {QR_PRINT_PRESETS.map((preset) => (
                    <QrOptionCard
                        key={preset.key}
                        selected={!plan.custom && plan.preset === preset.key}
                        onSelect={() =>
                            onChange({
                                preset: preset.key,
                                custom: false,
                                size: preset.size,
                                landscape: false,
                                format: 'pdf',
                            })
                        }
                        icon={PRESET_ICONS[preset.key]}
                        label={t(preset.labelKey)}
                        description={t(preset.whereKey)}
                        tag={
                            preset.tag
                                ? t('workspace.publication.qrScreen.preset.table.tag')
                                : undefined
                        }
                    />
                ))}
            </div>

            {/*
                `<details>` içeriği kapalıyken de DOM'da kalır: klavye, ekran
                okuyucu ve form doğrulaması etkilenmez; yalnız ilk bakışta
                görünmez. Açılması planı `custom` yapar — hazır ölçü ile elle
                seçilen ölçü aynı anda geçerli olamaz, yoksa ekran "A6" yazıp
                başka bir kâğıt basardı.
            */}
            <details
                open={plan.custom}
                onToggle={(event) =>
                    onChange({ custom: (event.currentTarget as HTMLDetailsElement).open })
                }
                className="rounded-[var(--radius-md)] border border-border"
            >
                <summary className="flex min-h-[var(--density-hit-area-min)] cursor-pointer list-none items-center gap-[var(--space-2)] px-[var(--space-3)] text-body font-medium text-fg-secondary">
                    <SlidersHorizontal size={18} weight="regular" aria-hidden="true" />
                    {t('workspace.publication.qrScreen.custom')}
                </summary>

                <div className="flex flex-col gap-[var(--space-4)] px-[var(--space-3)] pb-[var(--space-3)]">
                    <fieldset className="flex flex-col gap-[var(--space-2)]">
                        <legend className="text-body font-medium text-fg-secondary">
                            {t('workspace.publication.qrScreen.custom.paper')}
                        </legend>
                        {/*
                            MİLİMETRE EKRANDA YAZAR. "A6" bir restoran sahibine
                            hiçbir şey anlatmaz; "105 × 148 mm" pleksiglas
                            standın içine girip girmeyeceğini anlatır.
                        */}
                        <div
                            className="grid gap-[var(--space-1)]"
                            style={{
                                gridTemplateColumns: 'repeat(auto-fill, minmax(5.5rem, 1fr))',
                            }}
                        >
                            {QR_PAPER_SIZES.map((size) => (
                                <QrOptionChip
                                    key={size}
                                    selected={plan.size === size}
                                    onSelect={() => chooseSize(size)}
                                    label={size}
                                    detail={`${String(CARD_GEOMETRY_MM[size][0])}×${String(CARD_GEOMETRY_MM[size][1])}`}
                                />
                            ))}
                        </div>
                    </fieldset>

                    <fieldset className="flex flex-col gap-[var(--space-2)]">
                        <legend className="text-body font-medium text-fg-secondary">
                            {t('workspace.publication.qrScreen.custom.ratio')}
                        </legend>
                        {/*
                            KÂĞIT ve ORAN iki ayrı aile, TEK seçim. Yan yana iki
                            bağımsız liste olsaydı "A4 + 16:9" gibi ne
                            basılacağı belirsiz bir çift seçilebilirdi.
                        */}
                        <div className="flex flex-wrap gap-[var(--space-1)]">
                            {QR_RATIO_SIZES.map((ratio) => (
                                <QrOptionChip
                                    key={ratio}
                                    selected={plan.size === ratio}
                                    onSelect={() => chooseSize(ratio)}
                                    label={ratio}
                                    detail={t(QR_RATIO_USE_KEYS[ratio])}
                                />
                            ))}
                        </div>
                        <span className="text-body text-fg-secondary">
                            {t('workspace.publication.qrScreen.custom.ratioNote')}
                        </span>
                    </fieldset>

                    <fieldset className="flex flex-col gap-[var(--space-2)]">
                        <legend className="text-body font-medium text-fg-secondary">
                            {t('workspace.publication.qrScreen.custom.orientation')}
                        </legend>
                        <div className="flex gap-[var(--space-1)]">
                            {[false, true].map((landscape) => (
                                <QrOptionChip
                                    key={String(landscape)}
                                    selected={plan.landscape === landscape}
                                    onSelect={() => onChange({ custom: true, landscape })}
                                    label={t(
                                        landscape
                                            ? 'workspace.publication.qrScreen.custom.landscape'
                                            : 'workspace.publication.qrScreen.custom.portrait',
                                    )}
                                    className="flex-1"
                                />
                            ))}
                        </div>
                    </fieldset>

                    <fieldset className="flex flex-col gap-[var(--space-2)]">
                        <legend className="text-body font-medium text-fg-secondary">
                            {t('workspace.publication.qrScreen.custom.format')}
                        </legend>
                        <div className="flex gap-[var(--space-1)]">
                            {formats.map((format) => (
                                <QrOptionChip
                                    key={format.key}
                                    selected={plan.format === format.key}
                                    onSelect={() => onChange({ custom: true, format: format.key })}
                                    label={t(format.labelKey)}
                                    detail={t(format.useKey)}
                                    className="flex-1"
                                />
                            ))}
                        </div>
                        {/*
                            ÇİZİLMEYEN SEÇENEĞİN SEBEBİ YAZILIR. Kaynak üçüncü
                            bir biçim (PNG) gösteriyor; sunucuda karşılığı YOK
                            ve olmaması bir karar. Sessizce eksiltmek,
                            kullanıcıya ürünü eksik gösterirdi.
                        */}
                        <span className="text-meta text-fg-muted">
                            {t('workspace.publication.qrScreen.custom.noPng')}
                        </span>
                    </fieldset>
                </div>
            </details>
        </QrStepSection>
    );
}
