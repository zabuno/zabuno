/**
 * HAREKET KANCASI — kurumsal sayfa gövdelerinin TEK betiği.
 *
 * ═══ NE YAPAR, NE YAPMAZ ═══
 *
 * YAPAR: kök öğeye `data-motion="on"` yazar; `animation-timeline`
 * desteklemeyen tarayıcılarda giriş animasyonunu bir IntersectionObserver
 * ile tetikler.
 *
 * YAPMAZ: hiçbir animasyonu kendisi yürütmez, hiçbir `scroll` olayı
 * dinlemez, hiçbir `requestAnimationFrame` döngüsü açmaz, DOM'a bir öğe
 * eklemez, bir kütüphane yüklemez. Hareketin tamamı CSS'tedir
 * (`resources/css/site-motion.css`) ve tarayıcının bileşim iş parçacığında
 * koşar.
 *
 * Bir `scroll` dinleyicisi ana iş parçacığında çalışır: zayıf bir telefonda
 * her kaydırma karesinde JavaScript koşturmak, dokunmanın kendisini
 * geciktirir. Ölçülebilir sonucu, sahibin hedef kitlesinin — mutfaktan,
 * zayıf şebekeyle bakan restoran sahibinin — parmağının altında hissettiği
 * takılmadır. Bu dosya o dinleyiciyi hiç kurmaz.
 *
 * ═══ NEDEN GSAP DEĞİL — ÖLÇÜLDÜ (2026-09-08) ═══
 *
 *   gsap 3.15.0 çekirdek      72.927 bayt ham · 28.302 bayt gzip
 *   ScrollTrigger             44.575 bayt ham · 17.961 bayt gzip
 *   TOPLAM                                     46.263 bayt gzip
 *
 * Kurumsal sitenin BÜTÜN CSS'i 27.702 bayt gzip ve bugün SIFIR bayt
 * JavaScript iniyor. GSAP, sayfanın tamamından ağır bir betik demekti ve
 * karşılığında verdiği şey — kaydırmaya bağlı hareket — tarayıcıda zaten
 * var (`animation-timeline`). `docs/118` E5 GSAP'ı "birinci sıraya"
 * koyuyordu; o sıralama React'e karşı yazılmıştı ve gerekçesi buydu:
 * *"sıfır-React bir yüzeye ikinci bir bileşen sistemi sokmadan"*. Aynı
 * gerekçe, tarayıcının kendi zaman çizelgesi varken bir animasyon
 * kütüphanesi indirmeye karşı da geçerlidir.
 *
 * ═══ HAREKET KAPISINI BU DOSYA AÇMAZ ═══
 *
 * `data-motion="on"` tek başına hiçbir şeyi hareket ettirmez: CSS'teki her
 * kural ayrıca `@media (prefers-reduced-motion: no-preference)` içindedir.
 * Yani azaltılmış hareket isteyen bir ziyaretçide, bu betik özniteliği
 * yazsa BİLE hiçbir sahne kuralı doğmaz. Kapı tek yönlüdür ve CSS'tedir
 * (`docs/136` §7.3).
 *
 * Buna rağmen betik tercihi ayrıca okur ve `reduce` diyene özniteliği hiç
 * yazmaz. Sebep gözlemlenebilirlik: kök öğede `data-motion` yoksa, sayfayı
 * inceleyen biri "bu ziyaretçi hareket istemiyor" bilgisini DOM'da görür.
 *
 * Requirement ID: HOME-SCENE-02.
 */

const root = document.documentElement;

/*
    TERCİH ÖNCE OKUNUR. `matchMedia` desteklenmeyen bir ortamda (çok eski
    bir tarayıcı, bazı test koşucuları) `undefined` döner; o durumda hareket
    AÇILMAZ. Bilinmeyen bir tercih, "istendi" diye okunmaz.
*/
const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)');

if (reduced && !reduced.matches) {
    root.setAttribute('data-motion', 'on');

    /*
        TERCİH SONRADAN DEĞİŞEBİLİR. İşletim sisteminde "hareketi azalt"
        açan bir ziyaretçi, sayfayı yenilemeden de sakinleşmeli. CSS
        kuralları zaten `no-preference` sorgusunun içinde olduğu için
        hareket o an durur; öznitelik de DOM'da doğru kalsın diye
        güncellenir.
    */
    reduced.addEventListener('change', (event) => {
        if (event.matches) {
            root.removeAttribute('data-motion');
        } else {
            root.setAttribute('data-motion', 'on');
        }
    });

    /*
        GERİ DÜŞÜŞ — yalnız `animation-timeline` YOKSA.

        Destekleyen tarayıcıda (Chrome 115+, Safari 26+) giriş animasyonu
        kaydırmaya bağlıdır ve bu gözlemciye hiç gerek yoktur; kurmak, aynı
        öğeyi iki mekanizmayla oynatmak olurdu.

        Desteklemeyen tarayıcıda (bugün Firefox) gözlemci `data-seen="true"`
        yazar ve CSS geçişi orada tanımlıdır. Gözlemci her öğe için BİR KEZ
        çalışır ve hemen bırakır: sayfa boyunca canlı kalan bir dinleyici
        yoktur.
    */
    const hasScrollTimeline =
        typeof CSS !== 'undefined' &&
        typeof CSS.supports === 'function' &&
        CSS.supports('animation-timeline: view()');

    if (!hasScrollTimeline) {
        const targets = document.querySelectorAll<HTMLElement>('.site-reveal');

        if (targets.length > 0 && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver(
                (entries) => {
                    for (const entry of entries) {
                        if (!entry.isIntersecting) {
                            continue;
                        }

                        (entry.target as HTMLElement).dataset.seen = 'true';
                        observer.unobserve(entry.target);
                    }
                },
                /* Öğe görüntü alanına GİRERKEN başlar, tam ortalanınca
                   değil: hareket okumaya yetişmeli, okumayı beklememeli. */
                { rootMargin: '0px 0px -10% 0px' },
            );

            for (const target of targets) {
                observer.observe(target);
            }
        } else {
            /*
                Ne zaman çizelgesi var ne gözlemci: hiçbir öğe saydam
                kalmamalı. CSS'teki başlangıç durumu `data-motion="on"`e
                bağlı olduğu için, özniteliği geri almak sayfayı eksiksiz
                hâline döndürür — "yarım animasyon" diye bir durum yok.
            */
            root.removeAttribute('data-motion');
        }
    }
}
