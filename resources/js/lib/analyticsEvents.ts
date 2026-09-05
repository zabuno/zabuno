/**
 * Ölçüm TAKSONOMİSİ — olay adlarının ve alan adlarının TEK tanımı
 * (`docs/112` §7).
 *
 * Neden ayrı bir dosya ve neden serbest dize yok: ölçümün en sık ölme biçimi
 * yanlış ölçüm değil, **tutarsız** ölçümdür. `menu_pubished` diye bir yazım
 * hatası hiçbir yerde hata vermez — GTM sessizce YENİ bir olay yaratır, eski
 * olayın grafiği o günden sonra düşer, yenisininki sıfırdan başlar ve rapor
 * ikiye bölünür. Bunu üç ay sonra fark eden kişi, hangi grafiğin doğru
 * olduğunu bir daha asla söyleyemez.
 *
 * Bu yüzden burada tanımlı olmayan bir ad basılamaz: derleme zamanında tip
 * hatası, çalışma zamanında (geliştirme/test) atılan hata olur.
 *
 * ONAY (consent) NOTU — dürüst durum: bu dosya bir consent kapısı KURMAZ ve
 * kodda bugün böyle bir kapı YOKTUR. `modules/analytics-consent-tagging.md`
 * kuralı tarif eder, uygulama henüz onu uygulamaz. Bu paket mevcut davranışı
 * ne gevşetir ne genişletir; eksik burada yazılıdır ki bir sonraki okuyan
 * "kapı vardır" sanmasın.
 */

/**
 * GA4'ün sessiz kırpma sınırları.
 *
 * Sınırı aşan bir ad/değer hata vermez — GA4 onu KESER. Kesilmiş iki farklı
 * ad aynı ada dönüşebilir; o noktadan sonra iki ayrı ölçüm tek satırda
 * toplanır ve ayrıştırılamaz.
 */
export const GA4_LIMITS = {
    eventName: 40,
    fieldName: 40,
    fieldValue: 100,
} as const;

/**
 * Olay → o olayın basabileceği alanlar (`docs/112` §4).
 *
 * Alan listesi de kilitlidir, yalnız olay adı değil: aynı olaya bir yerde
 * `item_count`, başka bir yerde `items` yazmak, GA4'te iki ayrı özel boyut
 * demektir ve ikisi de yarım dolu olur.
 *
 * Listede olmayan bir olay BİLEREK yoktur (`docs/112` §4 ölçütü: bu olayı
 * bilmek bir ürün kararını değiştirir mi?). Yeni olay eklemek bu tabloya
 * satır eklemektir — çağrı yerine dize yazmak değil.
 */
