import { t } from '../../../i18n/workspace';
import type { DashboardMenuTree } from './DashboardPage';
import { QrDestinationRegion } from './publication/QrDestinationRegion';
import { useCurrentPublication } from './qr/useCurrentPublication';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';

export type QrCodesPageProps = {
    workspaceId?: number;
    dashboardMenuTree?: DashboardMenuTree | null;
    onNavigateToSection?: (section: string) => void;
};

/**
 * QR kodları — kendi hedefi, yayınlama ekranının içinde gizli değil.
 *
 * Önceden yayın sayfasının en altındaydı. Oysa restoran sahibi QR koduna
 * yayınlamak için değil, BASMAK ve masaya koymak için gelir; bu iki iş aynı
 * gün bile yapılmaz. Yayının içine gömülü olması, sık yapılan bir işi nadir
 * yapılan bir işin arkasına saklıyordu (`docs/50` §5).
 */
export function QrCodesPage({
    workspaceId,
    dashboardMenuTree = null,
    onNavigateToSection,
}: QrCodesPageProps) {
    const menuId = dashboardMenuTree?.id ?? null;
    const locationId = dashboardMenuTree?.locationId ?? null;
    const { current } = useCurrentPublication(workspaceId, menuId);

    return (
        <div id="section-qr-codes">
            <WorkspacePageFrame
                title={t('workspace.shell.nav.qrCodes')}
                description={t('workspace.qrCodes.operational.description')}
            >
                {workspaceId !== undefined && locationId !== null && menuId !== null ? (
                    <QrDestinationRegion
                        workspaceId={workspaceId}
                        locationId={locationId}
                        menuId={menuId}
                        hasCurrentPublication={current !== null}
                    />
                ) : (
                    /*
                        Boş durum yalnız "yok" demez, ÇIKIŞ YOLU verir.
                        QR kodu yayınlanmış bir menüye işaret eder; menü yoksa
                        kullanıcının burada yapabileceği bir şey yoktur ve
                        yapabileceği yere götürülmesi gerekir.
                    */
                    <div className="flex flex-col items-start gap-3">
                        <p role="status" className="text-body text-fg-secondary">
                            {t('workspace.qrCodes.empty.needsMenu')}
                        </p>
                        <button
                            type="button"
                            onClick={() => onNavigateToSection?.('menu')}
                            className="min-h-[var(--density-hit-area-min)] rounded-md border border-action bg-action px-4 py-2 text-body font-semibold text-action-fg"
                        >
                            {t('workspace.qrCodes.empty.goToMenu')}
                        </button>
                    </div>
                )}
            </WorkspacePageFrame>
        </div>
    );
}

export default QrCodesPage;
