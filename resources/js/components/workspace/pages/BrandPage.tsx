import { t } from '../../../i18n/workspace';
import { BrandEditForm, type BrandProfile } from '../BrandEditForm';
import { BrandLogoRegion } from './brand/BrandLogoRegion';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';

type BrandPageProps = {
    workspaceId: number;
    brand: BrandProfile | null;
    onSaved: (brand: BrandProfile) => void;
};

export function BrandPage({ workspaceId, brand, onSaved }: BrandPageProps) {
    return (
        <div id="section-brand">
            <WorkspacePageFrame
                measure="settings"
                title={t('workspace.shell.nav.brand')}
                description={t('workspace.brand.operational.description')}
            >
                {brand ? (
                    <>
                        <BrandEditForm workspaceId={workspaceId} brand={brand} onSaved={onSaved} />
                        <BrandLogoRegion
                            workspaceId={workspaceId}
                            initialMediaAssetId={brand.logoMediaAssetId ?? null}
                        />
                    </>
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
