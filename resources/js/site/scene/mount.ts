import { createField } from './field';
import { createParallax, createProgress, observeReveals } from './depth';
import { SceneRuntime, motionRequested } from './runtime';
import { bindTilt } from './tilt';

/**
 * SAHNEYİ KURAR — motorun TEK dış kapısı.
 *
 * ── NEDEN ÖZNİTELİKLE, İSİMLE DEĞİL ──
 *
 * Motor hangi SAYFADA olduğunu bilmez ve bilmemelidir. Bir sayfa sahneyi
 * `data-scene="field"` yazarak ister; motor onu bulur. Sayfa adlarını
 * betiğe yazmak, her yeni kurumsal sayfada bu dosyayı düzenlemek demekti —
 * ve düzenlenmeyi unutan ilk sayfa sessizce sahnesiz kalırdı.
 *
 * ── NEDEN BİR ŞEY BULAMAZSA HİÇ BAŞLAMIYOR ──
 *
 * Sahnesi olmayan bir sayfada (yasal metinler, yardım makalesi) döngü
 * açmak, hiç kimsenin görmediği bir iş için pil harcamaktır. `data-motion`
 * da yazılmaz: CSS'in gördüğü tek gerçek, sahnenin GERÇEKTEN çalıştığıdır.
 */
export function mountScene(view: Window): SceneRuntime | null {
    const { document } = view;

    /*
        AZALTILMIŞ HAREKET — İLK VE MUTLAK KAPI.

        Buradan sonra hiçbir satır çalışmaz: gözlemci açılmaz, dinleyici
        bağlanmaz, tuval boyanmaz, `data-motion` yazılmaz. Sayfa TAM
        İŞLEVLİDİR ve bu bir "azaltılmış sürüm" değil — kurumsal sitenin
        bütün içeriği ve bütün yolları sunucu HTML'inde zaten var
        (`docs/118` E8).

        Parallax bazı insanlarda fiziksel rahatsızlık (baş dönmesi, mide
        bulantısı) yapar. Bu bir zevk meselesi değildir.
    */
    if (!motionRequested(view)) {
        return null;
    }

    const runtime = new SceneRuntime(view);

    for (const canvas of document.querySelectorAll<HTMLCanvasElement>('[data-scene="field"]')) {
        const field = createField(canvas, {
            sway: Number(canvas.dataset.sceneSway ?? '0.16'),
            speed: Number(canvas.dataset.sceneSpeed ?? '0.22'),
        });

        if (field !== null) {
            runtime.add(field);
        }
    }

    const parallax = createParallax(document);

    if (parallax !== null) {
        runtime.add(parallax);
    }

    const progress = createProgress(document);

    if (progress !== null) {
        runtime.add(progress);
    }

    const releaseReveals = observeReveals(document, view);
    const releaseTilt = bindTilt(document, view);

    runtime.start();

    /*
        TERCİH SONRADAN DEĞİŞİRSE.

        Ziyaretçi işletim sistemi ayarını sayfa açıkken değiştirebilir.
        "Azaltılmış hareket" tarafına geçtiğinde sahne O AN durur ve
        temizlenir. Ters yönde geri açılmaz: hareketi istemeyen birine, bir
        ayar penceresinde denerken bile sürpriz hareket göstermemek gerekir.
        Hareket isteyen bir değişiklik, sayfanın yeniden yüklenmesiyle gelir.
    */
    const reduced = view.matchMedia('(prefers-reduced-motion: reduce)');
    const onChange = () => {
        if (reduced.matches) {
            releaseReveals();
            releaseTilt();
            runtime.stop();
            reduced.removeEventListener('change', onChange);
        }
    };

    reduced.addEventListener('change', onChange);

    return runtime;
}
