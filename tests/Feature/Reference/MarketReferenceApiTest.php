<?php

declare(strict_types=1);

namespace Tests\Feature\Reference;

use App\Domain\Tenancy\ValueObject\LocaleCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * REF-MARKET — marka formunun referans verisi.
 *
 * Bu uç nokta, kullanıcıdan `Europe/Istanbul` ve `TRY` yazmasını istemeyi
 * bitirmek için var. Formun sunduğu her değer buradan gelir ve sunucu
 * doğrulaması da aynı kaynağı kullanır; listede olan bir değerin
 * doğrulamadan geçmesi böyle garanti edilir.
 */
final class MarketReferenceApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_it_is_behind_authentication(): void
    {
        $this->getJson('/api/reference/markets')->assertUnauthorized();
    }

    public function test_it_lists_markets_and_currencies_with_human_readable_names(): void
    {
        $response = $this->actingAs($this->user())->getJson('/api/reference/markets');

        $response->assertOk();

        $markets = $response->json('markets');
        $currencies = $response->json('currencies');

        self::assertNotEmpty($markets, 'REF-MARKET: pazar listesi boş.');
        self::assertNotEmpty($currencies, 'REF-MARKET: para birimi listesi boş.');

        // Kullanıcıya kod değil ad gösterilecek; ikisi de dönmeli.
        self::assertArrayHasKey('code', $markets[0]);
        self::assertArrayHasKey('name', $markets[0]);
        self::assertArrayHasKey('symbol', $currencies[0]);
    }

    public function test_it_derives_the_timezone_and_currency_from_a_country(): void
    {
        $response = $this->actingAs($this->user())
            ->getJson('/api/reference/markets?country=TR');

        $response->assertOk();

        self::assertSame('Europe/Istanbul', $response->json('defaults.timezone'));
        self::assertSame('TRY', $response->json('defaults.currency'));
    }

    public function test_it_suggests_the_country_from_a_browser_timezone(): void
    {
        // Tarayıcı kendi saat dilimini biliyor. Kullanıcıya 247 ülkelik bir
        // listeyi boş sunmak yerine muhtemel cevabı önermek, sorulabilecek
        // her şeyi sormamak demektir.
        $response = $this->actingAs($this->user())
            ->getJson('/api/reference/markets?timezone=Europe/Istanbul');

        $response->assertOk();
        self::assertSame('TR', $response->json('suggestedCountry'));
        self::assertSame('TRY', $response->json('defaults.currency'));
    }

    public function test_an_unknown_timezone_produces_no_suggestion_rather_than_a_wrong_one(): void
    {
        $response = $this->actingAs($this->user())
            ->getJson('/api/reference/markets?timezone=Mars/Olympus');

        $response->assertOk();
        self::assertNull($response->json('suggestedCountry'));
        self::assertNull($response->json('defaults'));

        // Liste yine gelir: öneri olmaması, seçim yapılamaması demek değil.
        self::assertNotEmpty($response->json('markets'));
    }

    public function test_the_timezone_list_is_narrowed_to_the_chosen_country(): void
    {
        $turkey = $this->actingAs($this->user())
            ->getJson('/api/reference/markets?country=TR')
            ->json('timezones');

        // ABD'de 29 saat dilimi var; hepsini her ülkede göstermek seçimi
        // kolaylaştırmaz, imkânsızlaştırır.
        self::assertCount(1, $turkey);
        self::assertSame('Europe/Istanbul', $turkey[0]['id']);

        // Kimlik saklanır, etiket gösterilir.
        self::assertStringContainsString('Istanbul', $turkey[0]['label']);
        self::assertStringContainsString('UTC+', $turkey[0]['label']);
    }

    public function test_currency_fraction_digits_are_reported_rather_than_assumed(): void
    {
        $currencies = $this->actingAs($this->user())
            ->getJson('/api/reference/markets')
            ->json('currencies');

        $byCode = [];

        foreach ($currencies as $currency) {
            $byCode[$currency['code']] = $currency;
        }

        // Sabit iki ondalık varsaymak yanlıştır: JPY sıfır kullanır ve o
        // varsayım, yüz katı fiyat gösterir.
        self::assertSame(2, $byCode['TRY']['fractionDigits']);
        self::assertSame(0, $byCode['JPY']['fractionDigits']);
    }

    /**
     * Diller KOD ile değil ADLA sunulur.
     *
     * Marka formu kullanıcıdan dil kodu YAZMASINI istiyordu. `tr` bir kullanıcı
     * dili değil, geliştirici kodudur — ve yanlış yazıldığında sunucu haklı
     * olarak reddeder, kullanıcı ise ne yazması gerektiğini hiçbir yerden
     * öğrenemez. Seçenek sunabilmek için listenin sunucudan gelmesi gerekir.
     */
    public function test_supported_locales_are_offered_with_human_names(): void
    {
        $locales = $this->actingAs($this->user())
            ->getJson('/api/reference/markets')
            ->json('locales');

        self::assertNotEmpty($locales);

        $byCode = [];

        foreach ($locales as $locale) {
            $byCode[$locale['code']] = $locale['name'];
        }

        self::assertSame('Turkish', $byCode['tr'] ?? null);
        self::assertSame('English', $byCode['en'] ?? null);

        // Ad, kodun kendisi DEĞİLDİR: aynı olsaydı listeyi sunmanın bir
        // anlamı kalmazdı.
        foreach ($locales as $locale) {
            self::assertNotSame($locale['code'], $locale['name']);
        }
    }

    /**
     * Sunulan her dil, sunucunun KABUL ETTİĞİ bir dildir.
     *
     * Listede olup doğrulamadan geçmeyen bir seçenek, kullanıcıyı seçtiği
     * şey yüzünden hataya sokardı — ve o hatadan çıkış yolu olmazdı.
     */
    public function test_every_offered_locale_is_accepted_by_the_domain(): void
    {
        $locales = $this->actingAs($this->user())
            ->getJson('/api/reference/markets')
            ->json('locales');

        foreach ($locales as $locale) {
            self::assertContains(
                $locale['code'],
                LocaleCode::supported(),
                "Offered locale {$locale['code']} is not accepted by LocaleCode.",
            );
        }
    }
}
