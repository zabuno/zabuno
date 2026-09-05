import { useState } from 'react';

import { t } from '../../../i18n/platform';
import { ReadinessChecklist } from './release-readiness/ReadinessChecklist';
import { WorkspaceDiscovery, type Workspace } from './subscriptions/WorkspaceDiscovery';

/**
 * Yayına hazırlık KANITI — geliştirici panelinde.
 *
 * Bu ekran 2026-08-27'ye kadar restoran panelindeydi ve orada commit hash'i,
 * test süresi, tenant izolasyonu ve yedek/geri-yükleme tatbikatı
 * gösteriyordu. Bunların hiçbiri restoran sahibinin işi değildir; sahibi
 * kararını verdi ve ekran geliştirici paneline taşındı (UX raporu §4.3 ve
 * §9.10, `docs/47` §1.4).
 *
 * Kanıt workspace'e bağlıdır (uç noktalar `/api/workspaces/{id}/security/...`),
 * bu yüzden önce bir workspace seçilir. Abonelik ekranındaki seçicinin
 * AYNISI kullanılır: iki ayrı workspace seçici, iki ayrı davranış demek
 * olurdu.
 */
export function ReleaseReadinessPage() {
    const [workspace, setWorkspace] = useState<Workspace | null>(null);

    return (
        // Başlık ve açıklama artık kabuğun sayfa başlığında (`OpsPageHeader`,
        // `docs/50` §9.2); burada tekrarlamak iki h1 üretirdi.
        <div id="section-release-readiness" className="flex flex-col gap-[var(--space-4)]">
            <WorkspaceDiscovery selectedWorkspace={workspace} onSelect={setWorkspace} />

            {workspace === null ? (
                // Boş durum yokluğu açıklamakla kalmaz, sıradaki eylemi
                // söyler (`docs/44` boş durum standardı).
                <p className="text-body text-fg-secondary">
                    {t('platform.releaseReadiness.workspace.empty')}
                </p>
            ) : (
                <ReadinessChecklist workspaceId={workspace.id} />
            )}
        </div>
    );
}

export default ReleaseReadinessPage;
