<?php

declare(strict_types=1);

namespace App\Domain\Modules;

/**
 * Durum rozeti — ve rozetin İDDİA ETMEDİĞİ şey (`docs/111` §4.1).
 *
 * Dört değer, dördü de bir GÖZLEMDEN türetilir; hiçbiri elle girilmez ve
 * hiçbiri `modules/*.md` metnindeki bir cümleden okunmaz. O 62 dosyanın
 * durum satırı bir zamanlar 62 kez "PLANNING ONLY" diyordu ve en az on
 * sekizinde yanlıştı; bu yüzden durum artık yalnız koddan ölçülür.
 *
 * ROZET NE SÖYLER, NE SÖYLEMEZ — bu ayrım ekranda da yazılıdır:
 *  - SÖYLER: deponun bu sürümünde bu modülün kod karşılığı şu kadardır.
 *  - SÖYLEMEZ: bu yeteneğin bu kurulumda üretimde çalıştığını. Kod karşılığı
 *    olan bir modül yapılandırılmamış, kapalı ya da hiç dağıtılmamış olabilir.
 *
 * EŞİKLER BURADA, TEK YERDE. Bir gün "bir test yetmez, üç test isterim"
 * denirse değişecek yer burasıdır ve `ModuleCodePresenceTest` o değişikliği
 * sessiz bırakmaz: eşik testte de yazılıdır.
 */
enum ModuleCodePresence: string
{
    /** Bağlam dizini VE (rota VEYA tablo) VE en az bir test — üçü birden. */
    case Implemented = 'implemented';

    /** Bağlam dizini var, ama yüzey/veri/test üçlüsünden en az biri yok. */
    case Partial = 'partial';

    /** Tanım var, `app/` karşılığı yok. */
    case DefinitionOnly = 'definition-only';

    /** Eşleme kurulamadı — "yok" değil, BİLİNMİYOR (`docs/111` §4.2). */
    case Unknown = 'unknown';

    /** En az kaç bağlam dizini bir modülü "kodda var" saymaya yeter. */
    public const MINIMUM_CONTEXT_DIRECTORIES = 1;

    /** Yüzey ya da veri: kaç rota dosyası VEYA kaç tablo yeterlidir. */
    public const MINIMUM_SURFACES = 1;

    /** Kanıt eşiği: kaç test dosyası "uygulanmış" demeye yeter. */
    public const MINIMUM_TEST_FILES = 1;

    public static function deriveFrom(ModuleCodeEvidence $evidence): self
    {
        if (! $evidence->isMeasurable()) {
            return self::Unknown;
        }

        if (count($evidence->directories()) < self::MINIMUM_CONTEXT_DIRECTORIES) {
            return self::DefinitionOnly;
        }

        $surfaces = count($evidence->routeFiles()) + count($evidence->tables());

        if ($surfaces >= self::MINIMUM_SURFACES && $evidence->testFiles() >= self::MINIMUM_TEST_FILES) {
            return self::Implemented;
        }

        return self::Partial;
    }
}
