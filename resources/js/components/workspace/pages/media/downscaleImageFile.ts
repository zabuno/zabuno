import {
    downscaleOutputType,
    planDownscale,
    type DownscalePlan,
    type DownscaleRequest,
} from './clientDownscale';

/**
 * Küçültmenin TARAYICI tarafı — karar `clientDownscale`'de, iş burada.
 *
 * Ayrım kasıtlı: geometri ve "en küçük ölçünün altına inme" kuralı DOM'suz
 * saf işlevlerde donduruldu ve testlerle korunuyor. Burada yalnız o kararın
 * uygulanması var — bir tuval, bir `drawImage` ve bir `toBlob`.
 *
 * Kütüphane kullanılmadı. Tarayıcının kendi tuvali bu işi zaten yapıyor ve
 * bir küçültme kütüphanesi eklemek, ağdan inen paketi büyütüp kazandığımız
 * baytları geri verirdi.
 */

/** Kaynağın çıktıya taşınmayan uzantısı düzeltilir: sunucu biçimi UZANTIDAN okur. */
function renameForType(name: string, type: 'image/png' | 'image/jpeg'): string {
    const stem = name.replace(/\.[a-z0-9]+$/i, '');

    return `${stem}${type === 'image/png' ? '.png' : '.jpg'}`;
}

export type DownscaleSource = {
    blob: Blob;
    name: string;
    width: number;
    height: number;
};

export type DownscaleOutcome = {
    file: File;
    width: number;
    height: number;
    plan: DownscalePlan;
};

/**
 * Kaynağı planın hedef ölçüsüne indirir.
 *
 * `null` dönmesi bir HATA DEĞİLDİR; "yapılacak bir şey yok" demektir ve
 * çağıran o durumda dosyayı olduğu gibi gönderir:
 *
 *   - plan küçültme öngörmüyorsa (kaynak zaten küçük, ya da slotun tabanı
 *     izin vermiyor),
 *   - tarayıcı nesne adresi veya tuval sağlamıyorsa (jsdom, eski tarayıcı),
 *   - üretilen dosya kaynaktan BÜYÜKSE. Bu gerçekten olur: küçük bir PNG'yi
 *     yeniden kodlamak çoğu zaman büyütür. Kazanç yoksa kazanç iddia
 *     edilmez.
 */
export async function downscaleImageFile(
    source: DownscaleSource,
    request: Omit<DownscaleRequest, 'source'>,
): Promise<DownscaleOutcome | null> {
    const plan = planDownscale({
        source: { width: source.width, height: source.height },
        ...request,
    });

    if (!plan.apply) return null;

    if (typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') {
        return null;
    }

    const objectUrl = URL.createObjectURL(source.blob);

    try {
        const image = await loadImage(objectUrl);

        if (image === null) return null;

        const canvas = document.createElement('canvas');
        canvas.width = plan.target.width;
        canvas.height = plan.target.height;

        const context = canvas.getContext('2d');

        if (context === null) return null;

        context.drawImage(image, 0, 0, plan.target.width, plan.target.height);

        const type = downscaleOutputType(source.blob.type);
        const blob = await toBlob(canvas, type);

        // Kazanç yoksa dosya değiştirilmez: yeniden kodlanmış ama büyümüş bir
        // dosya, hem kotayı hem görüntü kalitesini birlikte kaybettirir.
        if (blob === null || blob.size >= source.blob.size) return null;

        return {
            file: new File([blob], renameForType(source.name, type), { type }),
            width: plan.target.width,
            height: plan.target.height,
            plan,
        };
    } finally {
        if (typeof URL.revokeObjectURL === 'function') {
            URL.revokeObjectURL(objectUrl);
        }
    }
}

function loadImage(objectUrl: string): Promise<HTMLImageElement | null> {
    return new Promise((resolve) => {
        const image = new Image();

        image.onload = () => resolve(image);
        // Çözülemeyen dosya bir çökme sebebi değil: sunucu ona yine de kendi
        // cevabını verir ve o cevap bizimkinden daha yetkilidir.
        image.onerror = () => resolve(null);
        image.src = objectUrl;
    });
}

function toBlob(canvas: HTMLCanvasElement, type: string): Promise<Blob | null> {
    return new Promise((resolve) => {
        if (typeof canvas.toBlob !== 'function') {
            resolve(null);

            return;
        }

        // Kalite 0.82 — kaynağın kendi varsayılanı ("Yemek fotoğrafları için
        // doğru aralık"). Üstü göz farkı üretmeden dosyayı iki katına çıkarır.
        canvas.toBlob((blob) => resolve(blob), type, 0.82);
    });
}
