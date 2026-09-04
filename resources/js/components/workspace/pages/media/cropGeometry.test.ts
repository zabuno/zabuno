import { describe, expect, it } from 'vitest';

import { canCropInto, cropRectFor, largestRectFor, maxZoomFor, parseAspect } from './cropGeometry';

describe('kırpma geometrisi', () => {
    it('slot oranını okur, tanımadığını reddeder', () => {
        expect(parseAspect('3:1')).toBe(3);
        expect(parseAspect('1.91:1')).toBeCloseTo(1.91, 2);
        expect(parseAspect('1:1')).toBe(1);
        expect(parseAspect(null)).toBeNull();
        expect(parseAspect('')).toBeNull();
        // Sıfıra bölme ve saçma girdi sessizce 0/Infinity üretmez.
        expect(parseAspect('3:0')).toBeNull();
        expect(parseAspect('kare')).toBeNull();
    });

    it('en geniş çerçeve kaynağın dışına taşmaz', () => {
        // Geniş kaynak, dar oran → yükseklik tamamı kullanılır.
        expect(largestRectFor({ width: 4000, height: 1000 }, 3)).toEqual({
            width: 3000,
            height: 1000,
        });

        // Dar kaynak, geniş oran → genişlik tamamı kullanılır.
        expect(largestRectFor({ width: 1200, height: 1200 }, 3)).toEqual({
            width: 1200,
            height: 400,
        });
    });

    /*
        KIRPMA PİKSEL EKLEMEZ.

        Bu, aracın hiç açılmaması gereken durumu tanımlar: 800×600 bir
        fotoğraf 1200×400 isteyen bir slota hiçbir çerçeveyle sığmaz.
        Kullanıcıya araç gösterip sonunda "olmadı" demek, emeği boşa
        harcatmaktır.
    */
    it('kaynak küçükse kırpma imkânsızdır', () => {
        expect(canCropInto({ width: 800, height: 600 }, { width: 1200, height: 400 }, 3)).toBe(
            false,
        );

        expect(canCropInto({ width: 2000, height: 800 }, { width: 1200, height: 400 }, 3)).toBe(
            true,
        );
    });

    /*
        SESSİZ TUZAK: kaynak her iki kenarda da yeterince büyük olabilir ve
        oran uygulandığında yine de yetmeyebilir. 1300×1300, 1200×400 için
        her iki kenarda da büyüktür; ama 3:1 uygulanınca 1300×433 olur —
        genişlik yeter, yükseklik ancak. 1300×410 olsaydı 3:1 çerçeve
        1230×410 olur ve yine geçerdi; 1210×1210 ise 3:1'de 1210×403 verir.
        Kontrol bu yüzden ORANDAN SONRA yapılır.
    */
    it('oran uygulandıktan sonra yetmiyorsa da imkânsızdır', () => {
        expect(canCropInto({ width: 1250, height: 1250 }, { width: 1200, height: 500 }, 3)).toBe(
            false,
        );
        expect(canCropInto({ width: 1250, height: 1250 }, { width: 1200, height: 400 }, 3)).toBe(
            true,
        );
    });

    it('yakınlaştırma çerçeveyi en küçük ölçünün altına indiremez', () => {
        const zoom = maxZoomFor({ width: 2400, height: 1200 }, { width: 1200, height: 400 }, 3);

        // En geniş çerçeve 2400×800; en küçük 1200×400 → en fazla 2 kat.
        expect(zoom).toBe(2);

        const frame = cropRectFor({ width: 2400, height: 1200 }, 3, zoom, { x: 0, y: 0 });
        expect(frame.width).toBe(1200);
        expect(frame.height).toBe(400);
    });

    it('en küçük ölçü istemeyen slot bile sınırsız yakınlaştırmaz', () => {
        // Birkaç piksellik bir çerçeve teknik olarak geçerli, ürün olarak
        // anlamsızdır.
        expect(maxZoomFor({ width: 4000, height: 4000 }, { width: 0, height: 0 }, 1)).toBe(8);
    });

    it('çerçeve kaynağın dışına taşmaz, uçlara dayanır', () => {
        const source = { width: 2400, height: 1200 };

        const centred = cropRectFor(source, 3, 2, { x: 0, y: 0 });
        expect(centred).toEqual({ x: 600, y: 400, width: 1200, height: 400 });

        const topStart = cropRectFor(source, 3, 2, { x: -1, y: -1 });
        expect(topStart).toEqual({ x: 0, y: 0, width: 1200, height: 400 });

        const bottomEnd = cropRectFor(source, 3, 2, { x: 1, y: 1 });
        expect(bottomEnd.x + bottomEnd.width).toBe(source.width);
        expect(bottomEnd.y + bottomEnd.height).toBe(source.height);

        // Aralık dışı bir kayma sessizce taşırmaz, uca dayanır.
        const beyond = cropRectFor(source, 3, 2, { x: 9, y: -9 });
        expect(beyond.x + beyond.width).toBe(source.width);
        expect(beyond.y).toBe(0);
    });

    it('oransız slotta çerçeve kaynağın tamamıdır', () => {
        const rect = cropRectFor({ width: 900, height: 700 }, null, 1, { x: 0, y: 0 });

        expect(rect).toEqual({ x: 0, y: 0, width: 900, height: 700 });
    });
});
