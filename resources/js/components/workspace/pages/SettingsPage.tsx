import { lazy, Suspense } from 'react';

import { t } from '../../../i18n/workspace';
import { BrandEditForm, type BrandProfile } from '../BrandEditForm';
import { BrandLogoRegion } from './brand/BrandLogoRegion';
import { AuditTrailRegion } from './settings/AuditTrailRegion';
import { WorkspaceIdentityRegion } from './settings/WorkspaceIdentityRegion';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PanelCard } from './shared/PanelCard';

/*
    Plan ve fatura ekranı İSTENDİĞİNDE iner (FF-97). Ayarların üç sekmesinden
    biri için tüm faturalandırma kodunu her açılışta indirmek, günde bir kez
    menü düzenleyen restoranın beklediği süreyi uzatıyordu.
*/
const BillingPage = lazy(async () => ({ default: (await import('./BillingPage')).BillingPage }));

export type SettingsTab = 'brand' | 'workspace' | 'billing' | 'audit';

const TABS: ReadonlyArray<{ key: SettingsTab; labelKey: Parameters<typeof t>[0] }> = [
    { key: 'brand', labelKey: 'workspace.settings.tab.brand' },
    /*
        ÇALIŞMA ALANI (docs/109, kaynağın dizisi:
        `['Marka','Çalışma alanı','Plan ve fatura','Denetim']`).

        Bu sekmenin adı "Hesap"tı ve içinde KİŞİSEL ad/şifre formu duruyordu.
        Aynı form Profil ekranında da vardı: bir ayarın iki evi. Kaynak sınırı
        net çiziyor — Ayarlar ÇALIŞMA ALANINA aittir, kişiye ait olan her şey
        Profil'dedir. Ad değişmeden içerik düzeltilseydi, kullanıcı "Hesap"
        yazan yere bakıp kendi adını arar ve bulamazdı; ad ile içerik birlikte
        değişmek zorundaydı.
    */
    { key: 'workspace', labelKey: 'workspace.settings.tab.workspace' },
    { key: 'billing', labelKey: 'workspace.settings.tab.billing' },
    /*
        DENETİM (FF-132) — dördüncü sekme. En SONDA duruyor ve bu bilinçli:
        günlük bir iş değil, bir soru çıktığında açılan bir yer. Başa
        konsaydı sahibi her Ayarlar açtığında önce geçmişe bakardı.
    */
    { key: 'audit', labelKey: 'workspace.settings.tab.audit' },
];

export type SettingsPageProps = {
    workspaceId: number;
    brand: BrandProfile | null;
    onSaved: (brand: BrandProfile) => void;
    activeTab: SettingsTab;
    onSelectTab: (tab: SettingsTab) => void;
    /** Marka sekmesindeki "Değiştir" düğmesi için: dosyanın evi Medya'dır. */
    onNavigateToMedia: () => void;
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
 *
 * SINIR (docs/109, kanonik kaynak): bu ekran ÇALIŞMA ALANINA aittir. Kişiye
 * ait hiçbir şey —ad, e-posta, şifre, tema, satır aralığı— burada çizilmez;
 * hepsinin evi Profil ekranıdır. Aynı formu iki ekranda göstermek,
 * kullanıcıya aynı ayarın iki değeri varmış gibi görünüyordu.
 */
export function SettingsPage({
    workspaceId,
    brand,
    onSaved,
    activeTab,
    onSelectTab,
    onNavigateToMedia,
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
                                <div className="flex flex-col gap-[var(--space-6)]">
                                    {/*
                                        LOGO SATIRI EN ÜSTTE (docs/109,
                                        kaynağın `tabBrand` düzeni): sahip
                                        önce bugün ne olduğunu görür, sonra
                                        marka alanlarını düzenler.
                                    */}
                                    <BrandLogoRegion
                                        workspaceId={workspaceId}
                                        initialMediaAssetId={brand.logoMediaAssetId ?? null}
                                        fallbackInitial={(brand.name || '?')
                                            .slice(0, 1)
                                            .toLocaleUpperCase()}
                                        onNavigateToMedia={onNavigateToMedia}
                                    />
                                    <BrandEditForm
                                        workspaceId={workspaceId}
                                        brand={brand}
                                        onSaved={onSaved}
                                    />
                                </div>
                            ) : (
                                <p role="status" className="text-body text-fg-muted">
                                    {t('workspace.brand.loading')}
                                </p>
                            ))}

                        {activeTab === 'workspace' && (
                            <WorkspaceIdentityRegion workspaceId={workspaceId} />
                        )}

                        {activeTab === 'audit' && <AuditTrailRegion workspaceId={workspaceId} />}

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
