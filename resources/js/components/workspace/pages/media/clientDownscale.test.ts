import { describe, expect, it } from 'vitest';

import { canCropInto, parseAspect } from './cropGeometry';
import {
    DEFAULT_MAX_EDGE,
    downscaleOutputType,
    measuredSaving,
    planDownscale,
} from './clientDownscale';

/**
 * İSTEMCİDE KÜÇÜLTME — karar mantığının donduğu yer (kanonik kaynak:
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, "Yükle" ekranı,
 * 2. adım "Telefonda küçültüldü").
 *
 * Bugünkü davranış: telefonla çekilen 8 MB'lık bir fotoğraf olduğu gibi ağa
 * çıkıyor. Kebapçı mobil veriyle yükleme yapıyor, kota o 8 MB'ı sayıyor ve
 * sunucu aynı fotoğrafı zaten küçültüyor — yani ağdan geçen fazlalık hiçbir
 * işe yaramıyor.
 *
 * Kaynak bunu yüklemeden ÖNCE tarayıcıda çözmeyi şart koşuyor. Bu aynı
 * zamanda bir GÜVENLİK kararıdır: dosya kullanıcının kendi makinesinde
 * küçülür, taranmamış hâliyle sunucuya gidip oradan geri servis edilmez.
 *
 * Karar mantığı DOM'suz ve saf tutuluyor çünkü asıl kural burada:
 * **küçültme, slotun en küçük ölçüsünün ALTINA inemez.** Kırpma piksel
 * eklemez; 1200 × 400 isteyen bir slota 900 piksellik bir kaynak
 * gönderildiğinde sunucu onu reddeder ve kullanıcı emeğini boşa harcamış
 * olur. Bu kuralın kendisi `cropGeometry` içinde zaten yaşıyor
 * (`canCropInto` / `maxZoomFor`); burada TEKRARLANMAZ, kullanılır.
 */
describe('planDownscale — uzun kenar hedefi', () => {
    it('büyük bir telefon fotoğrafını uzun kenara indirir', () => {
        /*
            12 megapiksellik bir telefon karesi. Menüdeki bir yemek
            fotoğrafı için 4000 piksel genişlik hiçbir yerde kullanılmıyor;
            uzun kenar 2560'a inince dosya kabaca dörtte birine düşer.
        */
        const plan = planDownscale({
            source: { width: 4000, height: 3000 },
            minimum: { width: 0, height: 0 },
            aspect: null,
            maxEdge: DEFAULT_MAX_EDGE,
        });

        expect(plan.apply).toBe(true);
        expect(plan.target).toEqual({ width: 2560, height: 1920 });
        expect(plan.limitedBy).toBe('longEdge');
    });

    it('zaten küçük olan kaynağa dokunmaz', () => {
        /*
            Küçültme BÜYÜTME değildir. Hedef kaynaktan büyük çıkarsa yapılacak
            şey yoktur: yeniden kodlamak dosyayı bozar, çoğu zaman da
            büyütür.
        */
        const plan = planDownscale({
            source: { width: 1200, height: 900 },
            minimum: { width: 0, height: 0 },
            aspect: null,
            maxEdge: DEFAULT_MAX_EDGE,
        });

        expect(plan.apply).toBe(false);
        expect(plan.target).toEqual({ width: 1200, height: 900 });
        expect(plan.limitedBy).toBe('source');
    });
});

