<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * HEIC FOTOĞRAFA VİDEO CEVABI VERİLMEZ (HEIC-01A).
 *
 * MÜŞTERİ SORUNU. Restoran sahibi menüsündeki yemeği telefonuyla çekiyor.
 * iPhone'un fabrika ayarı HEIC'tir, yani sahibin elindeki dosya `.HEIC`
 * uzantılıdır ve bunu genelde BİLMEZ — o yalnız "fotoğraf" çekmiştir.
 * Yüklüyor ve şunu okuyor: *"Bu ürün video kabul etmiyor."*
 *
 * Cümle iki kez yanlıştı. Dosya video değildi; ve sahibe yapacak hiçbir
 * şey söylemiyordu. Sahip ne yapar: bir daha dener, sonra bir daha,
 * sonunda destek yazar — ya da menüsünü fotoğrafsız bırakır.
 *
 * SEBEP KAPTIR. HEIC ve MP4 AYNI ISO kabını kullanır: ikisinin de 5-8.
 * baytı `ftyp`tir. Alım kapısı yalnız kaba bakıyordu, markaya değil.
 *
 * BU PAKETİN DEĞİŞTİRMEDİĞİ ŞEY. HEIC hâlâ REDDEDİLİR. Bu üründe
 * kanıtlanmış bir HEIC çözücü yok; kabul etmek, açılamayan ve türev
 * üretilemeyen bir satırı "yüklendi" diye göstermek olurdu — sahibin
 * menüsü yine boş kalır, ama bu kez bunu bilmez. Değişen tek şey reddin
 * artık DOĞRU olması ve somut bir çıkış yolu (JPEG'e çevir) vermesidir.
 * Gerçek bir çözücü kanıtlandığı gün kabul kararı AYRI verilir; AVIF de
 * ayrı bir karardır ve bu cümleyi almaz.
 *
 * Requirement IDs: MEDIA-HEIC-NOT-VIDEO-01, MEDIA-HEIC-JPEG-GUIDANCE-01,
 * MEDIA-HEIC-STILL-REFUSED-01, MEDIA-HEIC-CONTENT-BASED-01.
 */
final class HeicUploadGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO_REFUSAL = 'Bu ürün video kabul etmiyor';

    private User $owner;

    private int $workspaceId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Bu dosyanın konusu tarayıcı değil, bayt kapısı (mevcut medya
        // testlerinin deseni — bkz. `TypeAwareUploadLimitTest`).
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        $this->owner = User::factory()->create(['email_verified_at' => now()]);

        $this->workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'heic-'.Str::lower(Str::random(8)), 'state' => 'active',
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $this->workspaceId, 'user_id' => $this->owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- 1. HEIC, VİDEO CEVABINI ALMAZ --------------------------------------

    /**
     * Sahibin telefonundan çıkan fotoğraf: reddediliyor, ama DOĞRU cümleyle.
     *
     * Slot bilerek `itemImage` — yapılandırma o slotta `heic` biçimini
     * listeliyor, yani ret SLOT politikasından gelmiyor. Reddin tek sebebi
     * çözücünün olmaması ve cümlenin bunu söylemesi.
     */
    #[Test]
    public function an_iphone_heic_photograph_is_refused_with_jpeg_guidance_not_a_video_sentence(): void
    {
        $response = $this->uploadHeic('en');

        $response->assertStatus(422, 'MEDIA-HEIC-STILL-REFUSED-01: kanıtlanmış çözücü yokken HEIC kabul edilemez.');

        $message = $this->fileError($response);

        self::assertStringContainsString(
            'JPEG',
            $message,
            'MEDIA-HEIC-JPEG-GUIDANCE-01: ret, sahibe yapacağı şeyi söylemeli — dosyayı JPEG olarak yükle.',
        );
        self::assertStringContainsString(
            'HEIC',
            $message,
            'MEDIA-HEIC-JPEG-GUIDANCE-01: ret, hangi biçimin reddedildiğini adıyla söylemeli.',
        );
        self::assertStringNotContainsString(
            self::VIDEO_REFUSAL,
            $message,
            'MEDIA-HEIC-NOT-VIDEO-01: fotoğrafa video cevabı verilemez; dosya video değil.',
        );
        self::assertStringNotContainsString(
            'video',
            mb_strtolower($message),
            'MEDIA-HEIC-NOT-VIDEO-01: cümlede "video" kelimesi hiç geçmemeli, sahip dosyasını video sanmamalı.',
        );
    }

    /** Türkçe paritesi: aynı yolculuk, sahibin kendi dilinde ve aynı çıkış yolu. */
    #[Test]
    public function the_same_guidance_is_read_in_turkish(): void
    {
        $turkish = $this->fileError($this->uploadHeic('tr'));
        $english = $this->fileError($this->uploadHeic('en'));

        self::assertNotSame(
            $english,
            $turkish,
            'MEDIA-HEIC-JPEG-GUIDANCE-01: Türkçe isteyen sahip İngilizce cümle okumamalı.',
        );
        self::assertStringContainsString(
            'JPEG',
            $turkish,
            'MEDIA-HEIC-JPEG-GUIDANCE-01: çıkış yolu her iki dilde de aynı — JPEG.',
        );
        self::assertStringContainsString(
            'HEIC',
            $turkish,
            'MEDIA-HEIC-JPEG-GUIDANCE-01: Türkçe cümle de reddedilen biçimi adıyla söylemeli.',
        );
        self::assertStringNotContainsString(
            self::VIDEO_REFUSAL,
            $turkish,
            'MEDIA-HEIC-NOT-VIDEO-01: video cevabı hiçbir dilde verilmez.',
        );
    }

    // --- 2. VİDEO CEVABI, GERÇEK VİDEODA YERİNDE DURUYOR ---------------------

    /**
     * Bu paket video kapısını AÇMAZ ve onun cümlesini de çalmaz.
     *
     * MP4 de `ftyp` taşır; ayrım markadan yapıldığı için MP4 ve WebM eski
     * cevabını almaya devam eder.
     */
    #[Test]
    public function real_video_containers_still_receive_the_video_refusal(): void
    {
        $mp4 = $this->fileError($this->uploadRaw(
            $this->isoContainer('isom', ['isom', 'iso2', 'avc1', 'mp42'], str_repeat("\x00", 64)),
            'tanitim.mp4',
            'video/mp4',
        ));

        self::assertStringContainsString(
            self::VIDEO_REFUSAL,
            $mp4,
            'MEDIA-HEIC-NOT-VIDEO-01: MP4 gerçekten videodur ve kendi dürüst cevabını almaya devam etmeli.',
        );

        // Matroska/WebM `ftyp` hiç taşımaz; HEIC kapısı ona dokunmamalı.
        $webm = $this->fileError($this->uploadRaw(
            "\x1A\x45\xDF\xA3".str_repeat("\x00", 64),
            'tanitim.webm',
            'video/webm',
        ));

        self::assertStringContainsString(
            self::VIDEO_REFUSAL,
            $webm,
            'MEDIA-HEIC-NOT-VIDEO-01: WebM de videodur; bu paket onun cevabını değiştirmez.',
        );
    }

    // --- 3. NAZİK CEVAP YALNIZ GERÇEK HEIC'E VERİLİR -------------------------

    /**
     * UZANTI VE MIME YALAN SÖYLEYEBİLİR; BAYTLAR SÖYLEMEZ.
     *
     * `.heic` adı verilmiş bir PHP yükü nazik cevabı ALMAZ — alsaydı, bu
     * cümle bir saldırganın kapıda hangi yolda olduğunu anlamasına yarayan
     * ücretsiz bir ipucu olurdu.
     *
     * Kesilmiş/bozuk bir `ftyp` de aynı: kutunun bildirdiği uzunluk
     * dosyada gerçekten yoksa dosya HEIC sayılmaz ve reddi sürer.
     */
    #[Test]
    public function a_spoofed_or_truncated_body_never_receives_the_heic_guidance(): void
    {
        $spoof = $this->uploadRaw("<?php echo 'yuk'; ?>\n".str_repeat('A', 64), 'yemek.heic', 'image/heic');
        $spoof->assertStatus(422, 'MEDIA-HEIC-CONTENT-BASED-01: uzantısı HEIC olan bir yük yine de reddedilmeli.');
        self::assertStringNotContainsString(
            'JPEG',
            $this->fileError($spoof),
            'MEDIA-HEIC-CONTENT-BASED-01: karar uzantıdan değil baytlardan verilir; sahte gövde nazik cevabı almaz.',
        );

        // Bildirilen `ftyp` kutusu 96 bayt, dosyada o kadar bayt YOK.
        $truncated = $this->uploadRaw(
            pack('N', 96).'ftyp'.'heic'.pack('N', 0).'mif1',
            'yarim.heic',
            'image/heic',
        );
        $truncated->assertStatus(422, 'MEDIA-HEIC-CONTENT-BASED-01: kesilmiş gövde reddedilmeli.');
        self::assertStringNotContainsString(
            'JPEG',
            $this->fileError($truncated),
            'MEDIA-HEIC-CONTENT-BASED-01: okunamayan bir kutu HEIC sayılmaz; fail-closed davranılır.',
        );
    }

    // --- 4. SIRADAN FOTOĞRAF ETKİLENMEZ --------------------------------------

    /** Bu kapı yalnız HEIC'e dokunur: JPEG yükleyen sahip hiçbir fark görmez. */
    #[Test]
    public function an_ordinary_jpeg_is_still_accepted(): void
    {
        $response = $this->actingAs($this->owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$this->workspaceId}/media",
            [
                'file' => UploadedFile::fake()->image('menemen.jpg', 800, 600),
                'altText' => 'Menemen',
                'slot' => 'itemImage',
            ],
        );

        $response->assertStatus(201, 'HEIC kapısı sıradan bir fotoğrafın yolunu kapatmamalı.');
    }

    // --- Yardımcılar ---------------------------------------------------------

    /**
     * Apple'ın ürettiğine benzeyen, en küçük gerçekçi HEIC başlığı.
     *
     * Ana marka `heic`, uyumlu markalar `mif1`/`heic` — telefonun yazdığı
     * sıra. Gövdenin geri kalanı bu kapının konusu değil (hiçbir şey
     * çözülmüyor); kutunun kendi uzunluğunun dosyada gerçekten olması için
     * arkasına bayt eklenir.
     */
    private function uploadHeic(string $locale): TestResponse
    {
        return $this->uploadRaw(
            $this->isoContainer('heic', ['mif1', 'heic'], 'mdat'.str_repeat("\x00", 64)),
            'yemek.heic',
            'image/heic',
            $locale,
        );
    }

    /** @param list<string> $compatible */
    private function isoContainer(string $major, array $compatible, string $tail): string
    {
        $body = $major.pack('N', 0).implode('', $compatible);

        return pack('N', 8 + strlen($body)).'ftyp'.$body.$tail;
    }

    private function uploadRaw(string $body, string $name, string $mime, ?string $locale = null): TestResponse
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'heic');
        file_put_contents($path, $body);

        $headers = ['Accept' => 'application/json'];

        if ($locale !== null) {
            // Dil gerçek bir istemcinin bildirdiği gibi geliyor
            // (`NegotiateLocale` her istekte bunu okur).
            $headers['Accept-Language'] = $locale;
        }

        return $this->actingAs($this->owner)->withHeaders($headers)->post(
            "/api/workspaces/{$this->workspaceId}/media",
            [
                // İstemcinin bildirdiği tür bilerek DOĞRU yazılır: kapının
                // ona güvenmediğini yukarıdaki sahte-gövde testi gösterir.
                'file' => new UploadedFile($path, $name, $mime, null, true),
                'altText' => 'Menemen',
                'slot' => 'itemImage',
            ],
        );
    }

    private function fileError(TestResponse $response): string
    {
        return (string) data_get($response->json(), 'errors.file.0', '');
    }
}
