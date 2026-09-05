<?php

declare(strict_types=1);

namespace App\Infrastructure\Publication\Persistence;

use App\Application\Publication\Port\GuestOpeningHoursPort;
use App\Domain\Tenancy\ValueObject\WeeklyOpeningHours;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EloquentGuestOpeningHours implements GuestOpeningHoursPort
{
    /** @return array{hours: WeeklyOpeningHours, isoWeekday: int, minuteOfDay: int}|null */
    public function forMenu(int $workspaceId, int $menuId): ?array
    {
        /*
            Şube menüden bulunur; kiracı koşulu İKİ tabloda birden durur.
            Yalnız `menus.workspace_id` yazsaydık, bir gün başka bir
            çalışma alanına taşınmış bir şube satırı sessizce okunabilirdi.
        */
        $location = DB::table('menus')
            ->join('locations', 'locations.id', '=', 'menus.location_id')
            ->where('menus.id', $menuId)
            ->where('menus.workspace_id', $workspaceId)
            ->where('locations.workspace_id', $workspaceId)
            ->select(['locations.id as location_id', 'locations.timezone as timezone'])
            ->first();

        if ($location === null) {
            return null;
        }

        $rows = DB::table('location_opening_hours')
            ->where('workspace_id', $workspaceId)
            ->where('location_id', (int) $location->location_id)
            ->orderBy('day_of_week')
            ->get();

        // SAATİ GİRİLMEMİŞ ŞUBE, bugün çalışan şubelerin çoğu. Uydurma bir
        // hafta kurmak yerine sessiz kalınır: şerit hiç çizilmez.
        if ($rows->isEmpty()) {
            return null;
        }

        $days = [];

        foreach ($rows as $row) {
            $days[] = [
                'day' => (int) $row->day_of_week,
                'closed' => (bool) $row->is_closed,
                'opens_minute' => $row->opens_minute === null ? null : (int) $row->opens_minute,
                'closes_minute' => $row->closes_minute === null ? null : (int) $row->closes_minute,
            ];
        }

        try {
            $hours = WeeklyOpeningHours::fromArray($days);
        } catch (InvalidArgumentException) {
            /*
                YARIM HAFTA MİSAFİRE HATA GÖSTERTMEZ. Yedi günün altısı
                yazılmış bir kayıt (elle düzeltilmiş bir satır, yarım kalmış
                bir aktarım) alan modelinde meşru olarak reddedilir. Sahibin
                panelinde bu bir hatadır ve orada görünür; masadaki misafirin
                ekranında ise 500'dür — yani menüsünü kaybetmesidir.

                Sessizlik burada yedek bir cevap DEĞİL, doğru cevaptır: eksik
                haftadan "açık mıyız" sorusunun cevabı çıkmaz ve çıkmayan bir
                cevabı uydurmayız.
            */
            return null;
        }

        $timezone = trim((string) $location->timezone);

        // Saat dilimi olmayan bir şube için "şu anda" diye bir an yoktur.
        // Sunucununkine düşmek, Berlin'deki misafire İstanbul'un saatiyle
        // "kapalıyız" dedirtirdi.
        if ($timezone === '') {
            return null;
        }

        /*
            AN, ŞUBENİN SAATİNDE ve TEK SEFERDE okunur.

            Gün ile dakikayı iki ayrı `now()` çağrısından almak yılda birkaç
            kez —tam gece yarısında— farklı iki güne bakardı; hata da tam o
            an, kimsenin bakmadığı saatte ortaya çıkardı.

            Okumanın burada olmasının sebebi katman kuralıdır: uygulama
            katmanı çerçeveye bağımlı olamaz (`GuestOpeningHoursPort`), duvar
            saati ise çerçeveden gelir. Test edilebilirliği de bu sağlar —
            `Carbon::setTestNow()` ile misafirin geldiği an sabitlenebiliyor.
        */
        $localNow = Carbon::now($timezone);

        return [
            'hours' => $hours,
            'isoWeekday' => $localNow->dayOfWeekIso,
            'minuteOfDay' => $localNow->hour * 60 + $localNow->minute,
        ];
    }
}
