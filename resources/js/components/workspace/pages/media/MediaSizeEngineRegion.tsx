import { useCallback, useEffect, useState } from 'react';
import { ArrowsClockwise } from '@phosphor-icons/react';
import { t } from '../../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { formatBytes } from './mediaFormat';

export type MediaDerivativeRule = {
    name: string;
    width: number;
    height: number | null;
    fit: string;
    formats: string[];
    /** Bu ölçüyü BUGÜN gerçekten üreten slotlar; boşsa hiç üretilmiyor. */
    producedBySlots: string[];
};

export type MediaDerivativeRules = {
    rules: MediaDerivativeRule[];
    regeneration: {
        affectedAssets: number;
        existingRenditions: number;
        batchLimit: number;
    };
    measured: {
        assets: number;
        originalBytes: number;
        largestRenditionBytes: number;
    };
};

type MediaSizeEngineRegionProps = {
    workspaceId: number;
};

type LoadState = 'loading' | 'error' | 'idle';

/**
 * Kural ADINDAN o ölçünün İŞİNE. Sunucu ölçüyü bilir; o ölçünün hangi ekranı
 * beslediğini ÜRÜN bilir ve o cümle çeviri kataloğunda durur.
 */
const USAGE_LABEL: Record<string, Parameters<typeof t>[0]> = {
    thumb: 'workspace.media.engine.rule.thumb.usage',
    small: 'workspace.media.engine.rule.small.usage',
    medium: 'workspace.media.engine.rule.medium.usage',
    large: 'workspace.media.engine.rule.large.usage',
    social: 'workspace.media.engine.rule.social.usage',
    print: 'workspace.media.engine.rule.print.usage',
};

const FIT_LABEL: Record<string, Parameters<typeof t>[0]> = {
    crop: 'workspace.media.engine.fit.crop',
    contain: 'workspace.media.engine.fit.contain',
};

function isRules(value: unknown): value is MediaDerivativeRules {
    if (typeof value !== 'object' || value === null) return false;
    const body = value as Record<string, unknown>;
    return Array.isArray(body.rules) && typeof body.regeneration === 'object';
}

/** Etiket + değer: değer daima `tabular-nums`, çünkü hepsi rakamdır. */
function StatRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex min-h-[var(--density-row-height)] flex-wrap items-baseline gap-[var(--space-2)] border-t border-border py-[var(--space-2)] first:border-t-0">
            <span className="flex-1 text-body text-fg-secondary">{label}</span>
            <span className="text-meta font-medium text-fg tabular-nums">{value}</span>
        </div>
    );
}

/**
 * BOYUT MOTORU — "her yüklenen görselden hangi boyutlar üretilecek?"
 * (kanonik kaynak: `docs/reference/media-manager/Medya Yonetimi v2.dc.html`,
 * ekran etiketi "Boyut motoru"; somut tablo `docs/108` §6.1).
 *
 * Bugüne kadar kural `config/media-slots.php` içinde slot başına düz bir
 * SAYI LİSTESİYDİ ve sahibin eli oraya hiç değmiyordu. `320` bir sayıdır;
 * `small · menü kartı · telefon` bir karardır — ve kural değiştiğinde hangi
 * ekranın etkileneceğini yalnız ikincisi söyler.
 *
 * ÜÇ DÜRÜSTLÜK KURALI bu bölümü biçimlendirir:
 *
 *   1. Adlandırılmış olmak ÜRETİLİYOR olmak değildir. Boru hattı bugün slot
 *      genişlik listesinden üretiyor; hiçbir slotta karşılığı olmayan kural
 *      "henüz üretilmiyor" diye YAZILIR. Gizlemek, sahibi olmayan bir
 *      yeteneğe güvendirirdi.
 *   2. Yeni kural yalnız yeni yüklemelere uygulanır. Eskiler ancak açık bir
 *      yeniden üretim işiyle değişir ve o iş asılları korur.
 *   3. "Ölçülen kazanç" UYDURULMAZ. Gerçek bayt farkı yoksa (hiç işlenmiş
 *      dosya yok ya da türev asıldan küçük değil) bölüm HİÇ ÇİZİLMEZ.
 *
 * Kurallar tek bir kartın İÇİDİR ve ayraç 1 piksellik çizgidir: kart başına
 * bir kural, altı ayrı duyuru gibi okunurdu.
 */
