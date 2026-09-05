<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Database\Seeders\PlanCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BİR PLANIN TEK ADI VARDIR (FF-161).
 *
 * MÜŞTERİ SORUNU. Depoda aynı üç plan iki ayrı isim listesiyle duruyordu:
 * fiyat sayfası ve fatura ekranı `plans.name` üzerinden **Starter ·
 * Restaurant · Team** diyordu, medya kotası kartı ise
 * `config/media-quota.php` içindeki ikinci listeden **Free · Standart ·
 * Pro**. "Restaurant" planını satın almış bir restoran sahibi, medya
 * ekranına girdiğinde kendini "Standart" planında buluyordu. Sahip bunu bir
 * hata değil, bir ABONELİK KAYMASI sanar: parasını ödediği plan gitmiş,
 * yerine tanımadığı bir plan gelmiştir. Destek çağrısı da tam bu cümleyle
 * gelir — "planım değişmiş".
 *
 * KATALOG KAZANIR. Plan adı bir TİCARİ karardır: fiyat sayfası, ödeme ve
 * fatura ona bağlıdır ve sahibi onu yarın değiştirebilir (`docs/90`: fiyat
 * şema değildir). Kota tablosu bir MÜHENDİSLİK ayarıdır. Ticari kararın,
 * mühendislik ayarının yan ürünü olmasına izin verilmez.
 *
 * ÇÖZÜM İKİNCİ LİSTEYİ SENKRONLAMAK DEĞİL, YOK ETMEKTİR. İki listeyi
 * elle eşitlemek bugünü düzeltir, yarını düzeltmez: sahibin fiyat
 * sayfasında yaptığı ilk ad değişikliği ayrışmayı geri getirirdi. Bu yüzden
 * kota yapılandırmasında `label` diye bir alan artık YOKTUR ve aşağıdaki
 * test onun geri gelmesini engeller.
 */
final class PlanNameHasOneSourceTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function ownerWorkspace(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slug,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $workspaceId;
    }

    private function subscribe(int $workspaceId, string $planCode): void
    {
        $planId = (int) DB::table('plans')->where('code', $planCode)->value('id');

        DB::table('subscriptions')->insert([
            'workspace_id' => $workspaceId,
            'plan_id' => $planId,
            'state' => 'active',
            'ends_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function planLabelSeenByOwner(User $owner, int $workspaceId): string
    {
        return (string) $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/media/storage-breakdown")
            ->json('totals.planLabel');
    }

    // --- PLAN-NAME-SINGLE-SOURCE-01 ---------------------------------------

    /**
     * Satın alınan adın, medya ekranında da AYNI ad olması.
     *
     * Fatura "Restaurant" diyorsa kota kartı da "Restaurant" der. İkisi
     * arasındaki her fark, sahibin gözünde ödediği şeyin kaybolmasıdır.
     */
    public function test_the_media_quota_card_shows_the_name_the_owner_paid_for(): void
    {
        $this->seed(PlanCatalogueSeeder::class);

        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'plan-adi-restaurant');
        $this->subscribe($workspaceId, 'restaurant');

        self::assertSame(
            'Restaurant',
            $this->planLabelSeenByOwner($owner, $workspaceId),
            'PLAN-NAME-SINGLE-SOURCE-01: fatura "Restaurant" derken kota kartı '
            .'başka bir ad söylüyor. Sahip bunu abonelik kayması sanar.'
        );
    }

    /**
     * Aboneliği olmayan çalışma alanı da ÜCRETSİZ KADEMENİN KATALOG ADINI
     * okur.
     *
     * Ücretsiz kullanıcı da bir gün fiyat sayfasına bakar; orada "Starter"
     * yazıp panelinde "Free" yazsaydı, hangi kademede olduğunu bilemezdi.
     */
    public function test_a_workspace_without_a_subscription_reads_the_free_tier_catalogue_name(): void
    {
        $this->seed(PlanCatalogueSeeder::class);

        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'plan-adi-varsayilan');

        self::assertSame(
            'Starter',
            $this->planLabelSeenByOwner($owner, $workspaceId),
            'Aboneliksiz çalışma alanı en dar kademededir ve o kademenin adı katalogda yazar.'
        );
    }

    /**
     * SAHİBİN AD DEĞİŞİKLİĞİ HER EKRANA GİDER.
     *
     * Bu testin ölçtüğü şey bugünkü üç ad değil, ADIN NEREDEN OKUNDUĞUDUR.
     * Katalogdaki ad değiştirilir ve kota kartı onu söylemek zorundadır;
     * ikinci bir liste hayatta kalsaydı burada eski adı söylerdi.
     */
    public function test_renaming_a_plan_in_the_catalogue_renames_it_on_every_screen(): void
    {
        $this->seed(PlanCatalogueSeeder::class);

        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'plan-adi-yeniden');
        $this->subscribe($workspaceId, 'team');

        DB::table('plans')->where('code', 'team')->update(['name' => 'Ekip']);

        self::assertSame(
            'Ekip',
            $this->planLabelSeenByOwner($owner, $workspaceId),
            'Ad katalogda değişti ama kota kartı eski adı söylüyor: ikinci bir isim listesi yaşıyor.'
        );
    }

    // --- PLAN-NAME-SINGLE-SOURCE-02 ---------------------------------------

    /**
     * İKİNCİ LİSTE GERİ GELEMEZ.
     *
     * Yukarıdaki testler bugünü korur; bu test yarını korur. Kota
     * yapılandırmasına yeniden bir `label` (ya da `name`) alanı eklenirse,
     * ayrışma ilk gün görünmez olur — tek şubeli işletmede yanlış modelin
     * doğru görünmesi gibi. O yüzden alanın YOKLUĞU dondurulur.
     */
    public function test_the_quota_configuration_carries_no_second_name_list(): void
    {
        foreach ((array) config('media-quota.plans', []) as $code => $plan) {
            self::assertIsArray($plan);

            foreach (['label', 'name', 'title'] as $forbidden) {
                self::assertArrayNotHasKey(
                    $forbidden,
                    $plan,
                    "PLAN-NAME-SINGLE-SOURCE-02: kota yapılandırması [{$code}] için ikinci bir ad "
                    ."taşıyor (`{$forbidden}`). Plan adının tek kaynağı plan kataloğudur."
                );
            }
        }
    }

    /**
     * Kota tablosundaki her plan KODU katalogda gerçekten vardır.
     *
     * Ad tek kaynaktan okunduğu için, katalogda karşılığı olmayan bir kod
     * artık adsız kalır: sahip kota kartında insan adı yerine `starter`
     * gibi bir anahtar okurdu. Bu testin işi, o durumu ekrana varmadan
     * yakalamaktır.
     */
    public function test_every_quota_plan_code_exists_in_the_catalogue(): void
    {
        $catalogue = array_keys(PlanCatalogueSeeder::catalogue());

        foreach (array_keys((array) config('media-quota.plans', [])) as $code) {
            self::assertContains(
                (string) $code,
                $catalogue,
                "Kota tablosundaki [{$code}] planının katalogda karşılığı yok; adı olmayan bir plan satılamaz."
            );
        }

        self::assertContains(
            (string) config('media-quota.default'),
            $catalogue,
            'Aboneliksiz çalışma alanının düştüğü varsayılan planın katalogda bir adı olmalı.'
        );
    }
}
