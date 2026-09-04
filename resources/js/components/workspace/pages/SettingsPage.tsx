import { lazy, Suspense } from 'react';

import { t } from '../../../i18n/workspace';
import { BrandEditForm, type BrandProfile } from '../BrandEditForm';
import { BrandLogoRegion } from './brand/BrandLogoRegion';
import { AccountSettingsRegion } from './settings/AccountSettingsRegion';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PanelCard } from './shared/PanelCard';

/*
    Plan ve fatura ekranı İSTENDİĞİNDE iner (FF-97). Ayarların üç sekmesinden
    biri için tüm faturalandırma kodunu her açılışta indirmek, günde bir kez
    menü düzenleyen restoranın beklediği süreyi uzatıyordu.
*/
const BillingPage = lazy(async () => ({ default: (await import('./BillingPage')).BillingPage }));

export type SettingsTab = 'brand' | 'account' | 'billing';

const TABS: ReadonlyArray<{ key: SettingsTab; labelKey: Parameters<typeof t>[0] }> = [
    { key: 'brand', labelKey: 'workspace.settings.tab.brand' },
    // Hesap, MARKA ile FATURA arasında: ikisi de "kurulur ve unutulur", hesap
    // ise arada bir onarılır (`docs/83`).
    { key: 'account', labelKey: 'workspace.settings.tab.account' },
    { key: 'billing', labelKey: 'workspace.settings.tab.billing' },
];

export type SettingsPageProps = {
    workspaceId: number;
    brand: BrandProfile | null;
    onSaved: (brand: BrandProfile) => void;
    activeTab: SettingsTab;
    onSelectTab: (tab: SettingsTab) => void;
    /** Oturumdaki kullanıcının adı; hesap sekmesi onu ön-doldurur. */
    userName?: string;
};

/**
 * Ayarlar — nadiren açılan, günlük operasyona ait OLMAYAN işler.
 *
 * Marka bilgileri ve plan/faturalandırma önceden ana menüde kalıcı birer
 * madde işgal ediyordu. İkisi de her gün yapılan işler değil: marka bir kez
 * kurulur, plan ayda bir bakılır. Her gün gidilen dört hedefin arasında
 * durmaları, listeyi okunması gereken dokuz maddelik bir yığına çeviriyordu
 * (`docs/50` §5).
 *
 * Bölüm içi gezinti YATAY SEKME olarak duruyor, üçüncü bir kalıcı sol ray
 * olarak değil — Carbon üç navigasyon katmanını desteklemez ve daha derin
 * seviye için sayfa içi sekme önerir (`docs/50` §4).
 */
export function SettingsPage({
    workspaceId,
    brand,
    onSaved,
    activeTab,
    onSelectTab,
    userName,
}: SettingsPageProps) {
    return (
        <div id="section-settings">
            <WorkspacePageFrame
                measure="settings"
                title={t('workspace.shell.nav.settings')}
                description={t('workspace.settings.operational.description')}
            >
                {/*
                    Sekmeler gerçek bir `tablist`. Bir dizi düğmeyi sekme gibi
                    GÖSTERMEK yetmez: ekran okuyucu kullanan biri kaç sekme
                    olduğunu, hangisinde bulunduğunu ve ok tuşlarıyla
                    gezinebileceğini ancak rollerden öğrenir.
                */}
                <div
                    role="tablist"
                    aria-label={t('workspace.settings.tabs.label')}
                    className="flex flex-wrap gap-2"
                >
                    {TABS.map((tab) => {
                        const selected = tab.key === activeTab;

                        return (
                            <button
                                key={tab.key}
                                type="button"
                                role="tab"
                                id={`settings-tab-${tab.key}`}
                                aria-selected={selected}
                                aria-controls={`settings-panel-${tab.key}`}
                                onClick={() => onSelectTab(tab.key)}
                                className={
                                    selected
                                        ? 'min-h-[var(--density-hit-area-min)] rounded-md border border-action bg-action px-4 py-2 text-body font-semibold text-action-fg'
                                        : 'min-h-[var(--density-hit-area-min)] rounded-md border border-border px-4 py-2 text-body font-medium text-fg-secondary hover:bg-surface-hover'
                                }
                            >
                                {t(tab.labelKey)}
                            </button>
                        );
                    })}
                </div>

                <div
                    role="tabpanel"
                    id={`settings-panel-${activeTab}`}
                    aria-labelledby={`settings-tab-${activeTab}`}
                >
                    <PanelCard>
                        {activeTab === 'brand' &&
                            (brand ? (
                                <>
                                    <BrandEditForm
                                        workspaceId={workspaceId}
                                        brand={brand}
                                        onSaved={onSaved}
                                    />
                                    <BrandLogoRegion
                                        workspaceId={workspaceId}
                                        initialMediaAssetId={brand.logoMediaAssetId ?? null}
                                    />
                                </>
                            ) : (
                                <p role="status" className="text-body text-fg-muted">
                                    {t('workspace.brand.loading')}
                                </p>
                            ))}

                        {activeTab === 'account' && (
                            <AccountSettingsRegion currentName={userName} />
                        )}

                        {activeTab === 'billing' && (
                            <Suspense fallback={null}>
                                <BillingPage workspaceId={workspaceId} />
                            </Suspense>
                        )}
                    </PanelCard>
                </div>
            </WorkspacePageFrame>
        </div>
    );
}

export default SettingsPage;
