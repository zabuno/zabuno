import { useEffect, useState } from 'react';

import { t } from '../../../i18n/workspace';
import { PageState } from './shared/PageState';
import { OrderHistoryRegion } from './orders/OrderHistoryRegion';
import { OrderingSwitchRegion } from './orders/OrderingSwitchRegion';
import { OrderQueueRegion } from './orders/OrderQueueRegion';
import type { KitchenSurfaceRenderer } from './orders/kitchenSurface';

/**
 * SİPARİŞLER — `docs/115` S4 + S5 + S6 (FF-179).
 *
 * Üç sekme, üç insan: garson kuyruğa bakar, aşçı monitöre, sahip şaltere ve
 * geçmişe. Üçünü ayrı bölümler yapmak, kenar çubuğunda her gün kullanılmayan
 * iki hedef daha demekti; tek bölüm ve gerçek alt adresler (`orders/kitchen`)
 * ise hem paylaşılabilir hem yer imine eklenebilir kalıyor — mutfak monitörü
 * duvardaki ekranda YER İMİDİR ve açıldığı yeri hatırlamak zorundadır.
 *
 * SEKME İZİNLE ÇİZİLİR (`docs/59`: yapılamayan iş çizilmez). Aşçının
 * ekranında "Ayarlar" sekmesi hiç yoktur; Editör bu bölümü zaten göremez.
 */
export type OrdersPageProps = {
    workspaceId: number;
    locationId: number | null;
    /** Bölüm içi adres: '' (kuyruk), 'kitchen', 'settings'. */
    subPath: string;
    onNavigate: (key: string) => void;
    can: (permission: string) => boolean;
    /**
     * Mutfak monitörünü çizen işlev — YALNIZ masaüstü paketinde vardır.
     *
     * `undefined` telefonun normal hâlidir (`docs/54`): o pakette monitörün
     * kodu hiç yoktur. Ekran boş bir kutu değil, nedenini söyleyen bir cümle
     * gösterir.
     */
    renderKitchenMonitor?: KitchenSurfaceRenderer;
};

type Tab = 'queue' | 'kitchen' | 'settings';

function resolveTab(subPath: string): Tab {
    if (subPath === 'kitchen') {
        return 'kitchen';
    }

    if (subPath === 'settings') {
        return 'settings';
    }

    return 'queue';
}

