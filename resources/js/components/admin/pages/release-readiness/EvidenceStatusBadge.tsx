import { Badge } from '../../../catalog/feedback/micro/Badge';
import { t } from '../../../../i18n/platform';

/**
 * Micro: honest "unavailable" status pill for a single readiness item.
 * There is no verified/ready/passed state yet — every item renders the
 * same neutral not-yet-verified status until a real evidence source exists.
 */
export function EvidenceStatusBadge() {
    return <Badge status="warning">{t('platform.releaseReadiness.item.status.unavailable')}</Badge>;
}

export default EvidenceStatusBadge;
