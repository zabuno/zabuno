import { t } from '../../../i18n/workspace';
import { BrandEditForm, type BrandProfile } from '../BrandEditForm';
import { BrandLogoRegion } from './brand/BrandLogoRegion';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';

type BrandPageProps = {
    workspaceId: number;
    brand: BrandProfile | null;
    onSaved: (brand: BrandProfile) => void;
    /** Logo satırındaki "Değiştir" için: dosyanın evi Medya ekranıdır. */
    onNavigateToMedia: () => void;
};

export function BrandPage({ workspaceId, brand, onSaved, onNavigateToMedia }: BrandPageProps) {
    return (
        <div id="section-brand">
            <WorkspacePageFrame
                measure="settings"
                title={t('workspace.shell.nav.brand')}
                description={t('workspace.brand.operational.description')}
            >
                {brand ? (
                    /*
                        Logo satırı ÜSTTE (docs/109): Ayarlar > Marka ile aynı
                        sıra. Aynı bölümün iki ekranda farklı sırayla durması,
                        kullanıcıya iki farklı bölüm gibi görünürdü.
                    */
                    <div className="flex flex-col gap-[var(--space-6)]">
                        <BrandLogoRegion
                            workspaceId={workspaceId}
                            initialMediaAssetId={brand.logoMediaAssetId ?? null}
                            fallbackInitial={(brand.name || '?').slice(0, 1).toLocaleUpperCase()}
                            onNavigateToMedia={onNavigateToMedia}
                        />
                        <BrandEditForm workspaceId={workspaceId} brand={brand} onSaved={onSaved} />
                    </div>
                ) : (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.brand.loading')}
                    </p>
                )}
            </WorkspacePageFrame>
        </div>
    );
}

export default BrandPage;
