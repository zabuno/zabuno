import { Button } from '../../../catalog/forms/micro/Button';
import { t } from '../../../../i18n/workspace';

/**
 * Yayınlanmış menünün DONMUŞ kopyası.
 *
 * Fiyat, para birimi ve menü satırı kimliği alanları İSTEĞE BAĞLIDIR ve bu
 * bir gevşeklik değil, geçmişin dürüst temsilidir: `menuItemId` snapshot'a
 * sonradan eklendi (`docs/82`) ve o alan eklenmeden önce yayınlanmış
 * sürümler hâlâ veritabanında duruyor. Alanları zorunlu saymak, o eski
 * sürümlerin ekranda çökmesi demekti.
 */
export type PublishedSnapshot = {
    categories: {
        name: string;
        menuItems: {
            menuItemId?: number;
            productName: string;
            priceMinorAmount?: number;
            currencyCode?: string;
        }[];
    }[];
};

export type CurrentPublication = {
    id: number;
    workspaceId: number;
    menuId: number;
    locationId: number;
    version: number;
    state: string;
    publishedAt: string;
    snapshot: PublishedSnapshot;
};

type PublicationStatusRegionProps = {
    current: CurrentPublication | null;
    loading: boolean;
    loadError: boolean;
    onRetry: () => void;
    checklistReady: boolean;
    confirmed: boolean;
    onConfirmedChange: (confirmed: boolean) => void;
    onPublish: () => void;
    publishing: boolean;
    errorMessage: string | null;
};

export function PublicationStatusRegion({
    current,
    loading,
    loadError,
    onRetry,
    checklistReady,
    confirmed,
    onConfirmedChange,
    onPublish,
    publishing,
    errorMessage,
}: PublicationStatusRegionProps) {
    return (
        <div
            role="region"
            aria-label={t('workspace.publication.status.region')}
            className="flex flex-col gap-3"
        >
            <h3 className="text-body font-bold text-fg">
                {t('workspace.publication.status.region')}
            </h3>

            {loading ? (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.publication.status.loading')}
                </p>
            ) : current === null && loadError ? (
                <div className="flex flex-col items-start gap-2">
                    <p role="alert" className="text-body text-fg-danger">
                        {t('workspace.publication.status.loadError')}
                    </p>
                    {/*
                        Düğme metni KATALOGDAN gelir. Önceden koda İngilizce
                        gömülüydü ("Retry"): Türkçe kullanan bir restoran
                        sahibi, hata anında — yani panik anında — ekranındaki
                        tek düğmeyi okuyamıyordu.
                    */}
                    <Button type="button" color="light" onClick={onRetry}>
                        {t('workspace.publication.status.retry')}
                    </Button>
                </div>
            ) : current === null ? (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.publication.status.notPublished')}
                </p>
            ) : (
                // Sürüm sayısı `tabular-nums`: aynı sayı hemen aşağıdaki
                // sürüm listesinde de geçiyor ve iki yerde farklı genişlikte
                // çizilmemeli.
                <p role="status" className="text-body tabular-nums text-fg-secondary">
                    {t('workspace.publication.status.summary', {
                        version: String(current.version),
                        // Sunucunun ham durum değeri DOĞRUDAN basılmaz;
                        // sözlükten geçer. Tanınmayan bir değer gelirse ham
                        // hâli gösterilir — uydurmak yerine dürüstçe.
                        state:
                            current.state === 'published'
                                ? t('workspace.publication.status.published')
                                : current.state === 'draft'
                                  ? t('workspace.publication.status.draft')
                                  : current.state,
                    })}
                </p>
            )}

            {/*
                ONAY VE YAYIN KENDİ ŞERİDİNDE (FF-131, `DESIGN_SPEC` §9 "Onay
                ve yayın").

                Ekranın tek gerçek eylemi budur ve misafirin gördüğü menüyü
                değiştirir. Onay kutusu ile düğme sayfanın geri kalanıyla aynı
                zemindeyken ikisi de "bir bilgi satırı" gibi okunuyordu; sahip
                duraksaması gereken yerde duraksamıyordu.

                Paket ikisini tonlu bir şeride alır: zemin değişir, göz durur.
                Karar ile eylem aynı kutunun içindedir — biri yukarıda biri
                aşağıda olsaydı, işaretlemeden yayınlamayı denemek sıradan bir
                davranış olurdu.
            */}
            <div
                data-publish-commit="true"
                className="flex flex-col gap-[var(--space-3)] rounded-[var(--radius-lg)] border border-border bg-surface-subtle p-[var(--density-padding-inline)]"
            >
                <label className="flex w-full items-center gap-2 text-body text-fg-secondary">
                    <input
                        type="checkbox"
                        checked={confirmed}
                        disabled={!checklistReady}
                        onChange={(event) => onConfirmedChange(event.target.checked)}
                    />
                    {t('workspace.publication.publishAction.checklistConfirmed')}
                </label>

                <Button
                    type="button"
                    color="light"
                    disabled={!checklistReady || !confirmed || publishing}
                    onClick={onPublish}
                    className="self-start"
                >
                    {t('workspace.publication.status.publishButton')}
                </Button>
            </div>

            {errorMessage ? (
                <p role="alert" className="text-body text-fg-danger">
                    {errorMessage}
                </p>
            ) : null}
        </div>
    );
}

export default PublicationStatusRegion;
