import type { SceneEffect, SceneFrame, SceneTier } from './contract';
import { FrameGovernor, demote, estimateTier } from './tier';

/**
 * SAHNE ÇALIŞMA ZAMANI — sayfadaki TEK animasyon döngüsü.
 *
 * ── NEDEN TEK DÖNGÜ ──
 *
 * Her efekt kendi `requestAnimationFrame`ini açsaydı sayfada kaç döngü
 * olduğu bilinemezdi ve "kare süresi" diye tek bir sayı ölçülemezdi. Tek
 * döngü ölçülebilir bir bütçe demektir: bütün sahne, karede bir kez.
 *
 * Bir ikinci kazanç: durdurma da tek yerdedir. Sekme arka plana geçtiğinde
 * ya da sahne görüntü alanından çıktığında hiçbir efektin "durmayı
 * unutması" mümkün değil.
 *
 * ── AZALTILMIŞ HAREKET: TEK YÖNLÜ KAPI ──
 *
 * `prefers-reduced-motion: reduce` diyen ziyaretçide bu sınıf HİÇ
 * BAŞLAMAZ — bir efekt kaydı bile açılmaz, `data-motion` yazılmaz, tuval
 * boyanmaz. Tercih sonradan açılırsa (kullanıcı ayarını değiştirdi) sahne o
 * an DURUR ve geri açılmaz: hareket isteyen bir tercih değişikliği sayfayı
 * yeniden yüklemekle gelir, kendiliğinden değil. Sebep: hareketi istemeyen
 * birine, bir ayar penceresinde denerken bile sürpriz hareket göstermemek.
 */
export class SceneRuntime {
    private readonly effects: SceneEffect[] = [];

    private readonly view: Window;

    private readonly root: HTMLElement;

    private readonly governor = new FrameGovernor();

    private tier: SceneTier;

    private handle = 0;

    private started = 0;

    private previous = 0;

    private running = false;

    private disposed = false;

    /** İmlecin HEDEF konumu; kare içinde yumuşatılarak izlenir. */
    private targetX = 0;

    private targetY = 0;

    private pointerX = 0;

    private pointerY = 0;

    private scroll = 0;

    private width = 0;

    private height = 0;

    private readonly listeners: Array<() => void> = [];

    constructor(view: Window) {
        this.view = view;
        this.root = view.document.documentElement;
        this.tier = estimateTier(view);
    }

    add(effect: SceneEffect): void {
        this.effects.push(effect);
    }

    /**
     * Sahneyi başlatır.
     *
     * `data-motion` ve `data-scene-tier` BURADA yazılır, efektlerde değil:
     * CSS'in gördüğü tek gerçek budur ve iki yerden yazılan bir öznitelik
     * bir gün yalnız birinde güncellenir.
     */
    start(): void {
        if (this.running || this.disposed || this.effects.length === 0) {
            return;
        }

        this.running = true;
        this.root.dataset.motion = 'on';
        this.root.dataset.sceneTier = this.tier;

        this.measure();
        this.bind();

        this.started = this.view.performance.now();
        this.previous = this.started;
        this.handle = this.view.requestAnimationFrame(this.tick);
    }

    /** Sahneyi durdurur ve BÜTÜN izlerini siler. Geri açılmaz. */
    stop(): void {
        if (this.disposed) {
            return;
        }

        this.disposed = true;
        this.running = false;
        this.view.cancelAnimationFrame(this.handle);

        for (const off of this.listeners) {
            off();
        }

        this.listeners.length = 0;

        for (const effect of this.effects) {
            effect.destroy?.();
        }

        this.effects.length = 0;

        delete this.root.dataset.motion;
        delete this.root.dataset.sceneTier;
    }

