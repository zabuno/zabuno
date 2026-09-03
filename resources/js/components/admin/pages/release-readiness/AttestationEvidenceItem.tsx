import { useCallback, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { t } from '../../../../i18n/platform';
import { Badge } from '../../../catalog/feedback/micro/Badge';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { ReadinessItem } from './ReadinessItem';

export type AttestationKey = 'qr-physical-scan' | 'rpo-rto-decision' | 'owasp-asvs-audit';

type AttestationEvidenceItemProps = {
    workspaceId: number;
    attestationKey: AttestationKey;
};

type EvidenceState =
    | { phase: 'loading' }
    | { phase: 'unavailable' }
    | { phase: 'error' }
    | {
          phase: 'resolved';
          status: string;
          summary: string;
          reference: string | null;
          attestedBy: string | null;
          attestedAt: string;
          payload: Record<string, string>;
      };

/**
 * Her maddenin kabul ettiği durumlar ve zorunlu yapılandırılmış alanları —
 * sunucudaki `ReleaseAttestationKey` ile birebir. Sunucu son sözü söyler;
 * bu tablo yalnız formu doğru çizmek için.
 */
const SHAPE: Record<
    AttestationKey,
    { statuses: string[]; fields: { name: string; labelKey: string; type: 'text' | 'number' }[] }
> = {
    'qr-physical-scan': {
        statuses: ['passed', 'failed'],
        fields: [
            {
                name: 'device',
                labelKey: 'platform.releaseReadiness.attest.field.device',
                type: 'text',
            },
        ],
    },
    'rpo-rto-decision': {
        statuses: ['decided'],
        fields: [
            {
                name: 'rpo_hours',
                labelKey: 'platform.releaseReadiness.attest.field.rpoHours',
                type: 'number',
            },
            {
                name: 'rto_hours',
                labelKey: 'platform.releaseReadiness.attest.field.rtoHours',
                type: 'number',
            },
        ],
    },
    'owasp-asvs-audit': { statuses: ['recorded'], fields: [] },
};

function evidenceEndpoint(workspaceId: number, key: AttestationKey): string {
    return `/api/workspaces/${workspaceId}/security/evidence/attestations/${key}`;
}

const RECORD_ENDPOINT = '/api/admin/release-attestations';

function parseEvidence(payload: unknown): EvidenceState | null {
    if (typeof payload !== 'object' || payload === null) return null;
    const data = (payload as { data?: unknown }).data;
    if (typeof data !== 'object' || data === null) return null;
    const record = data as Record<string, unknown>;

    if (typeof record.status !== 'string' || typeof record.summary !== 'string') return null;
    if (typeof record.attested_at !== 'string') return null;

    const rawPayload =
        typeof record.payload === 'object' && record.payload !== null
            ? (record.payload as Record<string, unknown>)
            : {};
    const payloadOut: Record<string, string> = {};
    for (const [name, value] of Object.entries(rawPayload)) payloadOut[name] = String(value);

    return {
        phase: 'resolved',
        status: record.status,
        summary: record.summary,
        reference: typeof record.reference === 'string' ? record.reference : null,
        attestedBy: typeof record.attested_by === 'string' ? record.attested_by : null,
        attestedAt: record.attested_at,
        payload: payloadOut,
    };
}

/**
 * İNSAN TANIKLIĞI satırı — `docs/98` FF-63.
 *
 * Makine kanıtından (tenant izolasyonu, yedek tatbikatı) görünür biçimde
 * AYRI: rozet "Attested" der, "Passed" değil; kim ve ne zaman söylediği
 * yazılır. Kayıt yoksa, bu sayfa zaten yalnız superadmine açık olduğu için
 * satırın altında kısa bir kayıt formu çizilir — "Unavailable" bir kapı
 * değil, sıradaki eylemin adresidir (`docs/59` boş durum kuralı).
 */
export function AttestationEvidenceItem({
    workspaceId,
    attestationKey,
}: AttestationEvidenceItemProps) {
    const [state, setState] = useState<EvidenceState>({ phase: 'loading' });
    const shape = SHAPE[attestationKey];

    const [status, setStatus] = useState(shape.statuses[0]);
    const [summary, setSummary] = useState('');
    const [reference, setReference] = useState('');
    const [fields, setFields] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);

    const load = useCallback(async () => {
        try {
            const response = await fetch(evidenceEndpoint(workspaceId, attestationKey));
            if (response.status === 404) {
                setState({ phase: 'unavailable' });
                return;
            }
            if (!response.ok) {
                setState({ phase: 'error' });
                return;
            }
            setState(parseEvidence(await response.json()) ?? { phase: 'error' });
        } catch {
            setState({ phase: 'error' });
        }
    }, [workspaceId, attestationKey]);

    useEffect(() => {
        let cancelled = false;
        void (async () => {
            if (!cancelled) await load();
        })();
        return () => {
            cancelled = true;
        };
    }, [load]);

    async function handleRecord() {
        setSaving(true);
        setSaveError(null);
        try {
            await bootstrapCsrfCookie();
            const response = await fetch(RECORD_ENDPOINT, {
                ...buildAuthRequestInit(),
                method: 'POST',
                headers: {
                    ...(buildAuthRequestInit().headers as Record<string, string>),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    key: attestationKey,
                    status,
                    summary,
                    reference: reference === '' ? null : reference,
                    payload: fields,
                }),
            });
            if (!response.ok) {
                const body = (await response.json().catch(() => null)) as {
                    message?: string;
                } | null;
                setSaveError(body?.message ?? t('platform.releaseReadiness.attest.error'));
                return;
            }
            setSummary('');
            setReference('');
            setFields({});
            await load();
        } catch {
            setSaveError(t('platform.releaseReadiness.attest.error'));
        } finally {
            setSaving(false);
        }
    }

    let statusNode: ReactNode;
    let details: ReactNode;

    if (state.phase === 'loading') {
        statusNode = (
            <span role="status">
                <Badge status="info">{t('platform.releaseReadiness.evidence.loading')}</Badge>
            </span>
        );
    } else if (state.phase === 'error') {
        statusNode = (
            <span role="alert">
                <Badge status="error">{t('platform.releaseReadiness.evidence.error')}</Badge>
            </span>
        );
    } else if (state.phase === 'unavailable') {
        statusNode = (
            <span role="status">
                <Badge status="warning">
                    {t('platform.releaseReadiness.item.status.unavailable')}
                </Badge>
            </span>
        );
        const inputClass =
            'min-h-[44px] w-full rounded-[var(--radius-sm)] border border-[var(--color-border)] px-3';
        details = (
            <div className="mt-2 flex flex-col gap-2">
                <p className="text-meta text-fg-muted">
                    {t(`platform.releaseReadiness.attest.help.${attestationKey}` as never)}
                </p>
                {shape.statuses.length > 1 ? (
                    <label className="flex flex-col gap-1 text-meta">
                        {t('platform.releaseReadiness.attest.field.status')}
                        <select
                            className={inputClass}
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            {shape.statuses.map((option) => (
                                <option key={option} value={option}>
                                    {t(
                                        `platform.releaseReadiness.attest.status.${option}` as never,
                                    )}
                                </option>
                            ))}
                        </select>
                    </label>
                ) : null}
                {shape.fields.map((field) => (
                    <label key={field.name} className="flex flex-col gap-1 text-meta">
                        {t(field.labelKey as never)}
                        <input
                            type={field.type}
                            inputMode={field.type === 'number' ? 'numeric' : undefined}
                            className={inputClass}
                            value={fields[field.name] ?? ''}
                            onChange={(event) =>
                                setFields((previous) => ({
                                    ...previous,
                                    [field.name]: event.target.value,
                                }))
                            }
                        />
                    </label>
                ))}
                <label className="flex flex-col gap-1 text-meta">
                    {t('platform.releaseReadiness.attest.field.summary')}
                    <textarea
                        className={inputClass}
                        rows={2}
                        value={summary}
                        onChange={(event) => setSummary(event.target.value)}
                    />
                </label>
                <label className="flex flex-col gap-1 text-meta">
                    {t('platform.releaseReadiness.attest.field.reference')}
                    <input
                        type="text"
                        className={inputClass}
                        value={reference}
                        onChange={(event) => setReference(event.target.value)}
                    />
                </label>
                <button
                    type="button"
                    className="min-h-[44px] self-start rounded-[var(--radius-sm)] bg-[var(--color-accent)] px-4 text-[var(--color-on-accent)]"
                    disabled={saving}
                    onClick={() => void handleRecord()}
                >
                    {t('platform.releaseReadiness.attest.submit')}
                </button>
                {saveError !== null ? <span role="alert">{saveError}</span> : null}
            </div>
        );
    } else {
        const passed =
            state.status === 'passed' || state.status === 'decided' || state.status === 'recorded';
        statusNode = (
            <span role="status">
                <Badge status={passed ? 'success' : 'error'}>
                    {t(`platform.releaseReadiness.attest.badge.${state.status}` as never)}
                </Badge>
            </span>
        );
        details = (
            <div className="flex flex-col gap-1 text-meta text-fg-muted">
                <p>{state.summary}</p>
                {Object.keys(state.payload).length > 0 ? (
                    <p>
                        {Object.entries(state.payload)
                            .map(([name, value]) => `${name}: ${value}`)
                            .join(' · ')}
                    </p>
                ) : null}
                {state.reference !== null ? <p>{state.reference}</p> : null}
                <p>
                    <span className="font-medium">
                        {t('platform.releaseReadiness.attest.by')}:{' '}
                    </span>
                    {state.attestedBy ?? t('platform.releaseReadiness.attest.by.server')} ·{' '}
                    {state.attestedAt}
                </p>
                {/* Tanıklık, makine kanıtı DEĞİLDİR — her satırda hatırlatılır. */}
                <p>{t('platform.releaseReadiness.attest.disclaimer')}</p>
            </div>
        );
    }

    return (
        <ReadinessItem
            title={t(`platform.releaseReadiness.checklist.${attestationKey}.title` as never)}
            description={t(
                `platform.releaseReadiness.checklist.${attestationKey}.description` as never,
            )}
            status={statusNode}
            details={details}
        />
    );
}

export default AttestationEvidenceItem;
