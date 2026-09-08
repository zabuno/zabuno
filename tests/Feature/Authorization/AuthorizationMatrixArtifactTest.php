<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domain\Authorization\Permission;
use App\Infrastructure\Authorization\AuthorizationMatrixReport;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * KOD İLE BELGENİN AYRIŞTIĞI GÜN KIRILAN KAPI — `docs/139` §1.1.
 *
 * ═══ NEDEN VAR ═══
 *
 * Elle yazılmış bir yetki matrisi bir gün yalan söyler. Söylediği gün de
 * kimse fark etmez: belgeyi okuyan kişi (zincirin hukukçusu) kodu okumaz,
 * kodu değiştiren kişi belgeyi açmaz. Aradaki tek bağ bu testtir.
 *
 * Belge yazılırken bunun bir varsayım değil bir OLGU olduğu görüldü: ilk
 * taslak "destek penceresinde yazma yoktur" diyordu ve ölçüm o cümleyi
 * yalanladı — aynı önekte iki ödeme ucu vardı. Cümle düzeltildi ve yerine
 * onu bozan değişikliği durduran bir kural kondu.
 *
 * ═══ NE ÖLÇER ═══
 *
 * Beş ayrı ayrışma yolu var ve beşi de burada kapalı:
 *  1. Belgedeki ızgara koddan üretilenle aynı mı (bayt bayt).
 *  2. Ekranın okuduğu veri koddan üretilenle aynı mı (bayt bayt).
 *  3. Her iznin kodda gerçekten bir okuyucusu var mı.
 *  4. Kiracı adresli yazma uçları dondurulmuş listeyle aynı mı, ve kiracı
 *     kimliğine bürünen bir uç doğmuş mu.
 *  5. Adı bilerek konmamış yetenekler hâlâ adsız mı.
 */
final class AuthorizationMatrixArtifactTest extends TestCase
{
    private function report(): AuthorizationMatrixReport
    {
        return new AuthorizationMatrixReport(base_path(), Route::getRoutes());
    }

    /**
     * Belgenin üretilen bölgesi, koddan üretilenle BİREBİR aynı olmalı.
     *
     * Bir hücrenin elle düzeltilmesi de, kodda bir izin değişip belgenin
     * güncellenmemesi de aynı yerden yakalanır: ikisi de aynı ayrışmadır.
     */
    public function test_the_document_region_is_exactly_what_the_code_produces(): void
    {
        $document = file_get_contents(base_path(AuthorizationMatrixReport::DOCUMENT_PATH));

        self::assertIsString($document);

        $start = strpos($document, AuthorizationMatrixReport::REGION_START);
        $end = strpos($document, AuthorizationMatrixReport::REGION_END);

        self::assertNotFalse($start, 'docs/139 üretilen bölge işareti kaybolmuş.');
        self::assertNotFalse($end, 'docs/139 üretilen bölgenin kapanış işareti kaybolmuş.');

        $region = substr(
            $document,
            $start,
            $end - $start + strlen(AuthorizationMatrixReport::REGION_END) + 1,
        );

        self::assertSame(
            $this->report()->markdown(),
            $region,
            'docs/139 ile kod ayrıştı. Düzeltme: `php artisan authorization:matrix`. '
                .'Belgedeki bir hücre elle değiştirildiyse doğru düzeltme koddadır, belgede değil.',
        );
    }

    /** Ekranın okuduğu veri de aynı üretimden çıkar. */
    public function test_the_screen_data_file_is_exactly_what_the_code_produces(): void
    {
        $path = base_path(AuthorizationMatrixReport::DATA_PATH);

        self::assertFileExists($path, 'Ekip ekranının okuduğu matris dosyası yok.');

        self::assertSame(
            $this->report()->json(),
            file_get_contents($path),
            AuthorizationMatrixReport::DATA_PATH.' ile kod ayrıştı. '
                .'Düzeltme: `php artisan authorization:matrix`.',
        );
    }

    /**
     * Adı konmuş ama hiçbir yerde sorulmayan bir izin, matriste bir yalandır.
     *
     * "Sahip menüyü yayınlayabilir" cümlesi enum'da bir satır olmasından
     * çıkmaz; o satırın bir yerde SORULUYOR olması gerekir. Sorulmayan izin
     * ne verildiğinde bir şey açar ne esirgendiğinde bir şey kapatır.
     */
    public function test_every_named_permission_is_actually_read_somewhere_in_the_code(): void
    {
        $orphans = [];

        foreach ($this->report()->enforcement() as $permission => $files) {
            if ($files === []) {
                $orphans[] = $permission;
            }
        }

        self::assertSame(
            [],
            $orphans,
            'Bu izinler `app/` altında hiçbir yerde okunmuyor; matriste bir hücreyi '
                .'doldururlar ama kodda hiçbir kapıyı korumuyorlar.',
        );
    }

    /**
     * Destek penceresi: kiracı adresli YAZMA uçları dondurulmuş listedir.
     *
     * Üçüncü bir uç eklendiği gün burası kırılır ve o ucun kiracı içeriğine
     * dokunup dokunmadığı konuşulur. Sessizce eklenemez.
     */
    public function test_tenant_addressed_write_endpoints_match_the_frozen_list(): void
    {
        $surface = $this->report()->platformSurface();
        $frozen = array_keys(AuthorizationMatrixReport::TENANT_ADDRESSED_WRITES);

        sort($frozen);
        $observed = $surface['tenantWindowWrite'];
        sort($observed);

        self::assertSame(
            $frozen,
            $observed,
            'Tek bir kiracıyı adlandıran yazma uçları değişti. Yeni bir uç eklendiyse '
                .'gerekçesi `AuthorizationMatrixReport::TENANT_ADDRESSED_WRITES` içine '
                .'yazılmalı ve kiracı İÇERİĞİNE dokunup dokunmadığı açıkça karara '
                .'bağlanmalıdır (docs/139 §3).',
        );
    }

    /**
     * Kiracı olarak oturum açma bu depoda yoktur (`docs/122` §5).
     *
     * Kolay bir impersonation, bir gün kimsenin hatırlamadığı bir erişim
     * olur. Bugün sıfır; sıfır kalmasını bu satır zorlar.
     */
    public function test_no_super_admin_route_impersonates_a_tenant(): void
    {
        self::assertSame(
            [],
            $this->report()->platformSurface()['impersonation'],
            'Süperadmin kapısının arkasında kiracı kimliğine bürünen bir uç belirdi. '
                .'docs/122 §5 bunu en tehlikeli süperadmin yeteneği sayar; eklenecekse '
                .'ayrı bir kararla ve ayrı bir belgeyle eklenir.',
        );
    }

    /**
     * Bir yetkiyi kimseye vermemek ile onu hiç adlandırmamak aynı şey
     * değildir; ikincisi daha güçlüdür (`docs/116` §4).
     */
    public function test_capabilities_that_must_never_exist_are_still_unnamed(): void
    {
        $named = array_map(static fn (Permission $p): string => $p->value, Permission::cases());

        foreach (array_keys(AuthorizationMatrixReport::ABSENT_BY_DESIGN) as $forbidden) {
            self::assertNotContains(
                $forbidden,
                $named,
                sprintf(
                    '`%s` izin listesine eklenmiş. %s',
                    $forbidden,
                    AuthorizationMatrixReport::ABSENT_BY_DESIGN[$forbidden],
                ),
            );
        }
    }
}
