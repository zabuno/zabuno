import { t, type WorkspaceTranslationKey } from '../../../../i18n/workspace';

export type MediaAssetStatus =
    'quarantined' | 'scanning' | 'rejected' | 'accepted' | 'processing' | 'ready' | 'failed';

export const MEDIA_ASSET_STATUSES: MediaAssetStatus[] = [
    'quarantined',
    'scanning',
    'rejected',
    'accepted',
    'processing',
    'ready',
    'failed',
];

type MediaAssetStatusBadgeProps = {
    status: string;
};

const STATUS_COPY_KEY: Record<MediaAssetStatus, WorkspaceTranslationKey> = {
    quarantined: 'workspace.media.library.asset.status.quarantined',
    scanning: 'workspace.media.library.asset.status.scanning',
    rejected: 'workspace.media.library.asset.status.rejected',
    accepted: 'workspace.media.library.asset.status.accepted',
    processing: 'workspace.media.library.asset.status.processing',
    ready: 'workspace.media.library.asset.status.ready',
    failed: 'workspace.media.library.asset.status.failed',
};

const STATUS_TONE_CLASS: Record<MediaAssetStatus | 'unknown', string> = {
    quarantined: 'text-fg-warning',
    scanning: 'text-fg-warning',
    rejected: 'text-fg-danger',
    accepted: 'text-fg-link',
    processing: 'text-fg-link',
    ready: 'text-fg-success',
    failed: 'text-fg-danger',
    unknown: 'text-fg-muted',
};

function isKnownStatus(status: string): status is MediaAssetStatus {
    return (MEDIA_ASSET_STATUSES as string[]).includes(status);
}

/**
 * Pure status presenter: the label text carries the meaning, tone classes
 * only reinforce it. Accepts any string so unrecognized runtime statuses
 * still render a safe, non-empty fallback (MEDIA-UI-STATUS-01).
 */
export function MediaAssetStatusBadge({ status }: MediaAssetStatusBadgeProps) {
    const label = isKnownStatus(status)
        ? t(STATUS_COPY_KEY[status])
        : t('workspace.media.library.asset.status.unknown');
    const toneClass = isKnownStatus(status) ? STATUS_TONE_CLASS[status] : STATUS_TONE_CLASS.unknown;

    return (
        <span role="status" className={`text-sm font-medium ${toneClass}`}>
            {label}
        </span>
    );
}

export default MediaAssetStatusBadge;
