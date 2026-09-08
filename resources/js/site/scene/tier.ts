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

/** Yöneticinin bir karede verdiği karar. */
export type TierMove = 'demote' | 'promote' | null;

/**
 * KARE SÜRESİ YÖNETİCİSİ — çalışırken ölçer, dereceyi indirir VE kaldırır.
 *
 * ── DÖNGÜ 1'DE MERDİVEN TEK YÖNLÜYDÜ; ARTIK DEĞİL (`docs/146` §9 madde 10) ──
 *
 * Tek yönlü merdivenin gerekçesi doğruydu: kılık değiştirip duran bir sahne,
 * yavaş bir sahneden kötüdür. Ama ölçülmeyen bir bedeli vardı — sekmeyi bir
 * süre arka planda bırakıp dönen ya da başka bir uygulama yüzünden bir kez
 * ısınan ziyaretçi, cihaz çoktan rahatlamışken sahnenin en sade hâlinde
 * KALIYORDU. Bir daha da çıkamıyordu; oturum boyunca.
 *
 * Çözüm "yükselt"i eklemek değil, yükselmeyi PAHALI yapmaktır. Üç kural:
 *
 *   1. **Asimetrik sabır.** İnmek için 30 kare yeter; çıkmak için 600 kare
 *      (~10 saniye) SÜREKLİ sakinlik gerekir. Bir cihazın yavaş olduğunu
 *      anlamak ucuz, hızlı olduğunu kanıtlamak pahalı olmalı.
 *   2. **Daha dar bütçe.** Çıkış eşiği tavanın kendisi değil, tavanın
 *      dörtte üçü. Tam tavanda seyreden bir cihazı yükseltmek, onu bir kare
 *      sonra yine indirmek demekti.
 *   3. **Geri çekilme (backoff).** Her yükselişten ve her düşüşten sonra
 *      gereken sakinlik İKİYE KATLANIR. Böylece salınım kendi kendini
 *      söndürür: bir cihaz iki kez yanılırsa üçüncü denemeyi kırk saniye
 *      bekler, dördüncüyü hiç yapmaz.
 *
 * ── NEDEN ORTALAMA DEĞİL, SAYAÇ ──
 *
 * Tek bir uzun kare (çöp toplama, sekme değişimi, bir resmin çözülmesi) her
 * ortalamayı bozar. Burada ölçülen şey SÜREKLİLİKTİR: arka arkaya kaç kare
 * bütçeyi aştı, arka arkaya kaç kare rahat geçti. Bir kez aşmak bir olay,
 * otuz kez aşmak bir durumdur.
 */
export class FrameGovernor {
    private overruns = 0;

    private calm = 0;

    private readonly budgetMs: number;

    private readonly patience: number;

    /** Yükselmek için gereken sakin kare sayısı; her karardan sonra ikiye katlanır. */
    private calmPatience: number;

    /** Kaç kez yükseltildi. Üçten sonra merdiven yine tek yönlü olur. */
    private promotions = 0;

    constructor(budgetMs = 22, patience = 30, calmPatience = 600) {
        this.budgetMs = budgetMs;
        this.patience = patience;
        this.calmPatience = calmPatience;
    }

    /**
     * @returns Bu karede derece değişmeli mi, hangi yöne?
     */
    observe(deltaSeconds: number): TierMove {
        const ms = deltaSeconds * 1000;

        /*
            SEKME ARKA PLANDAYKEN ÖLÇÜM YAPILMAZ.

            Görünmeyen bir sekmede tarayıcı kareleri saniyede bire kadar
            düşürür. O kareleri "yavaşlık" saymak, sekmeyi bir dakika arka
            planda bırakan HER ziyaretçide sahneyi en düşük dereceye
            indirirdi — ölçtüğü şey cihaz değil, kullanıcının davranışı olurdu.
            Aynı kare "sakinlik" de sayılmaz: hiç iş yapmadan geçen bir kare,
            cihazın güçlü olduğunu KANITLAMAZ.
        */
        if (ms > 200) {
            this.overruns = 0;
            this.calm = 0;

            return null;
        }

        if (ms > this.budgetMs) {
            this.calm = 0;
            this.overruns += 1;

            if (this.overruns >= this.patience) {
                this.overruns = 0;
                /* Yanılan merdiven bir dahaki sefere daha uzun bekler. */
                this.calmPatience *= 2;

                return 'demote';
            }

            return null;
        }

        this.overruns = 0;

        /* Sakinlik eşiği tavanın kendisi değil, dörtte üçü — bkz. kural 2. */
        if (ms > this.budgetMs * 0.75) {
            this.calm = 0;

            return null;
        }

        if (this.promotions >= 3) {
            return null;
        }

        this.calm += 1;

        if (this.calm >= this.calmPatience) {
            this.calm = 0;
            this.promotions += 1;
            this.calmPatience *= 2;

            return 'promote';
        }

        return null;
    }
}

/** Merdivende bir basamak aşağı; en alttaysa olduğu yerde kalır. */
export function demote(tier: SceneTier): SceneTier {
    if (tier === 'full') {
        return 'reduced';
    }

    return 'minimal';
}

/**
 * Merdivende bir basamak yukarı; en üstteyse olduğu yerde kalır.
 *
 * Yükseliş TEK BASAMAKTIR: `minimal`den doğrudan `full`e çıkmak, cihazın
 * kaldıramadığı yükü tek adımda geri yüklemek olurdu — ve o yük zaten bir
 * kez düşürülmüştü.
 */
export function promote(tier: SceneTier): SceneTier {
    if (tier === 'minimal') {
        return 'reduced';
    }

    return 'full';
}
