<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * UPLOAD-CHAIN — yükleme sınırı zincirdeki her halkada aynı olmalı.
 *
 * Zincir: Caddy → nginx → PHP → uygulama doğrulaması. Bir halka
 * diğerinden düşükse istek orada, uygulamanın hiç göremeyeceği bir yerde
 * ölür ve kullanıcı sebebini öğrenemez.
 *
 * İlk ölçümde zincir şöyleydi: Caddy sınırsız → nginx 32 MB → **PHP 2 MB**
 * → uygulama 50 MB. Yani uygulama 50 MB kabul ettiğini sanıyordu, gerçek
 * tavan 2 MB'ydı — telefonla çekilmiş neredeyse her fotoğraf reddediliyordu.
 */
final class UploadSizeChainTest extends TestCase
{
    /** Uygulamanın ilan ettiği sınır: `StoreMediaRequest` → `max:51199` KB. */
    private const APPLICATION_LIMIT_MB = 50;

    private function read(string $relative): string
    {
        $path = base_path($relative);

        self::assertFileExists($path, "Dosya yok: {$relative}");

        return (string) file_get_contents($path);
    }

    public function test_php_accepts_at_least_what_the_application_promises(): void
    {
        $dockerfile = $this->read('docker/Dockerfile');

        self::assertSame(
            1,
            preg_match('/upload_max_filesize=(\d+)M/', $dockerfile, $upload),
            'UPLOAD-CHAIN: imajda `upload_max_filesize` ayarlanmamış — varsayılan 2M.'
        );

        self::assertGreaterThanOrEqual(
            self::APPLICATION_LIMIT_MB,
            (int) $upload[1],
            'UPLOAD-CHAIN: PHP, uygulamanın kabul ettiğini söylediği dosyayı reddediyor.'
        );
    }

    public function test_the_post_body_has_room_for_the_file_plus_the_other_fields(): void
    {
        $dockerfile = $this->read('docker/Dockerfile');

        preg_match('/upload_max_filesize=(\d+)M/', $dockerfile, $upload);
        preg_match('/post_max_size=(\d+)M/', $dockerfile, $post);

        self::assertNotEmpty($post, 'UPLOAD-CHAIN: `post_max_size` ayarlanmamış.');

        // Aynı istekte alt metin, slot ve CSRF alanı da gidiyor. Eşit
        // olurlarsa tam sınırdaki bir dosya, fazladan alanlar yüzünden
        // reddedilir ve sebebi görünmez.
        self::assertGreaterThan(
            (int) $upload[1],
            (int) $post[1],
            'UPLOAD-CHAIN: gövde sınırı dosya sınırından büyük olmalı.'
        );
    }

    public function test_nginx_does_not_cut_the_request_before_php_sees_it(): void
    {
        preg_match('/client_max_body_size (\d+)m/', $this->read('docker/nginx.conf'), $nginx);
        preg_match('/post_max_size=(\d+)M/', $this->read('docker/Dockerfile'), $post);

        self::assertNotEmpty($nginx, 'UPLOAD-CHAIN: nginx sınırı okunamadı.');

        self::assertGreaterThanOrEqual(
            (int) $post[1],
            (int) $nginx[1],
            'UPLOAD-CHAIN: nginx, PHP kabul edecekken 413 döner ve uygulama hiç haberdar olmaz.'
        );
    }

    public function test_the_proxy_states_a_limit_rather_than_accepting_anything(): void
    {
        $caddyfile = $this->read('docker/Caddyfile');

        self::assertSame(
            1,
            preg_match('/max_size (\d+)MB/', $caddyfile, $caddy),
            'UPLOAD-CHAIN: vekilde gövde sınırı yok; sınırsız gövde sunucunun belleğini doldurabilir.'
        );

        preg_match('/post_max_size=(\d+)M/', $this->read('docker/Dockerfile'), $post);

        self::assertGreaterThanOrEqual(
            (int) $post[1],
            (int) $caddy[1],
            'UPLOAD-CHAIN: vekil, PHP kabul edecekken isteği kesiyor.'
        );
    }
}
