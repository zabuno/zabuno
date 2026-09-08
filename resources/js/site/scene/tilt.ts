/**
 * EĞİLEN KART — 3D görünen 2D.
 *
 * ── GİRİŞ KİPİ AYRIMI, İKİ KATMANDA BİRDEN ──
 *
 * Küresel kural (`TOUCH-FIRST-INTERFACE` §2): dokunma ile işaretleyici AYRI
 * etkileşim modelleridir. Dokunmalı bir cihazda `hover` yoktur ve parmak
 * kartın üstünü kapatır; eğilmeyi orada taklit etmek, GÖRÜNMEYEN bir efekt
 * için her dokunuşta iş yapmak olurdu.
 *
 * Ayrım hem CSS'te (`@media (pointer: fine)`) hem burada yazılı ve bu bir
 * tekrar değil: CSS efekti çizmemeyi, bu dosya olay dinleyicisini HİÇ
 * BAĞLAMAMAYI sağlar. Yalnız CSS'te dursaydı, dokunmalı bir cihaz görünmez
 * bir efekt için olay işlerdi.
 *
 * ── NEDEN AYRI BİR EFEKT DEĞİL ──
 *
 * Eğilme sürekli bir hareket değil, bir TEPKİDİR: yalnız imleç kartın
 * üstündeyken ve yalnız hareket ettiğinde iş yapar. Onu her kareye
 * bağlamak, imleç masanın öteki ucundayken bile hesap yapmak olurdu.
 * Geçişin kendisi CSS'te (`transition`), yani yumuşatmayı tarayıcı yapar.
 */
export function bindTilt(root: ParentNode, view: Window): () => void {
    if (typeof view.matchMedia !== 'function' || !view.matchMedia('(pointer: fine)').matches) {
        return () => {};
    }

    const cards = Array.from(root.querySelectorAll<HTMLElement>('.scene-tilt'));
    const off: Array<() => void> = [];

    /** Kartın en fazla kaç derece eğileceği. Fazlası oyuncak, azı görünmez. */
    const LIMIT = 7;

    for (const card of cards) {
        const onMove = (event: PointerEvent) => {
            /*
                Ölçüm olay ANINDA yapılıyor ve bu, "karede ölçme" kuralının
                bir istisnası DEĞİL: `pointermove` zaten kare başına en fazla
                bir kez gelir (tarayıcı olayları birleştirir) ve ölçülen tek
                bir kutudur. Önceden ölçüp saklamak, kart kaydırıldığında
                yanlış bir kutuyla hesap yapmak olurdu.
            */
            const rect = card.getBoundingClientRect();

            if (rect.width === 0 || rect.height === 0) {
                return;
            }

            const x = (event.clientX - rect.left) / rect.width;
            const y = (event.clientY - rect.top) / rect.height;

            /* Üst kenara gidince kart geriye yatar: ışık yukarıdan gelir. */
            card.style.setProperty('--scene-tilt-x', ((0.5 - y) * 2 * LIMIT).toFixed(2));
            card.style.setProperty('--scene-tilt-y', ((x - 0.5) * 2 * LIMIT).toFixed(2));
            card.style.setProperty('--scene-tilt-lift', '14');
            card.style.setProperty('--scene-glint', '1');
            card.style.setProperty('--scene-glint-x', `${(x * 100).toFixed(1)}%`);
            card.style.setProperty('--scene-glint-y', `${(y * 100).toFixed(1)}%`);
        };

        const onLeave = () => {
            card.style.setProperty('--scene-tilt-x', '0');
            card.style.setProperty('--scene-tilt-y', '0');
            card.style.setProperty('--scene-tilt-lift', '0');
            card.style.setProperty('--scene-glint', '0');
        };

        card.addEventListener('pointermove', onMove, { passive: true });
        card.addEventListener('pointerleave', onLeave, { passive: true });
        /*
            KLAVYE DE BİR GİRİŞ KİPİDİR.

            Kartın içindeki bağlantıya sekme ile gelen biri, imleçle gelen
            birinin gördüğü kalkışı görmeli. Aksi hâlde efekt, yalnız fare
            kullananlara ayrılmış bir bilgi olurdu — ve bu, odak halkasının
            hangi kartta olduğunu okumayı zorlaştırırdı.
        */
        const onFocus = () => {
            card.style.setProperty('--scene-tilt-lift', '14');
            card.style.setProperty('--scene-glint', '0.6');
            card.style.setProperty('--scene-glint-x', '50%');
            card.style.setProperty('--scene-glint-y', '0%');
        };

        card.addEventListener('focusin', onFocus);
        card.addEventListener('focusout', onLeave);

        off.push(() => {
            card.removeEventListener('pointermove', onMove);
            card.removeEventListener('pointerleave', onLeave);
            card.removeEventListener('focusin', onFocus);
            card.removeEventListener('focusout', onLeave);
            onLeave();
        });
    }

    return () => {
        for (const dispose of off) {
            dispose();
        }
    };
}
