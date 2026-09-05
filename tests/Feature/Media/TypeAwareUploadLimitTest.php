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
 * YÜKLEME SINIRI TÜRE GÖRE VERİLİR — sahibin isteği, 2026-09-05 (FF-158).
 *
 * MÜŞTERİ SORUNU. Tek düz bir sayı vardı: 30 MB, her tür için aynı
 * (`config/media-slots.php` → `limits.max_bytes`). O sayı iki yönde birden
 * yanlıştı:
 *
 *   - Basılı menüsünü tarayan sahip reddediliyordu. A3 600 DPI bir PNG 40
 *     MB'ı geçer ve bu meşru bir kullanımdır; kırk sayfalık baskıya hazır
 *     bir PDF ise zaten çok daha büyüktür.
 *   - Aynı sayı, 2 MB'lık bir "logo" SVG'sini de kabul ediyordu. Orada
 *     sınır bir kolaylık değildir: `SvgSanitizer` gövdenin tamamını
 *     ayrıştırmak zorundadır, yani sınır doğrudan saldırı yüzeyidir.
 *
 * Bu dosya dört şeyi dondurur:
 *
 *   1. Sınır TÜRE bağlıdır: aynı bayt sayısı görselde reddedilirken
 *      belgede kabul edilir.
 *   2. Ret, KULLANICIYA ne olduğunu söyler — dosyanın boyutunu ve
 *      uygulanan sınırı birlikte, sınırın teknik sebebini anlatmadan.
 *   3. Vektörün sınırı ayrıdır ve görselinkinden çok dardır.
 *   4. Ekran sınırı dosya SEÇİLMEDEN ÖNCE okuyabilir; ve kabul edilmeyen
 *      bir tür için sayı YAYIMLANMAZ (video).
 *
 * Sayıların TAVANI ayrı bir kapının konusudur (`UploadSizeCeilingTest`):
 * hiçbir tür sınırı aktarım zincirinin en dar halkasını aşamaz.
 *
 * Requirement IDs: MEDIA-SIZE-KIND-LIMIT-01, MEDIA-SIZE-REJECT-EXPLAINS-01,
 * MEDIA-SIZE-KIND-PUBLISHED-01, MEDIA-SIZE-NO-VIDEO-NUMBER-01.
 */
