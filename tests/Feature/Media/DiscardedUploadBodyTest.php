<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UPLOAD-TOO-LARGE — gövde çok büyükse sebebi, düzeltilecek alanın yanında.
 *
 * Laravel bunu ZATEN yakalıyor: `ValidatePostSize`, `Content-Length`
 * `post_max_size`'ı aştığında `PostTooLargeException` fırlatır ve 413 döner.
 * Yani tespit eksik değildi — ilk teşhisim yanlıştı.
 *
 * Eksik olan SUNUM. Varsayılan yanıt "The POST data is too large." diyor:
 * hangi alanın sorunlu olduğunu söylemiyor, sınırın ne olduğunu vermiyor ve
 * `errors` taşımadığı için form onu alan hatası olarak gösteremiyor. Sonuç,
 * kullanıcının hangi dosyayı ne kadar küçültmesi gerektiğini bilememesi.
 *
 * Yanıt artık doğrulama hatalarıyla aynı biçimde: `errors.file`. Mesaj,
 * kullanıcının düzelteceği yerin yanında çıkar.
 */ final class DiscardedUploadBodyTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_an_oversized_body_is_reported_as_too_large_not_as_missing(): void
    {
        // Test ortamının `post_max_size` değeri 8M; 100 MB bildirip boş
        // gövde göndermek, PHP'nin gövdeyi attığı durumun aynısıdır.
        $response = $this->actingAs($this->user())
            ->withServerVariables(['CONTENT_LENGTH' => (string) (100 * 1_048_576)])
            ->postJson('/api/workspaces/1/media', []);

        $response->assertStatus(413);

        self::assertStringContainsString(
            'larger than this server accepts',
            (string) $response->json('errors.file.0'),
            'UPLOAD-DISCARDED: hata, dosyanın büyüklüğünü söylemeli.'
        );

        // "Alan zorunludur" DEMEMELİ: alan gönderilmişti.
        self::assertStringNotContainsString(
            'required',
            strtolower((string) $response->json('errors.file.0')),
        );
    }

    public function test_a_normal_request_is_left_alone(): void
    {
        // Sınırın altındaki bir istek bu kontrolden etkilenmez; normal
        // doğrulama işler.
        $response = $this->actingAs($this->user())
            ->withServerVariables(['CONTENT_LENGTH' => '1024'])
            ->postJson('/api/workspaces/1/media', []);

        $response->assertStatus(422);
    }
}
