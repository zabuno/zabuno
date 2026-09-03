import { Button } from '../../../catalog/forms/micro/Button';
import { useState } from 'react';
import { t } from '../../../../i18n/platform';
import { AttestationEvidenceItem } from './AttestationEvidenceItem';
import { BackupRestoreEvidenceItem } from './BackupRestoreEvidenceItem';
import { HostCapabilityEvidenceItem } from './HostCapabilityEvidenceItem';
import { TenantIsolationEvidenceItem } from './TenantIsolationEvidenceItem';

type ReadinessChecklistProps = {
    workspaceId: number;
};

/**
 * Compound: the six canonical Stage 1 exit readiness items — `docs/98` FF-63.
 *
 * Altısı da artık gerçek bir kayıttan okunur; hiçbiri statik değil. Üçü
 * MAKİNE kanıtı (tenant izolasyonu, yedek tatbikatı, host yeteneği — bir
 * komut koşturulur, satır düşer), üçü İNSAN tanıklığı (QR saha taraması,
 * RPO/RTO kararı, ASVS raporu — biri "yaptım/karar verdim/işte rapor" der
 * ve kim/ne zaman dediği kaydın kendisidir). Ekran ikisini farklı
 * etiketler; "Attested" ile "Passed" aynı rozet değildir.
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
                <HostCapabilityEvidenceItem
                    key={`host-capability-${refreshToken}`}
                    workspaceId={workspaceId}
                />
                <AttestationEvidenceItem
                    key={`qr-physical-scan-${refreshToken}`}
                    workspaceId={workspaceId}
                    attestationKey="qr-physical-scan"
                />
                <AttestationEvidenceItem
                    key={`rpo-rto-decision-${refreshToken}`}
                    workspaceId={workspaceId}
                    attestationKey="rpo-rto-decision"
                />
                <AttestationEvidenceItem
                    key={`owasp-asvs-audit-${refreshToken}`}
                    workspaceId={workspaceId}
                    attestationKey="owasp-asvs-audit"
                />
            </ul>
        </div>
    );
}

export default ReadinessChecklist;