describe('planDownscale — slotun en küçük ölçüsü taban', () => {
    it('oransız slotta hedef, en küçük ölçünün altına inmez', () => {
        /*
            Kullanıcı uzun kenarı 1280'e çekiyor ama slot 2000 × 2000
            istiyor. 1280'e inmek dosyayı kullanılamaz yapardı — üstelik
            kullanıcı bunu ancak sunucu reddettikten sonra öğrenirdi.
        */
        const source = { width: 4000, height: 3000 };
        const minimum = { width: 2000, height: 2000 };

        const plan = planDownscale({ source, minimum, aspect: null, maxEdge: 1280 });

        expect(plan.limitedBy).toBe('slotMinimum');
        expect(plan.target.height).toBeGreaterThanOrEqual(minimum.height);
        expect(canCropInto(plan.target, minimum, null)).toBe(true);
    });

    it('oranlı slotta taban ORANDAN SONRA hesaplanır', () => {
        /*
            Sessiz deliğin ta kendisi: 4000 × 3000 bir fotoğraf, 1200 × 400
            isteyen 3:1 bir slot için her iki kenarda da bol görünür. Ama 3:1
            çerçeve 4000 × 1333 olur ve küçültme oranı yüksekliği önce
            tüketir. Taban, kenarlardan değil ÇERÇEVEDEN hesaplanmalı.
        */
        const source = { width: 4000, height: 3000 };
        const minimum = { width: 1200, height: 400 };
        const ratio = parseAspect('3:1');

        const plan = planDownscale({ source, minimum, aspect: '3:1', maxEdge: 1000 });

        expect(plan.limitedBy).toBe('slotMinimum');
        expect(canCropInto(plan.target, minimum, ratio)).toBe(true);
        // Uzun kenar 1000 istenmişti; taban onu 1200'e yükseltti.
        expect(plan.target.width).toBeGreaterThan(1000);
    });

    it('taban gerekmiyorsa uzun kenar kazanır ve sonuç yine kırpılabilir', () => {
        const source = { width: 4000, height: 3000 };
        const minimum = { width: 1200, height: 400 };
        const ratio = parseAspect('3:1');

        const plan = planDownscale({ source, minimum, aspect: '3:1', maxEdge: 1280 });

        expect(plan.limitedBy).toBe('longEdge');
        expect(plan.target).toEqual({ width: 1280, height: 960 });
        expect(canCropInto(plan.target, minimum, ratio)).toBe(true);
    });

    it('en küçük ölçü İSTEMEYEN slot küçültmeyi kısıtlamaz', () => {
        /*
            `maxZoomFor` kısıtsız slotta 8 döndürür — bu bir YAKINLAŞTIRMA
            ürün kararıdır ("birkaç piksellik çerçeve anlamsızdır") ve
            küçültmeye taşınmaz. Taşınsaydı 12000 piksellik bir tarayıcı
            çıktısı 1500'ün altına indirilemezdi.
        */
        const plan = planDownscale({
            source: { width: 12000, height: 9000 },
            minimum: { width: 0, height: 0 },
            aspect: null,
            maxEdge: 1280,
        });

        expect(plan.target).toEqual({ width: 1280, height: 960 });
    });
});

describe('measuredSaving — kazanç ölçülür, tahmin edilmez', () => {
    it('iki gerçek bayt arasındaki farkı verir', () => {
        const saving = measuredSaving(8_000_000, 1_200_000);

        expect(saving).not.toBeNull();
        expect(saving?.bytes).toBe(6_800_000);
        expect(saving?.percent).toBe(85);
    });

    it('kazanç yoksa kazanç UYDURMAZ', () => {
        /*
            Yeniden kodlama bazen büyütür (küçük bir PNG'yi JPEG'e çevirmek
            gibi). Ekranda "%0 küçüldü" yazmak bile yanlış olurdu: o durumda
            küçültme YAPILMAMIŞ olmalı ve ekran bunu hiç iddia etmemeli.
        */
        expect(measuredSaving(500_000, 500_000)).toBeNull();
        expect(measuredSaving(500_000, 640_000)).toBeNull();
        expect(measuredSaving(0, 0)).toBeNull();
    });
});

describe('downscaleOutputType', () => {
    it('HEIC/HEIF telefonda JPEG olur', () => {
        // Kaynak: "HEIC ve HEIF telefonda JPEG'e çevrilir." iPhone karesi
        // aksi hâlde çoğu tarayıcıda hiç açılmaz.
        expect(downscaleOutputType('image/heic')).toBe('image/jpeg');
        expect(downscaleOutputType('image/heif')).toBe('image/jpeg');
    });

    it('PNG saydamlığını korur', () => {
        // INV-07: bir logoyu JPEG'e çevirmek saydamlığı düz beyaza gömer.
        expect(downscaleOutputType('image/png')).toBe('image/png');
    });

    it('bilinmeyen tür JPEG kabul edilir', () => {
        expect(downscaleOutputType('')).toBe('image/jpeg');
        expect(downscaleOutputType('image/jpeg')).toBe('image/jpeg');
    });
});
