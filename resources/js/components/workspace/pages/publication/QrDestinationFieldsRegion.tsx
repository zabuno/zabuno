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
                className="min-h-[var(--density-hit-area-min)] self-start rounded border border-border bg-surface px-3 py-1 text-sm text-fg disabled:opacity-50"
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
