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
                {/*
                    BÖLÜMLÜ KONTROL (FF-131, teslim paketinin düzeni).

                    Sekmeler serbest duran, her biri kendi kenarlığını taşıyan
                    düğmelerdi ve "üç ayrı eylem" gibi okunuyordu. Oysa bunlar
                    birbirini DIŞLAYAN seçenekler: biri seçilince diğerleri
                    kapanır. Kenarlık dışarı alınınca o ilişki görünür oluyor
                    ve `tablist` semantiğiyle örtüşüyor — göz ile kulak aynı
                    şeyi söylüyor.

                    Kutu `w-fit` ve taşarsa yatay kayar: dört sekme dar bir
                    telefonda alt satıra düşseydi "bölüm" fikri dağılırdı.
                */}
                <div
                    role="tablist"
                    aria-label={t('workspace.settings.tabs.label')}
                    className="flex w-fit max-w-full gap-[var(--space-1)] overflow-x-auto rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-1)]"
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
                                        ? 'min-h-[var(--control-height)] rounded-[var(--radius-lg)] bg-action px-[var(--space-4)] py-[var(--space-1)] text-body font-bold whitespace-nowrap text-action-fg'
                                        : 'min-h-[var(--control-height)] rounded-[var(--radius-lg)] px-[var(--space-4)] py-[var(--space-1)] text-body font-medium whitespace-nowrap text-fg-secondary hover:bg-surface-hover'
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
                            <div className="flex flex-col gap-[var(--space-6)]">
                                <AccountSettingsRegion currentName={userName} />
                                {/*
                                    GÖRÜNÜM BURADAN GİTTİ (FF-130, sahibin
                                    kararı 2026-09-04: "zip dosyaları bu işin
                                    tanrısıdır").

                                    FF-119'da buraya taşınmıştı ve gerekçesi
                                    doğruydu: tema kişiye aittir. Ama teslim
                                    paketi aynı gerekçeyi bir adım öteye
                                    götürüyor — KİŞİYE ait olan her şey Profil
                                    ekranındadır; Ayarlar çalışma alanına
                                    aittir. Tema Ayarlar'da kaldıkça, kişisel
                                    bir tercih çalışma alanı değişince
                                    değişecekmiş gibi görünüyordu.

                                    Tek ev kuralı korunuyor: ayar iki yerde
                                    değil, Profil > Görünüm'de.
                                */}
                            </div>
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