    private bind(): void {
        const { view } = this;

        const onScroll = () => {
            this.scroll = view.scrollY;
        };

        const onResize = () => {
            this.measure();
        };

        /*
            İMLEÇ YALNIZ İŞARETLEYİCİLİ CİHAZDA DİNLENİR.

            Dokunmalı bir cihazda `pointermove` her sürüklemede yağar ve
            sahne, parmağın ekranı kapattığı noktada bir kez sıçrar. Bu bir
            estetik tercih değil, küresel `TOUCH-FIRST-INTERFACE` §2'nin
            karşılığı: dokunma ile işaretleyici AYRI etkileşim modelleridir.
        */
        const fine = view.matchMedia('(pointer: fine)');

        const onPointer = (event: PointerEvent) => {
            if (!fine.matches) {
                return;
            }

            this.targetX = (event.clientX / Math.max(this.width, 1)) * 2 - 1;
            this.targetY = (event.clientY / Math.max(this.height, 1)) * 2 - 1;
        };

        const onVisibility = () => {
            if (view.document.hidden) {
                view.cancelAnimationFrame(this.handle);
                this.running = false;
                return;
            }

            if (!this.running && !this.disposed) {
                this.running = true;
                /* Görünmez geçen süre bir "kare" değildir: saat sıfırlanır. */
                this.previous = view.performance.now();
                this.handle = view.requestAnimationFrame(this.tick);
            }
        };

        view.addEventListener('scroll', onScroll, { passive: true });
        view.addEventListener('resize', onResize, { passive: true });
        view.addEventListener('pointermove', onPointer, { passive: true });
        view.document.addEventListener('visibilitychange', onVisibility);

        this.listeners.push(
            () => view.removeEventListener('scroll', onScroll),
            () => view.removeEventListener('resize', onResize),
            () => view.removeEventListener('pointermove', onPointer),
            () => view.document.removeEventListener('visibilitychange', onVisibility),
        );
    }

    private measure(): void {
        this.width = this.view.innerWidth;
        this.height = this.view.innerHeight;
        this.scroll = this.view.scrollY;

        for (const effect of this.effects) {
            effect.measure?.(this.tier);
        }
    }

    private readonly tick = (now: number): void => {
        if (!this.running) {
            return;
        }

        /*
            DELTA ÜST SINIRLI.

            Sekmeye geri dönüldüğünde ya da bir uzun görev bittiğinde `now`
            saniyelerce ileri sıçrayabilir. Sınırsız bir delta, sürekli
            hareket eden her katmanı tek karede metrelerce ötelerdi — sahne
            "atlar". Üst sınır, atlamayı yavaşlamaya çevirir.
        */
        const delta = Math.min((now - this.previous) / 1000, 0.05);
        this.previous = now;

        if (this.governor.observe(delta) && this.tier !== 'minimal') {
            this.tier = demote(this.tier);
            this.root.dataset.sceneTier = this.tier;

            for (const effect of this.effects) {
                effect.measure?.(this.tier);
            }
        }

        /*
            İMLEÇ YUMUŞATMASI — kare hızından BAĞIMSIZ.

            Sabit bir katsayı (ör. `x += (hedef - x) * 0.08`) 120 Hz'lik bir
            ekranda iki kat hızlı yakınsar; aynı hareket iki cihazda iki
            farklı hızda görünürdü. Üstel form, deltayı hesaba katar.
        */
        const follow = 1 - Math.exp(-6 * delta);
        this.pointerX += (this.targetX - this.pointerX) * follow;
        this.pointerY += (this.targetY - this.pointerY) * follow;

        const frame: SceneFrame = {
            time: (now - this.started) / 1000,
            delta,
            scroll: this.scroll,
            width: this.width,
            height: this.height,
            pointerX: this.pointerX,
            pointerY: this.pointerY,
        };

        for (const effect of this.effects) {
            if (effect.visible !== undefined && !effect.visible()) {
                continue;
            }

            effect.frame(frame);
        }

        this.handle = this.view.requestAnimationFrame(this.tick);
    };
}

/**
 * HAREKET İSTENDİ Mİ?
 *
 * Tek soru, tek yer. `matchMedia` desteklenmeyen bir ortamda (eski bir
 * gömülü tarayıcı, bir test koşucusu) cevap "hayır"dır: ölçülemeyen bir
 * tercih, varmış gibi davranılacak bir tercih değildir.
 */
export function motionRequested(view: Window): boolean {
    if (typeof view.matchMedia !== 'function') {
        return false;
    }

    return !view.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
