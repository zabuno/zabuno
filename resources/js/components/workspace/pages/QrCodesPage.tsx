import { t } from '../../../i18n/workspace';
import type { DashboardMenuTree } from './DashboardPage';
import { QrDestinationRegion } from './publication/QrDestinationRegion';
import { useCurrentPublication } from './qr/useCurrentPublication';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PanelCard } from './shared/PanelCard';
import { PageState } from './shared/PageState';

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
    const { current, loading, loadError } = useCurrentPublication(workspaceId, menuId);

    return (
        <div id="section-qr-codes">
            <WorkspacePageFrame
                measure="standard"
                title={t('workspace.shell.nav.qrCodes')}
                description={t('workspace.qrCodes.operational.description')}
            >
                <PanelCard>
                    {workspaceId !== undefined && locationId !== null && menuId !== null ? (
                        <QrDestinationRegion
                            workspaceId={workspaceId}
                            locationId={locationId}
                            menuId={menuId}
                            hasCurrentPublication={current !== null}
                            /*
                                SAYFA YALAN SÖYLEMEZ (FF-108).

                                `useCurrentPublication` yayın bilgisini
                                getirirken de, sunucu 500 dönerken de
                                `current: null` verir. Sayfa yalnız ona
                                bakıyordu ve ikisini de "önce menünüzü
                                yayınlayın" diye okuyordu — yani yayında bir
                                menüsü ve masalarda çalışan kodları olan
                                sahibe, kodlarının var olmadığı söyleniyordu.

                                Üç hâl artık ayrı: biliniyor, henüz
                                bilinmiyor, sorulamadı.
                            */
                            publicationLoading={loading}
                            publicationLoadFailed={loadError}
                            onUpgrade={() => onNavigateToSection?.('billing')}
                        />
                    ) : (
                        /*
                        ÖN KOŞUL durumu — hata değil.

                        QR kodu yayınlanmış bir menüye işaret eder; menü yoksa
                        bozulmuş bir şey yoktur, yalnız sıradaki adım henüz
                        yapılmamıştır. `role="alert"` ile sunmak aciliyet
                        bildirir ve kullanıcıyı olmayan bir arızayı aramaya
                        iter (docs/59).
                    */
                        <PageState
                            kind="prerequisite"
                            title={t('workspace.qrCodes.empty.needsMenu')}
                            description={t('workspace.qrCodes.empty.needsMenu.why')}
                            action={
                                <button
                                    type="button"
                                    onClick={() => onNavigateToSection?.('menu')}
                                    className="min-h-[var(--density-hit-area-min)] rounded-md border border-action bg-action px-4 py-2 text-body font-semibold text-action-fg"
                                >
                                    {t('workspace.qrCodes.empty.goToMenu')}
                                </button>
                            }
                        />
                    )}
                </PanelCard>
            </WorkspacePageFrame>
        </div>
    );
}

export default QrCodesPage;
