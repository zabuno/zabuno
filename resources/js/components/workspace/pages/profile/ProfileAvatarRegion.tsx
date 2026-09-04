import { useCallback, useEffect, useId, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { MediaDropzone, type SelectedImage } from '../media/MediaDropzone';

type ProfileAvatarRegionProps = {
    workspaceId: number;
    /** Sunucunun bildirdiği mevcut bağ; `null` = fotoğraf yok. */
    initialMediaAssetId: number | null;
    /** Mevcut fotoğrafın önizleme adresi; işlenme bitmemişse `null`. */
    initialAvatarUrl: string | null;
    /** Baş harf dairesi için; fotoğraf yokken görünen şey. */
    fallbackInitial: string;
};

type UploadState = 'idle' | 'uploading' | 'binding' | 'done' | 'error';

function newIdempotencyKey(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
}

/**
 * Profil fotoğrafı — sahibin isteği (2026-09-04).
 *
 * Fotoğraf AYRI bir dosya yolu değil, medya kütüphanesinin bir varlığıdır:
 * karantinaya girer, taranır, türevleri üretilir, kotaya sayılır (`docs/49`).
 * Bu yüzden burada ikinci bir yükleme yolu YAZILMAZ — Media sayfasının
 * bıraktığı alan (`MediaDropzone`) aynen kullanılır, yalnız yuvası
 * `profileAvatar` olarak sabitlenir.
 *
 * İki adım tek düğmede birleşir: yükle, sonra bağla. Kullanıcı için tek bir
 * iş vardır ("fotoğrafımı koy"); sistemin bunu iki kayıtla yapması onun
 * sorunu değildir.
 *
 * İşlenme kuyrukta sürer. O yüzden başarıdan hemen sonra fotoğraf
 * görünmeyebilir ve ekran bunu SÖYLER — sessizce boş kalmaz.
 */
export function ProfileAvatarRegion({
    workspaceId,
    initialMediaAssetId,
    initialAvatarUrl,
    fallbackInitial,
}: ProfileAvatarRegionProps) {
    const dropzoneId = useId();
    const [selected, setSelected] = useState<SelectedImage | null>(null);
    const [state, setState] = useState<UploadState>('idle');
    const [error, setError] = useState<string | null>(null);
    const [boundId, setBoundId] = useState<number | null>(initialMediaAssetId);
    const [avatarUrl, setAvatarUrl] = useState<string | null>(initialAvatarUrl);
    const [processing, setProcessing] = useState(false);

    useEffect(
        () => () => {
            if (selected !== null) URL.revokeObjectURL(selected.previewUrl);
        },
        [selected],
    );

    const bind = useCallback(async (mediaAssetId: number | null) => {
        await bootstrapCsrfCookie();
        const response = await fetch(
            '/api/user/avatar',
            buildAuthRequestInit({
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mediaAssetId }),
            }),
        );

        if (!response.ok) {
            throw new Error('bind-failed');
        }

        return mediaAssetId;
    }, []);

    const upload = useCallback(async () => {
        if (selected === null) return;

        setState('uploading');
        setError(null);

        try {
            await bootstrapCsrfCookie();
            const formData = new FormData();
            formData.append('file', selected.file);
            formData.append('altText', t('workspace.profile.avatar.alt_default'));
            formData.append('slot', 'profileAvatar');
            formData.append('idempotencyKey', newIdempotencyKey());

            const response = await fetch(
                `/api/workspaces/${workspaceId}/media`,
                buildAuthRequestInit({ method: 'POST', body: formData }),
            );

            if (!response.ok) {
                const body = (await response.json().catch(() => null)) as {
                    message?: string;
                } | null;
                setError(body?.message ?? t('workspace.profile.avatar.error'));
                setState('error');
                return;
            }

            const body = (await response.json()) as { id: number };
            setState('binding');
            await bind(body.id);
            setBoundId(body.id);
            /*
                Yerel önizleme GEÇİCİ olarak gösterilir: sunucu türevi henüz
                yok. Yalanmış gibi durmasın diye yanına "işleniyor" notu
                düşülür.
            */
            setAvatarUrl(selected.previewUrl);
            setProcessing(true);
            setSelected(null);
            setState('done');
        } catch {
            setError(t('workspace.profile.avatar.error'));
            setState('error');
        }
    }, [bind, selected, workspaceId]);

    const remove = useCallback(async () => {
        setState('binding');
        setError(null);
        try {
            await bind(null);
            setBoundId(null);
            setAvatarUrl(null);
            setProcessing(false);
            setState('done');
        } catch {
            setError(t('workspace.profile.avatar.error'));
            setState('error');
        }
    }, [bind]);

    const busy = state === 'uploading' || state === 'binding';

    return (
        <section
            aria-labelledby="profile-avatar-heading"
            className="flex flex-col gap-[var(--space-3)]"
        >
            <h3 id="profile-avatar-heading" className="text-body font-semibold text-fg">
                {t('workspace.profile.avatar.heading')}
            </h3>
            <p className="text-body text-fg-secondary">{t('workspace.profile.avatar.help')}</p>

            <div className="flex items-center gap-[var(--space-4)]">
                {avatarUrl === null ? (
                    <span
                        aria-hidden="true"
                        className="flex h-[4rem] w-[4rem] shrink-0 items-center justify-center rounded-pill bg-[var(--color-surface-active)] text-heading font-semibold text-fg"
                    >
                        {fallbackInitial}
                    </span>
                ) : (
                    <img
                        src={avatarUrl}
                        alt={t('workspace.profile.avatar.current_alt')}
                        className="h-[4rem] w-[4rem] shrink-0 rounded-pill object-cover"
                    />
                )}
                {processing ? (
                    <span role="status" className="text-meta text-fg-muted">
                        {t('workspace.profile.avatar.processing')}
                    </span>
                ) : null}
            </div>

            <MediaDropzone
                selected={selected}
                invalid={false}
                describedBy={dropzoneId}
                onSelect={setSelected}
            />
            <p id={dropzoneId} className="text-meta text-fg-muted">
                {t('workspace.profile.avatar.formats')}
            </p>

            <div className="flex flex-wrap items-center gap-[var(--space-2)]">
                <button
                    type="button"
                    className="min-h-[var(--density-hit-area-min)] rounded-md border border-action bg-action px-4 py-2 text-body font-semibold text-action-fg disabled:opacity-60"
                    disabled={busy || selected === null}
                    onClick={() => void upload()}
                >
                    {busy
                        ? t('workspace.profile.avatar.saving')
                        : t('workspace.profile.avatar.save')}
                </button>
                {boundId !== null ? (
                    <button
                        type="button"
                        className="min-h-[var(--density-hit-area-min)] rounded-md border border-border px-4 py-2 text-body font-medium text-fg-secondary hover:bg-surface-hover"
                        disabled={busy}
                        onClick={() => void remove()}
                    >
                        {t('workspace.profile.avatar.remove')}
                    </button>
                ) : null}
                {state === 'done' ? (
                    <span role="status" className="text-body text-fg-secondary">
                        {t('workspace.profile.avatar.saved')}
                    </span>
                ) : null}
            </div>

            {error !== null ? (
                <p role="alert" className="text-body text-fg-danger">
                    {error}
                </p>
            ) : null}
        </section>
    );
}

export default ProfileAvatarRegion;
