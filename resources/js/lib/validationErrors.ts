import { trackEvent } from './analytics';

/**
 * Sunucunun doğrulama yanıtını okur.
 *
 * Neden ortak bir yer: on bir formdan onu, 422 yanıtının GÖVDESİNİ hiç
 * okumadan sabit bir "bir şeyler ters gitti" cümlesi gösteriyordu. Sunucu
 * her seferinde tam olarak neyin yanlış olduğunu söylüyordu — "The timezone
 * must be a valid IANA timezone identifier", "The email has already been
 * taken" — ve o cümle ağdan geçip çöpe gidiyordu.
 *
 * Sonuç bir döngüdür: kullanıcı neyi düzelteceğini bilmediği için aynı
 * veriyi tekrar gönderir, aynı cevabı alır. Kayıt ekranında bu, ürünle ilk
 * karşılaşmanın son karşılaşma olması demek.
 *
 * Laravel'in doğrulama yanıtı `{ message, errors: { alan: [mesaj, …] } }`
 * biçimindedir; Fortify de aynısını döndürür.
 */
export type ValidationFailure = {
    /** Özet mesaj. Alan hatalarının yerini TUTMAZ; ekran okuyucunun gönderim
     *  sonrası bir şey duyabilmesi için var. */
    message: string | null;
    /** Alan adı → ilk mesaj. */
    fields: Record<string, string>;
};

/**
 * @param fallbackMessage Gövde okunamazsa gösterilecek metin. Çağıran verir,
 *                        çünkü hangi işlemin başarısız olduğunu o bilir.
 * @param form Formun ölçüm adı (`register`, `brand_create`, …). Verilirse her
 *             hatalı alan için bir `form_validation_failed` olayı basılır.
 *             İsteğe bağlıdır çünkü bu yardımcıyı panelin dışındaki akışlar da
 *             kullanır; adı olmayan bir olay ise GTM'de kırılımsız kalırdı.
 */
export async function readValidationFailure(
    response: Response,
    fallbackMessage: string,
    form?: string,
): Promise<ValidationFailure> {
    const failure = await parseValidationFailure(response, fallbackMessage);

    if (form !== undefined) {
        trackFormValidationFailed(form, failure.fields);
    }

    return failure;
}

/**
 * Hangi alan kaç kişiyi durduruyor (`docs/112` §4.3).
 *
 * ÖLÇÜM BURADA, on bir formun içinde değil: 422 yanıtının gövdesini okuyan
 * TEK yer burasıdır, dolayısıyla ölçüm de burada tutarlı kalır. Her forma tek
 * tek olay basılsaydı on birincisi unutulur ve o formun kaç kişiyi çıkardığı
 * hiç bilinmezdi.
 *
 * Alan ADI basılır, alan DEĞERİ değil. Kullanıcının yazdığı e-posta, telefon
 * ya da isim `dataLayer`'a giremez (`docs/112` §3.1); "hangi alanda takıldı"
 * sorusunu cevaplamak için alanın adı zaten yeter.
 *
 * `reason` alanı BİLEREK gönderilmez. Sunucu makine okunur bir gerekçe değil,
 * yerelleştirilmiş bir insan cümlesi döndürür ("The email has already been
 * taken" / "E-posta adresi zaten alınmış"). O cümleyi anahtar kelimeyle
 * kovalamak, dil değiştiğinde sessizce yanlış kovaya düşerdi — ve yanlış bir
 * kova, eksik bir alandan daha pahalıdır (`docs/112` §3.4).
 */
function trackFormValidationFailed(form: string, fields: Record<string, string>): void {
    for (const field of Object.keys(fields)) {
        trackEvent('form_validation_failed', { form, field });
    }
}

async function parseValidationFailure(
    response: Response,
    fallbackMessage: string,
): Promise<ValidationFailure> {
    try {
        const body = (await response.json()) as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        const entries = Object.entries(body.errors ?? {});

        return {
            message: body.message ?? fallbackMessage,
            fields: Object.fromEntries(
                entries.map(([field, messages]) => [field, messages[0] ?? '']),
            ),
        };
    } catch {
        // Gövde JSON değilse söylenecek özel bir şey yok. Uydurmak yerine
        // çağıranın verdiği metne düşüyoruz.
        return { message: fallbackMessage, fields: {} };
    }
}

/**
 * İlk hatalı alana odak taşır.
 *
 * Hata mesajını göstermek yeterli değil: uzun bir formda kullanıcı onu
 * aramak zorunda kalır. W3C form yönergeleri hatalı alanın tanımlanmasını
 * ve düzeltme yolunun gösterilmesini ister.
 *
 * @param order Alanların ekrandaki sırası; hangisinin "ilk" olduğunu
 *              belirler. Nesne anahtar sırası buna güvenilir bir cevap
 *              vermez.
 */
export function focusFirstInvalidField(
    fields: Record<string, string>,
    order: readonly string[],
): void {
    const firstInvalid = order.find((field) => fields[field]);

    if (!firstInvalid) return;

    const element = document.querySelector<HTMLElement>(`[name="${firstInvalid}"]`);
    // `preventScroll` YOK: kullanıcı hatayı görmeli, sayfa oraya kaymalı.
    element?.focus();
}

/**
 * Sunucunun REDDETTİĞİ bir isteği temsil eder.
 *
 * Neden ayrı bir tür: "sunucu şunu söyledi" ile "istek hiç ulaşmadı" aynı
 * şey değil, ama ikisi de `catch` bloğuna düşer. Ayırmadan, ağ kopmasında
 * kullanıcıya ham JavaScript metni ("Network failure", "Failed to fetch")
 * gösterilir — bu bir iç detaydır ve kimseye bir şey anlatmaz.
 *
 * Yalnız bu türün mesajı ekrana çıkar; diğer her hata çağıranın kendi
 * genel metnine düşer.
 */
export class ServerRejectedError extends Error {
    constructor(message: string) {
        super(message);
        this.name = 'ServerRejectedError';
    }
}
