import { Button } from '../../../catalog/forms/micro/Button';
import { useState } from 'react';
import { t } from '../../../../i18n/platform';
import { BackupRestoreEvidenceItem } from './BackupRestoreEvidenceItem';
import { ReadinessItem } from './ReadinessItem';
import { TenantIsolationEvidenceItem } from './TenantIsolationEvidenceItem';

type ReadinessChecklistProps = {
    workspaceId: number;
};

type ReadinessChecklistEntry = {
    key: string;
    title: string;
    description: string;
};

const CHECKLIST_ENTRIES: ReadinessChecklistEntry[] = [
    {
        key: 'owasp-asvs',
        title: 'OWASP ASVS audit',
        description: 'A third-party security audit result for this application.',
    },
    {
        key: 'qr-scan',
        title: 'Physical QR scan evidence',
        description: 'A field test of a printed code scanned with a real device.',
    },
    {
        key: 'rpo-rto',
        title: 'RPO & RTO decision',
        description:
            'A recorded decision for how much data loss and downtime this system can tolerate.',
    },
    {
        key: 'shared-host-capability',
        title: 'Shared-host capability evidence',
        description:
            'Evidence that the application runs within its hosting plan’s resource limits.',
    },
];

/**
 * Compound: the six canonical Stage 1 exit readiness items. Tenant
 * isolation resolves from a real, independently run check
 * (TenantIsolationEvidenceItem); the remaining five stay a static,
 * truthful "Unavailable" until their own checks exist.
 */
export function ReadinessChecklist({ workspaceId }: ReadinessChecklistProps) {
    const [refreshToken, setRefreshToken] = useState(0);

    return (
        <div
            role="region"
            aria-label={t('platform.releaseReadiness.checklist.region')}
            className="flex flex-col gap-4"
        >
            <p className="text-body text-fg-muted">
                {t('platform.releaseReadiness.checklist.explanation')}
            </p>
            <Button
                color="light"
                type="button"
                onClick={() => setRefreshToken((token) => token + 1)}
                className="self-start"
            >
                {t('platform.releaseReadiness.refresh.button')}
            </Button>
            <ul className="flex flex-col gap-4">
                <TenantIsolationEvidenceItem
                    key={`tenant-isolation-${refreshToken}`}
                    workspaceId={workspaceId}
                />
                <BackupRestoreEvidenceItem
                    key={`backup-restore-${refreshToken}`}
                    workspaceId={workspaceId}
                />
                {CHECKLIST_ENTRIES.map((entry) => (
                    <ReadinessItem
                        key={entry.key}
                        title={entry.title}
                        description={entry.description}
                    />
                ))}
            </ul>
        </div>
    );
}

export default ReadinessChecklist;
