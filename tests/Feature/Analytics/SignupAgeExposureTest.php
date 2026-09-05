<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TIME TO FIRST QR'IN PAYDASI — `docs/112` §4.1 (`first_publish_completed`).
 *
 * Kullanıcı yolculuğu: Mehmet Usta kaydolur, markasını girer, menüsünü kurar
 * ve karekodunu basar. Bu yolculuğun KAÇ DAKİKA sürdüğü bugüne kadar hiçbir
 * yerde ölçülmüyordu; `docs/110` §7'deki "5 dakika mı 15 dakika mı"
 * tartışması bu sayı olmadan kapanamaz.
 *
 * Burada dondurulan karar, sayının NASIL taşındığıdır: sunucu hesabın YAŞINI
 * dakika olarak söyler, kayıt zaman damgasını değil. Damga gönderilseydi
 * tarayıcı farkı kendi saatine göre hesaplardı ve o saat kullanıcının kendi
 * ayarıdır — yanlış saat dilimi ya da elle geri alınmış bir saat "-180
 * dakikada yayınladı" gibi bir satır üretir ve ortalamayı sessizce bozar.
 *
 * Gereksinim: ANALYTICS-SIGNUP-AGE-01.
 */
final class SignupAgeExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_payload_carries_the_account_age_in_minutes(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'created_at' => now()->subMinutes(47),
        ]);

        $response = $this->actingAs($user)->getJson('/api/user');

        $response->assertOk();
        $response->assertJsonPath('signedUpMinutesAgo', 47);
    }

    /**
     * Kişisel veri sızmaz.
     *
     * Yaş bir SÜREdir; kayıt tarihi ise kişiyi tanımlamaya yarayan bir
     * damgadır ve `dataLayer` üzerinden üçüncü taraflara akacak bir gövdede
     * işi yoktur (`docs/112` §3.1).
     */
    public function test_payload_does_not_expose_the_signup_timestamp(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'created_at' => now()->subMinutes(3),
        ]);

        $body = $this->actingAs($user)->getJson('/api/user')->json();

        $this->assertArrayNotHasKey('createdAt', $body);
        $this->assertArrayNotHasKey('created_at', $body);
        $this->assertArrayNotHasKey('signedUpAt', $body);
    }

    /**
     * Yaş asla NEGATİF olmaz.
     *
     * Saat kayması ya da elle geri alınmış bir sunucu saati yüzünden
     * `created_at` gelecekte kalabilir. Negatif bir süre, ortalamayı bozan
     * ve kimsenin fark etmediği türden bir satır üretirdi.
     */
    public function test_a_future_dated_account_never_reports_a_negative_age(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'created_at' => now()->addMinutes(10),
        ]);

        $this->actingAs($user)->getJson('/api/user')->assertJsonPath('signedUpMinutesAgo', 0);
    }
}
