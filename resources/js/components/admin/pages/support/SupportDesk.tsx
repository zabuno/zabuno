import { useId, useState } from 'react';
import { LockKey, Warning } from '@phosphor-icons/react';

import { t } from '../../../../i18n/platform';
import { Button } from '../../../catalog/forms/micro/Button';
import { Label } from '../../../catalog/forms/micro/Label';
import { Textarea } from '../../../catalog/forms/micro/Textarea';
import { OpsCard } from '../../../ops/OpsCard';
import type { SupportAccessSession, TenantSupportView } from './types';

export type SupportDeskProps = {
    view: TenantSupportView;
    /** Açık oturum — yoksa `null`. Sunucudan gelir, ekranda türetilmez. */
    session: SupportAccessSession | null;
    windowMinutes: number;
    reasonMinLength: number;
    busy: boolean;
    /** Sunucunun reddettiği son isteğin cümlesi; yoksa `null`. */
    notice: string | null;
    onOpen: (reason: string) => void;
    onEnd: () => void;
};

const FINDING_KEYS = {
    workspace_not_serving: 'platform.support.finding.workspace_not_serving',
    subscription_not_active: 'platform.support.finding.subscription_not_active',
    no_location: 'platform.support.finding.no_location',
    location_has_no_menu: 'platform.support.finding.location_has_no_menu',
    location_has_no_qr: 'platform.support.finding.location_has_no_qr',
    qr_disabled: 'platform.support.finding.qr_disabled',
    qr_has_no_destination: 'platform.support.finding.qr_has_no_destination',
    qr_menu_never_published: 'platform.support.finding.qr_menu_never_published',
} as const;

type FindingKey = keyof typeof FINDING_KEYS;

function findingLabel(code: string): string {
    /*
        Sunucu bir gün dokuzuncu bulguyu eklerse ekran onu SAKLAMAZ: çevirisi
        olmayan kod ham adıyla yazılır. Bilinmeyen bir bulguyu gizleyen bir
        destek ekranı, destek görevlisini gördüğünden farklı bir gerçeğe
        inandırır.
    */
    if (!Object.prototype.hasOwnProperty.call(FINDING_KEYS, code)) {
        return code;
    }

    return t(FINDING_KEYS[code as FindingKey]);
}

/**
 * DESTEK MASASI — `docs/122` §3 boşluk 3 ve §5, `docs/133`.
 *
 * Sunum bileşeni: getirmeyi bilmez, sunucudan geleni çizer.
 *
 * SIRA BU EKRANIN ARGÜMANIDIR. Yukarıdan aşağıya: bulgular → misafirin
 * gördüğü adresler → talepler → bakış geçmişi → ve EN SONDA kiracı olarak
 * bakma. "Müşteri arıyor, ekranında ne var?" sorusunun cevabı ilk dört
 * kartta duruyor ve çoğu çağrı orada bitiyor; impersonation en aşağıda,
 * çünkü son çare olması gereken şey ilk görülen şey olmamalı.
 *
 * BAKMA KARTI KOLAY DEĞİLDİR VE OLMAYACAK:
 *  - Sebep alanı boş bırakılamaz ve tek kelime kabul etmez; düğme o zamana
 *    kadar pasiftir.
 *  - Kartın kendisi, kiracının bunu NEREDE göreceğini yazar. Süperadmin,
 *    yazdığı sebebin restoran sahibinin panelinde okunacağını bilerek yazar.
 *  - Süre ekranda değil sunucuda yaşar; buradaki sayı sunucudan gelir.
 *
 * AÇIK OTURUM EN ÜSTTE VE UYARI TONUNDA: destek görevlisinin "hâlâ bakıyor
 * muyum?" sorusunu sorması gerekmemeli. Unutulmuş bir oturum bu paketin
 * engellemek için var olduğu kusurdur.
 */
