import { useCallback, useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';
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

type Status = 'loading' | 'error' | 'ready';

export function AuditTrailRegion({ workspaceId }: { workspaceId: number }) {
    const [status, setStatus] = useState<Status>('loading');
    const [events, setEvents] = useState<AuditEvent[]>([]);

    const load = useCallback(async () => {
        setStatus('loading');

        try {
            const response = await fetch(`/api/workspaces/${String(workspaceId)}/audit-trail`, {
                credentials: 'include',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                setStatus('error');

                return;
            }

            const body = (await response.json()) as { data?: AuditEvent[] };

            setEvents(body.data ?? []);
            setStatus('ready');
        } catch {
            setStatus('error');
        }
    }, [workspaceId]);

    useEffect(() => {
        void load();
    }, [load]);

    return (
        <section
            aria-label={t('workspace.settings.audit.region')}
            className="flex flex-col gap-[var(--space-3)]"
        >
            <p className="text-body text-fg-secondary">{t('workspace.settings.audit.help')}</p>

            {status === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.settings.audit.loading')}
                </p>
            )}

            {status === 'error' && (
                <PageState
                    kind="error"
                    title={t('workspace.settings.audit.error')}
                    action={
                        <button
                            type="button"
                            onClick={() => void load()}
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

                            <span className="font-medium text-fg">
                                {t(
                                    (event.source === 'media'
                                        ? 'workspace.settings.audit.source.media'
                                        : 'workspace.settings.audit.source.publication') as Parameters<
                                        typeof t
                                    >[0],
                                )}
                            </span>

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
