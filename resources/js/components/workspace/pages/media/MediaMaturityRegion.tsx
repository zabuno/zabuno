import { useEffect, useState, type ReactNode } from 'react';
import {
    Eye,
    HardDrives,
    Resize,
    Scales,
    SelectionAll,
    ShieldCheck,
    Swap,
    Trash,
    UploadSimple,
} from '@phosphor-icons/react';
import { t } from '../../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../../lib/csrfHeader';

/** Kanıt referansının çözülmüş hâli — sunucunun sözlüğü. */
export type MediaMaturityEvidence = {
    /** `endpoint` · `requirement` · `test` */
    kind: string;
    /** Kanıtın kendisi: uç yolu, gereksinim kimliği ya da test adı. */
    ref: string;
    /** `found` · `absent` · `unverifiable` */
    state: string;
};

export type MediaMaturityRung = {
    level: number;
    /** `met` · `unmet` · `unverifiable` */
    state: string;
    evidence: MediaMaturityEvidence[];
};

export type MediaMaturityCapability = {
    key: string;
    level: number;
    rungs: MediaMaturityRung[];
};

export type MediaMaturityBody = {
    selfAssessed: boolean;
    score: { achieved: number; possible: number };
    capabilities: MediaMaturityCapability[];
};

type MediaMaturityRegionProps = {
    workspaceId: number;
};

type TranslationKey = Parameters<typeof t>[0];

/** Kaynağın ölçeği (`docs/108` §6.7): L0…L4. */
const MAX_LEVEL = 4;

const LEVEL_NAME: TranslationKey[] = [
    'workspace.media.maturity.level.0',
    'workspace.media.maturity.level.1',
    'workspace.media.maturity.level.2',
    'workspace.media.maturity.level.3',
    'workspace.media.maturity.level.4',
];

const LEVEL_DESCRIPTION: TranslationKey[] = [
    'workspace.media.maturity.level.0.description',
    'workspace.media.maturity.level.1.description',
    'workspace.media.maturity.level.2.description',
    'workspace.media.maturity.level.3.description',
    'workspace.media.maturity.level.4.description',
];

/**
 * Yetenek adı SUNUCUDAN GELMEZ. Uç yalnız anahtar gönderir (`docs/37`);
 * tanınmayan bir anahtar hiç çizilmez — ham `intake` kelimesini ekrana
 * basmak, sahibi kendi ürününde yabancı yapardı.
 */
const CAPABILITY_LABEL: Record<string, TranslationKey> = {
    intake: 'workspace.media.maturity.capability.intake',
    scan: 'workspace.media.maturity.capability.scan',
    derivatives: 'workspace.media.maturity.capability.derivatives',
    convert: 'workspace.media.maturity.capability.convert',
    viewer: 'workspace.media.maturity.capability.viewer',
    trash: 'workspace.media.maturity.capability.trash',
    bulk: 'workspace.media.maturity.capability.bulk',
    quota: 'workspace.media.maturity.capability.quota',
    governance: 'workspace.media.maturity.capability.governance',
};

/** İkon DEKORATİFTİR: satırın adını ikon değil etiket taşır. */
const CAPABILITY_ICON: Record<string, ReactNode> = {
    intake: <UploadSimple aria-hidden="true" size={18} />,
    scan: <ShieldCheck aria-hidden="true" size={18} />,
    derivatives: <Resize aria-hidden="true" size={18} />,
    convert: <Swap aria-hidden="true" size={18} />,
    viewer: <Eye aria-hidden="true" size={18} />,
    trash: <Trash aria-hidden="true" size={18} />,
    bulk: <SelectionAll aria-hidden="true" size={18} />,
    quota: <HardDrives aria-hidden="true" size={18} />,
    governance: <Scales aria-hidden="true" size={18} />,
};

const EVIDENCE_KIND: Record<string, TranslationKey> = {
    endpoint: 'workspace.media.maturity.evidence.kind.endpoint',
    requirement: 'workspace.media.maturity.evidence.kind.requirement',
    test: 'workspace.media.maturity.evidence.kind.test',
};

const EVIDENCE_STATE: Record<string, TranslationKey> = {
    found: 'workspace.media.maturity.evidence.found',
    absent: 'workspace.media.maturity.evidence.absent',
    unverifiable: 'workspace.media.maturity.evidence.unverifiable',
};

function isBody(value: unknown): value is MediaMaturityBody {
    if (typeof value !== 'object' || value === null) return false;
    const body = value as Record<string, unknown>;
    const score = body.score as Record<string, unknown> | undefined;

    return (
        Array.isArray(body.capabilities) &&
        typeof score === 'object' &&
        score !== null &&
        typeof score.achieved === 'number' &&
        typeof score.possible === 'number'
    );
}

function clampLevel(level: number): number {
    if (!Number.isFinite(level) || level < 0) return 0;

    return Math.min(Math.trunc(level), MAX_LEVEL);
}