export function OrdersPage({
    workspaceId,
    locationId,
    subPath,
    onNavigate,
    can,
    renderKitchenMonitor,
}: OrdersPageProps) {
    /*
        Şalterin hâli SAYFADA tutulur, iki sekmede birden okunmaz. Kuyruk
        ekranı boş listeyi doğru anlatabilmek için bu değere ihtiyaç duyuyor
        (sessiz akşam mı, kapalı şalter mi) ve iki ayrı okuma yapsaydık,
        sahip şalteri kapattığında kuyruk sekmesi bir süre daha "sipariş
        bekleniyor" derdi.
    */
    const [acceptsOrders, setAcceptsOrders] = useState<boolean | null>(null);

    /*
        PLAN, ŞALTERLE AYNI OKUMADAN GELİR — `docs/115` Y3.

        Üç sekme de aynı gerçeği söylemek zorunda: her biri kendi okumasını
        yapsaydı sahip aynı akşam bir sekmede "planında yok", ötekinde "bugün
        sipariş yok" okurdu ve hangisinin doğru olduğunu bilemezdi.

        `null` = henüz bilinmiyor; olmayan bir kısıtı ekrana yazmamak için
        bilinmeyen "eksik" sayılmaz.
    */
    const [planIncludesOrdering, setPlanIncludesOrdering] = useState<boolean | null>(null);

    /*
        ŞALTERİN HÂLİ KUYRUK İÇİN DE OKUNUR, ve okumak `order.view` yeter.

        Garsonun `order.settings` izni yoktur, yani Ayarlar sekmesi ona hiç
        çizilmez — o sekmeden gelecek bir değere bağlansaydık, kuyruk ekranı
        garsona boş listeyi HİÇBİR ZAMAN açıklayamazdı. Bu, ürünün en sessiz
        arızalarından biri olurdu: kapalı bir hizmet, sessiz bir akşam gibi
        görünür ve kimse şalteri açmayı akıl etmez (`docs/115` Y1).

        Tek seferlik: şalter yoklanmaz. Sahip onu akşamda bir kez çevirir;
        on saniyede bir sormak, cevabı hiç değişmeyen bir soruyu tekrarlamak
        olurdu.
    */
    useEffect(() => {
        if (locationId === null) {
            return;
        }

        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${String(workspaceId)}/locations/${String(locationId)}/ordering`,
                    { headers: { Accept: 'application/json' } },
                );

                if (cancelled || !response.ok) {
                    return;
                }

                const body = (await response.json()) as {
                    acceptsOrders?: boolean;
                    planIncludesOrdering?: boolean;
                };

                if (!cancelled) {
                    setAcceptsOrders(body.acceptsOrders === true);
                    setPlanIncludesOrdering(body.planIncludesOrdering !== false);
                }
            } catch {
                // Bilinmiyor olarak KALIR (`null`). Bilinmeyen bir şalteri
                // "açık" saymak, boş kuyruğu yanlış cümleyle açıklamak
                // olurdu.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, locationId]);

    const tab = resolveTab(subPath);
    const canKitchen = can('order.kitchen');
    const canSettings = can('order.settings');

    if (locationId === null) {
        return (
            <PageState
                kind="prerequisite"
                screen="orders"
                title={t('workspace.orders.prerequisite.title')}
                description={t('workspace.orders.prerequisite.description')}
                action={
                    <button
                        type="button"
                        className="underline"
                        onClick={() => onNavigate('locations')}
                    >
                        {t('workspace.shell.nav.locations')}
                    </button>
                }
            />
        );
    }

    const tabs: Array<{ key: Tab; path: string; label: string }> = [
        { key: 'queue', path: 'orders', label: t('workspace.orders.tab.queue') },
        ...(canKitchen
            ? [
                  {
                      key: 'kitchen' as const,
                      path: 'orders/kitchen',
                      label: t('workspace.orders.tab.kitchen'),
                  },
              ]
            : []),
        ...(canSettings
            ? [
                  {
                      key: 'settings' as const,
                      path: 'orders/settings',
                      label: t('workspace.orders.tab.settings'),
                  },
              ]
            : []),
    ];

    return (
        <div className="flex flex-col gap-[var(--space-4)]">
            <h2 className="text-title font-bold tracking-tight text-fg">
                {t('workspace.orders.heading')}
            </h2>

            <nav aria-label={t('workspace.orders.heading')}>
                <ul className="flex flex-wrap gap-[var(--space-2)]">
                    {tabs.map((entry) => (
                        <li key={entry.key}>
                            <button
                                type="button"
                                aria-current={tab === entry.key ? 'page' : undefined}
                                onClick={() => onNavigate(entry.path)}
                                className="min-h-[44px] rounded-[var(--radius-md)] border border-border px-[var(--space-4)] text-body font-medium text-fg"
                            >
                                {entry.label}
                            </button>
                        </li>
                    ))}
                </ul>
            </nav>

            {tab === 'queue' ? (
                <OrderQueueRegion
                    workspaceId={workspaceId}
                    locationId={locationId}
                    acceptsOrders={acceptsOrders}
                    planIncludesOrdering={planIncludesOrdering}
                    onNavigateToSettings={() => onNavigate('orders/settings')}
                    onNavigateToPlan={() => onNavigate('billing')}
                />
            ) : null}

            {tab === 'kitchen' ? (
                canKitchen ? (
                    planIncludesOrdering === false ? (
                        /*
                            BOŞ MONİTÖR, AŞÇI İÇİN "BU AKŞAM SAKİN" DEMEKTİR
                            ve bu cümle burada yanlıştır (`docs/115` Y3):
                            sipariş gelmiyor değil, GELEMİYOR. Monitörü
                            çizip üstüne bir uyarı koymak da yetmezdi —
                            tam ekran düğmesi ve "hiç iş yok" tahtası,
                            beklemeye devam etmeyi söyleyen bir ekrandır.

                            Çıkış yolu MUTFAKTA DEĞİL: planı sahip
                            değiştirir. Aşçıya basacağı bir düğme vermek,
                            yapamayacağı bir işi çizmek olurdu (`docs/59`).
                        */
                        <PageState
                            kind="planRestricted"
                            screen="orders_kitchen"
                            title={t('workspace.orders.kitchen.plan.title')}
                            description={t('workspace.orders.kitchen.plan.description', {
                                name: t('workspace.orders.plan.name'),
                            })}
                            whyNoAction={t('workspace.orders.kitchen.plan.description', {
                                name: t('workspace.orders.plan.name'),
                            })}
                        />
                    ) : renderKitchenMonitor !== undefined ? (
                        renderKitchenMonitor({
                            workspaceId,
                            locationId,
                            canAdvance: canKitchen,
                            canDeliver: can('order.confirm'),
                        })
                    ) : (
                        /*
                            TELEFONDA MONİTÖR YOKTUR ve bu dürüstçe söylenir
                            (`docs/54`). Sessiz bir boş ekran, aşçıya bir şeyin
                            bozulduğunu düşündürürdü; oysa bozulan bir şey yok,
                            bu ekran duvardaki monitör için yazıldı.
                        */
                        <PageState
                            kind="prerequisite"
                            screen="orders_kitchen"
                            title={t('workspace.orders.kitchen.desktopOnly.title')}
                            description={t('workspace.orders.kitchen.desktopOnly.description')}
                            whyNoAction={t('workspace.orders.kitchen.desktopOnly.description')}
                        />
                    )
                ) : (
                    <PageState
                        kind="permission"
                        screen="orders_kitchen"
                        title={t('workspace.orders.permission.title')}
                        description={t('workspace.orders.permission.description')}
                        whyNoAction={t('workspace.orders.permission.description')}
                    />
                )
            ) : null}

            {tab === 'settings' ? (
                canSettings ? (
                    <div className="flex flex-col gap-[var(--space-5)]">
                        <OrderingSwitchRegion
                            workspaceId={workspaceId}
                            locationId={locationId}
                            onChange={setAcceptsOrders}
                            onNavigateToPlan={() => onNavigate('billing')}
                        />
                        <OrderHistoryRegion workspaceId={workspaceId} locationId={locationId} />
                    </div>
                ) : (
                    <PageState
                        kind="permission"
                        screen="orders_settings"
                        title={t('workspace.orders.permission.title')}
                        description={t('workspace.orders.permission.description')}
                        whyNoAction={t('workspace.orders.permission.description')}
                    />
                )
            ) : null}
        </div>
    );
}

export default OrdersPage;
