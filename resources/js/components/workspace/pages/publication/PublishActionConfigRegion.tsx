import { t } from '../../../../i18n/workspace';

/**
 * Publish itself happens in PublicationStatusRegion (the one real checklist
 * checkbox and Publish button). This region only shows the immediate-mode
 * publish configuration — Stage 1 supports immediate publish only, so the
 * mode select stays fixed and informational, not a duplicate publish action.
 */
export function PublishActionConfigRegion() {
    return (
        <div
            role="region"
            aria-label={t('workspace.publication.publishAction.region')}
            className="flex w-full flex-col gap-3"
        >
            <h3 className="text-sm font-semibold text-fg">
                {t('workspace.publication.publishAction.region')}
            </h3>

            <label className="flex w-full flex-col gap-1 text-sm text-fg-secondary">
                {t('workspace.publication.publishAction.mode.label')}
                <select
                    disabled
                    value="immediate"
                    onChange={() => {}}
                    className="w-full rounded-lg border border-border px-3 py-2 text-sm "
                >
                    <option value="immediate">
                        {t('workspace.publication.publishAction.mode.immediate')}
                    </option>
                </select>
            </label>

            <p className="text-xs text-fg-muted">
                {t('workspace.publication.publishAction.permissionNotice')}
            </p>
            <p className="text-xs text-fg-muted">
                {t('workspace.publication.publishAction.scheduleNotice')}
            </p>
            <p className="text-xs text-fg-muted">
                {t('workspace.publication.publishAction.snapshotNotice')}
            </p>
            <p className="text-xs text-fg-muted">
                {t('workspace.publication.publishAction.failurePreservationNotice')}
            </p>
        </div>
    );
}

export default PublishActionConfigRegion;
