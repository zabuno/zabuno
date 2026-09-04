import { t } from '../../../../i18n/workspace';

/**
 * Yayınlamanın ne ANLAMA geldiğini söyleyen kısa bölge — `docs/101` A2/A5.
 *
 * Yayınlama eyleminin kendisi `PublicationStatusRegion`'dadır. Burası
 * eskiden dört ayrı teknik cümle ve KALICI DEVRE DIŞI bir "yayın kipi"
 * seçimi taşıyordu:
 *
 *   - Devre dışı seçim `docs/44`'ün yasağına giriyordu: kullanıcı onu
 *     etkinleştiremez, çünkü etkinleştirmenin bir yolu yok.
 *   - "Belirli bir saatte yayınlama henüz yok" cümlesi, yapılmamış bir
 *     özelliği kullanıcının ekranına taşıyordu (`docs/64` §4).
 *   - "İzniniz gerekir" cümlesi koşulsuz görünüyordu; izni olana da
 *     olmayana da aynı şeyi söylüyordu (yetki artık FF-74 ile ekranda
 *     zaten süzülüyor).
 *
 * Kalan iki cümle SAHİBİN işine yarayan iki gerçektir: yayınlamak donmuş
 * bir kopya bırakır, ve yayın başarısız olursa misafir eski menüyü görmeye
 * devam eder.
 */
export function PublishActionConfigRegion() {
    return (
        <div
            role="region"
            aria-label={t('workspace.publication.publishAction.region')}
            className="flex w-full flex-col gap-[var(--space-2)]"
        >
            <h3 className="text-body font-bold text-fg">
                {t('workspace.publication.publishAction.region')}
            </h3>
            <p className="max-w-[60ch] text-body text-fg-secondary">
                {t('workspace.publication.publishAction.snapshotNotice')}
            </p>
            <p className="max-w-[60ch] text-meta text-fg-muted">
                {t('workspace.publication.publishAction.failurePreservationNotice')}
            </p>
        </div>
    );
}

export default PublishActionConfigRegion;
