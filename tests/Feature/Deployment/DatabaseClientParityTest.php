<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * DEPLOY-PG-CLIENT-PARITY-14 — uygulama imajındaki `pg_dump`, sunucunun
 * Postgres sürümünden ESKİ olamaz.
 *
 * Yedek tatbikatı (`security:evidence:backup-restore`) `pg_dump` ve
 * `pg_restore`'u uygulama konteynerinden çağırır. 2026-09-08'e kadar imajda
 * ikisi de yoktu: tatbikat "araç bulunamadı, sonuç bilinmiyor" diye kayıt
 * atacaktı — o noktaya gelebilseydi (bkz. SEC-EVIDENCE-NO-GIT-01).
 *
 * "Yok" ile "yanlış sürüm" aynı kapıya çıkar: `pg_dump` kendinden yeni bir
 * sunucuyu REDDEDER ve koşucu bunu ön koşul hatası sayar. Debian bookworm'un
 * kendi paketi 15'tir, sunucu 17. Bu yüzden istemci PGDG deposundan gelir
 * ve büyük sürümü compose'daki `postgres:` etiketinden türetilir: sunucu
 * yükseltildiği gün bu kapı, imaj güncellenmeden düşer.
 */
final class DatabaseClientParityTest extends TestCase
{
    private function read(string $relative): string
    {
        $path = base_path($relative);

        self::assertFileExists($path, "Dağıtım dosyası yok: {$relative}");

        return (string) preg_replace('/^\s*#.*$/m', '', (string) file_get_contents($path));
    }

    public function test_the_image_installs_a_postgres_client_matching_the_server_major_version(): void
    {
        self::assertSame(
            1,
            preg_match('/^\s*image:\s*postgres:(\d+)/m', $this->read('docker-compose.yml'), $server),
            'DEPLOY-PG-CLIENT-PARITY-14: compose içinde `postgres:<major>` imajı okunamadı.'
        );

        $major = (int) $server[1];
        $dockerfile = $this->read('docker/Dockerfile');

        self::assertMatchesRegularExpression(
            '/\bpostgresql-client-'.$major.'\b/',
            $dockerfile,
            "DEPLOY-PG-CLIENT-PARITY-14: sunucu Postgres {$major}; imaj `postgresql-client-{$major}` kurmuyor, tatbikat ya 'araç yok' ya da 'sürüm eski' der."
        );

        // Debian'ın kendi deposu bu sürümü vermez; PGDG deposu eklenmiş
        // olmalı, yoksa `apt-get install` paketi bulamaz ve derleme durur.
        self::assertStringContainsString(
            'apt.postgresql.org',
            $dockerfile,
            'DEPLOY-PG-CLIENT-PARITY-14: PGDG deposu eklenmemiş; bookworm yalnız 15 taşır.'
        );

        // İmza dosyası olmadan depo eklemek, paketi doğrulamadan kurmaktır.
        self::assertStringContainsString(
            'signed-by=',
            $dockerfile,
            'DEPLOY-PG-CLIENT-PARITY-14: PGDG deposu imzasız ekleniyor.'
        );
    }
}
