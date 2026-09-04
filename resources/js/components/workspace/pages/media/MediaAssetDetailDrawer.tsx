import { useEffect, useState } from 'react';
import { Button } from '../../../catalog/forms/micro/Button';
import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { DrawerPanel } from '../../../catalog/overlays/compound/DrawerPanel';
import { t } from '../../../../i18n/workspace';
import { MediaAssetStatusBadge } from './MediaAssetStatusBadge';
import { displayName, formatBytes, formatDate } from './mediaFormat';
import { MediaRemoteSection, MediaUsageList, type Remote } from './MediaRemoteSection';
import type { MediaAsset, MediaLibraryActions, MediaUsage, MediaVersion } from '../MediaPage';

type MediaAssetDetailDrawerProps = {
    asset: MediaAsset | null;
    actions: MediaLibraryActions;
    onClose: () => void;
    onDelete: (id: number) => void;
    /** Sürüm/yeniden üretim sonrası liste satırının tazelenmesi için. */
    onChanged: () => void;
};

/**
 * Varlık detayı (`docs/49` Faz 4 madde 3): önizleme, dosya bilgisi,
 * "nerede kullanılıyor?", sürüm geçmişi (geri al), yeniden üretim.
 *
 * Kullanım ve sürümler ÇEKMECE AÇILINCA çekilir — liste yüklenirken her
 * varlık için iki istek atmak, 300 fotoğraflı bir kütüphanede 600 istek
 * demekti.
 */
export function MediaAssetDetailDrawer({ asset, ...rest }: MediaAssetDetailDrawerProps) {
    if (asset === null) {
        return null;
    }

    // Varlık değişince çekmece `key` ile sıfırdan kurulur: yükleme durumu
    // effect içinde setState ile sıfırlanmaz, ilk değerden başlar.
    return <DetailDrawer key={asset.id} asset={asset} {...rest} />;
}

