import { useState } from 'react';
import { ArrowsLeftRight, Check, Copy, DotsThree, Power } from '@phosphor-icons/react';

import { t } from '../../../../../i18n/workspace';
import { ActionMenu } from '../../../../catalog/overlays/compound/ActionMenu';
import { ConfirmDialog } from '../../../../catalog/overlays/compound/ConfirmDialog';

export type QrCodeItem = {
    id: number;
    workspaceId: number;
    locationId: number;
    menuId: number;
    token: string;
    resolverUrl: string;
    /**
     * Kodun İNSAN ADI: "Masa 12" ya da girişteki kod için `null`.
     *
     * Veritabanında `qr_codes.dining_table_id` zaten yazılıyordu ve liste
     * DTO'su onu düşürüyordu; sahip 40 kod arasından birini seçemiyordu.
     */
    tableName?: string | null;
    /** Masanın bulunduğu alan/bölüm: "Bahçe", "Üst kat" (FF-109). */
    areaLabel?: string | null;
    destinationType: string;
    state: string;
};

export type RetargetLocation = { id: number; displayName: string };

/**
 * Adresin KISALTILMIŞ hâli — `zabuno.com/q/yDeMVV…` (FF-110).
 *
 * Ham adres 43 karakterlik bir token taşır ve satırda tam hâliyle
 * yazıldığında kodun adından çok yer kaplayıp çok dikkat çekiyordu: sahibin
 * okuması gereken şey "T12". Kısaltma bir yalan değildir — bağlantının
 * `href`'i tam adrestir, "Bağlantıyı kopyala" tam adresi verir ve üç nokta
 * metnin kesildiğini söyler.
 */
