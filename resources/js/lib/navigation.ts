import type { MouseEvent } from 'react';

/**
 * Bir bağlantı tıklamasını uygulama mı karşılamalı, tarayıcı mı?
 *
 * Gezinti fragment ile yapılırken bu soru yoktu: `#menu` bağlantısını
 * tarayıcının izlemesi zararsızdı. Gerçek adreslere geçince zararsız
 * olmaktan çıktı — her tıklama tam sayfa yenilemesi olurdu ve tek sayfa
 * uygulamasının bütün anlamı kaybolurdu.
 *
 * Ama tıklamayı KOŞULSUZ engellemek de yanlıştır ve yaygın bir hatadır:
 * kullanıcı Ctrl/Cmd ile tıkladığında yeni sekmede açılmasını bekler, orta
 * tuşla tıkladığında da öyle. `preventDefault` bunları sessizce öldürür ve
 * kullanıcı "bu uygulamada bağlantılar çalışmıyor" der.
 *
 * Kural: YALNIZ süslenmemiş sol tıklama uygulamanındır.
 */
export function shouldInterceptNavigation(
    event: MouseEvent<HTMLAnchorElement | HTMLButtonElement>,
): boolean {
    // `button === 0` sol tuş. Orta tuş (1) tarayıcıda yeni sekme demektir.
    if (event.button !== 0) {
        return false;
    }

    // Ctrl/Cmd → yeni sekme, Shift → yeni pencere, Alt → indirme.
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }

    // Zaten başka bir yer engellemişse üstüne yazmayız.
    return !event.defaultPrevented;
}
