<?php

declare(strict_types=1);

namespace App\Infrastructure\MenuCatalog\Persistence;

use App\Application\MenuCatalog\Dto\MenuScheduleEntry;
use App\Application\MenuCatalog\Exception\LastMenuForLocationException;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuSchedulePort;
use App\Domain\MenuCatalog\MenuState;
use App\Domain\MenuCatalog\ServiceDayTimeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

/**
 * Şubenin günü — okuma ve TEK yazma kapısı.
 *
 * Kapsama kuralı ("boşluk yok / çakışma yok", `docs/109` §7.1) buradaki üç
 * yazma yolunun dışına çıkmaz: `setServiceWindow`, `clearServiceWindow`,
 * `delete`. Üçü de günü bir ÇEMBER olarak görür ve çemberde delik açamaz.
 */
final class EloquentMenuSchedule implements MenuSchedulePort
{
    /** @return list<MenuScheduleEntry> */
    public function forLocation(int $workspaceId, int $locationId): array
    {
        $menus = DB::table('menus')
            ->where('workspace_id', $workspaceId)
            ->where('location_id', $locationId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'state', 'sort_order', 'public_key']);

        if ($menus->isEmpty()) {
            return [];
        }

        $switches = $this->switchesForLocation($locationId);
        $windows = [];
        $servingNow = $this->anchorMenuId($locationId);

        if ($switches !== []) {
            try {
                $timeline = ServiceDayTimeline::fromSwitches($switches);

                /*
                    BİR MENÜNÜN BİRDEN ÇOK YAYI OLABİLİR ve bu normaldir:
                    "Kahvaltı 07–11" dendiğinde ana menü 00:00–07:00 ve
                    11:00–24:00 olmak üzere iki parçaya ayrılır. Yayları
                    üst üste yazsaydık hapın saat ipucu son parçayı
                    gösterir, sahip ana menünün sabahları da açık olduğunu
                    ekranda göremezdi.
                */
                foreach ($timeline->windows() as $window) {
                    $windows[$window['menuId']][] = $window;
                }

                // "Şu an" ŞUBENİN saatidir; hapın üstündeki işaret sahibin
                // misafirle aynı şeyi görmesi içindir.
                $localNow = Carbon::now($this->timezoneForLocation($locationId));
                $servingNow = $timeline->menuIdAt($localNow->hour * 60 + $localNow->minute) ?? $servingNow;
            } catch (InvalidArgumentException) {
                // Bozuk veri ekranı çökertmez: haplar saat ipucu olmadan çizilir.
                $windows = [];
            }
        }

        /*
            SIRA GÜNÜN AKIŞIDIR — VE TEK YERDE, BURADA TÜRETİLİR.

            `menus.sort_order` OLUŞTURMA sırasıdır: akşam menüsünü önce kuran
            sahip hapları "Akşam · Kahvaltı" diye okuyordu, oysa günü
            kahvaltıyla başlıyor. Ekran o an sahibin gününü değil, satırın
            yazılma anını gösteriyordu.

            Bunu bir sürükle-bırak denetimiyle çözmek, sahibe her saat
            değişikliğinden sonra ikinci bir iş yükler ve iki gerçek (saat ve
            sıra) bir gün birbirinden ayrılırdı: "Kahvaltı 07–11" yazan hap,
            akşam menüsünün solunda durmayı sürdürebilirdi. Sıra bu yüzden
            VERİLMEZ, servis başlangıç dakikasından TÜRETİLİR.

            Sıralama yalnız burada yapılır. Ekran da sıralasaydı iki gerçek
            doğardı ve bu liste yarın bir başka tüketiciye gittiğinde sıra
            orada başka türlü çıkardı.

            Anahtar üç basamaklıdır:

            1. Saati olan menüler önce. Rotasyonun DIŞINDA tutulmuş menü
               ("Ramazan kapalı") günün bir yerine düşemez — günde bir yeri
               yoktur. Onları saatlilerin ARDINA koymak, üstelik kendi
               aralarında bugünkü `sort_order` ile bırakmak, mevcut davranışı
               hiç bozmamanın da tek yoludur.
            2. EN ERKEN pencerenin başlangıç dakikası. Bir menünün birden çok
               yayı olabilir (ana menü kahvaltıyla ikiye bölünür); gün bir kez
               okunur, menü de günde bir kez görünür. `$menuWindows[0]` zaten
               en erken yaydır: `windows()` geçişleri dakikaya göre artan
               verir.

               Gece yarısını aşan pencere (22:00–02:00) BAŞLANGICINA göre
               yerleşir, bitişine göre değil; yoksa gece menüsü kahvaltıdan da
               önce gelir ve sahip gününün geceyle başladığını okurdu.
            3. Beraberlik `sort_order`/`id` sırasını korur — sorgu zaten öyle
               geliyor.
        */
        $rows = [];

