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
    onEnable: (qrCodeId: number) => void;
};

export function QrCodeListItem({ item, onDisable, onEnable }: QrCodeListItemProps) {
    const isActive = item.state === 'active';

    return (
        <li className="flex flex-col gap-1">
            {isActive ? (
                <a
                    href={item.resolverUrl}
                    className="break-all text-body text-fg-link underline underline-offset-2"
                >
                    {item.resolverUrl}
                </a>
            ) : (
                <span className="text-body text-fg-muted">
                    {t('workspace.publication.qrDestination.state.disabled')}
                </span>
            )}

            {/*
                Kapatmanın KARŞILIĞI olmalı (`docs/81`). Devre dışı bir kod
                geri açılamıyorsa, masadaki basılı kâğıt kalıcı olarak ölür
                ve tek çare yeniden bastırmak olur — bu ürünün temel vaadinin
                ihlali.
            */}
            {isActive ? (
                <button
                    type="button"
                    onClick={() => onDisable(item.id)}
                    className="self-start text-body text-fg-danger underline underline-offset-2"
                >
                    {t('workspace.publication.qrDestination.disableButton')}
                </button>
            ) : (
                <button
                    type="button"
                    onClick={() => onEnable(item.id)}
                    className="self-start text-body text-fg-link underline underline-offset-2"
                >
                    {t('workspace.publication.qrDestination.enableButton')}
                </button>
            )}
        </li>
    );
}

export default QrCodeListItem;
