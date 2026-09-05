<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Media\Port\MenuMediaPort;
use App\Application\Publication\Exception\PublicationPersistenceFailedException;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\Publication\Port\PublicationSchedulePort;
use Illuminate\Console\Command;

/**
 * Vakti gelen zamanlanmış yayınları yayına alır.
 *
 * ZAMANLANMIŞ YAYIN DA BİR YAYINDIR: normal yayınla aynı depoyu kullanır,
 * yeni bir sürüm numarası alır, önceki sürümü `superseded` yapar ve menünün
 * kalıcı adresine (dolayısıyla basılı QR'a) dokunmaz. Ayrı bir "planlı
 * yayın" kavramı üretilseydi, sahip geçmişte iki tür kayıt görür ve
 * hangisine geri dönebileceğini bilemezdi.
 *
 * İKİ KEZ ÇALIŞMAZ: her kayıt önce `claim()` ile sahiplenilir; sahiplenme
 * tek bir atomik `UPDATE ... WHERE state = 'pending'`tir. Dakikada bir
 * çalışan bir zamanlayıcıda üst üste binen koşular sıradan bir olaydır ve
 * aynı menüyü iki kez yayınlamak sahibin geçmişinde iki sahte sürüm
 * bırakırdı.
 */
final class PublishScheduledMenusCommand extends Command
{
    protected $signature = 'zabuno:publish-scheduled-menus';

    protected $description = 'Vakti gelen zamanlanmış menü yayınlarını yayına alır.';

    public function handle(
        PublicationSchedulePort $schedules,
        PublicationRepositoryPort $publications,
        MenuMediaPort $menuMedia,
    ): int {
        foreach ($schedules->due(now()) as $scheduled) {
            if (! $schedules->claim($scheduled->id)) {
                // Başka bir koşu bu kaydı zaten aldı. Sessizce geçmek
                // doğrudur: burada bir hata yok, bir yarış var ve kazananı
                // belli.
                continue;
            }

            try {
                $record = $publications->publish(
                    $scheduled->workspaceId,
                    $scheduled->menuId,
                    $scheduled->locationId,
                    $scheduled->snapshot,
                    $scheduled->scheduledByUserId,
                );
            } catch (PublicationPersistenceFailedException) {
                /*
                    Başarısız yayın MİSAFİRİ ETKİLEMEZ: son başarılı sürüm
                    yayında kalır. Kayıt `failed` işaretlenir ve tekrar
                    denenmez — gece yarısı sessizce tekrar tekrar denemek,
                    sahibin haberi olmadan menüsünü değiştirme ihtimalini
                    saatlerce açık tutardı.
                */
                $schedules->markFailed($scheduled->id);

                continue;
            }

            $menuMedia->recordPublicationUsages(
                $scheduled->workspaceId,
                $record->id,
                $scheduled->visibleItemIds,
                $scheduled->brandId,
            );

            $schedules->markPublished($scheduled->id, $record->id);
        }

        return self::SUCCESS;
    }
}