/**
 * Seviye rozeti.
 *
 * GÖRÜLEN "L2"dir, OKUNAN "Level 2 of 4". Kısaltma bir grafiktir; ekran
 * okuyucuda "el iki" diye duyulmaması için erişilebilir adı tam cümledir.
 */
function LevelBadge({ level }: { level: number }) {
    return (
        <span
            aria-label={t('workspace.media.maturity.level.badge', {
                level: String(level),
                max: String(MAX_LEVEL),
            })}
            className="inline-flex shrink-0 items-center rounded-pill border border-border px-[var(--space-2)] py-[var(--space-1)] text-meta font-bold text-fg tabular-nums"
        >
            <span aria-hidden="true">
                {t('workspace.media.maturity.level.short', { level: String(level) })}
            </span>
        </span>
    );
}

/**
 * Dört basamağın görsel özeti.
 *
 * TAMAMEN DEKORATİF ve `aria-hidden`: altındaki basamak listesi zaten her
 * basamağı kelimeyle anlatıyor. Durumu yalnız renkle anlatan bir gösterge,
 * rengi ayırt edemeyen kullanıcı için hiçbir şey anlatmaz — burada renk,
 * yazının yerine değil yanına konur.
 */
function RungTrack({ rungs, level }: { rungs: MediaMaturityRung[]; level: number }) {
    return (
        <span aria-hidden="true" className="flex gap-[var(--space-1)]">
            {rungs.map((rung) => (
                <span
                    key={rung.level}
                    className={`h-[var(--space-2)] flex-1 rounded-pill ${
                        rung.level <= level
                            ? 'bg-action'
                            : rung.state === 'unverifiable'
                              ? 'bg-fg-warning'
                              : 'bg-surface-active'
                    }`}
                />
            ))}
        </span>
    );
}

/**
 * OLGUNLUK — kanonik kaynak `docs/reference/panel-v3/MedyaModulu.dc.html`,
 * `data-screen-label="Olgunluk"`; seviye sözlüğü `docs/108` §6.7.
 *
 * ═══ BU EKRAN KENDİNİ ÖVEMEZ ═══
 *
 * Bir olgunluk tablosu, ürünün kendisi hakkında konuştuğu tek yerdir ve
 * en kolay yalan söyleyebileceği yer de burasıdır. Elle yazılmış bir "L4",
 * onu yazan kişinin o günkü iyimserliğini ölçer; restoran sahibi ise onu
 * bir güvence olarak okur ve ürünün yapamadığı bir şeye bel bağlar.
 *
 * O yüzden bu ekranda ROZET TEK BAŞINA DURMAZ. Her basamağın altında onu
 * hak ettiren KANIT yazılıdır: hangi uç kayıtlı, hangi adlandırılmış
 * gereksinim testli, hangi test yöntemi duruyor. Kanıtı olmayan basamak
 * "kanıt yok" der ve puana girmez; denetlenemeyen kanıt da "geçti"
 * sayılmaz, "buradan bakınca göremedim" der.
 *
 * Ve ekranın kendisi, ilk kutusunda, bunun bir ÖZ DEĞERLENDİRME olduğunu
 * söyler. Söylemeyen bir olgunluk tablosu, sahibin gözünde sessizce
 * bağımsız bir denetim raporuna dönüşür.
 */
