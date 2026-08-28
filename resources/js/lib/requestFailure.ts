/**
 * Bir başarısız yanıtın KULLANICI için ne anlama geldiği.
 *
 * Önceden her başarısızlık tek bir cümleye düşüyordu: "We could not create
 * your brand. Please try again." Kullanıcı bu cümleden şunların hiçbirini
 * öğrenemiyordu: yetkisi mi yok, aynı isimde kayıt mı var, bağlantı mı
 * koptu, sunucu mu hata verdi, yoksa işlem aslında başarılı olup cevabı mı
 * kayboldu.
 *
 * "Tekrar deneyin" bu durumların YALNIZ BİRİNDE doğru tavsiyedir. Yetki
 * yoksa tekrar denemek hiçbir zaman işe yaramaz; çakışma varsa veriyi
 * değiştirmek gerekir. Yanlış tavsiye, kullanıcıyı aynı yolu tekrar tekrar
 * denemeye ve sonunda vazgeçmeye götürür.
 */
export type RequestFailureKind =
    'validation' | 'permission' | 'conflict' | 'notFound' | 'server' | 'network';

export type RequestFailure = {
    kind: RequestFailureKind;
    /** Sunucunun izini sürebileceği kimlik, varsa. Uydurulmaz. */
    correlationId: string | null;
};

/** Yanıtın hangi arıza sınıfına ait olduğunu söyler. */
export function classifyResponse(response: Response): RequestFailure {
    const correlationId =
        response.headers.get('X-Request-Id') ?? response.headers.get('X-Correlation-Id');

    if (response.status === 422) {
        return { kind: 'validation', correlationId };
    }

    if (response.status === 403 || response.status === 401) {
        return { kind: 'permission', correlationId };
    }

    if (response.status === 409) {
        return { kind: 'conflict', correlationId };
    }

    if (response.status === 404) {
        return { kind: 'notFound', correlationId };
    }

    return { kind: 'server', correlationId };
}

/** İstek hiç kurulamadıysa. */
export function networkFailure(): RequestFailure {
    return { kind: 'network', correlationId: null };
}