        foreach ($menus as $menu) {
            $menuWindows = $windows[(int) $menu->id] ?? [];
            $first = $menuWindows[0] ?? null;

            $rows[] = [
                'orderKey' => [
                    $first === null ? 1 : 0,
                    $first['startMinute'] ?? 0,
                    count($rows),
                ],
                'entry' => new MenuScheduleEntry(
                    id: (int) $menu->id,
                    name: (string) $menu->name,
                    state: (string) $menu->state,
                    sortOrder: (int) $menu->sort_order,
                    startsAt: $first === null ? null : ServiceDayTimeline::clockFromMinute($first['startMinute']),
                    endsAt: $first === null ? null : ServiceDayTimeline::clockFromMinute($first['endMinute']),
                    windows: array_map(
                        static fn (array $window): array => [
                            'startsAt' => ServiceDayTimeline::clockFromMinute($window['startMinute']),
                            'endsAt' => ServiceDayTimeline::clockFromMinute($window['endMinute']),
                        ],
                        $menuWindows,
                    ),
                    isServingNow: $servingNow === (int) $menu->id,
                    isAddressAnchor: $menu->public_key !== null,
                ),
            ];
        }

        // Anahtar girişle aynı satırda taşınır: ikisi bir daha eşleştirilmez,
        // yani sıralamadan sonra ayrışmaları mümkün değildir.
        usort($rows, static fn (array $a, array $b): int => $a['orderKey'] <=> $b['orderKey']);

