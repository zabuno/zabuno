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
