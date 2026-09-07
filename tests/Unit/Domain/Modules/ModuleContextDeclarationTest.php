<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules;

use App\Domain\Modules\ModuleContextDeclaration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `contexts:` alanının dilbilgisi (`docs/111` §4.2 B).
 *
 * Eşlemeyi ekran koduna gömmek §4.2'nin açıkça REDDETTİĞİ yoldu: yanlış
 * olduğunda hiçbir test kırılmaz. Bu dosya o cümlenin karşılığıdır — eşleme
 * artık veridir ve verinin bir dilbilgisi vardır.
 */
final class ModuleContextDeclarationTest extends TestCase
{
    #[Test]
    public function a_single_context_is_read_as_ownership(): void
    {
        $declaration = ModuleContextDeclaration::parse('MenuCatalog');

        self::assertTrue($declaration->isMapped());
        self::assertSame(['MenuCatalog'], $declaration->contexts());
        self::assertSame('', $declaration->note());
    }

    #[Test]
    public function a_module_may_own_more_than_one_context(): void
    {
        // `core-money-ledger` iki yere dağılmış: Domain/Money ve
        // Infrastructure/Ledger (`docs/111` §4.2'nin kendi örneği).
        self::assertSame(['Money', 'Ledger'], ModuleContextDeclaration::parse(' Money , Ledger ')->contexts());
    }

    #[Test]
    public function no_code_is_an_answer_and_it_is_written_as_such(): void
    {
        $declaration = ModuleContextDeclaration::parse('yok');

        self::assertFalse($declaration->isMapped());
        self::assertFalse($declaration->isUnknown());
        self::assertSame('none', $declaration->kind());
    }

    #[Test]
    public function unknown_must_carry_its_reason(): void
    {
        $declaration = ModuleContextDeclaration::parse('belirsiz: Publication bağlamının içinde bir dilim');

        self::assertTrue($declaration->isUnknown());
        self::assertSame('Publication bağlamının içinde bir dilim', $declaration->note());
    }

    #[Test]
    public function a_reasonless_unknown_is_rejected(): void
    {
        /*
            Gerekçesiz bir "bilinmiyor", sessizce "yok" demenin kibar
            biçimidir: okuyan kişi neyin ölçülemediğini bilemez ve yazan kişi
            hiçbir şey söylememiş olur.
        */
        $this->expectException(InvalidArgumentException::class);

        ModuleContextDeclaration::parse('belirsiz');
    }

    #[Test]
    public function a_context_name_that_is_not_a_code_context_shape_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ModuleContextDeclaration::parse('menu-catalog');
    }

    #[Test]
    public function an_empty_field_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ModuleContextDeclaration::parse('   ');
    }
}
