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
 */
export async function readValidationFailure(
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
