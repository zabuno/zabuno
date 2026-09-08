import { Warning } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import { updatedAtLabel } from './orderPresentation';

/**
 * "Ne zaman güncellendi" satırı — `docs/115` §6.
 *
 * Ekran "canlı" demez. Mutfakta donmuş bir ekranla dolu bir ekran aynı
 * görünür; tek ayırt edici şey bu satırdır.
 *
 * KENDİ DOSYASINDA, çünkü her iki giriş kipi de aynı satırı çizer
 * (`docs/149` §4). Dokunma kuyruğunun içinde kalsaydı, masaüstü kuyruğu ya
 * onu oradan içeri alır — yani mobil ekranın tamamını kendi paketine
 * çeker — ya da ikinci bir kopyasını yazardı. İkisi de yanlış: bu satır
 * cihaza özgü değil, PAYLAŞILANdır.
 */
export function FeedStatusLine({
    feed,
    timeZone,
}: {
    feed: { lastUpdatedAt: Date | null; stale: boolean; refresh: () => void };
    timeZone: string | null;
}) {
    return (
        <div className="flex flex-wrap items-center gap-[var(--space-3)]">
            <p className="text-meta text-fg-muted" data-testid="orders-updated-at">
                {updatedAtLabel(feed.lastUpdatedAt, timeZone)}
            </p>
            {feed.stale ? (
                /*
                    `status`, `alert` DEĞİL: ortada bozulmuş bir şey yok,
                    yalnız son deneme tutmadı ve ekrandaki liste eskimiş
                    olabilir. `alert` ekran okuyucuyu bölerdi ve gerçek
                    uyarının değerini düşürürdü (`docs/59`).
                */
                <p role="status" className="flex items-center gap-1 text-meta text-fg-secondary">
                    <Warning size={16} weight="regular" aria-hidden="true" />
                    {t('workspace.orders.stale')}
                </p>
            ) : null}
            <button type="button" className="text-meta underline" onClick={() => feed.refresh()}>
                {t('workspace.orders.refresh')}
            </button>
        </div>
    );
}

export default FeedStatusLine;
