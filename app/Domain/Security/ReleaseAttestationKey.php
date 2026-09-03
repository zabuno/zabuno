<?php

declare(strict_types=1);

namespace App\Domain\Security;

/**
 * İnsan tanıklığı gerektiren üç readiness maddesi — `docs/98` FF-63.
 *
 * Her birinin kabul ettiği durum kümesi FARKLI: bir tarama geçer ya da
 * geçmez, bir karar yalnız "verildi", bir denetim raporu yalnız "kaydedildi"
 * (rapor bizim değil, geçip geçmediğine biz karar veremeyiz).
 */
enum ReleaseAttestationKey: string
{
    case QrPhysicalScan = 'qr-physical-scan';
    case RpoRtoDecision = 'rpo-rto-decision';
    case OwaspAsvsAudit = 'owasp-asvs-audit';

    /** @return list<string> */
    public function allowedStatuses(): array
    {
        return match ($this) {
            self::QrPhysicalScan => ['passed', 'failed'],
            self::RpoRtoDecision => ['decided'],
            self::OwaspAsvsAudit => ['recorded'],
        };
    }

    /**
     * Bu madde için ZORUNLU yapılandırılmış alanlar. Bir RPO/RTO kararı
     * saat rakamı olmadan karar değildir; bir tarama cihaz adı olmadan
     * "telefonla taradım" cümlesinden ibarettir.
     *
     * @return list<string>
     */
    public function requiredPayloadKeys(): array
    {
        return match ($this) {
            self::QrPhysicalScan => ['device'],
            self::RpoRtoDecision => ['rpo_hours', 'rto_hours'],
            self::OwaspAsvsAudit => [],
        };
    }
}
