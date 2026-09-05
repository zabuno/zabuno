import { useCallback, useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { Select } from '../../../catalog/forms/micro/Select';

type BrandLogoRegionProps = {
    workspaceId: number;
    /** Sunucunun söylediği mevcut bağ; `null` = logo yok. */
    initialMediaAssetId: number | null;
    /** Logo yokken gösterilen baş harf — markanın adından gelir. */
    fallbackInitial: string;
    /** "Değiştir" kullanıcıyı dosyanın evine, Medya ekranına götürür. */
    onNavigateToMedia: () => void;
};

type ReadyMediaRow = {
    id: number;
    altText: string;
    slot: string;
    status: string;
    previewUrl?: string | null;
};

/**
 * Marka logosu — `docs/98` FF-64 + kanonik kaynak (`panel.dc.html` >
 * "Ayarlar" > "Marka").
 *
 * Arka uç (`PUT .../brand/logo`, `docs/77`) 2026-08-29'dan beri vardı ve
 * misafir menüsünün başlığında logoyu gösteriyordu; ama onu BAĞLAYAN bir
 * ekran hiç olmadı. Sahip logoyu Media sayfasına yükleyebiliyor, markasına
 * takamıyordu.
 *
 * LOGO SATIRI EN ÜSTTE (docs/109). Kaynak Marka sekmesinin başına bir satır
 * koyuyor: kare bir önizleme (logo ya da baş harf), "Logo", kaynağın cümlesi
 * ve bir "Değiştir" düğmesi. Depoda bu bölüm sekmenin DİBİNDE duran bir
 * açılır listeydi ve sahip logosunu görmeden seçiyordu — yanlış dosyayı
 * bağladığını ancak misafir menüsü yayınlandıktan sonra fark ediyordu.
 * Sıra artık kaynağınki: önce BUGÜN NE OLDUĞU, sonra değiştirme yolu.
 *
 * Yükleme burada YENİDEN YAPILMAZ: "Değiştir" Medya ekranına götürür.
 * Seçici satırın altında kalır çünkü ürünün gerçek bağlama yolu odur —
 * Medya ekranında "bunu logo yap" diye bir eylem yok. Kaynağın düzenini
 * seçiciyi silerek uygulamak, yeteneği yok ederdi (docs/109 §4 madde 3).
 */
export function BrandLogoRegion({
    workspaceId,
    initialMediaAssetId,
    fallbackInitial,
    onNavigateToMedia,
}: BrandLogoRegionProps) {
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
    const bound = saved === null ? null : (media?.find((row) => row.id === saved) ?? null);
    const boundPreviewUrl = bound?.previewUrl ?? null;

    return (
        <section
            aria-labelledby="brand-logo-heading"
            className="flex flex-col gap-[var(--space-3)]"
        >
            {/*
                KAYNAĞIN LOGO SATIRI: kare önizleme + ad + cümle + "Değiştir".
                320 pikselde alt alta düşer (`flex-wrap`), çünkü 72 piksellik
                kare ile üç satır metin dar bir telefonda yan yana sığmaz.
            */}
            <div className="flex flex-wrap items-center gap-[var(--space-4)]">
                {boundPreviewUrl === null ? (
                    <span
                        aria-hidden="true"
                        data-testid="brand-logo-fallback"
                        className="grid h-[4.5rem] w-[4.5rem] shrink-0 place-items-center rounded-[var(--radius-lg)] bg-action text-section font-bold text-action-fg"
                    >
                        {fallbackInitial}
                    </span>
                ) : (
                    <img
                        src={boundPreviewUrl}
                        alt={bound?.altText || t('workspace.brand.logo.heading')}
                        className="h-[4.5rem] w-[4.5rem] shrink-0 rounded-[var(--radius-lg)] border border-border object-cover"
                    />
                )}

                <div className="flex flex-col gap-[var(--space-2)]">
                    <h3 id="brand-logo-heading" className="text-body font-bold text-fg">
                        {t('workspace.brand.logo.heading')}
                    </h3>
                    <p className="text-body text-fg-secondary">
                        {t('workspace.settings.logo.help')}
                    </p>
                    <button
                        type="button"
                        onClick={onNavigateToMedia}
                        className="min-h-[var(--control-height)] self-start rounded-md border border-border px-[var(--space-3)] py-[var(--space-2)] text-body font-medium text-fg hover:bg-surface-hover"
                    >
                        {t('workspace.settings.logo.change')}
                    </button>
                </div>
            </div>

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
                            className="min-h-[var(--density-hit-area-min)] rounded-md border border-action bg-action px-4 py-2 text-body font-bold text-action-fg"
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
