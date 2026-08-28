import { t } from '../../../i18n/workspace';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PlanCatalog } from './billing/PlanCatalog';
import { CurrentSubscriptionStatus } from './billing/CurrentSubscriptionStatus';
import { IyzicoSandboxCheckout } from './billing/IyzicoSandboxCheckout';
import { WorkspaceLedger } from './billing/WorkspaceLedger';

type BillingPageProps = {
    workspaceId: number;
};

/**
 * The Plan region and Current plan are server-authoritative (GET
 * /api/workspaces/{workspaceId}/plans and .../subscription). Manual payment
 * is read-only here — platform finance records it. The Iyzico sandbox
 * checkout is a frontend-only sandbox surface backed by the sandbox
 * session API — no real money is charged.
 */
export function BillingPage({ workspaceId }: BillingPageProps) {
    return (
        <div id="section-billing">
            <WorkspacePageFrame
                measure="settings"
                title={t('workspace.billing.heading')}
                description={t('workspace.billing.operational.description')}
            >
                <PlanCatalog
                    workspaceId={workspaceId}
                    label={t('workspace.billing.plan.region')}
                    loadingText={t('workspace.billing.plan.loading')}
                    emptyText={t('workspace.billing.plan.empty')}
                    errorText={t('workspace.billing.plan.error')}
                    retryText={t('workspace.billing.plan.retry')}
                    priceUnavailableText={t('workspace.billing.plan.priceUnavailable')}
                />

                <CurrentSubscriptionStatus workspaceId={workspaceId} />

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

                <WorkspaceLedger workspaceId={workspaceId} />
            </WorkspacePageFrame>
        </div>
    );
}

export default BillingPage;
