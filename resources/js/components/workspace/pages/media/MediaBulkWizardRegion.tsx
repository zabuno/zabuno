import { useEffect, useMemo, useState } from 'react';
import {
    ArrowCounterClockwise,
    Camera,
    Check,
    CheckSquare,
    CircleNotch,
    DownloadSimple,
    Flask,
    Lightning,
    LockSimple,
    Play,
    SelectionAll,
    SlidersHorizontal,
    Warning,
} from '@phosphor-icons/react';
import { t } from '../../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { formatBytes, displayName } from './mediaFormat';
import {
    BULK_ACTION_GROUPS,
    confirmSatisfied,
    failureReasonKey,
    groupResults,
    resultsToCsv,
    skipReasonKey,
    type MediaBulkActionKey,
    type MediaBulkPlan,
    type MediaBulkResult,
    type MediaBulkRunReport,
    type MediaBulkScopeKind,
    type MediaBulkStep,
} from './mediaBulk';
import type { MediaFolder, MediaFolderId } from './MediaFolderRail';
import type { MediaAsset } from '../MediaPage';

type MediaBulkWizardRegionProps = {
    workspaceId: number;
    folders: MediaFolder[];
    activeFolderId: MediaFolderId | null;
    assets: MediaAsset[];
};

const STEPS: { key: MediaBulkStep; labelKey: Parameters<typeof t>[0] }[] = [
    { key: 'scope', labelKey: 'workspace.media.bulk.step.scope' },
    { key: 'action', labelKey: 'workspace.media.bulk.step.action' },
    { key: 'config', labelKey: 'workspace.media.bulk.step.config' },
    { key: 'preview', labelKey: 'workspace.media.bulk.step.preview' },
    { key: 'run', labelKey: 'workspace.media.bulk.step.run' },
];

const STEP_ICONS: Record<MediaBulkStep, typeof SelectionAll> = {
    scope: SelectionAll,
    action: Lightning,
    config: SlidersHorizontal,
    preview: Flask,
    run: CheckSquare,
};

/**
 * Hedef biçimler. Liste `ListConversionTargetsController`in kendi
 * listesinden DAR: burada yalnız bu depoda görsel hattının ürettiği
 * biçimler var. Video (WebM) burada hiç yok, çünkü toplu bir işte
 * seçilebilir olsaydı sahip bin dosya seçer ve yalnız başarısızlık
 * toplardı — dönüştürme ekranı onu "bu kurulumda yapılamıyor" diye
 * gösterebilir, toplu iş gösteremez.
 */
const CONVERT_FORMATS = ['avif', 'webp', 'jpeg'] as const;

const FORMAT_LABEL: Record<string, string> = { avif: 'AVIF', webp: 'WebP', jpeg: 'JPEG' };

/**
 * İşlem anahtarı. Aynı anahtar iki kez çalışmaz (sunucu kısıtı); o yüzden
 * her YENİ iş için bir kez üretilir ve yeniden denemede YENİSİ üretilir —
 * yeniden deneme başka bir iştir, aynı işin tekrarı değil.
 */
function newOperationKey(): string {
    const random = globalThis.crypto?.randomUUID?.();

    return `bulk_${random ?? `${Date.now()}_${Math.random().toString(36).slice(2, 10)}`}`;
}

/**
 * TOPLU İŞLEM SİHİRBAZI — kanonik kaynak `docs/reference/panel-v3/
 * MedyaModulu.dc.html`, `data-screen-label="Toplu işlem"`
 * (plan `docs/109-PANEL-V3.md` §2). Bu bölüm depoda HİÇ YOKTU.
 *
 * Beş adım, kaynağın kendi sırası: Kapsam → Eylem → Ayar → Etki → Sonuç.
 *
 * ═══ EKRAN HİÇBİR SAYIYI KENDİ HESAPLAMAZ ═══
 *
 * Kaynağın tasarım dosyası atlama sayılarını oranlarla üretiyordu
 * (`Math.round(scopeN * 0.04)`). Bir tasarım dosyasında bu doğrudur,
 * üründe yalandır. Burada uygulanacak/atlanacak sayıları, atlama
 * sebeplerini ve kota etkisini SUNUCU verir; ekran yalnız çizer. Tek
 * hesabı sayfalama ve süzgeçtir.
 *
 * ═══ İLERİYE ATLANAMAZ ═══
 *
 * Adım şeridi geçmiş adımlara döner ama ileriye ATLAMAZ: atlanan bir
 * adım, görmeden onaylanan bir etkidir. Kaynağın kendi `locked` bayrağı
 * da tam olarak bunu yapıyor.
 */
