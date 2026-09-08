import { describe, expect, it } from 'vitest';

import { desktopPages } from './desktopPages';

/**
 * MASAÜSTÜ HARİTASININ KAPSAMI — `docs/151` §B.
 *
 * Bu test bir ekranın nasıl göründüğünü sormaz; haritanın BÜYÜKLÜĞÜNÜ
 * dondurur. İki yönde de bir arıza ailesi var:
 *
 * - **Sessiz büyüme.** Bir ekranın masaüstünde farkı yoksa haritaya
 *   yazılmaz (`docs/153` §9): boş bir kayıt, bakılacak ikinci bir dosya
 *   yaratıp hiçbir şey kazandırmaz. Liste kendiliğinden uzarsa bu kural bir
 *   yorum satırına döner.
 * - **Sessiz kayıp.** Bir anahtar düşerse ekran YİNE ÇİZİLİR — 320
 *   tabanıyla. Kimse bir hata görmez; masaüstü kullanıcısı yalnız işini
 *   yavaş yapar. Bir kaybın görülmesi ancak bir test tutuyorsa mümkün.
 *
 * Liste değişince bu satır da değişir ve o an bir KARAR anıdır: yeni ekranın
 * masaüstünde gerçekten farkı var mı?
 */

const REGISTERED = ['media', 'orders', 'ratings', 'team'];

describe('desktopPages', () => {
    it('yalnız gerçekten ayrışan ekranları taşır', () => {
        expect(Object.keys(desktopPages).sort()).toEqual(REGISTERED);
    });

    it('her kayıt bir çizicidir — bileşen değil', () => {
        /*
            Harita BİLEŞEN değil ÇİZİCİ taşır (`docs/153` §5). Bileşen
            geçseydi paylaşılan kayıt onu bir tür olarak anmak zorunda
            kalırdı; çizici, adı hiç geçmeden çalışır.
        */
        for (const renderer of Object.values(desktopPages)) {
            expect(typeof renderer).toBe('function');
        }
    });
});
