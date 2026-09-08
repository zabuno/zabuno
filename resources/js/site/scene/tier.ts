import type { SceneTier } from './contract';

/**
 * CİHAZ DERECESİ — açılış tahmini.
 *
 * ── NEDEN BİR TAHMİNLE BAŞLIYORUZ ──
 *
 * İlk kare çizilmeden cihazın gerçek gücü BİLİNEMEZ. Ama hiç tahmin
 * yapmadan başlamak, en zayıf telefonda ilk saniyeyi tam yükle geçirmek
 * demektir — ve ilk saniye, ziyaretçinin sayfa hakkında karar verdiği
 * saniyedir. Tahmin ucuzdur ve yanılırsa yönetici (`governor`) onu ilk
 * saniyede düzeltir.
 *
 * ── NEDEN BU İPUÇLARI ──
 *
 * `hardwareConcurrency` ve `deviceMemory` bir GPU ölçüsü değildir; ikisi de
 * yalnız KABA bir sınıf verir ve burada yapılan iş tam olarak budur. Ekran
 * alanı üçüncü ipucudur ve en dürüstüdür: boyanacak piksel sayısı, cihazın
 * ne kadar iş yapacağının doğrudan ölçüsüdür.
 *
 * Hiçbiri yoksa `full` DEĞİL `reduced` seçilir. Bilinmeyen bir cihazı güçlü
 * saymak, hatayı en zayıf cihaza ödetmek olurdu.
 */
export function estimateTier(view: Window): SceneTier {
    const nav = view.navigator as Navigator & {
        deviceMemory?: number;
        hardwareConcurrency?: number;
    };

    const cores = typeof nav.hardwareConcurrency === 'number' ? nav.hardwareConcurrency : 0;
    const memory = typeof nav.deviceMemory === 'number' ? nav.deviceMemory : 0;

    /* Boyanacak fiziksel piksel: mantıksal alan × piksel oranının karesi. */
    const dpr = Math.min(view.devicePixelRatio || 1, 3);
    const pixels = view.innerWidth * view.innerHeight * dpr * dpr;

    /* Bilinmeyen cihaz güçlü sayılmaz. */
    if (cores === 0 && memory === 0) {
        return 'reduced';
    }

    if (cores <= 2 || (memory > 0 && memory <= 2)) {
        return 'minimal';
    }

    if (cores <= 4 || (memory > 0 && memory <= 4) || pixels > 6_000_000) {
        return 'reduced';
    }

    return 'full';
}

/**
 * KARE SÜRESİ YÖNETİCİSİ — çalışırken ölçer, gerekirse dereceyi DÜŞÜRÜR.
 *
 * ── NEDEN SADECE DÜŞÜRÜYOR ──
 *
 * Yükseltmek, cihaz bir an rahatladığında sahneyi tekrar ağırlaştırır ve
 * ısınınca yine düşürür: kullanıcı, sahnenin sürekli kılık değiştirdiğini
 * görür. Tek yönlü bir merdiven daha az akıllıdır ama KARARLIDIR — ve
 * kararlılık burada doğruluktan daha değerlidir.
 *
 * ── NEDEN ORTALAMA DEĞİL, SAYAÇ ──
 *
 * Tek bir uzun kare (çöp toplama, sekme değişimi, bir resmin çözülmesi) her
 * ortalamayı bozar. Burada ölçülen şey SÜREKLİLİKTİR: arka arkaya kaç kare
 * bütçeyi aştı. Bir kez aşmak bir olay, otuz kez aşmak bir durumdur.
 */
export class FrameGovernor {
    private overruns = 0;

    private readonly budgetMs: number;

    private readonly patience: number;

    constructor(budgetMs = 22, patience = 30) {
        this.budgetMs = budgetMs;
        this.patience = patience;
    }

    /**
     * @returns Derece düşürülmeli mi?
     */
    observe(deltaSeconds: number): boolean {
        const ms = deltaSeconds * 1000;

        /*
            SEKME ARKA PLANDAYKEN ÖLÇÜM YAPILMAZ.

            Görünmeyen bir sekmede tarayıcı kareleri saniyede bire kadar
            düşürür. O kareleri "yavaşlık" saymak, sekmeyi bir dakika arka
            planda bırakan HER ziyaretçide sahneyi en düşük dereceye
            indirirdi — ölçtüğü şey cihaz değil, kullanıcının davranışı olurdu.
        */
        if (ms > 200) {
            this.overruns = 0;
            return false;
        }

        if (ms <= this.budgetMs) {
            this.overruns = 0;
            return false;
        }

        this.overruns += 1;

        if (this.overruns >= this.patience) {
            this.overruns = 0;
            return true;
        }

        return false;
    }
}

/** Merdivende bir basamak aşağı; en alttaysa olduğu yerde kalır. */
export function demote(tier: SceneTier): SceneTier {
    if (tier === 'full') {
        return 'reduced';
    }

    return 'minimal';
}
