<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SEÇTİRİLEN HER YUVANIN BİR ADI VARDIR (FF-224).
 *
 * MÜŞTERİ SORUNU. Sahip menü kataloğunda bir ürünün sunum panelini açtı,
 * "Photo" altında tek seçenek gördü ("No photo") ve sordu: *"Ürünlere,
 * menülere resim yükleme alanı yok?"* Yükleme vardı — Medya ekranında. Ama
 * medya YUVAYA göre yüklenir: sahip Medya ekranını bulup yanlış yuvayı
 * seçerse fotoğrafı menü panelinde yine görünmez.
 *
 * Ürün bu yüzden yuvanın adını ekranda söylüyor. O tarifin yürünebilmesi
 * TEK bir şarta bağlı: Medya ekranının açılır listesinde o yuvanın ADI
 * yazıyor olmalı.
 *
 * ═══ BU KAPI NEDEN VAR ═══
 *
 * Sunucu hangi yuvaların seçtirileceğine karar verir
 * (`ListSlotPoliciesController`, `config/media-slots.php`). Etiketler ise
 * istemci kataloğunda yaşar (`resources/js/i18n/workspace/media.ts`). İki
 * taraf birbirini görmüyor ve ayrışma SESSİZ: eksik bir anahtar `t()`'den
 * KENDİSİ olarak döner, yani açılır listede
 * "workspace.media.upload.field.assetSlot.menuImportSource" yazan bir
 * seçenek belirir. Kimse hata görmez; yalnız sahip okuyamaz.
 *
 * `menuImportSource` tam olarak bu hâldeydi ve ürün onu BAŞKA bir ekranda
 * adıyla ("Import source") istiyordu — tarif edilen yol, tarif edildiği
 * hâliyle yürünemiyordu.
 *
 * Kapı yeni bir yuva eklendiğinde de tutar: yapılandırmaya bir satır
 * eklemek, adını yazmadan bir seçenek doğurmaz.
 */
final class SlotNameIsReadableTest extends TestCase
{
    use RefreshDatabase;

    private const CATALOGUE = 'resources/js/i18n/workspace/media.ts';

    private const KEY_PREFIX = 'workspace.media.upload.field.assetSlot.';

    #[Test]
    public function every_offered_slot_has_a_readable_name_on_the_media_screen(): void
    {
        $catalogue = file_get_contents(base_path(self::CATALOGUE));

        $this->assertIsString(
            $catalogue,
            self::CATALOGUE.' okunamadı; yuva adlarının yaşadığı katalog taşınmış olabilir.',
        );

        foreach ($this->offeredSlotKeys() as $slot) {
            $this->assertStringContainsString(
                "'".self::KEY_PREFIX.$slot."':",
                $catalogue,
                "Yükleme sihirbazı `{$slot}` yuvasını seçtiriyor ama adı yok: ".self::CATALOGUE
                    .' içine `'.self::KEY_PREFIX.$slot.'` eklenmeli. Adı olmayan bir yuva,'
                    .' açılır listede ham anahtar olarak görünür.',
            );
        }
    }

    /**
     * Sunucunun GERÇEKTEN seçtirdiği yuvalar — liste elle yazılmaz, uç
     * noktadan okunur. Elle yazılsaydı bu dosya, ölçtüğünü sandığı şeyden
     * ayrışan ikinci bir yuva listesi olurdu.
     *
     * @return list<string>
     */
    private function offeredSlotKeys(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/media/slot-policies');

        $response->assertOk();

        /** @var array<int, array{key: string}> $slots */
        $slots = $response->json('slots');

        $this->assertNotEmpty($slots, 'Uç nokta hiç yuva döndürmedi; kapı hiçbir şey ölçemez.');

        return array_map(static fn (array $slot): string => $slot['key'], $slots);
    }
}
