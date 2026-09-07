<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules;

use App\Domain\Modules\ModuleCodeEvidence;
use App\Domain\Modules\ModuleCodePresence;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * DURUM ROZETİNİN GİRDİSİ VE EŞİĞİ BURADA DONAR (`docs/111` §4.1, §8.4).
 *
 * Bu testin işi rozetin "doğru göründüğünü" onaylamak değil; eşiğin bir gün
 * sessizce değişmesini imkânsız kılmak. Bugün "bir test yeter" diyoruz. Yarın
 * biri "üç test isterim" derse bu dosya kırılır ve karar konuşulur — kırılmasa
 * ekranda aynı rozet başka bir şey ölçmeye başlardı ve kimse fark etmezdi.
 * `docs/109` §8.7'nin beş örneğinin doğuş biçimi tam olarak buydu.
 */
final class ModuleCodePresenceTest extends TestCase
{
    #[Test]
    public function the_three_thresholds_are_written_down_and_not_inferred(): void
    {
        self::assertSame(1, ModuleCodePresence::MINIMUM_CONTEXT_DIRECTORIES);
        self::assertSame(1, ModuleCodePresence::MINIMUM_SURFACES);
        self::assertSame(1, ModuleCodePresence::MINIMUM_TEST_FILES);
    }

    #[Test]
    public function an_unmeasurable_module_is_unknown_and_never_silently_absent(): void
    {
        /*
            EN ÖNEMLİ SATIR BU. Eşleme kurulamadığında doğru cevap "yok"
            DEĞİLDİR; ölçüm yapılmadıysa sonuç "geçti" değil "bilinmiyor"dur.
            "Yok" demek, superadmin'in aramayı bırakması demektir.
        */
        self::assertSame(
            ModuleCodePresence::Unknown,
            ModuleCodePresence::deriveFrom(ModuleCodeEvidence::notMeasurable())
        );
    }

    #[Test]
    public function a_mapped_module_without_a_single_directory_is_definition_only(): void
    {
        self::assertSame(
            ModuleCodePresence::DefinitionOnly,
            ModuleCodePresence::deriveFrom(ModuleCodeEvidence::measured([], [], [], 0))
        );
    }

    #[Test]
    public function directory_plus_route_plus_test_is_implemented(): void
    {
        self::assertSame(
            ModuleCodePresence::Implemented,
            ModuleCodePresence::deriveFrom(
                ModuleCodeEvidence::measured(['app/Domain/MenuCatalog'], ['routes/api/menu-catalog.php'], [], 1)
            )
        );
    }

    #[Test]
    public function directory_plus_table_plus_test_is_also_implemented(): void
    {
        // Yüzey VEYA veri — ikisinden biri yeter (`docs/111` §4.1). Rota
        // taşımayan bir modül içeriden çalışıyor olabilir; onu "kısmen"
        // saymak, olmayan bir eksiği ölçmek olurdu.
        self::assertSame(
            ModuleCodePresence::Implemented,
            ModuleCodePresence::deriveFrom(
                ModuleCodeEvidence::measured(['app/Infrastructure/Ledger'], [], ['ledger_entries'], 1)
            )
        );
    }

    #[Test]
    public function a_directory_without_any_surface_is_only_partial(): void
    {
        self::assertSame(
            ModuleCodePresence::Partial,
            ModuleCodePresence::deriveFrom(
                ModuleCodeEvidence::measured(['app/Domain/Taxonomy'], [], [], 4)
            )
        );
    }

    #[Test]
    public function a_directory_with_a_surface_but_no_test_is_only_partial(): void
    {
        // Kanıtı olmayan bir yetenek "uygulanmış" sayılmaz. Bu, kodun
        // yokluğunu değil ÖLÇÜMÜN yokluğunu söyler ve ikisi aynı şey değildir.
        self::assertSame(
            ModuleCodePresence::Partial,
            ModuleCodePresence::deriveFrom(
                ModuleCodeEvidence::measured(['app/Application/Mail'], ['routes/api/mail.php'], [], 0)
            )
        );
    }

    #[Test]
    public function the_four_badge_values_are_frozen(): void
    {
        self::assertSame(
            ['implemented', 'partial', 'definition-only', 'unknown'],
            array_map(static fn (ModuleCodePresence $case): string => $case->value, ModuleCodePresence::cases())
        );
    }
}