        return array_map(static fn (array $row): MenuScheduleEntry => $row['entry'], $rows);
    }

    /** @return list<array{menuId:int,startMinute:int}> */
    public function switchesForLocation(int $locationId): array
    {
        return DB::table('menu_service_switches')
            ->where('location_id', $locationId)
            ->orderBy('start_minute')
            ->get(['menu_id', 'start_minute'])
            ->map(static fn (stdClass $row): array => [
                'menuId' => (int) $row->menu_id,
                'startMinute' => (int) $row->start_minute,
            ])
            ->all();
    }

    public function anchorMenuId(int $locationId): ?int
    {
        /*
            ÇIPA = şubenin genel adresini taşıyan menü. Adres bir kez
            basıldığı için onu taşıyan satır da hiç değişmez; menüler gelip
            gider, çıpa kalır. Adres hiç atanmamışsa (eski veri) en eski
            menü çıpa sayılır — kimliği en küçük olan, şubenin ilk menüsüdür.
        */
        $id = DB::table('menus')
            ->where('location_id', $locationId)
            ->whereNotNull('public_key')
            ->orderBy('id')
            ->value('id');

        $id ??= DB::table('menus')->where('location_id', $locationId)->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    public function locationIdForMenu(int $menuId): ?int
    {
        $id = DB::table('menus')->where('id', $menuId)->value('location_id');

        return $id === null ? null : (int) $id;
    }

    public function timezoneForLocation(int $locationId): string
    {
        $timezone = (string) (DB::table('locations')->where('id', $locationId)->value('timezone') ?: '');

        // Saat dilimi boşsa UTC: yanlış bir yerel saat uydurmaktansa,
        // sapması bilinen tek bir referans kullanmak yeğdir.
        return $timezone !== '' ? $timezone : 'UTC';
    }

    public function setServiceWindow(int $workspaceId, int $menuId, int $startMinute, int $endMinute): void
    {
        $this->assertMinute($startMinute);
        $this->assertMinute($endMinute);

        DB::transaction(function () use ($workspaceId, $menuId, $startMinute, $endMinute): void {
            $menu = $this->menuOrFail($workspaceId, $menuId);
            $locationId = (int) $menu->location_id;

            // 1. Menünün eski geçişleri düşer: aralığı taşımak, eskisini
            //    bırakmadan yenisini almak değildir.
            DB::table('menu_service_switches')->where('menu_id', $menuId)->delete();

            // 2. BİTİŞ ANINI KİM KAPLIYORDU? Bu soru, menü rotasyondan
            //    çıkarılmış hâldeyken sorulur; cevabı "11:00'de geri
            //    gelecek menü"dür. Sormasaydık 11:00'den sonrası kahvaltıya
            //    kalırdı ve sahip akşam kebap satamazdı.
            $resumeMenuId = $this->menuCoveringMinute($locationId, $endMinute);

            /*
                3. Menü ARALIĞIN TAMAMINI sahiplenir: "Kahvaltı 07:00–11:00"
                demek, o iki saat arasında başka hiçbir menünün başlamaması
                demektir. Aralığın içine düşen geçişler silinir — sahip
                oraya bir menü koymuşsa da, sonuncu söz sahibinin sözüdür.

                Gece yarısını aşan aralıkta (22:00–02:00) "içeri düşmek"
                iki parçadır: 22:00'den gün sonuna ve gün başından 02:00'ye.
                Bu satır olmasaydı 00:00'da duran bir geçiş gece menüsünü
                tam gece yarısında keserdi.

                `start === end` TÜM GÜN demektir ve günün tamamını temizler.
            */
            $this->clearArc($locationId, $startMinute, $endMinute);

            DB::table('menu_service_switches')->insert([
                'location_id' => $locationId,
                'menu_id' => $menuId,
                'start_minute' => $startMinute,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
                4. Bitiş geçişi. `start === end` "TÜM GÜN" demektir ve
                ikinci bir geçiş yazılmaz. Bitiş anında zaten bir geçiş
                varsa ona dokunulmaz: sahibin başka bir menü için verdiği
                karar burada ezilmez.
            */
            $needsResume = $endMinute !== $startMinute
                && $resumeMenuId !== null
                && $resumeMenuId !== $menuId
                && ! DB::table('menu_service_switches')
                    ->where('location_id', $locationId)
                    ->where('start_minute', $endMinute)
                    ->exists();

            if ($needsResume) {
                DB::table('menu_service_switches')->insert([
                    'location_id' => $locationId,
                    'menu_id' => $resumeMenuId,
                    'start_minute' => $endMinute,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('menus')->where('id', $menuId)->update([
                'state' => MenuState::Active->value,
                'updated_at' => now(),
            ]);
        });
    }

    public function clearServiceWindow(int $workspaceId, int $menuId): void
    {
        DB::transaction(function () use ($workspaceId, $menuId): void {
            $this->menuOrFail($workspaceId, $menuId);

            /*
                Geçişler düşer ve bıraktıkları saatler ÖNCEKİ geçişe geri
                döner — çemberde bir yay silinince komşusu genişler, delik
                açılmaz. Son geçiş de düşerse gün bütünüyle çıpa menüsüne
                kalır.
            */
            DB::table('menu_service_switches')->where('menu_id', $menuId)->delete();

            DB::table('menus')->where('id', $menuId)->update([
                'state' => MenuState::Disabled->value,
                'updated_at' => now(),
            ]);
        });
    }

    public function rename(int $workspaceId, int $menuId, string $name): MenuScheduleEntry
    {
        $menu = $this->menuOrFail($workspaceId, $menuId);

        DB::table('menus')->where('id', $menuId)->update(['name' => $name, 'updated_at' => now()]);

        foreach ($this->forLocation($workspaceId, (int) $menu->location_id) as $entry) {
            if ($entry->id === $menuId) {
                return $entry;
            }
        }

        throw MenuCatalogTenantMismatchException::forWorkspace($workspaceId);
    }

    public function delete(int $workspaceId, int $menuId): void
    {
        DB::transaction(function () use ($workspaceId, $menuId): void {
            $menu = $this->menuOrFail($workspaceId, $menuId);
            $locationId = (int) $menu->location_id;

            $survivorId = DB::table('menus')
                ->where('location_id', $locationId)
                ->where('id', '!=', $menuId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('id');

            if ($survivorId === null) {
                throw LastMenuForLocationException::forLocation($locationId);
            }

            $survivorId = (int) $survivorId;

            /*
                ADRES ÇIPASI TAŞINIR. Silinen menü şubenin genel adresini
                taşıyorsa adres hayatta kalan menüye geçer; masadaki basılı
                karekod ve paylaşılmış her bağlantı çalışmaya devam eder.
                Önce boşaltılır, sonra atanır: `public_key` benzersizdir ve
                ters sıra çakışırdı.
            */
            if ($menu->public_key !== null) {
                DB::table('menus')->where('id', $menuId)->update(['public_key' => null]);

                $survivorHasKey = DB::table('menus')->where('id', $survivorId)->whereNotNull('public_key')->exists();

                if (! $survivorHasKey) {
                    DB::table('menus')->where('id', $survivorId)->update([
                        'public_key' => $menu->public_key,
                        'updated_at' => now(),
                    ]);
                }
            }

            // Karekod hedefleri de hayatta kalan menüye taşınır: hedefi
            // silinen bir kod hiçbir yere gitmezdi.
            DB::table('qr_destinations')->where('menu_id', $menuId)->update([
                'menu_id' => $survivorId,
                'updated_at' => now(),
            ]);

            // Yayın işaretçisi ÖNCE düşer: yayınlara işaret ediyor.
            DB::table('menu_publication_current_pointers')->where('menu_id', $menuId)->delete();
            DB::table('menu_publication_schedules')->where('menu_id', $menuId)->delete();
            DB::table('menu_publications')->where('menu_id', $menuId)->delete();
            DB::table('menu_service_switches')->where('menu_id', $menuId)->delete();

            DB::table('menus')->where('id', $menuId)->delete();
        });
    }

    /**
     * `[start, end)` yayındaki geçişleri siler; `start === end` günün
     * tamamını temizler. Gece yarısını aşan yay iki parçada silinir.
     */
    private function clearArc(int $locationId, int $startMinute, int $endMinute): void
    {
        $query = DB::table('menu_service_switches')->where('location_id', $locationId);

        if ($startMinute === $endMinute) {
            $query->delete();

            return;
        }

        if ($startMinute < $endMinute) {
            $query->where('start_minute', '>=', $startMinute)
                ->where('start_minute', '<', $endMinute)
                ->delete();

            return;
        }

        $query->where(static function ($inner) use ($startMinute, $endMinute): void {
            $inner->where('start_minute', '>=', $startMinute)
                ->orWhere('start_minute', '<', $endMinute);
        })->delete();
    }

    /**
     * O dakikayı ŞU ANDAKİ geçişlere göre kim kaplıyor?
     */
    private function menuCoveringMinute(int $locationId, int $minute): ?int
    {
        $switches = $this->switchesForLocation($locationId);

        if ($switches === []) {
            return $this->anchorMenuId($locationId);
        }

        try {
            return ServiceDayTimeline::fromSwitches($switches)->menuIdAt($minute) ?? $this->anchorMenuId($locationId);
        } catch (InvalidArgumentException) {
            return $this->anchorMenuId($locationId);
        }
    }

    private function menuOrFail(int $workspaceId, int $menuId): stdClass
    {
        $menu = DB::table('menus')->where('id', $menuId)->where('workspace_id', $workspaceId)->first();

        if ($menu === null) {
            throw MenuCatalogTenantMismatchException::forWorkspace($workspaceId);
        }

        return $menu;
    }

    private function assertMinute(int $minute): void
    {
        if ($minute < 0 || $minute >= ServiceDayTimeline::MINUTES_PER_DAY) {
            throw new InvalidArgumentException('Service window minute must be within a single day.');
        }
    }
}