export const ANALYTICS_EVENTS = {
    /* ── Altyapı: taksonomiden önce de basılan olaylar ──────────────── */
    page_view: ['page_path', 'page_title'],
    /*
        `error_name` DEĞİL `error_class`.

        Eski ad kişisel veri süzgecine takılıyordu: süzgeç `name` alt dizesini
        arar ve `error_name` onu içerir. Yani ön yüz çökmeleri geliştirmede
        hata fırlatıyor, ÜRETİMDE İSE SESSİZCE DÜŞÜYORDU — boru hattının tek
        gerçek olayı hiç akmıyordu. Ad kişisel veri taşımıyordu, ama süzgeç
        bunu bilemez; doğru düzeltme süzgeci gevşetmek değil, alanı kişisel
        veri gibi okunmayan bir adla yazmaktır.
    */
    frontend_error_boundary: ['error_class', 'boundary_scope'],
    build_divergence_detected: ['divergence_kind'],

    /* ── §4.3 Sürtünme — "ürün nerede zorluyor?" ────────────────────── */
    form_validation_failed: ['form', 'field', 'reason'],
    action_blocked: ['action', 'reason'],
    upload_rejected: ['reason', 'kind'],
    empty_state_seen: ['screen'],
    retry_clicked: ['surface'],

    /* ── §4.1 Aktivasyon — "sahip ilk değerine ulaştı mı?" ──────────── */
    workspace_created: ['has_brand'],
    brand_saved: ['is_first'],
    location_created: ['location_count'],
    menu_created: ['source'],
    menu_item_added: ['item_count', 'has_photo', 'has_description'],
    first_publish_completed: ['minutes_since_signup', 'item_count'],
    qr_downloaded: ['format', 'size', 'count', 'is_bulk'],

    /* ── §4.2 Günlük işletim — "ürün her gün kullanılıyor mu?" ───────── */
    menu_item_price_changed: ['direction'],
    menu_item_stock_toggled: ['to'],
    menu_item_visibility_toggled: ['to'],
    menu_published: ['change_count', 'is_scheduled', 'is_rollback'],
    publication_rolled_back: ['versions_back'],
    media_uploaded: ['kind', 'size_bucket', 'outcome'],

    /* ── §4.4 AI — "makine gerçekten yardım ediyor mu?" ──────────────── */
    ai_import_started: ['source', 'page_count'],
    ai_import_reviewed: ['accepted_count', 'rejected_count', 'edited_count'],
    ai_suggestion_accepted: ['kind'],
    ai_unavailable_seen: ['capability', 'reason'],
} as const satisfies Record<string, readonly string[]>;

export type AnalyticsEventName = keyof typeof ANALYTICS_EVENTS;

type FieldNameOf<E extends AnalyticsEventName> = (typeof ANALYTICS_EVENTS)[E][number];

/**
 * Bir olayın yükü.
 *
 * Her alan İSTEĞE BAĞLIDIR ve `undefined` geçilebilir — çünkü sözleşmenin
 * en sert kuralı budur (`docs/112` §3.4): **bir alanın değeri yoksa alan HİÇ
 * gönderilmez.** `null`, `0` ya da `"unknown"` değil. Sıfır bir ölçümdür ve
 * bilinmeyenin yerine geçemez; "0 dakikada yayınladı" ile "ne zaman
 * yayınladığını bilmiyoruz" aynı grafikte toplanırsa ortalama yalan söyler.
 *
 * Çağıran bu yüzden `undefined` verebilir; alan yükten TAMAMEN düşer.
 */
export type AnalyticsEventPayload<E extends AnalyticsEventName> = {
    [K in FieldNameOf<E>]?: string | number | boolean | undefined;
};

/** Taksonomi ihlali; boş liste "temiz" demektir. */
export function taxonomyViolations(name: string, payload: Record<string, unknown>): string[] {
    const problems: string[] = [];

    if (!(name in ANALYTICS_EVENTS)) {
        problems.push(`unknown event name "${name}"`);

        return problems;
    }

    if (name.length > GA4_LIMITS.eventName) {
        problems.push(`event name "${name}" exceeds GA4's ${GA4_LIMITS.eventName} characters`);
    }

    const allowed: readonly string[] = ANALYTICS_EVENTS[name as AnalyticsEventName];

    for (const [field, value] of Object.entries(payload)) {
        if (!allowed.includes(field)) {
            problems.push(`unknown field "${field}" on event "${name}"`);

            continue;
        }

        if (field.length > GA4_LIMITS.fieldName) {
            problems.push(`field name "${field}" exceeds GA4's ${GA4_LIMITS.fieldName} characters`);
        }

        /*
            Değeri OLMAYAN bir alan hiç gönderilmemeliydi; buraya kadar
            geldiyse çağıran `null` ya da boş dize yazmış demektir. İkisi de
            "bilmiyorum"un uydurulmuş karşılığıdır ve raporda gerçek bir
            değermiş gibi toplanır.
        */
        if (value === null || value === '') {
            problems.push(`field "${field}" carries an empty stand-in value; omit the field`);

            continue;
        }

        if (typeof value === 'string' && value.length > GA4_LIMITS.fieldValue) {
            problems.push(
                `field "${field}" value exceeds GA4's ${GA4_LIMITS.fieldValue} characters`,
            );
        }
    }

    return problems;
}