export function MediaBulkWizardRegion({
    workspaceId,
    folders,
    activeFolderId,
    assets,
}: MediaBulkWizardRegionProps) {
    const [step, setStep] = useState<MediaBulkStep>('scope');
    const [scope, setScope] = useState<MediaBulkScopeKind>('workspace');
    const [pickedIds, setPickedIds] = useState<number[]>([]);
    const [action, setAction] = useState<MediaBulkActionKey | null>(null);
    const [format, setFormat] = useState<string>('avif');
    const [destinationFolderId, setDestinationFolderId] = useState<MediaFolderId | null>(null);
    const [plan, setPlan] = useState<MediaBulkPlan | null>(null);
    const [planState, setPlanState] = useState<'idle' | 'loading' | 'error'>('idle');
    const [confirmInput, setConfirmInput] = useState('');
    const [report, setReport] = useState<MediaBulkRunReport | null>(null);
    const [running, setRunning] = useState(false);
    const [runError, setRunError] = useState(false);
    const [resultFilter, setResultFilter] = useState<'all' | 'ok' | 'skip' | 'error'>('all');
    const [showSkipped, setShowSkipped] = useState(false);

    const config = useMemo<Record<string, unknown>>(() => {
        if (action === 'convert') return { format };
        if (action === 'move') return { folderId: destinationFolderId };

        return {};
    }, [action, format, destinationFolderId]);

    const endpoint = `/api/workspaces/${workspaceId}/media`;

    /*
        PLAN İSTEĞİ ETKİNİN İÇİNDE — dışarıda bir `useCallback` olarak değil.

        Önceki hâl `loadPlan` diye bir geri çağrı tanımlayıp etkiden
        çağırıyordu ve derleyici kapısı bunu reddediyordu: çağrı zincirinin
        ucunda durum yazan bir işlev, etkinin içinden EŞZAMANLI durum yazmak
        sayılıyor ve zincirleme çizime yol açıyor. Bir mikro görev beklemek
        de yetmedi — kapı çağrı zincirine bakıyor, zamanlamaya değil.

        `DiningAreasRegion` ve `AuditTrailRegion` aynı sorunu aynı biçimde
        çözüyor: iş etkinin içinde, iptal bayrağıyla. Böylece bileşen
        sökülürken gelen bir yanıt, artık ekranda olmayan bir duruma yazmaz.

        Plan eylem SEÇİLİR SEÇİLMEZ istenir, "Etki" adımına geçince değil:
        kartın kilitli olup olmadığını ancak sunucu bilir ve editör kilidin
        sebebini kartın üstünde okumalı — bir adım sonra değil.
    */
    useEffect(() => {
        let cancelled = false;

        if (action === null) {
            /*
                BURADA DURUM YAZILMAZ, TÜRETİLİR.

                Eylem seçimi kalkınca "planı unut" demek bir yan etki değil,
                bir SONUÇTUR: eylem yoksa plan da yoktur. Etkiden `setPlan`
                çağırmak zincirleme çizim üretiyordu; aşağıdaki `shownPlan`
                aynı şeyi tek bir çizimde söylüyor.
            */
            return () => {
                cancelled = true;
            };
        }

        (async () => {
            setPlanState('loading');

            try {
                const response = await fetch(`${endpoint}/bulk/plan`, {
                    ...buildAuthRequestInit({
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action,
                            scope,
                            assetIds: scope === 'selected' ? pickedIds : [],
                            folderId: scope === 'folder' ? activeFolderId : null,
                            config,
                        }),
                    }),
                    credentials: 'same-origin',
                });

                if (cancelled) return;

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                const body = (await response.json()) as MediaBulkPlan;

                if (cancelled) return;

                setPlan(body);
                setPlanState('idle');
            } catch {
                if (cancelled) return;

                setPlan(null);
                setPlanState('error');
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [action, config, endpoint, scope, pickedIds, activeFolderId]);

    /*
        Eylem yoksa plan da yoktur. Depolanan `plan` bir öncekinin kalıntısı
        olabilir; ekranda gösterilen her zaman türetilmiş olandır.
    */
    const shownPlan = action === null ? null : plan;
    const shownPlanState = action === null ? 'idle' : planState;

    const furthestStep: MediaBulkStep =
        report !== null
            ? 'run'
            : shownPlan !== null
              ? 'preview'
              : action !== null
                ? 'config'
                : 'action';

    const stepOrder = STEPS.map((one) => one.key);
    const reachableIndex = stepOrder.indexOf(furthestStep);

    function goTo(next: MediaBulkStep) {
        if (stepOrder.indexOf(next) <= reachableIndex) {
            setStep(next);
        }
    }

    const scopeChoices: {
        kind: MediaBulkScopeKind;
        labelKey: Parameters<typeof t>[0];
        descriptionKey: Parameters<typeof t>[0];
    }[] = [
        {
            kind: 'selected',
            labelKey: 'workspace.media.bulk.scope.selected',
            descriptionKey: 'workspace.media.bulk.scope.selected.description',
        },
        {
            kind: 'workspace',
            labelKey: 'workspace.media.bulk.scope.workspace',
            descriptionKey: 'workspace.media.bulk.scope.workspace.description',
        },
        {
            kind: 'folder',
            labelKey: 'workspace.media.bulk.scope.folder',
            descriptionKey: 'workspace.media.bulk.scope.folder.description',
        },
    ];

    function togglePicked(id: number) {
        setPickedIds((current) =>
            current.includes(id) ? current.filter((one) => one !== id) : [...current, id],
        );
    }

    async function run(assetIds: number[]) {
        if (action === null || running) {
            return;
        }

        setRunning(true);
        setRunError(false);

        try {
            const response = await fetch(`${endpoint}/bulk/run`, {
                ...buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action,
                        scope,
                        // Yeniden deneme YENİ bir anahtar alır: aynı işin
                        // tekrarı değil, başka bir iştir.
                        operationKey: newOperationKey(),
                        assetIds,
                        config,
                        confirm: confirmInput.trim(),
                    }),
                }),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            setReport((await response.json()) as MediaBulkRunReport);
            setStep('run');
        } catch {
            setRunError(true);
        } finally {
            setRunning(false);
        }
    }

    function restart() {
        setStep('scope');
        setAction(null);
        setPlan(null);
        setReport(null);
        setConfirmInput('');
        setResultFilter('all');
        setShowSkipped(false);
    }

    return (
        <section
            aria-label={t('workspace.media.bulk.region')}
            className="flex flex-col gap-[var(--space-4)]"
        >
            <p className="text-body text-fg-secondary">{t('workspace.media.bulk.lead')}</p>

            <ol
                aria-label={t('workspace.media.bulk.region')}
                className="flex flex-wrap gap-[var(--space-2)]"
            >
                {STEPS.map((one, index) => {
                    const Icon = STEP_ICONS[one.key];
                    const active = one.key === step;
                    const locked = index > reachableIndex;

                    return (
                        <li key={one.key}>
                            <button
                                type="button"
                                disabled={locked}
                                aria-current={active ? 'step' : undefined}
                                onClick={() => goTo(one.key)}
                                className={`inline-flex min-h-[var(--control-height)] items-center gap-[var(--space-2)] rounded-[var(--radius-md)] border px-[var(--space-3)] text-body ${
                                    active
                                        ? 'border-border-strong bg-surface-active font-bold text-fg'
                                        : 'border-border bg-surface font-medium text-fg-secondary'
                                } disabled:opacity-60`}
                            >
                                <Icon aria-hidden="true" size={16} />
                                {t(one.labelKey)}
                            </button>
                        </li>
                    );
                })}
            </ol>

            {step === 'scope' ? (
                <div className="flex flex-col gap-[var(--space-3)]">
                    <fieldset className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                        <legend className="text-body font-bold text-fg">
                            {t('workspace.media.bulk.scope.heading')}
                        </legend>

                        {scopeChoices.map((choice) => (
                            <button
                                key={choice.kind}
                                type="button"
                                role="radio"
                                aria-checked={scope === choice.kind}
                                onClick={() => setScope(choice.kind)}
                                className={`flex min-h-[var(--control-height)] flex-col items-start gap-[var(--space-1)] rounded-[var(--radius-md)] border p-[var(--space-3)] text-start ${
                                    scope === choice.kind
                                        ? 'border-action bg-surface-active'
                                        : 'border-border bg-surface'
                                }`}
                            >
                                <span className="text-body font-bold text-fg">
                                    {t(choice.labelKey)}
                                </span>
                                <span className="text-body text-fg-secondary">
                                    {choice.kind === 'folder' && activeFolderId === null
                                        ? t('workspace.media.bulk.scope.folder.none')
                                        : t(choice.descriptionKey)}
                                </span>
                            </button>
                        ))}
                    </fieldset>

                    {scope === 'selected' ? (
                        <ul className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface">
                            {assets.length === 0 ? (
                                <li className="p-[var(--space-3)] text-body text-fg-muted">
                                    {t('workspace.media.bulk.scope.empty')}
                                </li>
                            ) : (
                                assets.map((asset) => {
                                    const on = pickedIds.includes(asset.id);

                                    return (
                                        <li
                                            key={asset.id}
                                            className="flex min-h-[var(--density-row-height)] items-center gap-[var(--space-3)] border-t border-border px-[var(--space-3)] py-[var(--space-2)] first:border-t-0"
                                        >
                                            <button
                                                type="button"
                                                role="checkbox"
                                                aria-checked={on}
                                                aria-label={displayName(asset)}
                                                onClick={() => togglePicked(asset.id)}
                                                className={`grid size-[1.5rem] flex-none place-items-center rounded-[var(--radius-sm)] border ${
                                                    on
                                                        ? 'border-action bg-action text-action-fg'
                                                        : 'border-border bg-surface text-fg-muted'
                                                }`}
                                            >
                                                {on ? (
                                                    <Check
                                                        aria-hidden="true"
                                                        size={14}
                                                        weight="bold"
                                                    />
                                                ) : null}
                                            </button>
                                            <span className="min-w-0 flex-1 truncate text-body text-fg">
                                                {displayName(asset)}
                                            </span>
                                            <span className="flex-none text-meta text-fg-secondary tabular-nums">
                                                {formatBytes(asset.sizeBytes)}
                                            </span>
                                        </li>
                                    );
                                })
                            )}
                        </ul>
                    ) : null}

                    {/*
                        Kaynağın DEĞİŞMEZ cümlesi. Sorulmadan cevaplanan bir
                        soru: "ben çalışırken biri dosya yüklerse ne olur?"
                    */}
                    <p className="flex items-start gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface-subtle p-[var(--space-3)] text-body text-fg-secondary">
                        <Camera aria-hidden="true" size={20} className="flex-none" />
                        {t('workspace.media.bulk.scope.snapshot')}
                    </p>

                    <button
                        type="button"
                        onClick={() => setStep('action')}
                        className="inline-flex min-h-[var(--control-height)] items-center justify-center self-start rounded-[var(--radius-md)] bg-action px-[var(--space-4)] text-body font-bold text-action-fg"
                    >
                        {t('workspace.media.bulk.scope.next')}
                    </button>
                </div>
            ) : null}

            {step === 'action' ? (
                <div className="flex flex-col gap-[var(--space-4)]">
                    {BULK_ACTION_GROUPS.map((group) => (
                        <fieldset key={group.key} className="flex flex-col gap-[var(--space-2)]">
                            <legend className="text-body font-bold text-fg">
                                {t(group.labelKey)}
                            </legend>

                            {group.actions.map((meta) => {
                                const chosen = meta.action === action;
                                /*
                                    Kilit yalnız SEÇİLİ kartta bilinir: izin
                                    kararı sunucudan gelir ve sunucuya
                                    seçilen eylem sorulur. Bütün kartlar için
                                    ayrı ayrı plan istemek, bir ekran açılışında
                                    beş kuru çalışma çalıştırmak olurdu.
                                */
                                const locked =
                                    chosen && shownPlan !== null && !shownPlan.allowed
                                        ? shownPlan.requiredPermission
                                        : null;

                                return (
                                    <button
                                        key={meta.action}
                                        type="button"
                                        role="radio"
                                        aria-checked={chosen}
                                        onClick={() => {
                                            setAction(meta.action);
                                            setReport(null);
                                            setConfirmInput('');
                                        }}
                                        className={`flex min-h-[var(--control-height)] flex-col items-start gap-[var(--space-1)] rounded-[var(--radius-md)] border p-[var(--space-3)] text-start ${
                                            chosen
                                                ? 'border-action bg-surface-active'
                                                : 'border-border bg-surface'
                                        }`}
                                    >
                                        <span className="flex flex-wrap items-center gap-[var(--space-2)]">
                                            <span className="text-body font-bold text-fg">
                                                {t(meta.labelKey)}
                                            </span>
                                            <span
                                                className={`rounded-[var(--radius-pill)] px-[var(--space-2)] text-meta font-medium ${
                                                    meta.reversible
                                                        ? 'bg-surface-success text-fg-success'
                                                        : 'bg-surface-danger text-fg-danger'
                                                }`}
                                            >
                                                {meta.reversible
                                                    ? t('workspace.media.bulk.action.reversible')
                                                    : t('workspace.media.bulk.action.irreversible')}
                                            </span>
                                        </span>

                                        <span className="text-body text-fg-secondary">
                                            {t(meta.descriptionKey, {
                                                days: String(plan?.impact.undoWindowDays ?? 30),
                                            })}
                                        </span>

                                        {locked === null ? null : (
                                            <span className="flex items-center gap-[var(--space-1)] text-body text-fg-danger">
                                                <LockSimple aria-hidden="true" size={16} />
                                                {t('workspace.media.bulk.action.locked', {
                                                    permission: locked,
                                                })}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </fieldset>
                    ))}

                    <div className="flex flex-wrap gap-[var(--space-2)]">
                        <button
                            type="button"
                            onClick={() => setStep('scope')}
                            className="inline-flex min-h-[var(--control-height)] items-center rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-body font-medium text-fg"
                        >
                            {t('workspace.media.bulk.back')}
                        </button>
                        <button
                            type="button"
                            disabled={action === null}
                            onClick={() => setStep('config')}
                            className="inline-flex min-h-[var(--control-height)] items-center rounded-[var(--radius-md)] bg-action px-[var(--space-4)] text-body font-bold text-action-fg disabled:opacity-60"
                        >
                            {action === null
                                ? t('workspace.media.bulk.action.none')
                                : t('workspace.media.bulk.action.next', {
                                      action: t(
                                          BULK_ACTION_GROUPS.flatMap((group) => group.actions).find(
                                              (one) => one.action === action,
                                          )?.labelKey ?? 'workspace.media.bulk.action.optimize',
                                      ),
                                  })}
                        </button>
                    </div>
                </div>
            ) : null}

            {step === 'config' && action !== null ? (
                <div className="flex flex-col gap-[var(--space-3)]">
                    <fieldset className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                        <legend className="text-body font-bold text-fg">
                            {t('workspace.media.bulk.config.heading', {
                                action: t(
                                    BULK_ACTION_GROUPS.flatMap((group) => group.actions).find(
                                        (one) => one.action === action,
                                    )?.labelKey ?? 'workspace.media.bulk.action.optimize',
                                ),
                            })}
                        </legend>

                        {action === 'convert' ? (
                            <>
                                <span className="text-body font-medium text-fg">
                                    {t('workspace.media.bulk.config.format')}
                                </span>
                                <div className="flex flex-wrap gap-[var(--space-2)]">
                                    {CONVERT_FORMATS.map((one) => (
                                        <button
                                            key={one}
                                            type="button"
                                            role="radio"
                                            aria-checked={format === one}
                                            onClick={() => setFormat(one)}
                                            className={`inline-flex min-h-[var(--control-height)] items-center rounded-[var(--radius-md)] border px-[var(--space-3)] text-body ${
                                                format === one
                                                    ? 'border-action bg-surface-active font-bold text-fg'
                                                    : 'border-border bg-surface font-medium text-fg'
                                            }`}
                                        >
                                            {FORMAT_LABEL[one]}
                                        </button>
                                    ))}
                                </div>
                                <span className="text-body text-fg-secondary">
                                    {t('workspace.media.bulk.config.format.help')}
                                </span>
                            </>
                        ) : action === 'move' ? (
                            <>
                                <span className="text-body font-medium text-fg">
                                    {t('workspace.media.bulk.config.folder')}
                                </span>
                                <div className="flex flex-wrap gap-[var(--space-2)]">
                                    {[null, ...folders.map((one) => one.id)].map((id) => (
                                        <button
                                            key={String(id)}
                                            type="button"
                                            role="radio"
                                            aria-checked={destinationFolderId === id}
                                            onClick={() => setDestinationFolderId(id)}
                                            className={`inline-flex min-h-[var(--control-height)] items-center rounded-[var(--radius-md)] border px-[var(--space-3)] text-body ${
                                                destinationFolderId === id
                                                    ? 'border-action bg-surface-active font-bold text-fg'
                                                    : 'border-border bg-surface font-medium text-fg'
                                            }`}
                                        >
                                            {id === null
                                                ? t('workspace.media.bulk.config.folder.root')
                                                : (folders.find((one) => one.id === id)?.name ??
                                                  String(id))}
                                        </button>
                                    ))}
                                </div>
                                <span className="text-body text-fg-secondary">
                                    {t('workspace.media.bulk.config.folder.help')}
                                </span>
                            </>
                        ) : (
                            <span className="text-body text-fg-secondary">
                                {t('workspace.media.bulk.config.none')}
                            </span>
                        )}
                    </fieldset>

                    <div className="flex flex-wrap gap-[var(--space-2)]">
                        <button
                            type="button"
                            onClick={() => setStep('action')}
                            className="inline-flex min-h-[var(--control-height)] items-center rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-body font-medium text-fg"
                        >
                            {t('workspace.media.bulk.back')}
                        </button>
                        <button
                            type="button"
                            onClick={() => setStep('preview')}
                            className="inline-flex min-h-[var(--control-height)] items-center rounded-[var(--radius-md)] bg-action px-[var(--space-4)] text-body font-bold text-action-fg"
                        >
                            {t('workspace.media.bulk.config.next')}
                        </button>
                    </div>
                </div>
            ) : null}

            {step === 'preview' ? (
                <PreviewStep
                    plan={shownPlan}
                    planState={shownPlanState}
                    confirmInput={confirmInput}
                    onConfirmInput={setConfirmInput}
                    showSkipped={showSkipped}
                    onToggleSkipped={() => setShowSkipped((current) => !current)}
                    running={running}
                    runError={runError}
                    onBack={() => setStep('config')}
                    onRun={() => void run(plan?.snapshot.assetIds ?? [])}
                />
            ) : null}

            {step === 'run' && report !== null ? (
                <ResultStep
                    report={report}
                    filter={resultFilter}
                    onFilter={setResultFilter}
                    running={running}
                    onRetry={(ids) => void run(ids)}
                    onRestart={restart}
                />
            ) : null}
        </section>
    );
}

/** Etiket + değer satırı; değer daima `tabular-nums`, çünkü hepsi rakamdır. */
function StatRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex min-h-[var(--density-row-height)] flex-wrap items-baseline gap-[var(--space-2)] border-t border-border py-[var(--space-2)] first:border-t-0">
            <span className="flex-1 text-body text-fg-secondary">{label}</span>
            <span className="text-meta font-medium text-fg tabular-nums">{value}</span>
        </div>
    );
}

/**
 * ETKİ ADIMI — kaynağın "Kuru çalışma sonucu" kartı.
 *
 * Buradaki hiçbir sayı ekranda hesaplanmaz. "Sonra (tahmini)" satırı
 * BİLEREK yoktur: yeniden üretimin ne kadar yer tutacağı ancak kodlamadan
 * sonra bilinir ve bir tahmin, bu kartın tek sözünü ("sayılar gerçek")
 * bozardı.
 */
function PreviewStep({
    plan,
    planState,
    confirmInput,
    onConfirmInput,
    showSkipped,
    onToggleSkipped,
    running,
    runError,
    onBack,
    onRun,
}: {
    plan: MediaBulkPlan | null;
    planState: 'idle' | 'loading' | 'error';
    confirmInput: string;
    onConfirmInput: (value: string) => void;
    showSkipped: boolean;
    onToggleSkipped: () => void;
    running: boolean;
    runError: boolean;
    onBack: () => void;
    onRun: () => void;
}) {
    if (planState === 'loading') {
        return <p className="text-body text-fg-muted">{t('workspace.media.bulk.loading')}</p>;
    }

    if (planState === 'error' || plan === null) {
        return (
            <p role="alert" className="text-body text-fg-danger">
                {t('workspace.media.bulk.failed')}
            </p>
        );
    }

    const ready = plan.allowed && confirmSatisfied(plan, confirmInput);
    const destructive = plan.action === 'purge' || plan.action === 'trash';

    return (
        <div className="flex flex-col gap-[var(--space-3)]">
            <section className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                <h3 className="flex items-center gap-[var(--space-2)] text-body font-bold text-fg">
                    <Flask aria-hidden="true" size={20} />
                    {t('workspace.media.bulk.preview.heading')}
                </h3>
                <p className="text-body text-fg-secondary">
                    {t('workspace.media.bulk.preview.lead')}
                </p>

                <div className="grid grid-cols-[repeat(auto-fill,minmax(10rem,1fr))] gap-[var(--space-2)]">
                    <div className="flex flex-col rounded-[var(--radius-md)] border border-border-success bg-surface-success p-[var(--space-3)]">
                        <span className="text-body text-fg-secondary">
                            {t('workspace.media.bulk.preview.apply')}
                        </span>
                        <span className="text-body font-bold text-fg-success tabular-nums">
                            {plan.applyCount}
                        </span>
                    </div>
                    <div className="flex flex-col rounded-[var(--radius-md)] border border-warning bg-surface-warning p-[var(--space-3)]">
                        <span className="text-body text-fg-secondary">
                            {t('workspace.media.bulk.preview.skip')}
                        </span>
                        <span className="text-body font-bold text-fg-warning tabular-nums">
                            {plan.skippedAssets.length}
                        </span>
                    </div>
                    {plan.remaining > 0 ? (
                        <div className="flex flex-col rounded-[var(--radius-md)] border border-border bg-surface p-[var(--space-3)]">
                            <span className="text-body text-fg-secondary">
                                {t('workspace.media.bulk.preview.remaining')}
                            </span>
                            <span className="text-body font-bold text-fg tabular-nums">
                                {plan.remaining}
                            </span>
                        </div>
                    ) : null}
                </div>

                {plan.remaining > 0 ? (
                    <p className="text-body text-fg-secondary">
                        {t('workspace.media.bulk.preview.remaining.note', {
                            limit: String(plan.batchLimit),
                        })}
                    </p>
                ) : null}
            </section>

            {plan.skips.length === 0 ? null : (
                <section className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                    <h3 className="text-body font-bold text-fg">
                        {t('workspace.media.bulk.skip.heading')}
                    </h3>

                    {plan.skips.map((skip) => (
                        <div
                            key={skip.reason}
                            className="flex items-start gap-[var(--space-2)] border-t border-border pt-[var(--space-2)] first:border-t-0 first:pt-0"
                        >
                            <span className="grid min-h-[1.6rem] min-w-[2rem] flex-none place-items-center rounded-[var(--radius-pill)] bg-surface-warning text-meta font-bold text-fg-warning tabular-nums">
                                {skip.count}
                            </span>
                            <span className="flex-1 text-body text-fg-secondary">
                                {t(skipReasonKey(skip.reason))}
                            </span>
                        </div>
                    ))}

                    <button
                        type="button"
                        onClick={onToggleSkipped}
                        aria-expanded={showSkipped}
                        className="inline-flex min-h-[var(--control-height)] items-center self-start rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-body font-medium text-fg"
                    >
                        {showSkipped
                            ? t('workspace.media.bulk.skip.hide')
                            : t('workspace.media.bulk.skip.show')}
                    </button>

                    {showSkipped ? (
                        <ul
                            aria-label={t('workspace.media.bulk.skip.files')}
                            className="flex flex-col"
                        >
                            {plan.skippedAssets.map((one) => (
                                <li
                                    key={one.id}
                                    className="flex flex-wrap items-baseline gap-[var(--space-2)] border-t border-border py-[var(--space-2)]"
                                >
                                    <span className="min-w-0 flex-1 truncate text-body text-fg">
                                        {one.name}
                                    </span>
                                    <span className="text-body text-fg-secondary">
                                        {t(skipReasonKey(one.reason))}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </section>
            )}

            <section className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                <h3 className="pb-[var(--space-2)] text-body font-bold text-fg">
                    {t('workspace.media.bulk.impact.heading')}
                </h3>

                {plan.impact.newVersion ? (
                    <StatRow
                        label={t('workspace.media.bulk.impact.newVersion')}
                        value={t('workspace.media.bulk.impact.newVersion.value', {
                            count: String(plan.applyCount),
                        })}
                    />
                ) : null}
                <StatRow
                    label={t('workspace.media.bulk.impact.originals')}
                    value={t('workspace.media.bulk.impact.originals.kept')}
                />
                <StatRow
                    label={t('workspace.media.bulk.impact.undo')}
                    value={
                        plan.impact.undoWindowDays === null
                            ? t('workspace.media.bulk.impact.undo.none')
                            : t('workspace.media.bulk.impact.undo.days', {
                                  days: String(plan.impact.undoWindowDays),
                              })
                    }
                />
                <StatRow
                    label={t('workspace.media.bulk.impact.storage')}
                    value={
                        plan.impact.quotaBytesFreed === null
                            ? t('workspace.media.bulk.impact.storage.unknown', {
                                  used: formatBytes(plan.impact.quotaBytesUsed),
                              })
                            : t('workspace.media.bulk.impact.storage.freed', {
                                  used: formatBytes(plan.impact.quotaBytesUsed),
                                  freed: formatBytes(plan.impact.quotaBytesFreed),
                              })
                    }
                />
            </section>

            {plan.confirmation.required && plan.confirmation.word !== null ? (
                <section className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-danger bg-surface-danger p-[var(--space-4)]">
                    <h3 className="flex items-center gap-[var(--space-2)] text-body font-bold text-fg">
                        <Warning aria-hidden="true" size={20} weight="fill" />
                        {plan.action === 'purge'
                            ? t('workspace.media.bulk.confirm.heading.purge')
                            : t('workspace.media.bulk.confirm.heading.large')}
                    </h3>
                    <p className="text-body text-fg-secondary">
                        {plan.action === 'purge'
                            ? t('workspace.media.bulk.confirm.body.purge', {
                                  count: String(plan.applyCount),
                              })
                            : t('workspace.media.bulk.confirm.body.large', {
                                  count: String(plan.applyCount),
                              })}
                    </p>
                    <label className="flex flex-col gap-[var(--space-1)]">
                        <span className="text-body text-fg">
                            {t('workspace.media.bulk.confirm.label', {
                                word: plan.confirmation.word,
                            })}
                        </span>
                        <input
                            value={confirmInput}
                            onChange={(event) => onConfirmInput(event.target.value)}
                            className="min-h-[var(--control-height)] rounded-[var(--radius-md)] border border-border-strong bg-surface px-[var(--space-3)] text-body text-fg"
                        />
                    </label>
                </section>
            ) : null}

            {plan.allowed ? null : (
                <p role="alert" className="text-body text-fg-danger">
                    {t('workspace.media.bulk.action.locked', {
                        permission: plan.requiredPermission ?? '',
                    })}
                </p>
            )}

            {runError ? (
                <p role="alert" className="text-body text-fg-danger">
                    {t('workspace.media.bulk.run.failed')}
                </p>
            ) : null}

            <div className="flex flex-wrap gap-[var(--space-2)]">
                <button
                    type="button"
                    onClick={onBack}
                    className="inline-flex min-h-[var(--control-height)] items-center rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-body font-medium text-fg"
                >
                    {t('workspace.media.bulk.back')}
                </button>
                <button
                    type="button"
                    disabled={!ready || running}
                    onClick={onRun}
                    className={`inline-flex min-h-[var(--control-height)] items-center gap-[var(--space-2)] rounded-[var(--radius-md)] px-[var(--space-4)] text-body font-bold ${
                        destructive
                            ? 'bg-surface-danger text-fg-danger'
                            : 'bg-action text-action-fg'
                    } disabled:opacity-60`}
                >
                    {running ? (
                        <CircleNotch aria-hidden="true" size={18} />
                    ) : (
                        <Play aria-hidden="true" size={18} />
                    )}
                    {running
                        ? t('workspace.media.bulk.run.running')
                        : plan.applyCount === 0
                          ? t('workspace.media.bulk.run.start.empty')
                          : destructive
                            ? t('workspace.media.bulk.run.start.destructive', {
                                  count: String(plan.applyCount),
                              })
                            : t('workspace.media.bulk.run.start', {
                                  count: String(plan.applyCount),
                              })}
                </button>
            </div>
        </div>
    );
}

/**
 * SONUÇ ADIMI — kaynağın "Dosya dosya sonuç" kartı.
 *
 * "Yalnız hatalıları yeniden dene" tam olarak hatalı kimliklerle çalışır:
 * başarılı olanlara ikinci kez dokunmak bir sürüm daha açardı ve sahip
 * aynı işi iki kez yaptırdığını hiçbir yerden anlamazdı.
 */
function ResultStep({
    report,
    filter,
    onFilter,
    running,
    onRetry,
    onRestart,
}: {
    report: MediaBulkRunReport;
    filter: 'all' | 'ok' | 'skip' | 'error';
    onFilter: (value: 'all' | 'ok' | 'skip' | 'error') => void;
    running: boolean;
    onRetry: (ids: number[]) => void;
    onRestart: () => void;
}) {
    const counts = groupResults(report.results);
    const rows: MediaBulkResult[] =
        filter === 'all' ? report.results : report.results.filter((one) => one.status === filter);

    const filters: {
        key: 'all' | 'ok' | 'skip' | 'error';
        labelKey: Parameters<typeof t>[0];
        count: number;
    }[] = [
        { key: 'all', labelKey: 'workspace.media.bulk.result.filter.all', count: counts.all },
        { key: 'ok', labelKey: 'workspace.media.bulk.result.filter.ok', count: counts.ok },
        { key: 'skip', labelKey: 'workspace.media.bulk.result.filter.skip', count: counts.skip },
        { key: 'error', labelKey: 'workspace.media.bulk.result.filter.error', count: counts.error },
    ];

    function downloadCsv() {
        const csv = resultsToCsv(
            [
                t('workspace.media.bulk.result.csv.header.id'),
                t('workspace.media.bulk.result.csv.header.name'),
                t('workspace.media.bulk.result.csv.header.status'),
                t('workspace.media.bulk.result.csv.header.reason'),
            ],
            report.results,
        );

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${report.operationKey}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    }

    return (
        <div className="flex flex-col gap-[var(--space-3)]">
            <p role="status" className="text-body text-fg-secondary">
                {report.replayed
                    ? t('workspace.media.bulk.result.replayed')
                    : t('workspace.media.bulk.result.summary', {
                          applied: String(report.applied),
                          skipped: String(report.skipped),
                          failed: String(report.failed),
                      })}
            </p>

            <section className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface">
                <div className="flex flex-wrap items-center gap-[var(--space-2)] border-b border-border p-[var(--space-3)]">
                    <h3 className="flex-1 text-body font-bold text-fg">
                        {t('workspace.media.bulk.result.heading')}
                    </h3>
                    {filters.map((one) => (
                        <button
                            key={one.key}
                            type="button"
                            aria-pressed={filter === one.key}
                            onClick={() => onFilter(one.key)}
                            className={`inline-flex min-h-[var(--control-height)] items-center rounded-[var(--radius-md)] border px-[var(--space-3)] text-body ${
                                filter === one.key
                                    ? 'border-border-strong bg-surface-active font-bold text-fg'
                                    : 'border-border bg-surface font-medium text-fg-secondary'
                            }`}
                        >
                            {t(one.labelKey)} <span className="tabular-nums">{one.count}</span>
                        </button>
                    ))}
                </div>

                <ul className="flex flex-col">
                    {rows.map((row) => (
                        <li
                            key={`${row.status}-${row.id}`}
                            className="flex min-h-[var(--density-row-height)] flex-wrap items-baseline gap-[var(--space-2)] border-t border-border px-[var(--space-3)] py-[var(--space-2)] first:border-t-0"
                        >
                            <span className="min-w-0 flex-1 truncate text-body text-fg">
                                {row.name}
                            </span>
                            <span
                                className={
                                    row.status === 'error'
                                        ? 'text-body text-fg-danger'
                                        : 'text-body text-fg-secondary'
                                }
                            >
                                {row.status === 'ok'
                                    ? t('workspace.media.bulk.result.filter.ok')
                                    : row.status === 'skip'
                                      ? t(skipReasonKey(row.reason ?? ''))
                                      : t(failureReasonKey(row.reason))}
                            </span>
                        </li>
                    ))}
                </ul>

                <div className="flex flex-wrap gap-[var(--space-2)] border-t border-border bg-surface-subtle p-[var(--space-3)]">
                    {counts.error > 0 ? (
                        <button
                            type="button"
                            disabled={running}
                            onClick={() => onRetry(counts.errorIds)}
                            className="inline-flex min-h-[var(--control-height)] items-center gap-[var(--space-2)] rounded-[var(--radius-md)] bg-action px-[var(--space-4)] text-body font-bold text-action-fg disabled:opacity-60"
                        >
                            <ArrowCounterClockwise aria-hidden="true" size={18} />
                            {t('workspace.media.bulk.result.retry', {
                                count: String(counts.error),
                            })}
                        </button>
                    ) : null}
                    <button
                        type="button"
                        onClick={downloadCsv}
                        className="inline-flex min-h-[var(--control-height)] items-center gap-[var(--space-2)] rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-body font-medium text-fg"
                    >
                        <DownloadSimple aria-hidden="true" size={18} />
                        {t('workspace.media.bulk.result.csv')}
                    </button>
                </div>
            </section>

            {/*
                DENETİM KAYDI. Kaynağın cümlesi birebir: kayıt silinemez ve
                aynı işlem anahtarıyla iş iki kez çalıştırılamaz.
            */}
            <section className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                <h3 className="pb-[var(--space-2)] text-body font-bold text-fg">
                    {t('workspace.media.bulk.audit.heading')}
                </h3>
                <StatRow label={t('workspace.media.bulk.audit.key')} value={report.operationKey} />
                <p className="pt-[var(--space-2)] text-body text-fg-secondary">
                    {t('workspace.media.bulk.audit.note')}
                </p>
            </section>

            <button
                type="button"
                onClick={onRestart}
                className="inline-flex min-h-[var(--control-height)] items-center self-start rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-body font-medium text-fg"
            >
                {t('workspace.media.bulk.result.restart')}
            </button>
        </div>
    );
}

export default MediaBulkWizardRegion;
