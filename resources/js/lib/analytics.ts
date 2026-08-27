/**
 * Ölçüm sözleşmesi — `window.dataLayer`'a giden TEK kapı.
 *
 * Sahibin kilit kuralı: her şey tenant bazında analiz edilebilmeli. Bu dosya
 * o kuralı bir dilek olmaktan çıkarıp uygulanabilir hâle getirir, çünkü üç
 * sessiz kayıp burada kapatılır:
 *
 * 1. **SPA'da sayfa görüntülemesi kendiliğinden oluşmaz.** GA4 ve Metrica
 *    ilk yüklemede bir kez ölçer; `history.pushState` ile ekran değiştiğinde
 *    HİÇBİR şey olmaz. Panelde on ekran gezen bir kullanıcı, ölçümde tek
 *    sayfalık bir ziyaret gibi görünürdü.
 * 2. **Tenant bağlamı geç gelir.** Workspace kimliği API'den döner; o ana
 *    kadar oluşan olaylar tenant'sız olurdu. Bu yüzden bağlam bilinene
 *    kadar olaylar KUYRUĞA alınır, sonra sırayla gönderilir.
 * 3. **Kişisel veri sızıntısı geri alınamaz.** `dataLayer`'a giren şey GTM
 *    üzerinden üçüncü taraflara akar. Bu yüzden bilinen kişisel alan adları
 *    geliştirme ve testte HATA fırlatır — üretimde sessizce düşürülür.
 */

type AnalyticsValue = string | number | boolean | null;

export type AnalyticsTenantContext = {
    tenantId: string;
    tenantSlug: string;
    plan?: string;
    role?: string;
};

type AnalyticsPayload = Record<string, AnalyticsValue>;

type QueuedEvent = { name: string; payload: AnalyticsPayload };

/**
 * `dataLayer`'a asla girmemesi gereken alan adları.
 *
 * Liste kısa ve kasıtlı: kapsamlı bir kişisel veri dedektörü yazmıyoruz —
 * o yanlış bir güven duygusu verirdi. Bunlar gerçekte yapılan hatalardır:
 * bir kullanıcı nesnesini olduğu gibi olaya koymak.
 */
const FORBIDDEN_KEYS = [
    'email',
    'e_mail',
    'mail',
    'name',
    'full_name',
    'first_name',
    'last_name',
    'phone',
    'password',
    'token',
    'address',
];

let context: AnalyticsTenantContext | null = null;
let queue: QueuedEvent[] = [];

/**
 * Kuyruk sınırı. Ölçüm hiçbir koşulda belleği büyütmemeli: bağlam hiç
 * gelmezse (örneğin kullanıcının hiçbir workspace'i yoksa) kuyruk sonsuza
 * kadar dolmaya devam ederdi.
 */
const QUEUE_LIMIT = 50;

function isDevelopment(): boolean {
    return import.meta.env?.MODE !== 'production';
}

function assertNoPersonalData(name: string, payload: AnalyticsPayload): boolean {
    const offending = Object.keys(payload).filter((key) =>
        FORBIDDEN_KEYS.some((forbidden) => key.toLowerCase().includes(forbidden)),
    );

    if (offending.length === 0) {
        return true;
    }

    if (isDevelopment()) {
        throw new Error(
            `Analytics event "${name}" carries personal data fields: ${offending.join(', ')}. ` +
                'Personal data must never reach the dataLayer.',
        );
    }

    return false;
}

function push(name: string, payload: AnalyticsPayload): void {
    const layer = (window as unknown as { dataLayer?: unknown[] }).dataLayer;

    // Ölçüm kapalıyken (yerel geliştirme, test, kimlik verilmemiş kurulum)
    // `dataLayer` yoktur. Bu bir hata değildir; olay sessizce düşer.
    if (!Array.isArray(layer)) {
        return;
    }

    layer.push({ event: name, ...payload });
}

/**
 * Tenant bağlamını bildirir ve o ana kadar biriken olayları serbest bırakır.
 *
 * Workspace verisi yüklendiği anda çağrılır. İkinci kez çağrılması
 * (kullanıcı workspace değiştirdiğinde) bağlamı değiştirir; kuyruk zaten
 * boşalmıştır.
 */
export function setAnalyticsContext(next: AnalyticsTenantContext): void {
    context = next;

    const pending = queue;
    queue = [];

    for (const event of pending) {
        push(event.name, { ...event.payload, ...contextPayload() });
    }
}

/** Oturum kapanışı ve testler için: bağlam ve kuyruk sıfırlanır. */
export function resetAnalyticsContext(): void {
    context = null;
    queue = [];
}

function contextPayload(): AnalyticsPayload {
    if (context === null) {
        return {};
    }

    return {
        zabuno_tenant_id: context.tenantId,
        zabuno_tenant_slug: context.tenantSlug,
        ...(context.plan === undefined ? {} : { zabuno_plan: context.plan }),
        ...(context.role === undefined ? {} : { zabuno_role: context.role }),
    };
}

export function trackEvent(name: string, payload: AnalyticsPayload = {}): void {
    if (!assertNoPersonalData(name, payload)) {
        return;
    }

    if (context === null) {
        if (queue.length < QUEUE_LIMIT) {
            queue.push({ name, payload });
        }

        return;
    }

    push(name, { ...payload, ...contextPayload() });
}

/**
 * Ekran değişimi. GA4'ün `page_view`'ı ile aynı adı taşır ki GTM'de ayrıca
 * eşleme yazmak gerekmesin.
 *
 * `page_path` sunucunun gördüğü yolun AYNISIDIR — fragment değil. Böylece
 * sunucu günlüğü, GA4 raporu ve Metabase sorgusu aynı satırı gösterir; üç
 * kaynağın birbirini doğrulayabilmesinin tek yolu budur.
 */
export function trackPageView(path: string, title: string): void {
    trackEvent('page_view', { page_path: path, page_title: title });
}