/**
 * Değeri olmayan alanları yükten DÜŞÜRÜR.
 *
 * `docs/112` §3.4'ün uygulanabilir hâli: çağıran "bilmiyorum"u `undefined`
 * ile söyler, alan yüke hiç girmez.
 */
export function withoutMissingFields(
    payload: Record<string, string | number | boolean | undefined>,
): Record<string, string | number | boolean> {
    const kept: Record<string, string | number | boolean> = {};

    for (const [field, value] of Object.entries(payload)) {
        if (value !== undefined) {
            kept[field] = value;
        }
    }

    return kept;
}

/**
 * Bayt → kova (`docs/112` §4.2).
 *
 * Ham bayt GA4'te yüksek kardinalite üretir ve kimse "7.318.402 bayt" diye
 * bir rapor okumaz; okunabilir soru "kaç yükleme büyük dosya yüzünden
 * takılıyor?"dur ve onu kova cevaplar.
 */
export function sizeBucket(bytes: number): '<1mb' | '1-5mb' | '5-15mb' | '15mb+' {
    const megabytes = bytes / 1_048_576;

    if (megabytes < 1) return '<1mb';
    if (megabytes < 5) return '1-5mb';
    if (megabytes < 15) return '5-15mb';

    return '15mb+';
}

/* ────────────────────────────────────────────────────────────────────────
   `minutes_since_signup` — Time to First QR'ın payda tarafı
   ──────────────────────────────────────────────────────────────────────── */

/**
 * Kayıt anı SAAT olarak değil, SÜRE olarak taşınır.
 *
 * Sunucu `GET /api/user` yanıtında hesabın kaç DAKİKALIK olduğunu söyler
 * (`signedUpMinutesAgo`), kayıt zaman damgasını değil. Sebep: iki zaman
 * damgasının farkı, damgaların aynı saate göre okunmasını gerektirir —
 * ama tarayıcı saati kullanıcının kendi ayarıdır ve düzenli olarak yanlıştır
 * (yanlış saat dilimi, elle geri alınmış saat, uykudan yeni uyanmış bir
 * dizüstü). Yanlış saatli tek bir cihaz "-180 dakikada yayınladı" gibi bir
 * satır üretir ve ortalamayı sessizce bozar.
 *
 * Burada iki terim de SÜREdir: sunucunun kendi saatinde ölçtüğü hesap yaşı,
 * artı sayfanın açık kaldığı süre. İkincisi `performance.now()` ile ölçülür;
 * o monoton bir sayaçtır ve kullanıcı saatini değiştirse bile geri gitmez.
 * Böylece sonuç istemci saatinden TAMAMEN bağımsız olur.
 */
let signupAgeAnchor: { minutes: number; monotonic: number } | null = null;

export function setSignupAgeMinutes(minutes: number): void {
    // Sunucu bu alanı hiç göndermediyse (eski gövde) çapa kurulmaz ve olay
    // alanı taşımaz — uydurulmuş bir sayı, eksik bir alandan çok daha kötüdür.
    if (!Number.isFinite(minutes) || minutes < 0) {
        return;
    }

    signupAgeAnchor = { minutes, monotonic: performance.now() };
}

/** Çapa kurulmadıysa `undefined` — ve o alan olaya hiç girmez. */
export function minutesSinceSignup(): number | undefined {
    if (signupAgeAnchor === null) {
        return undefined;
    }

    const elapsed = (performance.now() - signupAgeAnchor.monotonic) / 60_000;

    return Math.round(signupAgeAnchor.minutes + elapsed);
}

/** Oturum kapanışı ve testler için. */
export function resetSignupAge(): void {
    signupAgeAnchor = null;
}