export function SupportDesk({
    view,
    session,
    windowMinutes,
    reasonMinLength,
    busy,
    notice,
    onOpen,
    onEnd,
}: SupportDeskProps) {
    const reasonId = useId();
    const reasonHelpId = `${reasonId}-help`;
    const [reason, setReason] = useState('');

    const watchingThisTenant = session !== null && session.workspaceId === view.workspace.id;
    const canOpen = !busy && session === null && reason.trim().length >= reasonMinLength;

    return (
        <div className="flex flex-col gap-[var(--space-4)]">
            {session !== null && (
                <OpsCard title={t('platform.support.session.title')}>
                    <div className="flex flex-col gap-[var(--space-2)]">
                        <p
                            role="status"
                            className="flex items-start gap-[var(--space-2)] text-body text-fg"
                        >
                            <LockKey aria-hidden="true" size={18} className="mt-[2px] flex-none" />
                            <span>
                                {watchingThisTenant
                                    ? t('platform.support.session.here', {
                                          name: view.workspace.name,
                                          expiresAt: session.expiresAt,
                                      })
                                    : t('platform.support.session.elsewhere', {
                                          expiresAt: session.expiresAt,
                                      })}
                            </span>
                        </p>
                        <p className="text-meta text-fg-muted">
                            {t('platform.support.session.reason', { reason: session.reason })}
                        </p>
                        <p className="text-meta text-fg-muted">
                            {t('platform.support.session.readOnly')}
                        </p>
                        <Button type="button" className="w-full" onClick={onEnd} disabled={busy}>
                            {t('platform.support.session.end')}
                        </Button>
                    </div>
                </OpsCard>
            )}

            {notice !== null && (
                <p role="alert" className="text-body font-medium text-fg-danger">
                    {notice}
                </p>
            )}

            <OpsCard title={t('platform.support.findings.title')}>
                {view.findings.length === 0 ? (
                    <p className="text-body text-fg-muted">{t('platform.support.findings.none')}</p>
                ) : (
                    <ul className="flex flex-col gap-[var(--space-2)]">
                        {view.findings.map((finding, index) => (
                            <li
                                key={`${finding.code}-${String(finding.locationId)}-${String(index)}`}
                                className="flex items-start gap-[var(--space-2)] text-body text-fg"
                            >
                                <Warning
                                    aria-hidden="true"
                                    size={18}
                                    className="mt-[2px] flex-none"
                                />
                                <span>
                                    {findingLabel(finding.code)}
                                    {finding.locationId !== null && (
                                        <span className="text-fg-muted">
                                            {' · '}
                                            {view.locations.find(
                                                (location) => location.id === finding.locationId,
                                            )?.displayName ?? String(finding.locationId)}
                                        </span>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
                <p className="mt-[var(--space-3)] text-meta text-fg-muted">
                    {t('platform.support.findings.scope')}
                </p>
            </OpsCard>

            <OpsCard title={t('platform.support.guest.title')}>
                {view.locations.length === 0 ? (
                    <p className="text-body text-fg-muted">
                        {t('platform.support.guest.noLocation')}
                    </p>
                ) : (
                    <ul className="flex flex-col gap-[var(--space-4)]">
                        {view.locations.map((location) => (
                            <li key={location.id} className="flex flex-col gap-[var(--space-1)]">
                                <p className="text-body font-medium text-fg">
                                    {location.displayName}
                                </p>
                                <p className="text-meta text-fg-muted">
                                    {t('platform.support.guest.locationMeta', {
                                        menus: String(location.menuCount),
                                        ordering: location.acceptsOrders
                                            ? t('platform.support.guest.orderingOn')
                                            : t('platform.support.guest.orderingOff'),
                                    })}
                                </p>
                                {location.qrCodes.length === 0 ? (
                                    <p className="text-meta text-fg-muted">
                                        {t('platform.support.guest.noQr')}
                                    </p>
                                ) : (
                                    <ul className="flex flex-col gap-[var(--space-2)]">
                                        {location.qrCodes.map((code) => (
                                            <li
                                                key={code.id}
                                                className="flex flex-col gap-[var(--space-1)]"
                                            >
                                                {/*
                                                    ADRES KIRILARAK sığar: 320
                                                    pikselde uzun bir bağlantı,
                                                    kırılmazsa sayfayı yana
                                                    kaydırır.
                                                */}
                                                <a
                                                    href={code.guestUrl}
                                                    className="block min-h-[var(--density-hit-area-min)] break-all text-body underline text-fg"
                                                    rel="noreferrer"
                                                    target="_blank"
                                                >
                                                    {code.guestUrl}
                                                </a>
                                                <span className="text-meta text-fg-muted">
                                                    {code.menuName ??
                                                        t('platform.support.guest.noDestination')}
                                                    {' · '}
                                                    {code.publishedVersion === null
                                                        ? t('platform.support.guest.notPublished')
                                                        : t('platform.support.guest.published', {
                                                              version: String(
                                                                  code.publishedVersion,
                                                              ),
                                                          })}
                                                    {' · '}
                                                    {code.state}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </OpsCard>

            <OpsCard title={t('platform.support.requests.title')}>
                {view.supportRequests.length === 0 ? (
                    <p className="text-body text-fg-muted">{t('platform.support.requests.none')}</p>
                ) : (
                    <ul className="flex flex-col gap-[var(--space-2)]">
                        {view.supportRequests.map((request) => (
                            <li key={request.reference} className="flex flex-col">
                                <span className="text-body text-fg">
                                    {request.reference} · {request.subject}
                                </span>
                                <span className="text-meta tabular-nums text-fg-muted">
                                    {request.receivedAt} · {request.status}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </OpsCard>

            <OpsCard title={t('platform.support.history.title')}>
                {view.accessHistory.length === 0 ? (
                    <p className="text-body text-fg-muted">{t('platform.support.history.none')}</p>
                ) : (
                    <ul className="flex flex-col gap-[var(--space-2)]">
                        {view.accessHistory.map((entry) => (
                            <li key={entry.id} className="flex flex-col">
                                <span className="text-meta tabular-nums text-fg-muted">
                                    {entry.startedAt} ·{' '}
                                    {entry.actor ?? t('platform.support.history.unknownActor')}
                                </span>
                                <span className="text-body text-fg">{entry.reason}</span>
                            </li>
                        ))}
                    </ul>
                )}
                <p className="mt-[var(--space-3)] text-meta text-fg-muted">
                    {t('platform.support.history.tenantSees')}
                </p>
            </OpsCard>

            {/*
                EN SONDA VE KASITLI OLARAK ZOR. Bu kart, yukarıdaki dört kart
                cevabı vermediğinde açılır; `docs/122` §5 impersonation'ı en
                tehlikeli süperadmin yeteneği sayar ve kolay olmamasını şart
                koşar.
            */}
            <OpsCard title={t('platform.support.open.title')}>
                <form
                    className="flex flex-col gap-[var(--space-3)]"
                    /*
                        `noValidate` — `docs/47` Kural 5(b): sebep alanı
                        `required` taşır ve onsuz tarayıcı kendi baloncuğunu
                        gösterip `submit` olayını yutardı. Doğrulama bizde:
                        eksik sebepte düğme pasif, sunucu reddederse cümleyi
                        biz yazarız.
                    */
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        onOpen(reason.trim());
                    }}
                >
                    <p className="text-body text-fg-secondary">
                        {t('platform.support.open.contract', {
                            minutes: String(windowMinutes),
                        })}
                    </p>

                    <div className="flex flex-col gap-[var(--space-1)]">
                        <Label htmlFor={reasonId} required>
                            {t('platform.support.open.reason')}
                        </Label>
                        <Textarea
                            id={reasonId}
                            name="support-access-reason"
                            rows={3}
                            value={reason}
                            required
                            maxLength={500}
                            aria-describedby={reasonHelpId}
                            onChange={(event) => {
                                setReason(event.target.value);
                            }}
                        />
                        <p id={reasonHelpId} className="text-meta text-fg-muted">
                            {t('platform.support.open.reasonHelp', {
                                min: String(reasonMinLength),
                            })}
                        </p>
                    </div>

                    <Button type="submit" className="w-full" disabled={!canOpen} loading={busy}>
                        {t('platform.support.open.submit', { name: view.workspace.name })}
                    </Button>

                    {session !== null && (
                        <p className="text-meta text-fg-muted">
                            {t('platform.support.open.alreadyOpen')}
                        </p>
                    )}
                </form>
            </OpsCard>
        </div>
    );
}

export default SupportDesk;
