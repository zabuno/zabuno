import { type ReactNode, useEffect, useState } from 'react';
import {
    FileText,
    Files,
    ForkKnife,
    HardDrives,
    Megaphone,
    PaintBrush,
    Trash,
} from '@phosphor-icons/react';
import { t } from '../../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { formatBytes } from './mediaFormat';

export type MediaStorageTotals = {
    planLabel: string;
    bytesUsed: number;
    bytesLimit: number;
    assetsUsed: number;
    assetsLimit: number;
};

export type MediaStorageSlice = {
    key: string;
    bytes: number;
    assets: number;
};

export type MediaStorageBreakdownBody = {
    totals: MediaStorageTotals;
    categories: MediaStorageSlice[];
    trash: { bytes: number; assets: number };
};

type MediaStorageBreakdownProps = {
    workspaceId: number;
};

/**
 * Kategori ADI sunucudan gelmez, ÇEVİRİ kataloğundan gelir. Sunucu bir
 * anahtar bilir (`products`); o anahtarın sahibin dilindeki karşılığı bir
 * ÜRÜN kararıdır ve `docs/37` gereği katalogda durur.
 */
const CATEGORY_LABEL: Record<string, Parameters<typeof t>[0]> = {
    products: 'workspace.media.storage.category.products',
    promotion: 'workspace.media.storage.category.promotion',
    brand: 'workspace.media.storage.category.brand',
    documents: 'workspace.media.storage.category.documents',
    other: 'workspace.media.storage.category.other',
};

const CATEGORY_ICON: Record<string, typeof ForkKnife> = {
    products: ForkKnife,
    promotion: Megaphone,
    brand: PaintBrush,
    documents: FileText,
    other: Files,
};

/**
 * Sınıra bu orandan sonra "yaklaşılmış" sayılır. Kaynak %89'u uyarı
 * renginde gösteriyor; eşiği %85'e çekmek sahibe bir sonraki yüklemeden
 * ÖNCE haber verir — duvara toslamadan.
 */
const NEAR_LIMIT_RATIO = 0.85;

function isBody(value: unknown): value is MediaStorageBreakdownBody {
    if (typeof value !== 'object' || value === null) return false;
    const body = value as Record<string, unknown>;
    const totals = body.totals as Record<string, unknown> | undefined;

    return (
        typeof totals === 'object' &&
        totals !== null &&
        typeof totals.bytesUsed === 'number' &&
        typeof totals.bytesLimit === 'number' &&
        Array.isArray(body.categories)
    );
}

function percent(part: number, whole: number): number {
    if (!Number.isFinite(whole) || whole <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((part / whole) * 100));
}

/**
 * Ölçü şeridi. Genişlik VERİDEN gelir, tasarımdan değil — bu yüzden satır
 * içi bir yüzdedir; bir tasarım sabiti olsaydı jetona bağlanırdı.
 *
 * Şerit `aria-hidden`: yanındaki oran cümlesi aynı bilgiyi zaten
 * söylüyor ve ekran okuyucuya iki kez duyurmak gürültüdür.
 */
function Meter({ ratio, warning }: { ratio: number; warning: boolean }) {
    return (
        <div
            aria-hidden="true"
            className="h-[var(--space-2)] overflow-hidden rounded-pill bg-surface-active"
        >
            <div
                className={`h-full rounded-pill ${warning ? 'bg-fg-warning' : 'bg-action'}`}
                style={{ inlineSize: `${ratio}%` }}
            />
        </div>
    );
}

/**
 * Kota KARTI — kaynağın §6.4 listesinden yalnız SAYILAN olanlar.
 *
 * Kaynak dört kart sayıyor (depolama, dosya sayısı, dönüştürme, CDN
 * trafiği). Bu depoda son ikisinin ne sayacı ne sınırı var; uç onları hiç
 * göndermiyor, burası da çizmiyor. Uydurulmuş bir kart, sahibi olmayan bir
 * yeteneğe güvendirir.
 */
function QuotaCard({
    icon,
    label,
    used,
    limit,
    ratioText,
    freeText,
}: {
    /** DEKORATİF: kartın adını ikon değil etiket taşır. */
    icon: ReactNode;
    label: string;
    used: number;
    limit: number;
    ratioText: string;
    freeText: string;
}) {
    const ratio = percent(used, limit);
    const near = limit > 0 && used / limit >= NEAR_LIMIT_RATIO;

    return (
        <div className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-md)] border border-border p-[var(--space-3)]">
            {/* Kartın ADI gövde metnidir; ayrım boyutla değil renkle yapılır. */}
            <span className="flex items-center gap-[var(--space-2)] text-body text-fg-secondary">
                {icon}
                {label}
            </span>
            {/* Değer bir ÖLÇÜDÜR: `text-meta`nın meşru kullanımı, sabit genişlikli rakam. */}
            <span className="text-meta font-medium text-fg tabular-nums">{ratioText}</span>
            <Meter ratio={ratio} warning={near} />
            <span className={`text-body ${near ? 'text-fg-warning' : 'text-fg-muted'}`}>
                {near
                    ? t('workspace.media.storage.card.near', { free: freeText })
                    : t('workspace.media.storage.card.free', {
                          percent: String(ratio),
                          free: freeText,
                      })}
            </span>
        </div>
    );
}

