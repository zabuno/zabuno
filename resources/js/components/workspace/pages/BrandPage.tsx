import { t } from '../../../i18n/workspace';
import { BrandEditForm, type BrandProfile } from '../BrandEditForm';
import { WorkspacePageFrame, type WorkspacePageStatusBadge } from './shared/WorkspacePageFrame';

type BrandPageProps = {
    workspaceId: number;
    brand: BrandProfile | null;
    onSaved: (brand: BrandProfile) => void;
};

export function BrandPage({ workspaceId, brand, onSaved }: BrandPageProps) {
    const badges: WorkspacePageStatusBadge[] = brand
        ? [{ key: 'brand-status', status: 'success', label: `#${brand.id}` }]
        : [];

    return (
        <div id="section-brand">
            <WorkspacePageFrame
                title={t('workspace.shell.nav.brand')}
                description={t('workspace.brand.operational.description')}
                badges={badges}
            >
                {brand ? (
                    <BrandEditForm workspaceId={workspaceId} brand={brand} onSaved={onSaved} />
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
