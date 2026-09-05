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
 *
 * Dördüncüsü FF-167'de eklendi: **serbest dize kabul edilmez.** Olay ve alan
 * adları `analyticsEvents.ts`'te tek bir yerde tanımlıdır ve buradan geçmek
 * zorundadır (`docs/112` §7). Bir yazım hatası GTM'de sessizce ikinci bir
 * olay yaratır ve raporu ikiye böler; o bölünme geriye dönük onarılamaz.
 */

import {
    taxonomyViolations,
    withoutMissingFields,
    type AnalyticsEventName,
    type AnalyticsEventPayload,
} from './analyticsEvents';

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
export const FORBIDDEN_KEYS = [
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
    /*
        Misafirin ARAMA TERİMİ (`docs/112` §3.1 ve §5). Sahibin verisidir,
        bizim raporumuzun değil, ve sunucuda zaten ölçülüyor
        (`analytics_events` → `search_no_results`). GTM'e taşımak, masadaki
        misafirin yazdığı metni üçüncü taraflara akıtmak olurdu.
    */
    'search_term',
];

/**
 * Anonim yüzeyin işareti. `null` "bağlam henüz gelmedi" demektir ve olayı
 * kuyruğa alır; bu ise "bağlam GELMEYECEK" demektir ve olayı geçirir. İki
 * durumu tek bir `null` ile anlatmak, bekleyeni düşenden ayırt edilemez
 * kılardı.
 */
const ANONYMOUS = Symbol('anonymous-analytics-surface');

let context: AnalyticsTenantContext | typeof ANONYMOUS | null = null;
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
/**
 * ANONİM YÜZEY: kiracı YOK, ve bu bir eksiklik değil bir OLGU.
 *
 * Kayıt, giriş, şifre sıfırlama ve davet kabulü ekranlarında henüz bir
 * çalışma alanı yoktur. `setAnalyticsContext` orada hiç çağrılmıyordu ve
 * sonuç şuydu: o ekranlarda basılan her olay kuyruğa girip sayfa
 * değiştiğinde SESSİZCE DÜŞÜYORDU.
 *
 * Bedeli somut: sürtünme ölçümünün en değerli noktası kayıt formudur —
 * insanların ürüne girmeden vazgeçtiği yer orasıdır — ve tam orası
 * ölçülemiyordu.
 *
 * Kusur "bağlam gelmedi" değil, VARSAYIMDI: olay basmanın kiracı
 * gerektirdiği varsayılmıştı. Oysa kiracı bir olayın NİTELİĞİDİR, ön koşulu
 * değil. Anonim yüzey bunu açıkça ilan eder ve olaylar kiracı alanları
 * OLMADAN akar — uydurma bir kimlik ("anonymous", "0") basmak, raporlarda
 * var olmayan bir kiracı yaratırdı.
 */
export function markAnalyticsSurfaceAnonymous(): void {
    context = ANONYMOUS;

    const pending = queue;
    queue = [];

    for (const event of pending) {
        push(event.name, event.payload);
    }
}

export function resetAnalyticsContext(): void {
    context = null;
    queue = [];
}

function contextPayload(): AnalyticsPayload {
    if (context === null || context === ANONYMOUS) {
        return {};
    }

    return {
        zabuno_tenant_id: context.tenantId,
        zabuno_tenant_slug: context.tenantSlug,
        ...(context.plan === undefined ? {} : { zabuno_plan: context.plan }),
        ...(context.role === undefined ? {} : { zabuno_role: context.role }),
    };
}

/**
 * Taksonomi kapısı.
 *
 * Kişisel veri kontrolüyle AYNI politikayı izler ve bu kasıtlı: geliştirme ve
 * testte HATA fırlatır, üretimde olayı sessizce düşürür. Üretimde patlamak
 * yanlış olurdu — ürünün çalışması ölçüme bağlı değildir; ama bozuk bir olayı
 * göndermek de yanlış olurdu, çünkü rapora giren yanlış satır geri alınamaz.
 */
function assertKnownEvent(name: string, payload: AnalyticsPayload): boolean {
    const problems = taxonomyViolations(name, payload);

    if (problems.length === 0) {
        return true;
    }

    if (isDevelopment()) {
        throw new Error(
            `Analytics event "${name}" breaks the taxonomy: ${problems.join('; ')}. ` +
                'Event and field names live in resources/js/lib/analyticsEvents.ts (docs/112 §7).',
        );
    }

    return false;
}

export function trackEvent<E extends AnalyticsEventName>(
    name: E,
    payload: AnalyticsEventPayload<E> = {},
): void {
    // Değeri olmayan alanlar ÖNCE düşer: "bilmiyorum"u `undefined` ile
    // söyleyen çağıran, taksonomi kapısında boş-değer ihlaliyle
    // suçlanmamalı (`docs/112` §3.4).
    const fields = withoutMissingFields(payload);

    if (!assertNoPersonalData(name, fields)) {
        return;
    }

    if (!assertKnownEvent(name, fields)) {
        return;
    }

    if (context === null) {
        if (queue.length < QUEUE_LIMIT) {
            queue.push({ name, payload: fields });
        }

        return;
    }

    push(name, { ...fields, ...contextPayload() });
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
