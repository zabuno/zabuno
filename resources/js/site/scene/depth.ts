import type { SceneEffect, SceneFrame, SceneTier } from './contract';

/**
 * DERİNLİK — parallax düzlemleri ve bölüm ilerlemesi.
 *
 * ── NEDEN KAREDE ÖLÇÜM YOK ──
 *
 * `getBoundingClientRect()` tarayıcıyı düzeni YENİDEN HESAPLAMAYA zorlar.
 * Her karede, her katman için çağrıldığında ekranda hiçbir şey değişmese
 * bile kare süresi katman sayısıyla birlikte büyür. Burada ölçüm yalnız
 * `measure()` içinde — yani açılışta, boyut değiştiğinde ve derece
 * düştüğünde. Kare içinde yapılan tek şey aritmetik ve bir CSS değişkeni
 * yazmak.
 *
 * ── NEDEN CSS DEĞİŞKENİ, DOĞRUDAN `transform` DEĞİL ──
 *
 * Mesafe betikte değil `site-scene.css`te durur (`--motion-depth-*`). Betik
 * yalnız −1…1 arası bir İLERLEME yazar; o ilerlemenin kaç piksel ettiğine
 * tasarım katmanı karar verir. Böylece üç düzlemin hızı tek bir jeton
 * kümesinden okunur ve bir gün "parallax çok sert" denildiğinde
 * değiştirilecek yer tektir.
 */

interface Plane {
    readonly element: HTMLElement;
    /** Düzlemin ait olduğu bandın belge içindeki üst kenarı. */
    top: number;
    height: number;
}

export function createParallax(root: ParentNode): SceneEffect | null {
    const elements = Array.from(root.querySelectorAll<HTMLElement>('[data-plane]'));

    if (elements.length === 0) {
        return null;
    }

    const planes: Plane[] = elements.map((element) => ({ element, top: 0, height: 1 }));
    let tier: SceneTier = 'full';

    return {
        measure(nextTier) {
            tier = nextTier;

            for (const plane of planes) {
                /*
                    Ölçülen kutu düzlemin KENDİSİ değil, içinde yaşadığı
                    sahne. Düzlem mutlak konumlandırılmıştır ve kendi kutusu
                    sahnenin kutusudur; ama bir gün sahne içinde daha küçük
                    bir katman kullanılırsa ölçüm yine doğru kalsın diye
                    sahne açıkça aranıyor.
                */
                const stage = plane.element.closest('.site-stage') ?? plane.element;
                const rect = stage.getBoundingClientRect();
                plane.top = rect.top + window.scrollY;
                plane.height = Math.max(rect.height, 1);
            }
        },
        frame(scene: SceneFrame) {
            if (tier === 'minimal') {
                return;
            }

            for (const plane of planes) {
                /*
                    İLERLEME: bant görüntü alanının ortasındayken 0, üstten
                    çıkarken −1, alttan girerken +1. Sıfır noktasının ORTA
                    olması önemli — bant ekranın ortasındayken hiçbir katman
                    ötelenmez, yani "doğru" kompozisyon okunduğu andır.
                */
                const center = plane.top + plane.height / 2;
                const viewCenter = scene.scroll + scene.height / 2;
                const span = (plane.height + scene.height) / 2;
                const shift = Math.max(Math.min((viewCenter - center) / span, 1), -1);

                plane.element.style.setProperty('--scene-shift', shift.toFixed(4));
            }
        },
        destroy() {
            for (const plane of planes) {
                plane.element.style.removeProperty('--scene-shift');
            }
        },
    };
}

/**
 * BÖLÜM İLERLEMESİ — bölümlerin birbirine dönüşmesi.
 *
 * `--scene-progress` bir bölümün görüntü alanından geçişini 0 → 1 olarak
 * yazar. Sahne bandının ışığı, ufkun büyümesi ve derin bandın doygunluğu
 * aynı sayıyı okur; yani üç ayrı efekt DEĞİL, tek bir geçişin üç yüzü.
 */
