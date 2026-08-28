import { describe, expect, it } from 'vitest';
import { messageForFailure } from './failureMessage';
import { classifyResponse, networkFailure } from '../../../lib/requestFailure';

/**
 * Arıza sözlüğü — `docs/67`.
 *
 * Bu testin ölçtüğü şey tek bir cümle değil, cümlelerin AYRI olması: altı
 * arıza sınıfı altı farklı çıkış yolu gerektirir ve ikisi aynı metne düşerse
 * kullanıcıya yanlış tavsiye verilmiş olur.
 */
function response(status: number, headers: Record<string, string> = {}): Response {
    return { ok: false, status, headers: new Headers(headers) } as Response;
}

describe('arıza sözlüğü', () => {
    it('her arıza sınıfı için AYRI bir cümle verir', () => {
        const messages = [
            messageForFailure(classifyResponse(response(403))),
            messageForFailure(classifyResponse(response(409))),
            messageForFailure(classifyResponse(response(404))),
            messageForFailure(classifyResponse(response(500))),
            messageForFailure(networkFailure()),
        ];

        expect(new Set(messages).size).toBe(messages.length);
    });

    /**
     * "Tekrar deneyin" yalnız SUNUCU hatasında ve bağlantı kopmasında doğru
     * tavsiyedir. Yetki yoksa tekrar denemek hiçbir zaman işe yaramaz;
     * çakışma varsa veriyi değiştirmek gerekir.
     */
    it('tekrar denemeyi yalnız denemenin işe yarayabileceği yerde önerir', () => {
        expect(messageForFailure(classifyResponse(response(403)))).not.toMatch(/try again/i);
        expect(messageForFailure(classifyResponse(response(409)))).not.toMatch(/try again/i);
        expect(messageForFailure(classifyResponse(response(404)))).not.toMatch(/try again/i);

        expect(messageForFailure(classifyResponse(response(500)))).toMatch(/try again/i);
        expect(messageForFailure(networkFailure())).toMatch(/try again|still here/i);
    });

    /**
     * Yetki eksikliği kullanıcının kendi kendine çözebileceği bir şey
     * değildir; kimden isteneceği söylenmelidir.
     */
    it('yetki eksikliğinde kimden isteneceğini söyler', () => {
        expect(messageForFailure(classifyResponse(response(403)))).toMatch(/owner|manager/i);
    });

    /**
     * İzleme kimliği VARSA gösterilir, yoksa uydurulmaz: destek ekibinin
     * arayamayacağı bir kod, hiç kod olmamasından kötüdür.
     */
    it('izleme kimliğini yalnız sunucu verdiyse gösterir', () => {
        const withId = messageForFailure(
            classifyResponse(response(500, { 'X-Request-Id': 'req-8Q4M' })),
        );
        const withoutId = messageForFailure(classifyResponse(response(500)));

        expect(withId).toContain('req-8Q4M');
        expect(withoutId).not.toMatch(/reference/i);
    });

    /** Bağlantı kopmasında asıl korku veri kaybıdır ve cevaplanmalıdır. */
    it('bağlantı koptuğunda verinin durduğunu söyler', () => {
        expect(messageForFailure(networkFailure())).toMatch(/still here|nothing was lost/i);
    });
});
