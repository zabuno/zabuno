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
    loadError: boolean;
    checklistReady: boolean;
    confirmed: boolean;
    onConfirmedChange: (confirmed: boolean) => void;
    onPublish: () => void;
    publishing: boolean;
    errorMessage: string | null;
};

export function PublicationStatusRegion({
    current,
    loadError,
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
            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                {t('workspace.publication.status.region')}
            </h3>

            {current === null && loadError ? (
                <p role="alert" className="text-sm text-red-600 dark:text-red-400">
                    {t('workspace.publication.status.loadError')}
                </p>
            ) : current === null ? (
                <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                    {t('workspace.publication.status.notPublished')}
                </p>
            ) : (
                <p role="status" className="text-sm text-gray-700 dark:text-gray-300">
                    {t('workspace.publication.status.summary', {
                        id: String(current.id),
                        version: String(current.version),
                        state: current.state,
                    })}
                </p>
            )}

            <label className="flex w-full items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                    type="checkbox"
                    checked={confirmed}
                    disabled={!checklistReady}
                    onChange={(event) => onConfirmedChange(event.target.checked)}
                />
                {t('workspace.publication.publishAction.checklistConfirmed')}
            </label>

            <button
                type="button"
                disabled={!checklistReady || !confirmed || publishing}
                onClick={onPublish}
                className="self-start rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 disabled:text-gray-400 dark:border-gray-600 dark:text-gray-300 dark:disabled:text-gray-500"
            >
                {t('workspace.publication.status.publishButton')}
            </button>

            {errorMessage ? (
                <p role="alert" className="text-sm text-red-600 dark:text-red-400">
                    {errorMessage}
                </p>
            ) : null}
        </div>
    );
}

export default PublicationStatusRegion;