export function createProgress(root: ParentNode): SceneEffect | null {
    const elements = Array.from(root.querySelectorAll<HTMLElement>('[data-scene-progress]'));

    if (elements.length === 0) {
        return null;
    }

    // Keep section geometry, but scope changing values to the effects that read them.
    // A nested section owns its own consumers and must not receive the outer value.
    const boxes = elements.map((element) => ({
        element,
        consumers: Array.from(
            element.querySelectorAll<HTMLElement>('.scene-progress-glow, .scene-morph'),
        ).filter((consumer) => consumer.closest('[data-scene-progress]') === element),
        top: 0,
        height: 1,
    }));

    return {
        measure() {
            for (const box of boxes) {
                const rect = box.element.getBoundingClientRect();
                box.top = rect.top + window.scrollY;
                box.height = Math.max(rect.height, 1);
            }
        },
        frame(scene: SceneFrame) {
            for (const box of boxes) {
                const start = box.top - scene.height;
                const span = box.height + scene.height;
                const progress = Math.max(Math.min((scene.scroll - start) / span, 1), 0);

                for (const consumer of box.consumers) {
                    consumer.style.setProperty('--scene-progress', progress.toFixed(4));
                }
            }
        },
        destroy() {
            for (const box of boxes) {
                for (const consumer of box.consumers) {
                    consumer.style.removeProperty('--scene-progress');
                }
            }
        },
    };
}

/**
 * GİRİŞ — bölüm görünür alana girdiğinde doğar.
 *
 * Bir efekt DEĞİL, bir gözlemci: kare almaz. `IntersectionObserver`
 * kaydırmayı dinlemeden, düzen ölçmeden ve her karede iş yapmadan aynı
 * soruyu cevaplar.
 *
 * ── TEK YÖNLÜ ──
 *
 * Bir kez doğan öğe geri kaybolmaz. Yukarı kaydıran ziyaretçi, okuduğu
 * içeriğin tekrar silinmesini görmemeli; "yeniden animasyon" bir efekt
 * değil, bir engeldir.
 *
 * ── İLK EKRAN GİRİŞ ANİMASYONUNA GİRMEZ ──
 *
 * Gizleme kuralı CSS'te `:root[data-motion='on']` altında ve o öznitelik
 * `runtime.start()` içinde, BU FONKSİYONDAN SONRA yazılır. Aradaki sıra
 * kasıtlıdır: burada ilk ekranda duran her öğe daha öznitelik yazılmadan
 * `data-revealed="true"` alır, yani hiçbir kare boyunca saydam olmaz ve
 * hiçbir gecikme kuyruğuna girmez.
 *
 * Gerekçe ölçülmüş bir kusurdan değil, bir RİSKTEN geliyor ve Döngü 2'nin
 * kapısı onu ölçüyor (`scene-perf-gate --first-screen`): giriş animasyonu
 * `IntersectionObserver`in geri çağrımını beklerdi, o geri çağrım bir sonraki
 * karede gelirdi, üstüne `--scene-order` gecikmesi binerdi. Yavaş bir cihazda
 * ya da geç yüklenen bir betikte bu zincirin toplamı, ziyaretçinin karar
 * verdiği ilk saniyeye düşer. GÖSTERİ, DÖNÜŞÜM EYLEMİNİ GECİKTİREMEZ:
 * kahramandaki düğme bir animasyonun kuyruğunda bekleyemez.
 *
 * Aşağıdaki öğeler (ilk ekranın altındakiler) eskisi gibi kaydırınca doğar.
 */
export function observeReveals(root: ParentNode, view: Window = window): () => void {
    const all = Array.from(root.querySelectorAll<HTMLElement>('.scene-reveal'));

    /*
        TEK BİR DÜZEN OKUMASI, AÇILIŞTA.

        `getBoundingClientRect()` düzeni zorlar ve bu dosyanın kendi kuralı
        onu KARE İÇİNDE yasaklıyor. Burası kare değil: bu okuma sahne
        başlamadan önce, bir kez yapılır.
    */
    const fold = view.innerHeight || 0;
    const elements: HTMLElement[] = [];

    for (const element of all) {
        if (element.getBoundingClientRect().top < fold) {
            element.dataset.revealed = 'true';
            continue;
        }

        elements.push(element);
    }

    if (elements.length === 0 || typeof IntersectionObserver !== 'function') {
        for (const element of elements) {
            element.dataset.revealed = 'true';
        }

        return () => {};
    }

    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) {
                    continue;
                }

                (entry.target as HTMLElement).dataset.revealed = 'true';
                observer.unobserve(entry.target);
            }
        },
        /* Öğe tam görünmeden biraz önce doğar: giriş, okunmaya başladığında
           bitmiş olur. */
        { rootMargin: '0px 0px -12% 0px' },
    );

    for (const element of elements) {
        observer.observe(element);
    }

    return () => observer.disconnect();
}