function DetailDrawer({
    asset,
    actions,
    onClose,
    onDelete,
    onChanged,
}: Omit<MediaAssetDetailDrawerProps, 'asset'> & { asset: MediaAsset }) {
    const [usages, setUsages] = useState<Remote<MediaUsage>>({ state: 'loading' });
    const [versions, setVersions] = useState<Remote<MediaVersion>>({ state: 'loading' });
    const [busy, setBusy] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [altDraft, setAltDraft] = useState(asset.altText);

    const assetId = asset.id;

    useEffect(() => {
        let cancelled = false;

        void actions
            .loadUsages(assetId)
            .then((rows) => {
                if (!cancelled) setUsages({ state: 'ready', rows });
            })
            .catch(() => {
                if (!cancelled) setUsages({ state: 'error' });
            });
        void actions
            .loadVersions(assetId)
            .then((rows) => {
                if (!cancelled) setVersions({ state: 'ready', rows });
            })
            .catch(() => {
                if (!cancelled) setVersions({ state: 'error' });
            });

        return () => {
            cancelled = true;
        };
    }, [assetId, actions]);

    const name = displayName(asset);
    const latestVersion = versions.state === 'ready' ? (versions.rows[0]?.number ?? null) : null;

    async function run(key: string, work: () => Promise<unknown>, done: string) {
        if (busy !== null) return;
        setBusy(key);
        setNotice(null);
        try {
            await work();
            setNotice(done);
            setVersions({ state: 'ready', rows: await actions.loadVersions(asset.id) });
            onChanged();
        } catch {
            setNotice(t('workspace.media.library.detail.actionFailed'));
        } finally {
            setBusy(null);
        }
    }

    return (
        <DrawerPanel open onClose={onClose} title={name}>
            <div className="flex flex-col gap-4">
                {asset.previewUrl ? (
                    <img
                        src={asset.previewUrl}
                        alt={asset.altText}
                        className="w-full rounded-lg border border-border bg-surface-muted object-contain"
                    />
                ) : (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.media.library.detail.noPreview')}
                    </p>
                )}

                <MediaAssetStatusBadge status={asset.status} reason={asset.statusReason} />

                {/* Ad = alt metin; sonradan düzeltilir, adres değişmez (`docs/49` §5.2). */}
                <form
                    noValidate
                    className="flex flex-wrap items-end gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        void run(
                            'rename',
                            () => actions.updateAltText(asset.id, altDraft.trim()),
                            t('workspace.media.library.detail.renamed'),
                        );
                    }}
                >
                    <label className="flex min-w-0 flex-1 flex-col gap-1 text-meta text-fg-muted">
                        {t('workspace.media.library.detail.altText')}
                        <TextInput
                            type="text"
                            value={altDraft}
                            onChange={(event) => setAltDraft(event.target.value)}
                        />
                    </label>
                    <Button
                        color="light"
                        type="submit"
                        disabled={
                            busy !== null || altDraft.trim() === '' || altDraft === asset.altText
                        }
                    >
                        {t('workspace.media.library.detail.rename')}
                    </Button>
                </form>

                <dl className="grid grid-cols-2 gap-x-3 gap-y-1 text-body">
                    <dt className="text-fg-muted">{t('workspace.media.library.detail.file')}</dt>
                    <dd className="text-fg break-all">{asset.originalName ?? '—'}</dd>
                    <dt className="text-fg-muted">{t('workspace.media.library.detail.size')}</dt>
                    <dd className="text-fg">{formatBytes(asset.sizeBytes) || '—'}</dd>
                    <dt className="text-fg-muted">
                        {t('workspace.media.library.detail.uploaded')}
                    </dt>
                    <dd className="text-fg">{formatDate(asset.createdAt) || '—'}</dd>
                    <dt className="text-fg-muted">{t('workspace.media.library.detail.slot')}</dt>
                    <dd className="text-fg">{asset.slot}</dd>
                </dl>

                {asset.duplicateOfId ? (
                    <p role="status" className="text-meta text-fg-muted">
                        {t('workspace.media.library.detail.duplicate', {
                            id: String(asset.duplicateOfId),
                        })}
                    </p>
                ) : null}

                <MediaRemoteSection
                    id={`media-usages-${asset.id}`}
                    heading={t('workspace.media.library.usages.heading')}
                    remote={usages}
                    loading={t('workspace.media.library.usages.loading')}
                    failed={t('workspace.media.library.usages.failed')}
                    empty={t('workspace.media.library.usages.none')}
                >
                    {(rows) => <MediaUsageList usages={rows} />}
                </MediaRemoteSection>

                <MediaRemoteSection
                    id={`media-versions-${asset.id}`}
                    heading={t('workspace.media.library.versions.heading')}
                    remote={versions}
                    loading={t('workspace.media.library.versions.loading')}
                    failed={t('workspace.media.library.versions.failed')}
                    empty={t('workspace.media.library.versions.none')}
                >
                    {(rows) => (
                        <ul
                            aria-label={t('workspace.media.library.versions.heading')}
                            className="flex flex-col gap-1"
                        >
                            {rows.map((version) => (
                                <li
                                    key={version.number}
                                    className="flex items-center justify-between gap-2 text-body"
                                >
                                    <span className="text-fg">
                                        {t('workspace.media.library.versions.row', {
                                            number: String(version.number),
                                            by: version.createdBy,
                                            renditions: String(version.renditionCount),
                                        })}
                                    </span>
                                    {version.number === latestVersion ? (
                                        <span className="text-meta text-fg-muted">
                                            {t('workspace.media.library.versions.current')}
                                        </span>
                                    ) : (
                                        <Button
                                            color="light"
                                            type="button"
                                            className="text-meta"
                                            disabled={busy !== null}
                                            onClick={() =>
                                                void run(
                                                    `restore-${version.number}`,
                                                    () =>
                                                        actions.restoreVersion(
                                                            asset.id,
                                                            version.number,
                                                        ),
                                                    t('workspace.media.library.versions.restored', {
                                                        number: String(version.number),
                                                    }),
                                                )
                                            }
                                        >
                                            {t('workspace.media.library.versions.restore', {
                                                number: String(version.number),
                                            })}
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </MediaRemoteSection>

                {notice ? (
                    <p role="status" className="text-meta text-fg-secondary">
                        {notice}
                    </p>
                ) : null}

                <div className="flex flex-wrap gap-2">
                    {/*
                        Asıl dosya: sahibin kararıyla "tamamen serbest". Adres
                        10 dakika geçerli, yeni sekmede açılır; sayfa kendi
                        durumunu kaybetmez.
                    */}
                    <Button
                        color="light"
                        type="button"
                        disabled={busy !== null}
                        onClick={() =>
                            void run(
                                'download',
                                async () => {
                                    const url = await actions.downloadOriginal(asset.id);
                                    window.open(url, '_blank', 'noopener');
                                },
                                t('workspace.media.library.detail.downloadReady'),
                            )
                        }
                    >
                        {t('workspace.media.library.detail.download')}
                    </Button>
                    {asset.status === 'ready' ? (
                        <Button
                            color="light"
                            type="button"
                            disabled={busy !== null}
                            onClick={() =>
                                void run(
                                    'reprocess',
                                    () => actions.reprocess(asset.id),
                                    t('workspace.media.library.detail.reprocessed'),
                                )
                            }
                        >
                            {t('workspace.media.library.detail.reprocess')}
                        </Button>
                    ) : null}
                    <Button
                        color="light"
                        type="button"
                        disabled={busy !== null}
                        onClick={() => onDelete(asset.id)}
                        aria-label={t('workspace.media.library.asset.delete.named', { name })}
                    >
                        {t('workspace.media.library.asset.delete')}
                    </Button>
                </div>
            </div>
        </DrawerPanel>
    );
}