final class TypeAwareUploadLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Bu dosyanın konusu tarayıcı değil, bayt kapısı (mevcut medya
        // testlerinin deseni).
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->workspaceId = $this->workspaceFor($this->owner);
    }

    private function workspaceFor(User $user): int
    {
        $id = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'size-'.Str::lower(Str::random(8)), 'state' => 'active',
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $id, 'user_id' => $user->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    // --- 1. AYNI BAYT SAYISI, İKİ FARKLI CEVAP -------------------------------

    /**
     * Sınırın türe bağlı olduğunu KANITLAYAN test.
     *
     * Sayılar burada bilerek küçültülüyor: kanıtlanan şey MEKANİZMADIR
     * (görselin sınırı belgeninkinden dardır), sahibin seçtiği rakamlar
     * değil — onlar aşağıda ayrıca donduruluyor. Gerçek boyutlarla
     * çalışmak testi onlarca megabayt dosya yazmaya zorlardı ve tek
     * kazandığı şey yavaşlık olurdu.
     */
    #[Test]
    public function the_same_byte_count_is_refused_as_an_image_and_accepted_as_a_document(): void
    {
        config([
            'media-slots.limits.max_bytes' => 4 * 1024 * 1024,
            'media-slots.limits.max_bytes_by_kind' => [
                'image' => 512 * 1024,
                'document' => 4 * 1024 * 1024,
                'vector' => 64 * 1024,
            ],
        ]);

        // 1 MB'lık bir fotoğraf: görsel sınırının (512 KB) üstünde.
        $this->uploadImage(kilobytes: 1024, slot: 'itemImage')->assertStatus(
            422,
            'MEDIA-SIZE-KIND-LIMIT-01: görselin sınırı kendi türünden okunmalı.',
        );

        // AYNI ağırlıkta bir belge: belge sınırının (4 MB) altında.
        $this->uploadPdf(padTo: 1024 * 1024)->assertStatus(
            201,
            'MEDIA-SIZE-KIND-LIMIT-01: belgenin sınırı görselinkiyle aynı olmamalı; '
            .'basılı bir menü meşru şekilde büyüktür.',
        );
    }

    // --- 2. RET, NE OLDUĞUNU SÖYLER -----------------------------------------

    /**
     * "Dosya çok büyük" kullanıcıya ne yapacağını söylemez.
     *
     * Kaç MB'a inmesi gerektiğini bilmeden dosyayı küçültemez; hangi
     * sınırın uygulandığını bilmeden de doğru türü yüklediğinden emin
     * olamaz. Cümle sınırın SEBEBİNİ anlatmaz — aktarım zinciri ve
     * tarayıcı sınırları sahibin sorunu değildir.
     */
    #[Test]
    public function the_refusal_names_the_file_size_the_type_and_the_limit(): void
    {
        $response = $this->uploadImage(kilobytes: 26 * 1024, slot: 'itemImage');

        $response->assertStatus(422);

        $message = (string) data_get($response->json(), 'errors.file.0', '');

        self::assertStringContainsString('26 MB', $message, 'Ret, dosyanın KENDİ boyutunu söylemeli.');
        self::assertStringContainsString('25 MB', $message, 'Ret, uygulanan sınırı söylemeli.');
        self::assertStringContainsString('görseller', $message, 'Ret, sınırın hangi tür için olduğunu söylemeli.');
        self::assertStringNotContainsString(
            'ClamAV',
            $message,
            'MEDIA-SIZE-REJECT-EXPLAINS-01: sahip aktarım zincirini bilmek zorunda değil; '
            .'sebep kodda yazar, ekranda değil.',
        );
    }

    // --- 3. VEKTÖRÜN SINIRI AYRIDIR -----------------------------------------

    /**
     * 3 MB'lık bir "logo" görsel sınırının altındadır — ama kabul edilmez.
     *
     * Vektörde sınır, temizleyicinin AYRIŞTIRMAK ZORUNDA olduğu gövdenin
     * büyüklüğüdür. Meşru bir logo bunun onda birine sığar.
     */
    #[Test]
    public function a_vector_that_would_pass_the_image_limit_is_still_refused(): void
    {
        $body = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">'
            .'<title>Zeytin</title>'
            // Patolojik bir gövde: tek bir yolun binlerce kez tekrarı.
            .str_repeat('<path d="M256 32 L480 256 L256 480 L32 256 Z" fill="#0a7d3f"/>', 46_000)
            .'</svg>';

        self::assertGreaterThan(2 * 1024 * 1024, strlen($body), 'Gövde vektör sınırının üstünde olmalı.');
        self::assertLessThan(25 * 1024 * 1024, strlen($body), 'Gövde görsel sınırının ALTINDA kalmalı — testin bütün anlamı bu.');

        $response = $this->uploadRaw($body, 'logo.svg', 'image/svg+xml', 'logo');

        $response->assertStatus(422, 'MEDIA-SIZE-KIND-LIMIT-01: vektörün sınırı görselinkinden bağımsızdır.');
        self::assertStringContainsString(
            'SVG',
            (string) data_get($response->json(), 'errors.file.0', ''),
            'Ret, hangi türün sınırına takıldığını söylemeli.',
        );
    }

    // --- 4. SAHİBİN SEÇTİĞİ RAKAMLAR ----------------------------------------

    /**
     * Rakamlar donduruldu — çünkü bir sayı bir SÖZDÜR.
     *
     * Gerekçeleri `config/media-slots.php` içinde yazılı. Buradaki test,
     * onların sessizce değişmesini engeller: değişecekse gerekçesi de
     * değişmeli ve bu dosya o değişikliği görünür kılar.
     */
    #[Test]
    public function the_shipped_numbers_are_the_ones_the_owner_decided(): void
    {
        $byKind = (array) config('media-slots.limits.max_bytes_by_kind');

        self::assertSame(25 * 1024 * 1024, $byKind['image'] ?? null, 'Görsel: taranmış A3 menüyü kesmemeli.');
        self::assertSame(45 * 1024 * 1024, $byKind['document'] ?? null, 'Belge: aktarım zincirinin izin verdiği en büyük değer.');
        self::assertSame(2 * 1024 * 1024, $byKind['vector'] ?? null, 'Vektör: temizleyicinin ayrıştıracağı yüzeyin kısıtı.');
        self::assertArrayNotHasKey('video', $byKind, 'Video hattı yok; olmayan bir yetenek için sayı yazılmaz.');
    }

    // --- 4b. VİDEO: SAYI YOK, AMA CEVAP VAR ---------------------------------

    /**
     * Sınırsızlık sessizlik demek değildir.
     *
     * Video için bir sayı yazmıyoruz, çünkü hat yok. Ama sahip menüsüne
     * kısa bir tanıtım videosu koymak isteyip MP4'ünü sürüklediğinde bir
     * cevap almalı — ve o cevap "dosyanız bozuk" anlamına gelen genel
     * güvenlik cümlesi OLMAMALI: sahip dosyasında bir sorun sanır, bir daha
     * dener, sonra destek yazar.
     */
    #[Test]
    public function a_real_video_is_refused_with_a_sentence_that_says_what_is_actually_missing(): void
    {
        // Gerçek bir ISO kap başlığı (`ftyp`) — uzantı değil, İÇERİK.
        $body = "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41".str_repeat("\x00", 512);

        $response = $this->uploadRaw($body, 'tanitim.mp4', 'video/mp4', 'itemImage');

        $response->assertStatus(422);

        $message = (string) data_get($response->json(), 'errors.file.0', '');

        self::assertStringContainsString('video', $message, 'Ret, eksik olanın ne olduğunu söylemeli.');
        self::assertDoesNotMatchRegularExpression(
            '/\d+\s*MB/u',
            $message,
            'MEDIA-SIZE-NO-VIDEO-NUMBER-01: video için bir boyut sayısı YAZILMAZ — '
            .'sayı, olmayan bir yeteneğin sözü olurdu.',
        );
    }

    /**
     * Nazik cevap YALNIZ gerçekten videoya verilir.
     *
     * Uzantısı `.mp4` yapılmış bir PHP yükü hâlâ güvenlik cümlesine düşer;
     * yoksa saldırgan, kapının hangi yoldan geçtiğini adlandırmasıyla
     * öğrenirdi.
     */
    #[Test]
    public function a_payload_wearing_a_video_extension_still_falls_to_the_security_sentence(): void
    {
        $response = $this->uploadRaw("<?php echo 'pwned'; ?>", 'tanitim.mp4', 'video/mp4', 'itemImage');

        $response->assertStatus(422);
        self::assertStringNotContainsString(
            'video',
            (string) data_get($response->json(), 'errors.file.0', ''),
        );
    }

    // --- 5. EKRAN SINIRI ÖNCEDEN OKUYABİLİR ---------------------------------

    #[Test]
    public function the_screen_can_read_every_limit_before_a_file_is_chosen(): void
    {
        $response = $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/api/media/slot-policies');

        $response->assertStatus(200);

        $byKind = (array) data_get($response->json(), 'limits.maxBytesByKind', []);

        self::assertSame(25 * 1024 * 1024, $byKind['image'] ?? null);
        self::assertSame(45 * 1024 * 1024, $byKind['document'] ?? null);
        self::assertSame(2 * 1024 * 1024, $byKind['vector'] ?? null);
        self::assertArrayNotHasKey(
            'video',
            $byKind,
            'MEDIA-SIZE-NO-VIDEO-NUMBER-01: uç, kabul edilmeyen bir tür için sayı göndermez — '
            .'gönderseydi ekran "şu boyuta kadar yükleyebilirsiniz" diye yazardı.',
        );
    }

    // --- Yardımcılar ---------------------------------------------------------

    private function uploadImage(int $kilobytes, string $slot): TestResponse
    {
        // `image()` gerçek bir görsel üretir (magic-byte kapısı ondan geçer);
        // `size()` yalnız BİLDİRİLEN boyutu ayarlar. Yirmi altı megabaytlık
        // gerçek bir dosya yazmak testi yavaşlatır, kanıtı değiştirmez.
        $file = UploadedFile::fake()->image('menemen.jpg', 1200, 1200)->size($kilobytes);

        return $this->actingAs($this->owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$this->workspaceId}/media",
            ['file' => $file, 'altText' => 'Menemen', 'slot' => $slot],
        );
    }

    private function uploadPdf(int $padTo): TestResponse
    {
        $drawn = 'BT /F1 12 Tf 40 800 Td (Fiyat listesi) Tj ET';

        $body = "%PDF-1.4\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R >> endobj\n"
            .'4 0 obj << /Length '.strlen($drawn)." >> stream\n".$drawn."\nendstream endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";

        // Dolgu bir PDF YORUMUDUR (`%` ile başlar): gövdeyi büyütür, denetçinin
        // aradığı hiçbir şeyi eklemez.
        if (strlen($body) < $padTo) {
            $body .= '%'.str_repeat('A', $padTo - strlen($body) - 2)."\n";
        }

        return $this->uploadRaw($body, 'fiyat-listesi.pdf', 'application/pdf', 'document');
    }

    private function uploadRaw(string $body, string $name, string $mime, string $slot): TestResponse
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'size');
        file_put_contents($path, $body);

        return $this->actingAs($this->owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$this->workspaceId}/media",
            [
                // İstemcinin bildirdiği tür bilerek DOĞRU yazılır: kapının
                // ona güvenmediğini başka testler gösterir.
                'file' => new UploadedFile($path, $name, $mime, null, true),
                'altText' => 'Belge',
                'slot' => $slot,
            ],
        );
    }
}