function shortUrl(url: string): string {
    const withoutScheme = url.replace(/^https?:\/\//, '');
    const parts = withoutScheme.split('/');
    const token = parts.at(-1) ?? '';

    if (token.length <= 10) return withoutScheme;

    return `${parts.slice(0, -1).join('/')}/${token.slice(0, 6)}…`;
}

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

/**
 * Adresi panoya kopyalar.
 *
 * Ham adresi ekranda göstermenin tek meşru sebebi, sahibin onu bir yere
 * yapıştırmak istemesidir — elle seçtirmek yerine tek dokunuşla vermek.
 * Pano API'si olmayan ya da izin vermeyen bir ortamda düğme SESSİZCE
 * başarısız olmaz; adres zaten yanında seçilebilir hâlde durur.
 */
function CopyUrlButton({ url }: { url: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <button
            type="button"
            onClick={() => {
                void navigator.clipboard
                    ?.writeText(url)
                    .then(() => setCopied(true))
                    .catch(() => setCopied(false));
            }}
            className="inline-flex min-h-[var(--density-hit-area-min)] items-center gap-[var(--space-1)] rounded-[var(--radius-md)] px-[var(--space-2)] text-meta text-fg-secondary hover:bg-surface-hover hover:text-fg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
        >
            {copied ? <Check size={14} weight="bold" /> : <Copy size={14} />}
            {t(
                copied
                    ? 'workspace.publication.qrDestination.url.copied'
                    : 'workspace.publication.qrDestination.url.copy',
            )}
        </button>
    );
}

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
    const [confirmingDisable, setConfirmingDisable] = useState(false);
    /*
        Erişilebilir adlar KODUN ADINI taşır: 40 satırlık bir listede "diğer
        işlemler" başlıklı 40 düğme, ekran okuyucu kullanan biri için tek bir
        düğmeye eşdeğerdir.
    */
    const name = item.tableName ?? t('workspace.publication.qrDestination.item.entrance');

    return (
        <li className="flex flex-col gap-[var(--space-1)] border-b border-border py-[var(--space-2)] last:border-b-0">
            {/*
                KODUN ADI ÖNDE, ADRES ARKADA — sahibin bildirimi (2026-09-04).

                Önceden satırın başlığı 43 karakterlik ham çözümleyici
                adresiydi ve ortasından kırılarak sarıyordu. Bir restoran
                sahibi o dizeden hiçbir şey öğrenmez; öğrenmesi gereken şey
                "bu hangi masanın kodu". Ad yoksa dürüst olan, adres yerine
                "menü kodu" demek ve adresi kopyalanabilir bir ayrıntıya
                indirmektir.

                Devre dışı satır da KİMLİĞİNİ KORUR: eskiden yalnız "Disabled"
                kelimesine iniyordu ve birden fazla kod varken hangisinin
                kapatıldığı anlaşılmıyordu.
            */}
            <span className="flex items-center gap-[var(--space-2)]">
                <span className="flex min-w-0 flex-wrap items-center gap-[var(--space-2)]">
                    <span className="text-body font-medium text-fg">{name}</span>
                    {/*
                        Alan, adın YANINDA ve daha sessiz (FF-109). 40 masalı
                        bir salonda "T12" tek başına yetmez — sahip kartı
                        fiziksel olarak bulmak için bölümü bilmek ister.
                    */}
                    {item.areaLabel ? (
                        <span className="text-meta text-fg-muted">{item.areaLabel}</span>
                    ) : null}
                    {isActive ? null : (
                        <span className="rounded-pill bg-surface-active px-[var(--space-2)] text-meta text-fg-muted">
                            {t('workspace.publication.qrDestination.state.disabled')}
                        </span>
                    )}
                </span>
                {/*
                    YIKICI EYLEM, SIRADAN EYLEMİN YANINDA DURMAZ (FF-110).

                    Önceki hâlde "Disable" ile "Move", satırın altında yan yana
                    iki küçük altı çizili yazıydı ve yalnız RENKLE ayrılıyordu.
                    Renk tek başına bir ayrım değildir (renk körlüğü, düşük
                    kontrastlı ekran, güneş altındaki telefon) ve iki hedef
                    birbirine bitişikti. Bir masanın kodunu yanlışlıkla kapatmak,
                    o masadaki basılı kartın misafir için ölmesi demek —
                    kullanıcının bunu fark etme yolu, bir misafirin şikâyet
                    etmesi.

                    Kapatma artık taşma menüsünde ve bir ONAY adımı arkasında.
                    Onay metni ne olacağını SOMUT söyler: kart taranınca menü
                    açılmaz. Yeniden açmak da menüde — kapatmanın karşılığı
                    olmalı (`docs/81`), yoksa basılı kâğıt kalıcı ölür.
                */}
                <ActionMenu
                    label={t('workspace.publication.qrDestination.rowActions', { name })}
                    tone="quiet"
                    triggerContent={<DotsThree size={18} weight="bold" aria-hidden="true" />}
                    className="ms-auto min-h-[var(--density-hit-area-min)] min-w-[var(--density-hit-area-min)]"
                    items={[
                        ...(isActive && onStartMove !== undefined && !moving
                            ? [
                                  {
                                      key: 'move',
                                      label: t('workspace.publication.qrDestination.move.start'),
                                      icon: <ArrowsLeftRight size={18} />,
                                      onSelect: () => onStartMove(item.id),
                                  },
                              ]
                            : []),
                        isActive
                            ? {
                                  key: 'disable',
                                  label: t('workspace.publication.qrDestination.disableButton'),
                                  icon: <Power size={18} />,
                                  destructive: true,
                                  onSelect: () => setConfirmingDisable(true),
                              }
                            : {
                                  key: 'enable',
                                  label: t('workspace.publication.qrDestination.enableButton'),
                                  icon: <Power size={18} />,
                                  onSelect: () => onEnable(item.id),
                              },
                    ]}
                />
            </span>

            <span className="flex flex-wrap items-center gap-[var(--space-2)]">
                {/*
                    Adres YENİ SEKMEDE açılır: kodu denemek için tıklayan
                    sahip, yönetim panelinden çıkıp gitmemeli.
                */}
                <a
                    href={item.resolverUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    title={item.resolverUrl}
                    className="inline-flex min-h-[var(--density-hit-area-min)] max-w-full items-center truncate text-meta text-fg-muted underline underline-offset-2 hover:text-fg-link focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                    {shortUrl(item.resolverUrl)}
                </a>
                <CopyUrlButton url={item.resolverUrl} />
            </span>

            <ConfirmDialog
                open={confirmingDisable}
                onClose={() => setConfirmingDisable(false)}
                onConfirm={() => {
                    setConfirmingDisable(false);
                    onDisable(item.id);
                }}
                title={t('workspace.publication.qrDestination.disable.confirmTitle', { name })}
                confirmLabel={t('workspace.publication.qrDestination.disableButton')}
                cancelLabel={t('workspace.publication.qrDestination.move.cancel')}
                destructive
            >
                {t('workspace.publication.qrDestination.disable.confirmBody')}
            </ConfirmDialog>

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
