import { useCallback, useState } from 'react';

import { t } from '../../../i18n/workspace';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PlanCatalog, type Plan } from './billing/PlanCatalog';
import { CurrentSubscriptionStatus } from './billing/CurrentSubscriptionStatus';
import { IyzicoSandboxCheckout } from './billing/IyzicoSandboxCheckout';
import { SubscribeCheckout, type CatalogPlansState } from './billing/SubscribeCheckout';
import { WorkspaceInvoices } from './billing/WorkspaceInvoices';
import { WorkspaceLedger } from './billing/WorkspaceLedger';

type BillingPageProps = {
    workspaceId: number;
    /** Ödeme sayfasına gidiş; varsayılan tarayıcı yönlendirmesidir, testler yakalar. */
    navigateToPayment?: (url: string) => void;
};

/**
 * The Plan region and Current plan are server-authoritative (GET
 * /api/workspaces/{workspaceId}/plans and .../subscription). Manual payment
 * is read-only here — platform finance records it. The Iyzico sandbox
 * checkout is a frontend-only sandbox surface backed by the sandbox
 * session API — no real money is charged.
 */
export function BillingPage({ workspaceId, navigateToPayment }: BillingPageProps) {
    // Katalog BİR kez iner: liste hem bilgi kartlarını hem seçim panelini besler.
    const [plans, setPlans] = useState<CatalogPlansState>({ state: 'loading', items: [] });
    const handlePlansChange = useCallback((status: CatalogPlansState['state'], list: Plan[]) => {
        setPlans({ state: status, items: list });
    }, []);

    return (
        <div id="section-billing">
            <WorkspacePageFrame
                measure="settings"
                title={t('workspace.billing.heading')}
                description={t('workspace.billing.operational.description')}
            >
                <PlanCatalog
                    workspaceId={workspaceId}
                    onPlansChange={handlePlansChange}
                    label={t('workspace.billing.plan.region')}
                    loadingText={t('workspace.billing.plan.loading')}
                    emptyText={t('workspace.billing.plan.empty')}
                    errorText={t('workspace.billing.plan.error')}
                    retryText={t('workspace.billing.plan.retry')}
                    priceUnavailableText={t('workspace.billing.plan.priceUnavailable')}
                />

                <CurrentSubscriptionStatus workspaceId={workspaceId} />

                {/*
                    KENDİ KENDİNE ABONELİK (docs/107 Faz 1.1 + 1.3, docs/123).

                    Plan seç → fatura profili → Iyzico ödeme sayfası. Kart
                    alanı yok; kart yalnız sağlayıcının sayfasına girilir.
                    Sandbox kipinde de aynı yol: prova gerçek yolun aynısı
                    olmalı, "test mode" cümlesi bunu söyler.
                */}
                <SubscribeCheckout
                    workspaceId={workspaceId}
                    plans={plans}
                    navigateToPayment={navigateToPayment}
                />

                {/*
                    MANUEL ÖDEME FORMU KALDIRILDI.

                    Dört devre dışı alan ve devre dışı bir "Record payment"
                    düğmesi duruyordu; yanında da "bu görünüm salt-okunur"
                    yazıyordu. Devre dışı bir kontrol yalnız üç koşul birden
                    sağlanırsa gösterilir: görünmesi kullanıcıya yolculuğu
                    öğretiyorsa, nasıl etkinleşeceği açıksa, ve kullanıcı
                    gerekli koşulu TAMAMLAYABİLİYORSA.

                    Burada üçü de yoktu: manuel ödemeyi yalnız platform finans
                    ekibi kaydeder, restoran sahibi bu düğmeyi hiçbir koşulda
                    etkinleştiremez. Gösterilmesi bir beklenti yaratıyor ve
                    karşılığı hiç gelmiyordu.

                    Bu yalnız bir arayüz sorunu değildi: başka bir rolün işini
                    bu ekranda göstermek, kapsam ve yetki sınırını bulanıklaştırır
                    (docs/57).
                */}

                {/*
                    Sandbox ödeme yüzeyi ÜRETİMDE bulunmaz.

                    Gerçek para hareketi olmayan bir test akışını müşteri
                    panelinde göstermek, ürünün yapılmamış tarafını kullanıcıya
                    taşımaktır. `import.meta.env.MODE` derleme zamanında
                    çözülür: üretim paketinde bu bileşenin kodu hiç bulunmaz.
                */}
                {import.meta.env?.MODE !== 'production' ? (
                    <IyzicoSandboxCheckout workspaceId={workspaceId} />
                ) : null}

                {/*
                    FATURA (docs/107 Faz 1.4, docs/130).

                    Defterin ÜSTÜNDE durur ve bilerek: defter muhasebenin iç
                    kaydıdır, fatura ise sahibin muhasebecisine verdiği
                    belgedir — aradığı şey odur.
                */}
                <WorkspaceInvoices workspaceId={workspaceId} />

                <WorkspaceLedger workspaceId={workspaceId} />
            </WorkspacePageFrame>
        </div>
    );
}

export default BillingPage;
