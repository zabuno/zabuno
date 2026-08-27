/**
 * Ekranda çalışan şeyin, sunucunun sunduğu şey olup olmadığı — "Preview Truth".
 *
 * Bu modül bir sürüm göstergesi değildir. Belirli bir sessiz arıza sınıfını
 * görünür kılar: arayüz kusursuz görünürken YANLIŞ sürümü göstermesi.
 * Böyle bir durumda hiçbir hata oluşmaz, hiçbir kayıt düşmez; yalnızca
 * o tur boyunca yapılan her görsel değerlendirme geçersiz olur — ve bunu
 * kimse fark etmez.
 *
 * İki AYRI bayatlık türü vardır ve birbirinin yerine geçmez:
 *
 * 1. **Sürüm uyuşmazlığı** — sayfa ile paket farklı commit'lerden.
 *    Localhost'un başka bir worktree'den sunulması bunun tipik hâlidir.
 * 2. **Bayat derleme** — kaynak düzenlenmiş, `npm run build` çalışmamış.
 *    Commit oluşmadığı için SÜRÜMLER EŞİTTİR; birinci kontrol bunu
 *    yakalayamaz, ve geliştirmede daha sık olan da budur.
 *
 * İkisini tek bir "güncel mi" sorusuna indirgemek, ikisinden birini
 * kaçırmak demektir.
 */

export type BuildDivergence =
    | { kind: 'fresh' }
    | { kind: 'revision-mismatch'; served: string; running: string }
    | { kind: 'stale-build' };

function meta(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const element = document.querySelector(`meta[name="${name}"]`);
    const content = element?.getAttribute('content') ?? '';

    return content === '' ? null : content;
}

/**
 * Paketin içine gömülü sürüm.
 *
 * `typeof` kontrolü zorunlu: sabit yalnız Vite derlemesinde tanımlıdır.
 * Testte veya başka bir derleyicide doğrudan okumak ReferenceError fırlatır
 * — yani ölçüm aracının kendisi uygulamayı çökertirdi.
 */
export function runningRevision(): string | null {
    if (typeof __ZABUNO_BUILD_REVISION__ === 'undefined') {
        return null;
    }

    return __ZABUNO_BUILD_REVISION__ === '' ? null : __ZABUNO_BUILD_REVISION__;
}

export function servedRevision(): string | null {
    return meta('zabuno-build-revision');
}

export function isBannerEnabled(): boolean {
    return meta('zabuno-build-banner') === 'true';
}

/**
 * Geliştirme sunucusu (HMR) çalışıyor mu? — cevabı SUNUCU verir.
 *
 * Sıcak sunucu altında bayatlık kavramı YOKTUR: modüller her değişiklikte
 * kaynaktan yeniden üretilir, ortada "eski derleme" diye bir şey olamaz. Bu
 * ayrımı yapmayan bir kontrol, geliştiricinin en çok çalıştığı anda sürekli
 * yanlış alarm verirdi — ve sürekli yanlış alarm veren bir uyarı, kapatılan
 * bir uyarıdır. O andan itibaren gerçek ayrışmayı da göstermez.
 *
 * Burada `import.meta.hot` BİLEREK kullanılmıyor. Test koşucusunda da
 * tanımlıdır: bu dala bağlanan her kontrol testte sessizce "sıcak" sanılır ve
 * hiçbir zaman sınanmaz. Sunucu ise sıcak dosyanın varlığından kesin bilir,
 * ve o cevap bir meta etiketi olduğu için sınanabilir.
 */
function isHotDevServer(): boolean {
    return meta('zabuno-build-hot') === 'true';
}

export function detectDivergence(): BuildDivergence {
    if (isHotDevServer()) {
        return { kind: 'fresh' };
    }

    if (meta('zabuno-build-stale') === 'true') {
        return { kind: 'stale-build' };
    }

    const served = servedRevision();
    const running = runningRevision();

    // Taraflardan biri bilinmiyorsa karşılaştırma YAPILMAZ. Bilinmeyeni
    // "farklı" saymak her kurulumda alarm verirdi; "aynı" saymak ise
    // dedektörü sessizce işlevsiz bırakırdı. İkisi de yanlış; doğru cevap
    // bir iddiada bulunmamaktır.
    if (served === null || running === null || served === running) {
        return { kind: 'fresh' };
    }

    return { kind: 'revision-mismatch', served, running };
}

export function shortRevision(revision: string): string {
    return revision.slice(0, 7);
}
