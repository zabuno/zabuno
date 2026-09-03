import { useCallback, useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { Select } from '../../../catalog/forms/micro/Select';

type BrandLogoRegionProps = {
    workspaceId: number;
    /** Sunucunun söylediği mevcut bağ; `null` = logo yok. */
    initialMediaAssetId: number | null;
};

type ReadyMediaRow = {
    id: number;
    altText: string;
    slot: string;
    status: string;
};

/**
 * Marka logosu — `docs/98` FF-64.
 *
 * Arka uç (`PUT .../brand/logo`, `docs/77`) 2026-08-29'dan beri vardı ve
 * misafir menüsünün başlığında logoyu gösteriyordu; ama onu BAĞLAYAN bir
 * ekran hiç olmadı. Sahip logoyu Media sayfasına yükleyebiliyor, markasına
 * takamıyordu.
 *
 * Yükleme burada YENİDEN YAPILMAZ: Media sayfasındaki `logo` slotuna
 * yüklenmiş, işlenmesi bitmiş görseller listelenir ve biri seçilir —
 * ürün fotoğrafı seçiciyle aynı desen (`MenuCatalogWorkspace`).
 */
export function BrandLogoRegion({ workspaceId, initialMediaAssetId }: BrandLogoRegionProps) {
    const [media, setMedia] = useState<ReadyMediaRow[] | null>(null);
    const [choice, setChoice] = useState(
        initialMediaAssetId === null ? '' : String(initialMediaAssetId),
    );
    const [saved, setSaved] = useState<number | null>(initialMediaAssetId);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [done, setDone] = useState(false);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/media`,
                    buildAuthRequestInit(),
                );
                if (cancelled) return;
                if (!response.ok) {
                    setMedia([]);
                    return;
                }
                const body = (await response.json()) as { data?: ReadyMediaRow[] };
                setMedia(
                    (body.data ?? []).filter(
                        (row) => row.status === 'ready' && row.slot === 'logo',
                    ),
                );
            } catch {
                if (!cancelled) setMedia([]);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId]);

    const bind = useCallback(
        async (mediaAssetId: number | null) => {
            setSaving(true);
            setError(null);
            setDone(false);
            try {
                await bootstrapCsrfCookie();
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/brand/logo`,
                    buildAuthRequestInit({
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ mediaAssetId }),
                    }),
                );
                if (!response.ok) {
                    const body = (await response.json().catch(() => null)) as {
                        message?: string;
                    } | null;
                    setError(body?.message ?? t('workspace.brand.logo.error'));
                    return;
                }
                setSaved(mediaAssetId);
                setChoice(mediaAssetId === null ? '' : String(mediaAssetId));
                setDone(true);
            } catch {
                setError(t('workspace.brand.logo.error'));
            } finally {
                setSaving(false);
            }
        },
        [workspaceId],
    );

    const dirty = (choice === '' ? null : Number(choice)) !== saved;

    return (
        <section
            aria-labelledby="brand-logo-heading"
            className="mt-6 flex flex-col gap-3 border-t border-border pt-6"
        >
            <h3 id="brand-logo-heading" className="text-body font-semibold text-fg">
                {t('workspace.brand.logo.heading')}
            </h3>
            <p className="text-body text-fg-secondary">{t('workspace.brand.logo.help')}</p>

            {media === null ? (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.brand.logo.loading')}
                </p>
            ) : media.length === 0 ? (
                // Boş durum bir sonraki eylemi söyler: yükleme Media'da.
                <p className="text-body text-fg-muted">{t('workspace.brand.logo.empty')}</p>
            ) : (
                <>
                    <label className="text-body font-medium text-fg" htmlFor="brand-logo-choice">
                        {t('workspace.brand.logo.choose')}
                    </label>
                    <Select
                        id="brand-logo-choice"
                        name="brand-logo-choice"
                        value={choice}
                        onChange={(event) => setChoice(event.target.value)}
                    >
                        <option value="">{t('workspace.brand.logo.none')}</option>
                        {media.map((row) => (
                            <option key={row.id} value={String(row.id)}>
                                {row.altText || `#${row.id}`}
                            </option>
                        ))}
                    </Select>
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            className="min-h-[var(--density-hit-area-min)] rounded-md border border-action bg-action px-4 py-2 text-body font-semibold text-action-fg"
                            disabled={saving || !dirty}
                            onClick={() => void bind(choice === '' ? null : Number(choice))}
                        >
                            {saving
                                ? t('workspace.brand.logo.saving')
                                : t('workspace.brand.logo.save')}
                        </button>
                        {done ? (
                            <span role="status" className="text-body text-fg-secondary">
                                {t('workspace.brand.logo.saved')}
                            </span>
                        ) : null}
                    </div>
                </>
            )}

            {error !== null ? (
                <p role="alert" className="text-body text-fg-danger">
                    {error}
                </p>
            ) : null}
        </section>
    );
}

export default BrandLogoRegion;
