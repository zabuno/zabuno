import { HandPointing, MapPinArea, PlusCircle, SquaresFour } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import type { QrScreenCode } from '../publication/QrTableCardGrid';
import { QrOptionChip } from './QrPrintControls';
import { QrStepSection } from './QrStepSection';
import { areasOf, codeName, codesInScope, type QrPrintPlan } from './qrPrintPlan';

/** Numaralı ızgarada okunan kısa ad: "Masa 12" → "12". */
function shortName(code: QrScreenCode): string {
    const digits = code.tableName ? /(\d+)\s*$/.exec(code.tableName) : null;

    return digits ? digits[1] : (code.tableName ?? '–');
}

type QrPrintScopeStepProps = {
    codes: readonly QrScreenCode[];
    plan: QrPrintPlan;
    onChange: (patch: Partial<QrPrintPlan>) => void;
    /** Kaynağın "Yeni masa ekle" bölümü — toplu sihirbaz buraya iner. */
    bulkWizard: React.ReactNode;
};

/**
 * 2 · HANGİ MASALAR? — panel v3.1 kanonik kaynağı.
 *
 * Kaynağın üç kapsamı ürünün gerçek yeteneklerine birebir oturur: hepsi ve
 * bölge, arşiv ucunun `areaId` süzgecidir (`ExportQrCardsZipController`); tek
 * masa, tek kodun kart ucudur.
 *
 * BÖLGE LİSTESİ İKİNCİ BİR İSTEK ATMAZ: kodların kendisi `areaId` ve
 * `areaLabel` taşıyor. Kimlikle süzülür, etiketle değil — iki bölüm aynı adı
 * taşıyabilir ("Bahçe" iki katta da olabilir) ve o gün süzgeç sessizce yanlış
 * kartları basardı.
 *
 * ÇİZİLMEYEN: kaynağın masa ızgarasındaki her karede bir tarama sayısı var.
 * Burada sayı yalnız SEÇİLİ masanın notunda yazıyor ve yalnız ölçüm bize
 * açıkken (`scanCount` bir sayıysa). Ölçüm plana bağlıdır ve alan `null`
 * gelebilir; `null`'ı "0 tarama" diye çizmek, kodun hiç okutulmadığını
 * söylemek olurdu — bilmediğimiz bir şeyi bilir gibi.
 */
export function QrPrintScopeStep({ codes, plan, onChange, bulkWizard }: QrPrintScopeStepProps) {
    const areas = areasOf(codes);
    const selected = codesInScope(codes, plan);
    const selectedArea = areas.find((area) => area.id === plan.areaId) ?? null;
    const selectedCode = plan.scope === 'one' ? (selected[0] ?? null) : null;

    return (
        <QrStepSection step={2} title={t('workspace.publication.qrScreen.step2')}>
            <div className="flex flex-wrap gap-[var(--space-1)]">
                <QrOptionChip
                    selected={plan.scope === 'all'}
                    onSelect={() => onChange({ scope: 'all' })}
                    icon={<SquaresFour size={18} weight="regular" aria-hidden="true" />}
                    label={t('workspace.publication.qrScreen.scope.all')}
                    detail={t('workspace.publication.qrScreen.scope.tableCount', {
                        count: String(codes.length),
                    })}
                />

                {/*
                    BÖLGE SEÇENEĞİ, BÖLGE VARSA ÇİZİLİR. Tek salonlu bir kafeye
                    "bir bölge seç" demek, olmayan bir işi önermektir.
                */}
                {areas.length > 0 ? (
                    <QrOptionChip
                        selected={plan.scope === 'area'}
                        onSelect={() =>
                            onChange({
                                scope: 'area',
                                areaId: plan.areaId ?? areas[0].id,
                            })
                        }
                        icon={<MapPinArea size={18} weight="regular" aria-hidden="true" />}
                        label={t('workspace.publication.qrScreen.scope.area')}
                        detail={t('workspace.publication.qrScreen.scope.areaCount', {
                            count: String(areas.length),
                        })}
                    />
                ) : null}

                <QrOptionChip
                    selected={plan.scope === 'one'}
                    onSelect={() =>
                        onChange({ scope: 'one', codeId: plan.codeId ?? codes[0]?.id ?? null })
                    }
                    icon={<HandPointing size={18} weight="regular" aria-hidden="true" />}
                    label={t('workspace.publication.qrScreen.scope.one')}
                />
            </div>

            {plan.scope === 'area' ? (
                <div className="flex flex-col gap-[var(--space-2)]">
                    <div className="flex flex-wrap gap-[var(--space-1)]">
                        {areas.map((area) => (
                            <QrOptionChip
                                key={area.id}
                                selected={plan.areaId === area.id}
                                onSelect={() => onChange({ areaId: area.id })}
                                label={area.label}
                                detail={t('workspace.publication.qrScreen.scope.tableCount', {
                                    count: String(area.count),
                                })}
                            />
                        ))}
                    </div>
                    {selectedArea ? (
                        <p role="status" className="text-body text-fg-secondary">
                            {t('workspace.publication.qrScreen.areaNote', {
                                area: selectedArea.label,
                                count: String(selected.length),
                            })}
                        </p>
                    ) : null}
                </div>
            ) : null}

            {plan.scope === 'one' ? (
                <div className="flex flex-col gap-[var(--space-2)]">
                    {/*
                        NUMARA IZGARASI, kaynağın kendi düzeni. Kırk masalı bir
                        restoranda kırk satırlık bir liste taranmaz; kareler yan
                        yana durunca göz aradığı numarayı bir bakışta bulur.
                        Kısa etiket görsel içindir; ekran okuyucu masanın TAM
                        adını duyar (`aria-label`).
                    */}
                    <div
                        className="grid gap-[var(--space-1)]"
                        style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(4.25rem, 1fr))' }}
                    >
                        {codes.map((code) => (
                            <QrOptionChip
                                key={code.id}
                                selected={selectedCode?.id === code.id}
                                onSelect={() => onChange({ codeId: code.id })}
                                label={shortName(code)}
                                ariaLabel={codeName(code)}
                            />
                        ))}
                    </div>
                    {selectedCode ? (
                        <p role="status" className="text-body text-fg-secondary">
                            {typeof selectedCode.scanCount !== 'number'
                                ? codeName(selectedCode)
                                : selectedCode.scanCount === 0
                                  ? t('workspace.publication.qrScreen.tableNote.never', {
                                        table: codeName(selectedCode),
                                    })
                                  : t('workspace.publication.qrScreen.tableNote.scanned', {
                                        table: codeName(selectedCode),
                                        count: String(selectedCode.scanCount),
                                    })}
                        </p>
                    ) : null}
                </div>
            ) : null}

            <details className="rounded-[var(--radius-md)] border border-border">
                <summary className="flex min-h-[var(--density-hit-area-min)] cursor-pointer list-none items-center gap-[var(--space-2)] px-[var(--space-3)] text-body font-medium text-fg-secondary">
                    <PlusCircle size={18} weight="regular" aria-hidden="true" />
                    {t('workspace.publication.qrScreen.addTables')}
                </summary>
                <div className="px-[var(--space-3)] pb-[var(--space-3)]">{bulkWizard}</div>
            </details>
        </QrStepSection>
    );
}
