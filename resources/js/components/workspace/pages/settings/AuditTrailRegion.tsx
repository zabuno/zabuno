import { useEffect, useState } from 'react';
import { LockKey } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import { trackEvent } from '../../../../lib/analytics';
import { PageState } from '../shared/PageState';

/**
 * Denetim izi — "bunu kim, ne zaman yaptı?" (FF-132).
 *
 * Bu bölüm bir ekran değil, bir SORUYA cevap: menü bir gece değişti, sabah
 * kimse hatırlamıyor. Kayıtlar depoda zaten vardı ama her biri kendi
 * köşesindeydi — medya izi bir uçta, yayın geçmişi başka bir uçta — ve
 * sahibin ikisini kafasında birleştirmesi bekleniyordu.
 *
 * LİSTE BİR SÖZ VERMEZ. Yardım metni açıkça "her şey burada" demez, "sistemin
 * zaten yazdıkları burada" der. Eksik bir izi tam sanmak, izin hiç olmamasından
 * tehlikelidir: sahibi, kaydı olmayan bir olayın olmadığına ikna eder.
 */
type AuditEvent = {
    source: string;
    action: string;
    subject: string | null;
    actor: string | null;
    at: string | null;
};

/**
 * Platform ekibinin bu hesaba bakışı — `docs/122` Y7, `docs/133` §3.
 *
 * AYRI BİR ALAN, listenin ÜSTÜNDE. Aynı kayıt aşağıdaki zaman çizgisinde de
 * var; ama yüz satırlık bir izin ortasına düşen bir satır "teknik olarak
 * gösterildi" demektir, "sahibin gözüne çarptı" demek değil. `docs/122` §5
 * kaydın kiracının GÖREBİLECEĞİ biçimde yazılmasını istiyor ve o iki cümle
 * aynı cümle değil.
 */
type SupportAccessEntry = {
    id: number;
    actor: string | null;
    reason: string;
    startedAt: string;
    expiresAt: string;
    active: boolean;
};

const SOURCE_LABELS = {
    media: 'workspace.settings.audit.source.media',
    publication: 'workspace.settings.audit.source.publication',
    'support-access': 'workspace.settings.audit.source.support-access',
} as const;

type SourceKey = keyof typeof SOURCE_LABELS;

function sourceLabel(source: string): string {
    // Çevirisi olmayan kaynak ham adıyla yazılır: bilinmeyen bir olayı
    // gizleyen bir iz, iz olmaktan çıkar.
    if (!Object.prototype.hasOwnProperty.call(SOURCE_LABELS, source)) {
        return source;
    }

    return t(SOURCE_LABELS[source as SourceKey]);
}

type Status = 'loading' | 'error' | 'ready';

