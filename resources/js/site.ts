import { bindDismiss } from './site/dismiss';
import { bindTheme } from './site/theme';
import { mountScene } from './site/scene/mount';

/**
 * KURUMSAL SİTENİN TEK GİRİŞ NOKTASI.
 *
 * ── NEDEN REACT YOK ──
 *
 * Kurumsal sayfalar React paketini hiç yüklemiyor ve bu ölçülmüş bir kazanç
 * (`HOME-NO-REACT-05`, `docs/38` §16): sunucuda üretilen gövde bir arama
 * botunun ve JavaScript çalıştırmayan bir AI botunun gördüğü şeydir. Sahne
 * motorunun etkileşim durumu yok — tuval, kaydırma ve imleç. Bunun için bir
 * bileşen ağacı, bir eşitleyici ve 60 KB'lık bir çalışma zamanı taşımak,
 * hiçbir şey karşılığında ödenen bir bedel olurdu (`docs/118` E5 madde 3:
 * *"React adacığı yalnız bir bileşen GERÇEKTEN etkileşim gerektirdiğinde
 * açılır; süs için açılmaz."*).
 *
 * ── NEDEN `DOMContentLoaded` BEKLENMİYOR ──
 *
 * Bu dosya bir modül olarak yükleniyor; modüller varsayılan olarak
 * ERTELENİR ve belge ayrıştırıldıktan sonra çalışır. Ayrıca bir kez daha
 * beklemek, ilk karenin görünmesini gereksiz yere geciktirirdi.
 */
mountScene(window);

/*
    KAPANMA YOLLARI — SAHNENİN DIŞINDA, BİLEREK.

    `mountScene` azaltılmış hareket isteyen ziyaretçide hiç başlamaz ve bu
    doğru: parallax bir süstür. Menüyü kapatabilmek ise bir süs değil, bir
    ÇIKIŞ YOLUDUR. İkisini aynı kapının arkasına koymak, hareketi istemeyen
    birinin menüyü kapatamaması demek olurdu — kuralı doğru uygulamış
    olmanın yarattığı bir arıza.
*/
bindDismiss(document);

/*
    GÖRÜNÜM TERCİHİ — kapanma yollarıyla aynı gerekçe.

    Sahnenin dışında: hareket istemeyen bir ziyaretçi de temasını
    seçebilmelidir. Denetim sunucuda `hidden` doğar ve yalnız bu çağrı onu
    açar — betik gelmezse çalışmayan bir düğme görünmez.
*/
bindTheme(document, window);