export function MediaMaturityRegion({ workspaceId }: MediaMaturityRegionProps) {
    const [data, setData] = useState<MediaMaturityBody | null>(null);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/media/maturity`,
                    buildAuthRequestInit(),
                );

                if (!response.ok) return;

                const body = (await response.json()) as unknown;

                if (!cancelled && isBody(body)) {
                    setData(body);
                }
            } catch {
                // Sessiz: olgunluk okunamadı diye kütüphane çalışmaz olmaz.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId]);

    /*
        Veri gelmeden HİÇBİR ŞEY çizilmez. Boş bir puan tablosu, sahibin
        gözünde "sıfır olgunluk" demektir — oysa okunamamış bir uç,
        ölçülmemiş bir üründen başka bir şeydir.
    */
    if (data === null) {
        return null;
    }

    const ratio =
        data.score.possible > 0 ? Math.round((data.score.achieved / data.score.possible) * 100) : 0;

    return (
        <section
            aria-label={t('workspace.media.maturity.region')}
            className="flex flex-col gap-[var(--space-5)]"
        >
            <div className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-md)] border border-border p-[var(--space-4)]">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.media.maturity.score.heading')}
                </h3>
                {/* Puan bir ÖLÇÜDÜR: `text-meta`nın meşru kullanımı, sabit genişlikli rakam. */}
                <span className="text-meta font-bold text-fg tabular-nums">
                    {t('workspace.media.maturity.score.value', {
                        achieved: String(data.score.achieved),
                        possible: String(data.score.possible),
                    })}
                </span>
                <span
                    aria-hidden="true"
                    className="h-[var(--space-2)] overflow-hidden rounded-pill bg-surface-active"
                >
                    <span
                        className="block h-full rounded-pill bg-action"
                        style={{ inlineSize: `${ratio}%` }}
                    />
                </span>
                <p className="text-body text-fg-muted">
                    {t('workspace.media.maturity.score.note')}
                </p>
                {/*
                    ÖZ DEĞERLENDİRME UYARISI, puanın hemen ALTINDA durur.
                    Sayfanın dibinde bir dipnot olsaydı, puanı okuyan çoğu
                    kişi onu hiç görmezdi.
                */}
                <p className="text-body text-fg-secondary">
                    {t('workspace.media.maturity.selfAssessed')}
                </p>
            </div>

            <div className="flex flex-col gap-[var(--space-2)]">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.media.maturity.legend.heading')}
                </h3>
                <ul className="flex flex-col gap-[var(--space-2)]">
                    {LEVEL_NAME.map((nameKey, index) => (
                        <li key={nameKey} className="flex items-start gap-[var(--space-2)]">
                            <LevelBadge level={index} />
                            <span className="text-body text-fg">
                                <span className="font-medium">{t(nameKey)}</span>{' '}
                                <span className="text-fg-muted">{t(LEVEL_DESCRIPTION[index])}</span>
                            </span>
                        </li>
                    ))}
                </ul>
            </div>

            {/* 320 pikselde tek sütun: kartlar alt alta akar, hiçbir şey yan yana sıkışmaz. */}
            <ul className="flex flex-col gap-[var(--space-3)]">
                {data.capabilities.map((capability) => {
                    const labelKey = CAPABILITY_LABEL[capability.key];

                    if (labelKey === undefined) {
                        // Tanınmayan bir yetenek anahtarı UYDURULMAZ.
                        return null;
                    }

                    const level = clampLevel(capability.level);

                    return (
                        <li key={capability.key}>
                            <div
                                role="group"
                                aria-label={t(labelKey)}
                                className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-md)] border border-border p-[var(--space-3)]"
                            >
                                <div className="flex items-center gap-[var(--space-2)]">
                                    <span className="shrink-0 text-fg-secondary">
                                        {CAPABILITY_ICON[capability.key]}
                                    </span>
                                    <span className="min-w-0 flex-1 text-body font-bold text-fg">
                                        {t(labelKey)}
                                    </span>
                                    <LevelBadge level={level} />
                                </div>

                                <RungTrack rungs={capability.rungs} level={level} />

                                <p className="text-body text-fg-muted">
                                    {t(LEVEL_DESCRIPTION[level])}
                                </p>

                                <p className="text-body font-medium text-fg">
                                    {t('workspace.media.maturity.evidence.heading')}
                                </p>

                                <ul className="flex flex-col gap-[var(--space-2)]">
                                    {capability.rungs.map((rung) => (
                                        <li
                                            key={rung.level}
                                            className="flex flex-col gap-[var(--space-1)]"
                                        >
                                            <span className="text-body text-fg-secondary">
                                                {t('workspace.media.maturity.rung', {
                                                    level: String(rung.level),
                                                    name: t(LEVEL_NAME[clampLevel(rung.level)]),
                                                })}
                                            </span>

                                            {rung.evidence.length === 0 ? (
                                                /*
                                                    KANIT YOK — ve bu, ekranın
                                                    söyleyebileceği en yararlı
                                                    cümle. Sahip burada bir
                                                    boşluk değil, bir SEBEP
                                                    okur.
                                                */
                                                <span className="text-body text-fg-muted">
                                                    {t('workspace.media.maturity.evidence.none')}
                                                </span>
                                            ) : (
                                                <ul className="flex flex-col gap-[var(--space-1)]">
                                                    {rung.evidence.map((evidence) => {
                                                        const kindKey =
                                                            EVIDENCE_KIND[evidence.kind];
                                                        const stateKey =
                                                            EVIDENCE_STATE[evidence.state];

                                                        return (
                                                            <li
                                                                key={`${evidence.kind}:${evidence.ref}`}
                                                                className="flex flex-col gap-[var(--space-1)]"
                                                            >
                                                                {/*
                                                                    KANITIN KENDİSİ.
                                                                    Uzun uç yolları ve
                                                                    test adları
                                                                    kırılarak sarılır;
                                                                    yatay kaydırma
                                                                    telefonda okumayı
                                                                    bitirir.
                                                                */}
                                                                <span className="break-words text-body text-fg">
                                                                    {evidence.ref}
                                                                </span>
                                                                {kindKey === undefined ||
                                                                stateKey === undefined ? null : (
                                                                    <span
                                                                        className={`text-body ${
                                                                            evidence.state ===
                                                                            'found'
                                                                                ? 'text-fg-muted'
                                                                                : 'text-fg-warning'
                                                                        }`}
                                                                    >
                                                                        {t(
                                                                            'workspace.media.maturity.evidence.item',
                                                                            {
                                                                                kind: t(kindKey),
                                                                                state: t(stateKey),
                                                                            },
                                                                        )}
                                                                    </span>
                                                                )}
                                                            </li>
                                                        );
                                                    })}
                                                </ul>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
