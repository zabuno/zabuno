/**
 * BOŞ KUYRUK NEDEN BOŞ — kararın TEK yeri (`docs/115` Y1/Y3, `docs/153` §4).
 *
 * Bu bir biçimlendirme yardımcısı değil, bir ÜRÜN kararıdır: aynı boş liste
 * üç ayrı şey anlatabilir ve üçünün çıkış yolu farklıdır. Sessiz bir akşam
 * beklemeyi, kapalı bir şalter Ayarlar'ı, eksik bir plan hakkı ise sahibi
 * gerektirir.
 *
 * Karar bu yüzden ÇİZİMDEN ayrı durur ve iki giriş kipi (dokunma kuyruğu ve
 * masaüstü kuyruğu) aynı işlevi çağırır. Her yüzey kendi başına karar
 * verseydi, aynı akşam telefonda "bugün sipariş yok", masaüstünde "planında
 * yok" yazabilirdi — ve sahip hangisinin doğru olduğunu bilemezdi. Ayrışan
 * yalnız SUNUM olmalı (`docs/153` §4); bu dosya ayrışmayanı tutar.
 */

/** Plan hakkı YOKSA söylenir — liste dolu olsa BİLE. */
export function planGateVisible(planIncludesOrdering: boolean | null): boolean {
    /*
        Liste doluyken de söylenir ve bu bilinçli: ekrandaki siparişler hak
        düşmeden önce gelmiş olabilir, yani o listeye bir daha hiçbir şey
        EKLENMEYECEK. Yalnız boşken söylemek, en tehlikeli hâli — akşamın
        ortasında donmuş bir kuyruğu — sessiz bırakırdı.

        `null` (henüz bilinmiyor) kapı SAYILMAZ: olmayan bir kısıtı ekrana
        yazmak, bilinmeyeni "eksik" saymaktır.
    */
    return planIncludesOrdering === false;
}

/**
 * Boş listenin sebebi.
 *
 * - `none`: liste dolu, ya da sebebi plan kapısı zaten anlatıyor.
 * - `closed`: şalter kapalı — çıkış yolu Ayarlar.
 * - `quiet`: gerçekten sessiz bir akşam — çıkış yolu yok, beklenir.
 */
export type QueueEmptyReason = 'none' | 'closed' | 'quiet';

export function queueEmptyReason(
    orderCount: number,
    acceptsOrders: boolean | null,
    planIncludesOrdering: boolean | null,
): QueueEmptyReason {
    if (orderCount > 0) {
        return 'none';
    }

    /*
        Plan kapısı zaten ekranda: aynı boşluğu ikinci kez anlatmak,
        kullanıcıya hangisinin gerçek sebep olduğunu sordururdu.
    */
    if (planGateVisible(planIncludesOrdering)) {
        return 'none';
    }

    /*
        Şalter kapalıyken "bugün sipariş yok" demek, sahibi bütün akşam
        bekletir: gelmiyor değil, GELEMİYOR. `null` yine kapı sayılmaz.
    */
    if (acceptsOrders === false) {
        return 'closed';
    }

    return 'quiet';
}
