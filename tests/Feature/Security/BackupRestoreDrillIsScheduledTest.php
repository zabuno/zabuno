<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * FF-199 (docs/124) — "denenmemiş bir yedek, yedek değildir" — ve
 * denenmesi bir insanın hatırlamasına bırakılmaz.
 *
 * Bu test yalnız TANIMI kilitler: zamanlayıcıda günde bir kez, üst üste
 * binmeden koşan bir tatbikat girdisi var. Tatbikatın ÇALIŞIP ÇALIŞMADIĞI
 * buradan okunmaz; yalnız kanıt kaydından okunur
 * (`backup_restore_evidence`, `media_backup_restore_evidence`). Bu test
 * yeşilken de üretimde hiç tatbikat yapılmamış olabilir — o iddia bu
 * testin değil, kanıt ucunun işidir.
 *
 * Requirement IDs: SEC-BR-SCHEDULED-01.
 */
final class BackupRestoreDrillIsScheduledTest extends TestCase
{
    private const COMMAND = 'security:evidence:backup-restore';

    /**
     * @return list<Event>
     */
    private function drillEvents(): array
    {
        $schedule = $this->app->make(Schedule::class);

        return array_values(array_filter(
            $schedule->events(),
            static fn ($event): bool => str_contains((string) $event->command, self::COMMAND),
        ));
    }

    public function test_the_drill_is_scheduled_exactly_once(): void
    {
        self::assertCount(
            1,
            $this->drillEvents(),
            'SEC-BR-SCHEDULED-01: `'.self::COMMAND.'` zamanlayıcıda tam olarak bir kez olmalı.'
        );
    }

    public function test_the_drill_runs_once_a_day_not_every_minute(): void
    {
        foreach ($this->drillEvents() as $event) {
            self::assertMatchesRegularExpression(
                '/^\d{1,2} \d{1,2} \* \* \*$/',
                $event->expression,
                'SEC-BR-SCHEDULED-01: tatbikat gün ölçeğinde bir iştir (her gün, sabit saatte).'
            );
        }
    }

    public function test_the_drill_never_overlaps_itself(): void
    {
        foreach ($this->drillEvents() as $event) {
            self::assertTrue(
                $event->withoutOverlapping,
                'SEC-BR-SCHEDULED-01: uzun süren bir tatbikat, ertesi koşuyla üst üste binmemeli.'
            );
        }
    }

    /**
     * Varsayılan kapsam (veritabanı + medya) zamanlayıcıya kopyalanmaz:
     * girdi bir `--media`/`--database` daraltması taşımaz.
     */
    public function test_the_scheduled_drill_covers_both_database_and_media(): void
    {
        foreach ($this->drillEvents() as $event) {
            self::assertStringNotContainsString('--media', (string) $event->command);
            self::assertStringNotContainsString('--database', (string) $event->command);
        }
    }
}
