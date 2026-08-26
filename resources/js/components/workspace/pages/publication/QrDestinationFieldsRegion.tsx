import { t } from '../../../../i18n/workspace';

type QrDestinationFieldsRegionProps = {
    disabled: boolean;
    onCreate: () => void;
};

/**
 * Create is disabled until a real current publication exists — never a
 * fake/pre-generated token before the server confirms one.
 */
export function QrDestinationFieldsRegion({ disabled, onCreate }: QrDestinationFieldsRegionProps) {
    return (
        <div className="flex flex-col gap-2">
            <button
                type="button"
                disabled={disabled}
                onClick={onCreate}
                className="self-start rounded border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >
                {t('workspace.publication.qrDestination.createButton')}
            </button>

            {disabled ? (
                <p role="status" className="text-sm text-fg-muted">
                    {t('workspace.publication.qrDestination.fields.unavailable')}
                </p>
            ) : null}
        </div>
    );
}

export default QrDestinationFieldsRegion;
