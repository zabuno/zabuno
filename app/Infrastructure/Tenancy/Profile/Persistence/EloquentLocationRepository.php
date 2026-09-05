<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Profile\Persistence;

use App\Application\Tenancy\Profile\Dto\LocationProfile;
use App\Application\Tenancy\Profile\Port\LocationRepositoryPort;
use App\Domain\Tenancy\ValueObject\OpeningHoursDay;
use App\Domain\Tenancy\ValueObject\WeeklyOpeningHours;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentLocationRepository implements LocationRepositoryPort
{
    /**
     * Masa sayısı TEK SORGUDA gelir — `docs/109` §6.4.
     *
     * Şube kartı "N masa" yazıyor. Sayıyı satır satır saymak, beş şubeli bir
     * markada beş ek sorgu (N+1) demekti; bağlı alt sorgu aynı işi bir kez
     * yapar ve sonucu satırın kendisine iliştirir.
     *
     * `dining_tables` için bir Eloquent modeli YOK ve bu paket bir tane
     * eklemiyor: bu tabloyu okuyan kod (`EloquentQrCodeRepository`,
     * `EloquentDiningAreaRepository`) zaten sorgu kurucusunu kullanıyor ve
     * yalnız sayım uğruna yeni bir model, iki farklı erişim biçimi yaratırdı.
     */
    private function baseQuery(int $workspaceId): Builder
    {
        return Location::query()
            ->select('locations.*')
            ->selectSub(
                DB::table('dining_tables')
                    ->selectRaw('count(*)')
                    ->whereColumn('dining_tables.location_id', 'locations.id'),
                'table_count',
            )
            ->where('locations.workspace_id', $workspaceId);
    }

    /**
     * Çalışma saatleri TEK SORGUDA, bütün şubeler için birden okunur.
     *
     * Şube başına ayrı sorgu, kart ızgarasında yeniden bir N+1 açardı —
     * masa sayısı için kapatılan deliğin aynısı. Kiracı sınırı sorgunun
     * KENDİSİNDE durur: `workspace_id` koşulu olmadan, şube kimliği
     * tahmin edilebildiği anda komşunun saatleri okunabilirdi.
     *
     * @param  list<int>  $locationIds
     * @return array<int, WeeklyOpeningHours>
     */
    private function openingHoursFor(int $workspaceId, array $locationIds): array
    {
        if ($locationIds === []) {
            return [];
        }

        $rows = DB::table('location_opening_hours')
            ->where('workspace_id', $workspaceId)
            ->whereIn('location_id', $locationIds)
            ->orderBy('location_id')
            ->orderBy('day_of_week')
            ->get();

        $byLocation = [];

        foreach ($rows as $row) {
            $byLocation[(int) $row->location_id][] = [
                'day' => (int) $row->day_of_week,
                'closed' => (bool) $row->is_closed,
                'opens_minute' => $row->opens_minute === null ? null : (int) $row->opens_minute,
                'closes_minute' => $row->closes_minute === null ? null : (int) $row->closes_minute,
            ];
        }

        return array_map(
            static fn (array $days): WeeklyOpeningHours => WeeklyOpeningHours::fromArray($days),
            $byLocation,
        );
    }

    public function findByWorkspaceAndId(int $workspaceId, int $locationId): ?LocationProfile
    {
        $location = $this->baseQuery($workspaceId)
            ->whereKey($locationId)
            ->first();

        if ($location === null) {
            return null;
        }

        $hours = $this->openingHoursFor($workspaceId, [(int) $location->getKey()]);

        return $this->toProfile($location, null, $hours[(int) $location->getKey()] ?? null);
    }

    /**
     * @return list<LocationProfile>
     */
    public function listByWorkspaceAndBrand(int $workspaceId, int $brandId): array
    {
        $locations = $this->baseQuery($workspaceId)
            ->where('locations.brand_id', $brandId)
            ->orderBy('locations.id')
            ->get();

        $hours = $this->openingHoursFor(
            $workspaceId,
            $locations->map(static fn (Location $location): int => (int) $location->getKey())->all(),
        );

        return $locations
            ->map(fn (Location $location): LocationProfile => $this->toProfile(
                $location,
                null,
                $hours[(int) $location->getKey()] ?? null,
            ))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $workspaceId, int $brandId, array $attributes): LocationProfile
    {
        $location = Location::query()->create([
            ...$attributes,
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
        ]);

        // Yeni açılan şubenin masası HENÜZ YOKTUR; sayım için ikinci bir
        // sorgu atmak, cevabı bilinen bir soruyu tekrar sormak olurdu.
        return $this->toProfile($location, 0);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        int $workspaceId,
        int $locationId,
        array $attributes,
        ?WeeklyOpeningHours $openingHours = null,
    ): ?LocationProfile {
        $location = Location::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($locationId)
            ->first();

        if ($location === null) {
            return null;
        }

        /*
            Adres ve saatler TEK İŞLEMDE yazılır.

            İkisi ayrı yazılsaydı ve ikincisi düşseydi, sahip "kaydettim"
            diyen bir ekrandan yarısı kaydedilmiş bir şubeyle ayrılırdı:
            adresi yeni, saatleri eski. Kaydet düğmesi tek bir söz verir.
        */
        DB::transaction(function () use ($location, $attributes, $workspaceId, $locationId, $openingHours): void {
            $location->update($attributes);

            // `null` DOKUNMA demektir: alanı hiç göndermeyen bir istemci,
            // hiç bilmediği bir veriyi silemez.
            if ($openingHours !== null) {
                $this->replaceOpeningHours($workspaceId, $locationId, $openingHours);
            }
        });

        /*
            Kayıt masa sayısıyla BİRLİKTE geri okunur: kaydedilen form şube
            kartına dönüyor ve kart "N masa" yazıyor. Sayıyı burada sıfır
            saymak, adresi düzeltilen bir şubeyi ekranda kurulumdaymış gibi
            gösterirdi.
        */
        return $this->findByWorkspaceAndId($workspaceId, $locationId);
    }

    /**
     * Hafta SİLİNİP yeniden yazılır.
     *
     * Gün gün karşılaştırıp fark güncellemek daha "verimli" görünürdü ama
     * hafta zaten bütün geliyor (yedi gün ya da hiç): fark hesabı, tek
     * kaynaktan gelen bir bütünü parçalayıp yeniden kurmak olurdu.
     * Silme+yazma, çağıran ne gönderdiyse tabloda tam onun durmasını
     * garanti eder — yarım kalmış eski bir gün geride kalmaz.
     */
    private function replaceOpeningHours(int $workspaceId, int $locationId, WeeklyOpeningHours $hours): void
    {
        DB::table('location_opening_hours')
            ->where('workspace_id', $workspaceId)
            ->where('location_id', $locationId)
            ->delete();

        if ($hours->isEmpty()) {
            return;
        }

        $now = now();

        DB::table('location_opening_hours')->insert(array_map(
            static fn (OpeningHoursDay $day): array => [
                'workspace_id' => $workspaceId,
                'location_id' => $locationId,
                'day_of_week' => $day->day,
                'is_closed' => $day->closed,
                'opens_minute' => $day->opensMinute,
                'closes_minute' => $day->closesMinute,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $hours->days(),
        ));
    }

    private function toProfile(
        Location $location,
        ?int $tableCount = null,
        ?WeeklyOpeningHours $openingHours = null,
    ): LocationProfile {
        return new LocationProfile(
            (int) $location->getKey(),
            (int) $location->workspace_id,
            (int) $location->brand_id,
            (string) $location->display_name,
            (string) $location->country_code,
            (string) $location->timezone,
            (string) $location->city,
            (string) $location->address_line1,
            $location->address_line2,
            $location->postal_code,
            $tableCount ?? (int) ($location->getAttribute('table_count') ?? 0),
            $openingHours,
        );
    }
}
