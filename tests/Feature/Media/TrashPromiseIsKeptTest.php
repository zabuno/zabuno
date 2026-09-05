<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * EKRANDA VERİLEN SÖZ, ZAMANLAYICIDA KARŞILIĞI OLMALI.
 *
 * Medya ekranı sahibe planına göre bir süre söylüyor: "silinen dosya N gün
 * burada bekler, sonra kalıcı silinir." Komut da yazılmıştı
 * (`media:purge-trash`) ve doğru çalışıyordu. Eksik olan tek şey, onu
 * ÇAĞIRAN bir şeydi.
 *
 * Kimse fark etmemişti çünkü hiçbir test kırılmıyordu: komutun kendi
 * testleri komutu doğrudan çağırıyor, yani zamanlayıcının sessizliğini
 * göremiyorlardı. Kusur ancak ürünün SÖZÜYLE zamanlayıcının içeriği yan yana
 * konunca görünüyor — bu test tam olarak onu yapıyor.
 *
 * Sahibin yaşadığı: dosyayı siliyor, çöpte kalıyor, kotadan düşmüyor, yer
 * açmak istediğinde açamıyor ve nedenini hiçbir yerden öğrenemiyor.
 *
 * Requirement IDs: MEDIA-TRASH-PROMISE-SCHEDULED-01.
 */
final class TrashPromiseIsKeptTest extends TestCase
{
    /** Zamanlayıcıya kayıtlı her komutun ifadesi. */
    private function scheduledCommands(): array
    {
        $schedule = $this->app->make(Schedule::class);

        return array_map(
            static fn ($event): string => (string) $event->command,
            $schedule->events(),
        );
    }

    // --- MEDIA-TRASH-PROMISE-SCHEDULED-01 ---------------------------------

    public function test_the_trash_is_actually_emptied_by_something(): void
    {
        $matching = array_values(array_filter(
            $this->scheduledCommands(),
            static fn (string $command): bool => str_contains($command, 'media:purge-trash'),
        ));

        self::assertCount(
            1,
            $matching,
            'MEDIA-TRASH-PROMISE-SCHEDULED-01: `media:purge-trash` zamanlayıcıda tam olarak bir kez olmalı — '
            .'yoksa ekranda verilen "N gün sonra kalıcı silinir" sözü tutulmaz.'
        );
    }

    /**
     * SÜRE ZAMANLAYICIYA KOPYALANMAZ.
     *
     * Saklama süresi plana bağlıdır (7/30/90) ve kota yapılandırmasında
     * yaşar. Zamanlamaya `--days=N` yazmak o kararı ikinci bir yere
     * kopyalamak olurdu; iki gün sonra biri değişir, öteki kalır ve ürün
     * ekranda söylediğinden başka bir süre uygular.
     *
     * Bu tam olarak bir kez yaşandı: `config/media-slots.php` içinde hiçbir
     * kodun okumadığı ölü bir `trash_retention_days => 30` duruyordu ve
     * okuyan birine yanlış cevap veriyordu.
     */
    public function test_the_retention_window_is_not_copied_into_the_schedule(): void
    {
        foreach ($this->scheduledCommands() as $command) {
            if (! str_contains($command, 'media:purge-trash')) {
                continue;
            }

            self::assertStringNotContainsString(
                '--days',
                $command,
                'MEDIA-TRASH-PROMISE-SCHEDULED-01: süre plandan gelir; zamanlamaya sabit gün yazmak '
                .'kota kararını ikinci bir yere kopyalar ve iki kaynak bir gün ayrışır.'
            );
        }
    }

    /**
     * GÜNDE BİR YETER — ve dakikada bir ZARARLIDIR.
     *
     * Saklama süresi gün ölçeğindedir. Dakikada bir taramak aynı sorguyu
     * günde bin dört yüz kez boşuna koşturur; bu, paylaşımlı barındırma
     * tabanının (`docs/15`) taşımak zorunda olmadığı bir yük.
     */
    public function test_the_purge_does_not_run_every_minute(): void
    {
        $schedule = $this->app->make(Schedule::class);

        foreach ($schedule->events() as $event) {
            if (! str_contains((string) $event->command, 'media:purge-trash')) {
                continue;
            }

            self::assertNotSame(
                '* * * * *',
                $event->expression,
                'MEDIA-TRASH-PROMISE-SCHEDULED-01: çöp temizliği gün ölçeğinde bir iştir.'
            );
        }
    }
}
