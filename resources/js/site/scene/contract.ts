/**
 * SAHNE SÖZLEŞMESİ — motorun bütün parçalarının paylaştığı tek dil.
 *
 * Bu dosya hiçbir şey ÇİZMEZ ve hiçbir DOM'a dokunmaz. Tek işi, her efektin
 * aynı kareyi, aynı dereceyi ve aynı yaşam döngüsünü konuşmasını sağlamak.
 * Ayrı durmasının sebebi ölçülebilir: bir efekt kendi `requestAnimationFrame`
 * döngüsünü açtığı anda, sayfada kaç döngü olduğu bilinemez hâle gelir ve
 * "kare süresi" diye tek bir sayı ölçülemez.
 */

/** Bir karenin BÜTÜN durumu. Efektler bunun dışında hiçbir şey okumaz. */
export interface SceneFrame {
    /** Sahne başladığından beri geçen süre, saniye. */
    readonly time: number;
    /** Bir önceki kareden bu yana geçen süre, saniye (üst sınırlı). */
    readonly delta: number;
    /** Sayfanın dikey kaydırma konumu, piksel. */
    readonly scroll: number;
    /** Görüntü alanı, CSS pikseli. */
    readonly width: number;
    readonly height: number;
    /**
     * İMLEÇ, görüntü alanına göre −1…1 aralığında ve YUMUŞATILMIŞ.
     *
     * Dokunmalı cihazda her zaman (0, 0)'dır ve bu bir varsayılan değil bir
     * KARARDIR: dokunmada imleç yoktur, `hover` yoktur ve parmak ekranı
     * kapatır. Ham imleç konumu kullanılsaydı, dokunulan tek noktada sahne
     * bir kez sıçrayıp donardı.
     */
    readonly pointerX: number;
    readonly pointerY: number;
}

/**
 * CİHAZ DERECESİ — sahne gizlenmez, DERECELENİR.
 *
 * `full`     : bütün katmanlar, tam çözünürlük.
 * `reduced`  : yıldız sayısı ve çözünürlük düşer, en pahalı boyama kapanır.
 * `minimal`  : tuval hiç boyanmaz; geriye yüzey dilinin kendisi kalır.
 */
export type SceneTier = 'full' | 'reduced' | 'minimal';

export const TIER_ORDER: readonly SceneTier[] = ['full', 'reduced', 'minimal'];

/** Bir efektin motora verdiği söz. */
export interface SceneEffect {
    /**
     * Her karede çağrılır. Bir efekt burada DOM ÖLÇMEZ (`getBoundingClientRect`
     * ve benzeri): ölçüm düzeni zorlar ve kare süresini kestirilemez yapar.
     * Ölçüm `measure()` içinde, yalnız gerektiğinde yapılır.
     */
    frame(frame: SceneFrame): void;
    /** Boyut değiştiğinde ya da derece düştüğünde. Düzen ölçmek SERBEST. */
    measure?(tier: SceneTier): void;
    /** Sahne durduğunda: dinleyiciler, gözlemciler ve GPU kaynakları bırakılır. */
    destroy?(): void;
    /**
     * Efekt şu an görünür alanda mı? Görünmeyen bir efekt kare almaz.
     * Belirtilmezse her zaman canlı sayılır.
     */
    visible?(): boolean;
}
