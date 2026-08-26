import { t } from '../../../../../i18n/workspace';

export type QrCodeItem = {
    id: number;
    workspaceId: number;
    locationId: number;
    menuId: number;
    token: string;
    resolverUrl: string;
    destinationType: string;
    state: string;
};

type QrCodeListItemProps = {
    item: QrCodeItem;
    onDisable: (qrCodeId: number) => void;
};

export function QrCodeListItem({ item, onDisable }: QrCodeListItemProps) {
    const isActive = item.state === 'active';

    return (
        <li className="flex flex-col gap-1">
            {isActive ? (
                <a
                    href={item.resolverUrl}
                    className="break-all text-sm text-blue-700 underline dark:text-blue-400"
                >
                    {item.resolverUrl}
                </a>
            ) : (
                <span className="text-sm text-fg-muted">
                    {t('workspace.publication.qrDestination.state.disabled')}
                </span>
            )}

            {isActive ? (
                <button
                    type="button"
                    onClick={() => onDisable(item.id)}
                    className="self-start text-sm text-red-600 underline dark:text-red-400"
                >
                    {t('workspace.publication.qrDestination.disableButton')}
                </button>
            ) : null}
        </li>
    );
}

export default QrCodeListItem;