/**
 * "YERİ NE DOLDURUYOR?" (kanonik kaynak: `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html`, ekran etiketi "Kota ve çöp"; somut liste
 * `docs/108` §6.4).
 *
 * Kota şeridi bugüne kadar tek bir cümle söylüyordu: "185 MB / 200 MB".
 * Restoran sahibi bunu okuduğunda NE YAPACAĞINI bilmiyordu — hangi
 * dosyaları silsin? Bir toplam yalnız "dolu" der; kırılım bir EYLEM önerir.
 *
 * ÜÇ DÜRÜSTLÜK KURALI:
 *
 *   1. Kart yalnız SAYILAN şey için çizilir.
 *   2. Satır SLOT ADI taşımaz. Eşlemenin gerekçesi sunucudadır
 *      (`App\Domain\Media\StorageCategory`); ekran yalnız anahtarı çevirir.
 *   3. ÇÖP AYRI ve uyarı renginde. Silmek yer AÇMAZ (kota çöpü içerir);
 *      çöp, sahibin bugün geri kazanabileceği tek dilimdir. Boşsa satır
 *      hiç çizilmez — boşaltılacak bir şey yoktur.
 *
 * Uç okunamazsa bölüm sessizce çekilir: kota bir kapı değil göstergedir ve
 * gösterge yok diye yükleme durmaz.
 */
export function MediaStorageBreakdown({ workspaceId }: MediaStorageBreakdownProps) {
    const [data, setData] = useState<MediaStorageBreakdownBody | null>(null);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/media/storage-breakdown`,
                    buildAuthRequestInit(),
                );

                if (!response.ok) return;

                const body = (await response.json()) as unknown;

                if (!cancelled && isBody(body)) {
                    setData(body);
                }
            } catch {
                // Sessiz: sunucu kotayı kendi uygular, gösterge bir kapı değildir.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId]);

    if (data === null) {
        return null;
    }

    const { totals, categories } = data;
    // Çöp alanı eksik gelirse SIFIR varsayılır: eksik veri "bilinmiyor"dur,
    // uydurulmuş bir sayı değil.
    const trash = data.trash ?? { bytes: 0, assets: 0 };
    const storedBytes = totals.bytesUsed;
    const hasTrash = trash.assets > 0 || trash.bytes > 0;

    return (
        <section
            aria-label={t('workspace.media.storage.region')}
            className="flex flex-col gap-[var(--space-4)]"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-[var(--space-2)]">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.media.storage.breakdown.heading')}
                </h3>
                <span className="text-body text-fg-muted">
                    {t('workspace.media.storage.plan', { plan: totals.planLabel })}
                </span>
            </div>

            <div className="grid grid-cols-[repeat(auto-fit,minmax(12rem,1fr))] gap-[var(--space-3)]">
                <QuotaCard
                    icon={<HardDrives aria-hidden="true" size={18} />}
                    label={t('workspace.media.storage.card.storage')}
                    used={totals.bytesUsed}
                    limit={totals.bytesLimit}
                    ratioText={t('workspace.media.storage.card.ratio', {
                        used: formatBytes(totals.bytesUsed),
                        limit: formatBytes(totals.bytesLimit),
                    })}
                    freeText={formatBytes(Math.max(0, totals.bytesLimit - totals.bytesUsed))}
                />
                <QuotaCard
                    icon={<Files aria-hidden="true" size={18} />}
                    label={t('workspace.media.storage.card.assets')}
                    used={totals.assetsUsed}
                    limit={totals.assetsLimit}
                    ratioText={t('workspace.media.storage.card.ratio', {
                        used: String(totals.assetsUsed),
                        limit: String(totals.assetsLimit),
                    })}
                    freeText={String(Math.max(0, totals.assetsLimit - totals.assetsUsed))}
                />
            </div>

            {categories.length === 0 && !hasTrash ? (
                <p className="text-body text-fg-muted">
                    {t('workspace.media.storage.breakdown.empty')}
                </p>
            ) : (
                <ul className="flex flex-col gap-[var(--space-3)]">
                    {categories.map((slice) => {
                        const Icon = CATEGORY_ICON[slice.key] ?? Files;
                        const labelKey =
                            CATEGORY_LABEL[slice.key] ?? 'workspace.media.storage.category.other';
                        const share = percent(slice.bytes, storedBytes);

                        return (
                            <li key={slice.key} className="flex flex-col gap-[var(--space-1)]">
                                <div className="flex flex-wrap items-baseline gap-[var(--space-2)]">
                                    <Icon aria-hidden="true" size={18} />
                                    <span className="flex-1 text-body text-fg">{t(labelKey)}</span>
                                    <span className="text-meta text-fg-muted tabular-nums">
                                        {t('workspace.media.storage.share', {
                                            bytes: formatBytes(slice.bytes),
                                            percent: String(share),
                                        })}
                                    </span>
                                </div>
                                <Meter ratio={share} warning={false} />
                            </li>
                        );
                    })}

                    {hasTrash ? (
                        /*
                            ÇÖP UYARI RENGİNDE. Rengi bir hata değil, bir
                            FIRSAT işaretidir: burası boşaltılabilir bir yer
                            kaplar ve sahibin elindeki tek geri kazanma
                            düğmesidir. Satır listenin SONUNDADIR çünkü
                            kategori değil, kategori dışıdır.
                        */
                        <li className="flex flex-col gap-[var(--space-1)] text-fg-warning">
                            <div className="flex flex-wrap items-baseline gap-[var(--space-2)]">
                                <Trash aria-hidden="true" size={18} />
                                <span className="flex-1 text-body">
                                    {t('workspace.media.storage.trash')}
                                </span>
                                <span className="text-meta tabular-nums">
                                    {t('workspace.media.storage.share', {
                                        bytes: formatBytes(trash.bytes),
                                        percent: String(percent(trash.bytes, storedBytes)),
                                    })}
                                </span>
                            </div>
                            <Meter ratio={percent(trash.bytes, storedBytes)} warning={true} />
                            <p className="text-body">
                                {t('workspace.media.storage.trash.note', {
                                    bytes: formatBytes(trash.bytes),
                                })}
                            </p>
                        </li>
                    ) : null}
                </ul>
            )}
        </section>
    );
}
