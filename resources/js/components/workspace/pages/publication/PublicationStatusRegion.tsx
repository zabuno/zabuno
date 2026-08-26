import { Button } from '../../../catalog/forms/micro/Button';
import { t } from '../../../../i18n/workspace';

export type CurrentPublication = {
    id: number;
    workspaceId: number;
    menuId: number;
    locationId: number;
    version: number;
    state: string;
    publishedAt: string;
    snapshot: {
        categories: {
            name: string;
            menuItems: { productName: string }[];
        }[];
    };
};

type PublicationStatusRegionProps = {
    current: CurrentPublication | null;
    loading: boolean;
    loadError: boolean;
    onRetry: () => void;
    checklistReady: boolean;
    confirmed: boolean;
    onConfirmedChange: (confirmed: boolean) => void;
    onPublish: () => void;
    publishing: boolean;
    errorMessage: string | null;
};

export function PublicationStatusRegion({
    current,
    loading,
    loadError,
    onRetry,
    checklistReady,
    confirmed,
    onConfirmedChange,
    onPublish,
    publishing,
    errorMessage,
}: PublicationStatusRegionProps) {
    return (
        <div
            role="region"
            aria-label={t('workspace.publication.status.region')}
            className="flex flex-col gap-3"
        >
            <h3 className="text-sm font-semibold text-fg">
                {t('workspace.publication.status.region')}
            </h3>

            {loading ? (
                <p role="status" className="text-sm text-fg-muted">
                    {t('workspace.publication.status.loading')}
                </p>
            ) : current === null && loadError ? (
                <div className="flex flex-col items-start gap-2">
                    <p role="alert" className="text-sm text-fg-danger">
                        {t('workspace.publication.status.loadError')}
                    </p>
                    <Button type="button" color="light" onClick={onRetry}>
                        Retry
                    </Button>
                </div>
            ) : current === null ? (
                <p role="status" className="text-sm text-fg-muted">
                    {t('workspace.publication.status.notPublished')}
                </p>
            ) : (
                <p role="status" className="text-sm text-fg-secondary">
                    {t('workspace.publication.status.summary', {
                        id: String(current.id),
                        version: String(current.version),
                        state: current.state,
                    })}
                </p>
            )}

            <label className="flex w-full items-center gap-2 text-sm text-fg-secondary">
                <input
                    type="checkbox"
                    checked={confirmed}
                    disabled={!checklistReady}
                    onChange={(event) => onConfirmedChange(event.target.checked)}
                />
                {t('workspace.publication.publishAction.checklistConfirmed')}
            </label>

            <Button
                type="button"
                color="light"
                disabled={!checklistReady || !confirmed || publishing}
                onClick={onPublish}
                className="self-start"
            >
                {t('workspace.publication.status.publishButton')}
            </Button>

            {errorMessage ? (
                <p role="alert" className="text-sm text-fg-danger">
                    {errorMessage}
                </p>
            ) : null}
        </div>
    );
}

export default PublicationStatusRegion;