export function AuditTrailRegion({ workspaceId }: { workspaceId: number }) {
    const [status, setStatus] = useState<Status>('loading');
    const [events, setEvents] = useState<AuditEvent[]>([]);
    const [supportAccess, setSupportAccess] = useState<SupportAccessEntry[]>([]);

    /*
        YÜKLEME ETKİNİN İÇİNDE (FF-132).

        Dışarıda tanımlanmış bir `load` işlevini etkiden çağırmak, derleyici
        kapısının "etki içinde eşzamanlı setState" uyarısını tetikliyor:
        `await` sınırını göremediği için durum güncellemelerini çizim
        sırasında sanıyor. `DiningAreasRegion` aynı sorunu aynı biçimde
        çözüyor — iş etkinin içinde, iptal bayrağıyla.

        Yeniden deneme bir SAYAÇLA yapılır: kullanıcı düğmeye bastığında
        sayaç artar, etki yeniden koşar. Böylece "yeniden yükle" ile "ilk
        yükleme" aynı yoldan geçer ve ikisi ayrışamaz.
    */
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(`/api/workspaces/${String(workspaceId)}/audit-trail`, {
                    credentials: 'include',
                    headers: { Accept: 'application/json' },
                });

                if (cancelled) return;

                if (!response.ok) {
                    setStatus('error');

                    return;
                }

                const body = (await response.json()) as {
                    data?: AuditEvent[];
                    supportAccess?: SupportAccessEntry[];
                };

                if (cancelled) return;

                setEvents(body.data ?? []);
                setSupportAccess(body.supportAccess ?? []);
                setStatus('ready');
            } catch {
                if (!cancelled) setStatus('error');
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, attempt]);

    return (
        <section
            aria-label={t('workspace.settings.audit.region')}
            className="flex flex-col gap-[var(--space-3)]"
        >
            <p className="text-body text-fg-secondary">{t('workspace.settings.audit.help')}</p>

            {/*
                EN ÜSTTE VE KENDİ KUTUSUNDA. Sahibin bilmesi gereken tek şey
                bu ekranda menü olaylarıyla aynı ağırlıkta durmamalı: kendi
                ekibinin yaptığı bir fiyat değişikliğiyle, dışarıdan birinin
                hesabına bakması aynı önemde iki satır değildir.
            */}
            {supportAccess.length > 0 && (
                <section
                    aria-label={t('workspace.settings.audit.support.title')}
                    className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface px-[var(--density-padding-inline)] py-[var(--space-3)]"
                >
                    <h3 className="flex items-start gap-[var(--space-2)] text-body font-bold text-fg">
                        <LockKey aria-hidden="true" size={18} className="mt-[2px] flex-none" />
                        <span>{t('workspace.settings.audit.support.title')}</span>
                    </h3>
                    <p className="text-meta text-fg-secondary">
                        {t('workspace.settings.audit.support.help')}
                    </p>
                    <ul className="flex flex-col gap-[var(--space-3)]">
                        {supportAccess.map((entry) => (
                            <li key={entry.id} className="flex flex-col gap-[var(--space-1)]">
                                <span className="text-meta tabular-nums text-fg-muted">
                                    {entry.active
                                        ? t('workspace.settings.audit.support.open', {
                                              expiresAt: entry.expiresAt,
                                          })
                                        : t('workspace.settings.audit.support.closed', {
                                              startedAt: entry.startedAt,
                                              expiresAt: entry.expiresAt,
                                          })}
                                </span>
                                <span className="text-body text-fg">
                                    {t('workspace.settings.audit.support.reason', {
                                        reason: entry.reason,
                                    })}
                                </span>
                                <span className="text-meta text-fg-muted">
                                    {t('workspace.settings.audit.support.by', {
                                        actor:
                                            entry.actor ??
                                            t('workspace.settings.audit.support.unknownActor'),
                                    })}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {status === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.settings.audit.loading')}
                </p>
            )}

            {status === 'error' && (
                <PageState
                    kind="error"
                    screen="settings_audit"
                    title={t('workspace.settings.audit.error')}
                    action={
                        <button
                            type="button"
                            onClick={() => {
                                // Hangi hata tekrar denettiriyor (`docs/112`
                                // §4.3).
                                trackEvent('retry_clicked', { surface: 'settings_audit' });
                                setStatus('loading');
                                setAttempt((previous) => previous + 1);
                            }}
                            className="min-h-[var(--control-height)] rounded-[var(--radius-md)] border border-border px-[var(--space-3)] py-[var(--space-2)] text-body font-medium whitespace-nowrap text-fg-secondary hover:bg-surface-hover"
                        >
                            {t('workspace.settings.audit.retry')}
                        </button>
                    }
                />
            )}

            {status === 'ready' && events.length === 0 && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.settings.audit.empty')}
                </p>
            )}

            {status === 'ready' && events.length > 0 && (
                /*
                    SATIR KART DEĞİL: iz bir listedir, olaylar zaten bir
                    aradadır ve her olaya ayrı kenarlık vermek onları
                    birbirinden bağımsız nesneler gibi gösterirdi.
                */
                <ul className="flex flex-col">
                    {events.map((event, index) => (
                        <li
                            key={`${event.source}-${String(index)}`}
                            className="flex min-h-[var(--density-row-height)] flex-wrap items-center gap-x-[var(--space-3)] gap-y-[var(--space-1)] border-t border-border px-[var(--density-padding-inline)] py-[var(--space-2)] text-body text-fg-secondary first:border-t-0"
                        >
                            {/*
                                ZAMAN SABİT GENİŞLİKTE ve `tabular-nums`:
                                damgalar alt alta hizalanmazsa göz tarihi
                                değil, satırın kenarını takip eder.
                            */}
                            <span className="w-[11rem] flex-none text-meta tabular-nums text-fg-muted">
                                {event.at ?? ''}
                            </span>

                            <span className="font-medium text-fg">{sourceLabel(event.source)}</span>

                            <span>{event.action}</span>

                            {event.subject !== null && event.subject !== '' ? (
                                <span className="text-fg">{event.subject}</span>
                            ) : null}

                            {/*
                                FAİL E-POSTAYLA yazılır: bir ekipte iki
                                "Mehmet" olabilir ve "Mehmet sildi" cümlesi
                                hiçbir soruyu kapatmaz. Kullanıcı silinmişse
                                kaydı gizlemek yerine failin bilinmediği
                                söylenir.
                            */}
                            <span className="ms-auto text-meta text-fg-muted">
                                {event.actor ?? t('workspace.settings.audit.unknownActor')}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export default AuditTrailRegion;
