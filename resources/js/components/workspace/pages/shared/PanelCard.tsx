import { OpsCard, type OpsCardProps } from '../../../ops/OpsCard';

/**
 * Restoran paneli kartı — `docs/102` §1: superadmin kabuğuyla AYNI kart
 * grameri (`OpsCard`), ayrı bir kopya değil. Kart yalnız sınırının anlam
 * taşıdığı yerde kullanılır: bir bölge (yükleme, kütüphane, QR listesi),
 * bir form, bir liste (`docs/36` §5.2).
 */
export function PanelCard(props: OpsCardProps) {
    return <OpsCard {...props} />;
}

export default PanelCard;