export function MediaSizeEngineRegion({ workspaceId }: MediaSizeEngineRegionProps) {
    const [data, setData] = useState<MediaDerivativeRules | null>(null);
    const [loadState, setLoadState] = useState<LoadState>('loading');
    const [regenerating, setRegenerating] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const [noticeIsError, setNoticeIsError] = useState(false);

    const load = useCallback(async (): Promise<MediaDerivativeRules | null> => {
        const response = await fetch(
            `/api/workspaces/${workspaceId}/media/derivative-rules`,
            buildAuthRequestInit(),
        );

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const body = (await response.json()) as unknown;

        return isRules(body) ? body : null;
    }, [workspaceId]);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const body = await load();
                if (cancelled) return;
                setData(body);
                setLoadState(body === null ? 'error' : 'idle');
            } catch {
                if (!cancelled) setLoadState('error');
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [load]);

    async function handleRegenerate() {
        if (regenerating) {
            return;
        }

        setRegenerating(true);
        setNotice(null);
        setNoticeIsError(false);

        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/media/reprocess`, {
                ...buildAuthRequestInit({ method: 'POST' }),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const body = (await response.json()) as {
                succeeded?: number;
                failed?: number;
                remaining?: number;
            };

            /*
                Cevap ÜÇ sayıyı birden taşır ve üçü de söylenir. "Bitti"
                demek, kırk dokuzu yenilenip biri düşmüşken sahibi yanıltır;
                kalan dosya sayısını söylememek de onu ekranın önünde
                beklemeye bırakır.
            */
            const parts = [
                t('workspace.media.engine.regen.done', { count: String(body.succeeded ?? 0) }),
            ];

            if ((body.failed ?? 0) > 0) {
                parts.push(
                    t('workspace.media.engine.regen.someFailed', {
                        count: String(body.failed ?? 0),
                    }),
                );
            }

            if ((body.remaining ?? 0) > 0) {
                parts.push(
                    t('workspace.media.engine.regen.remaining', {
                        count: String(body.remaining ?? 0),
                    }),
                );
            }

            setNotice(parts.join(' '));

            // Sayılar yeniden üretimden sonra DEĞİŞİR; eski sayıyı ekranda
            // bırakmak, yapılan işi yapılmamış gösterirdi.
            const refreshed = await load();
            if (refreshed !== null) {
                setData(refreshed);
            }
        } catch {
            setNotice(t('workspace.media.engine.regen.failed'));
            setNoticeIsError(true);
        } finally {
            setRegenerating(false);
        }
    }

    if (loadState === 'loading') {
        return <p className="text-body text-fg-muted">{t('workspace.media.engine.loading')}</p>;
    }

    if (loadState === 'error' || data === null) {
        return (
            <p role="alert" className="text-body text-fg-danger">
                {t('workspace.media.engine.failed')}
            </p>
        );
    }

    const { measured, regeneration } = data;

    /*
        ÖLÇÜLEN KAZANÇ ancak GERÇEKTEN ölçüldüyse çizilir: hiç işlenmiş dosya
        yoksa, ya da servis edilen en büyük türev asıldan küçük değilse,
        ortada gösterilecek bir kazanç yoktur ve olmayan bir kazancı çizmek
        yalan olurdu.
    */
    const hasMeasuredSaving =
        measured.assets > 0 &&
        measured.originalBytes > 0 &&
        measured.largestRenditionBytes > 0 &&
        measured.largestRenditionBytes < measured.originalBytes;

    const savedPercent = hasMeasuredSaving
        ? Math.round(
              ((measured.originalBytes - measured.largestRenditionBytes) / measured.originalBytes) *
                  100,
          )
        : 0;

    return (
        <section
            aria-label={t('workspace.media.engine.region')}
            className="flex flex-col gap-[var(--space-4)]"
        >
            <p className="text-body text-fg-secondary">{t('workspace.media.engine.lead')}</p>

            {/*
                Kurallar TEK kartın içidir; ayrım 1 piksellik çizgidir.
                Boşluk + çizgi birlikte, çizgiyi satırdan koparıp havada
                bırakırdı (kütüphane ve denetim izi ile aynı gramer).
            */}
            <ul className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                {data.rules.map((rule) => (
                    <li
                        key={rule.name}
                        className="flex flex-col gap-[var(--space-1)] border-t border-border py-[var(--space-3)] first:border-t-0 first:pt-0 last:pb-0"
                    >
                        <div className="flex flex-wrap items-baseline gap-[var(--space-2)]">
                            <span className="text-body font-medium text-fg">{rule.name}</span>
                            {/*
                                Ölçü bir SAYIDIR: `text-meta` burada meşrudur
                                ve `tabular-nums` şarttır — altı ölçü alt
                                alta okunur, orantılı rakamda sütun titrer.
                            */}
                            <span className="text-meta text-fg-secondary tabular-nums">
                                {rule.height === null
                                    ? t('workspace.media.engine.rule.width', {
                                          width: String(rule.width),
                                      })
                                    : t('workspace.media.engine.rule.frame', {
                                          width: String(rule.width),
                                          height: String(rule.height),
                                      })}
                            </span>
                        </div>

                        <span className="text-body text-fg-muted">
                            {`${t(
                                USAGE_LABEL[rule.name] ??
                                    'workspace.media.engine.rule.usage.unknown',
                            )} · ${t(FIT_LABEL[rule.fit] ?? 'workspace.media.engine.fit.contain')}`}
                        </span>

                        <div className="flex flex-wrap items-center gap-[var(--space-2)]">
                            {rule.formats.map((format) => (
                                <span
                                    key={format}
                                    className="rounded-pill border border-border px-[var(--space-2)] text-body text-fg-secondary"
                                >
                                    {format}
                                </span>
                            ))}
                            {/*
                                DÜRÜSTLÜK ROZETİ. Kuralın adı olması, o
                                ölçünün üretildiği anlamına gelmez.
                            */}
                            <span className="text-body text-fg-muted">
                                {rule.producedBySlots.length === 0
                                    ? t('workspace.media.engine.rule.notProduced')
                                    : t('workspace.media.engine.rule.producedBy', {
                                          count: String(rule.producedBySlots.length),
                                      })}
                            </span>
                        </div>
                    </li>
                ))}
            </ul>

            <section className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.media.engine.regen.heading')}
                </h3>
                <p className="text-body text-fg-muted">{t('workspace.media.engine.regen.lead')}</p>

                <div className="flex flex-col">
                    <StatRow
                        label={t('workspace.media.engine.regen.affected')}
                        value={String(regeneration.affectedAssets)}
                    />
                    <StatRow
                        label={t('workspace.media.engine.regen.renditions')}
                        value={String(regeneration.existingRenditions)}
                    />
                    <StatRow
                        label={t('workspace.media.engine.regen.batch')}
                        value={String(regeneration.batchLimit)}
                    />
                </div>

                {/*
                    Dokunulacak dosya yokken düğme KAPALI: basılabilen ama
                    hiçbir şey yapmayan bir düğme, ürünün bozuk olduğunu
                    düşündürür.
                */}
                <button
                    type="button"
                    onClick={() => void handleRegenerate()}
                    disabled={regenerating || regeneration.affectedAssets === 0}
                    className="inline-flex min-h-[var(--control-height)] items-center justify-center gap-[var(--space-2)] self-start rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-body font-medium text-fg hover:bg-surface-hover disabled:opacity-60"
                >
                    <ArrowsClockwise aria-hidden="true" size={18} />
                    {regenerating
                        ? t('workspace.media.engine.regen.running')
                        : t('workspace.media.engine.regen.start')}
                </button>

                {regeneration.affectedAssets === 0 ? (
                    <p className="text-body text-fg-muted">
                        {t('workspace.media.engine.regen.nothing')}
                    </p>
                ) : null}

                {notice === null ? null : (
                    <p
                        role="status"
                        className={
                            noticeIsError
                                ? 'text-body font-medium text-fg-danger'
                                : 'text-body text-fg-secondary'
                        }
                    >
                        {notice}
                    </p>
                )}
            </section>

            {hasMeasuredSaving ? (
                <section className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                    <h3 className="text-body font-bold text-fg">
                        {t('workspace.media.engine.measured.heading')}
                    </h3>
                    <p className="text-body text-fg-muted">
                        {t('workspace.media.engine.measured.lead', {
                            count: String(measured.assets),
                        })}
                    </p>
                    <div className="flex flex-col">
                        <StatRow
                            label={t('workspace.media.engine.measured.originals')}
                            value={formatBytes(measured.originalBytes)}
                        />
                        <StatRow
                            label={t('workspace.media.engine.measured.served')}
                            value={formatBytes(measured.largestRenditionBytes)}
                        />
                    </div>
                    {/*
                        Fark bir SONUÇ cümlesidir, dördüncü bir sayaç değil:
                        sahibin okuyacağı tek satır budur.
                    */}
                    <p className="text-body font-medium text-fg tabular-nums">
                        {t('workspace.media.engine.measured.delta', {
                            percent: String(savedPercent),
                        })}
                    </p>
                </section>
            ) : null}
        </section>
    );
}

export default MediaSizeEngineRegion;
