import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { t } from '../../../../i18n/platform';
import { Badge } from '../../../catalog/feedback/micro/Badge';
import { ReadinessItem } from './ReadinessItem';

type HostCapabilityEvidenceItemProps = {
    workspaceId: number;
};

type EvidenceState =
    | { phase: 'loading' }
    | { phase: 'unavailable' }
    | { phase: 'error' }
    | {
          phase: 'resolved';
          phpVersion: string;
          degradations: string[];
          ranAt: string;
          claim: string;
      };

function evidenceEndpoint(workspaceId: number): string {
    return `/api/workspaces/${workspaceId}/security/evidence/host-capability`;
}

function parseEvidence(payload: unknown): EvidenceState | null {
    if (typeof payload !== 'object' || payload === null) return null;
    const data = (payload as { data?: unknown }).data;
    if (typeof data !== 'object' || data === null) return null;
    const record = data as Record<string, unknown>;

    if (typeof record.php_version !== 'string' || typeof record.ran_at !== 'string') return null;
    if (typeof record.claim !== 'string' || !Array.isArray(record.degradations)) return null;

    return {
        phase: 'resolved',
        phpVersion: record.php_version,
        degradations: record.degradations.map(String),
        ranAt: record.ran_at,
        claim: record.claim,
    };
}

/**
 * Paylaşımlı-host yetenek kanıtı — `docs/98` FF-63.
 *
 * Kayıt 2026-08-26'dan beri yazılıyordu; okuyan uç yoktu, satır bu yüzden
 * "Unavailable" kalıyordu. Eksik yetenek HATA değildir, planlı bir düşüştür
 * (`skills/shared-host-capability.md`): kayıt "geçti" demez, hangi
 * yeteneğin olmadığını ve ürünün buna nasıl düştüğünü söyler — ekran onu
 * olduğu gibi listeler, yeşile boyamaz.
 */
export function HostCapabilityEvidenceItem({ workspaceId }: HostCapabilityEvidenceItemProps) {
    const [state, setState] = useState<EvidenceState>({ phase: 'loading' });

    useEffect(() => {
        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(evidenceEndpoint(workspaceId));
                if (cancelled) return;
                if (response.status === 404) {
                    setState({ phase: 'unavailable' });
                    return;
                }
                if (!response.ok) {
                    setState({ phase: 'error' });
                    return;
                }
                const parsed = parseEvidence(await response.json());
                if (!cancelled) setState(parsed ?? { phase: 'error' });
            } catch {
                if (!cancelled) setState({ phase: 'error' });
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId]);

    let status: ReactNode;
    let details: ReactNode;

    if (state.phase === 'loading') {
        status = (
            <span role="status">
                <Badge status="info">{t('platform.releaseReadiness.evidence.loading')}</Badge>
            </span>
        );
    } else if (state.phase === 'unavailable') {
        status = (
            <span role="status">
                <Badge status="warning">
                    {t('platform.releaseReadiness.item.status.unavailable')}
                </Badge>
            </span>
        );
    } else if (state.phase === 'error') {
        status = (
            <span role="alert">
                <Badge status="error">{t('platform.releaseReadiness.evidence.error')}</Badge>
            </span>
        );
    } else {
        // Düşüş varsa "geçti" DEĞİL "düşüşle çalışıyor": ikisi farklı işletim kararıdır.
        const degraded = state.degradations.length > 0;
        status = (
            <span role="status">
                <Badge status={degraded ? 'warning' : 'success'}>
                    {degraded
                        ? t('platform.releaseReadiness.hostCapability.status.degraded', {
                              count: String(state.degradations.length),
                          })
                        : t('platform.releaseReadiness.hostCapability.status.full')}
                </Badge>
            </span>
        );
        details = (
            <div className="flex flex-col gap-1 text-meta text-fg-muted">
                <p>
                    <span className="font-medium">
                        {t('platform.releaseReadiness.evidence.recordedAt')}:{' '}
                    </span>
                    {state.ranAt} · PHP {state.phpVersion}
                </p>
                {degraded ? (
                    <ul className="list-disc ps-4">
                        {state.degradations.map((line) => (
                            <li key={line}>{line}</li>
                        ))}
                    </ul>
                ) : null}
                <p>{state.claim}</p>
            </div>
        );
    }

    return (
        <ReadinessItem
            title={t('platform.releaseReadiness.checklist.hostCapability.title')}
            description={t('platform.releaseReadiness.checklist.hostCapability.description')}
            status={status}
            details={details}
        />
    );
}

export default HostCapabilityEvidenceItem;
