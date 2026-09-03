import { useState } from 'react';

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

export type RetargetLocation = { id: number; displayName: string };

type QrCodeListItemProps = {
    item: QrCodeItem;
    onDisable: (qrCodeId: number) => void;
    onEnable: (qrCodeId: number) => void;
    /**
     * Taşıma — `docs/81` P1-03, ekranı `docs/98` FF-64. Şube listesi
     * "Taşı" istenene kadar YÜKLENMEZ: kodların çoğu hiç taşınmaz ve her
     * açılışta bir istek daha atmak, taşımayan sahibe bedel ödetirdi.
     */
    moving?: boolean;
    otherLocations?: RetargetLocation[] | null;
    onStartMove?: (qrCodeId: number) => void;
    onCancelMove?: () => void;
    onRetarget?: (qrCodeId: number, locationId: number) => void;
};

export function QrCodeListItem({
    item,
    onDisable,
    onEnable,
    moving = false,
    otherLocations = null,
    onStartMove,
    onCancelMove,
    onRetarget,
}: QrCodeListItemProps) {
    const isActive = item.state === 'active';
    const [target, setTarget] = useState('');

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
            <div className="flex flex-wrap items-center gap-3">
                {isActive ? (
                    <button
                        type="button"
                        onClick={() => onDisable(item.id)}
                        className="text-body text-fg-danger underline underline-offset-2"
                    >
                        {t('workspace.publication.qrDestination.disableButton')}
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={() => onEnable(item.id)}
                        className="text-body text-fg-link underline underline-offset-2"
                    >
                        {t('workspace.publication.qrDestination.enableButton')}
                    </button>
                )}

                {/* Kart fiziksel olarak başka şubeye gittiğinde tek çare
                    yeniden bastırmak olmamalı. Token DEĞİŞMEZ. */}
                {isActive && onStartMove !== undefined && !moving ? (
                    <button
                        type="button"
                        onClick={() => onStartMove(item.id)}
                        className="text-body text-fg-link underline underline-offset-2"
                    >
                        {t('workspace.publication.qrDestination.move.start')}
                    </button>
                ) : null}
            </div>

            {moving && onRetarget !== undefined ? (
                otherLocations === null ? (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.publication.qrDestination.move.loading')}
                    </p>
                ) : otherLocations.length === 0 ? (
                    <p className="text-body text-fg-muted">
                        {t('workspace.publication.qrDestination.move.noOther')}
                    </p>
                ) : (
                    <div className="flex flex-wrap items-center gap-2">
                        <label
                            className="text-body text-fg-secondary"
                            htmlFor={`qr-move-${item.id}`}
                        >
                            {t('workspace.publication.qrDestination.move.label')}
                        </label>
                        <select
                            id={`qr-move-${item.id}`}
                            className="min-h-[var(--density-hit-area-min)] rounded-md border border-border bg-surface px-2 text-body"
                            value={target}
                            onChange={(event) => setTarget(event.target.value)}
                        >
                            <option value="">
                                {t('workspace.publication.qrDestination.move.choose')}
                            </option>
                            {otherLocations.map((location) => (
                                <option key={location.id} value={String(location.id)}>
                                    {location.displayName}
                                </option>
                            ))}
                        </select>
                        <button
                            type="button"
                            disabled={target === ''}
                            onClick={() => {
                                onRetarget(item.id, Number(target));
                                setTarget('');
                            }}
                            className="text-body text-fg-link underline underline-offset-2 disabled:no-underline disabled:opacity-60"
                        >
                            {t('workspace.publication.qrDestination.move.button')}
                        </button>
                        {onCancelMove !== undefined ? (
                            <button
                                type="button"
                                onClick={onCancelMove}
                                className="text-body text-fg-secondary underline underline-offset-2"
                            >
                                {t('workspace.publication.qrDestination.move.cancel')}
                            </button>
                        ) : null}
                    </div>
                )
            ) : null}
        </li>
    );
}

export default QrCodeListItem;
