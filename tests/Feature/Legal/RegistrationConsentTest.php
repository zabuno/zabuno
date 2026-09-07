<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use App\Application\Legal\ConsentRecorder;
use App\Application\Legal\Port\LegalLibraryPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * REG-CONSENT-01…05 — kayıt, sözleşme onayı olmadan OLMAZ (FF-198).
 *
 * ÖLÇÜLDÜ: kayıt ekranında hiçbir onay kutusu yoktu ve onayı kaydeden bir
 * tablo yoktu. Yani bir kebapçı hesabını açıyor, biz de onun neyi kabul
 * ettiğini hiçbir yerde tutmuyorduk. Sözleşme onayı bir varsayım değil,
 * bir KAYITTIR: kim, hangi belgenin hangi sürümünü, ne zaman, hangi
 * adresten.
 */
final class RegistrationConsentTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_PASSWORD = 'Cok-Guclu-Parola-1';

    /** @return array<string, string> */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'terms_accepted' => true,
        ], $overrides);
    }

    // --- REG-CONSENT-01: onaysız kayıt 422 -----------------------------------

    public function test_registration_without_accepting_the_terms_is_rejected_and_creates_no_account(): void
    {
        $response = $this->withHeaders($this->jsonHeaders())
            ->post('/register', $this->payload(['terms_accepted' => false]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('terms_accepted');
        self::assertSame(0, User::query()->count(), 'REG-CONSENT-01: onaysız kayıt hesap açmamalı.');
        self::assertSame(0, DB::table('consent_records')->count());
    }

    public function test_the_field_is_required_not_merely_falsy(): void
    {
        $payload = $this->payload();
        unset($payload['terms_accepted']);

        $this->withHeaders($this->jsonHeaders())->post('/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('terms_accepted');
    }

    // --- REG-CONSENT-02: onaylı kayıt iki satır yazar --------------------------

    public function test_an_accepted_registration_records_terms_and_privacy_with_their_current_versions(): void
    {
        $this->withHeaders($this->jsonHeaders())
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'PHPUnit/1.0'])
            ->post('/register', $this->payload())
            ->assertSuccessful();

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
        $records = DB::table('consent_records')->where('user_id', $user->id)->orderBy('document_key')->get();

        self::assertCount(2, $records, 'REG-CONSENT-02: kayıt anında terms + privacy yazılmalı, başka bir şey değil.');
        self::assertSame(['privacy', 'terms'], $records->pluck('document_key')->all());

        $library = app(LegalLibraryPort::class);

        foreach ($records as $record) {
            self::assertSame('registration', $record->kind);
            self::assertSame(1, (int) $record->granted);
            self::assertSame($library->find($record->document_key)?->version, $record->document_version,
                'REG-CONSENT-02: kaydedilen sürüm, o an yayında olan sürüm olmalı.');
            self::assertSame('203.0.113.7', $record->ip);
            self::assertSame('PHPUnit/1.0', $record->user_agent);
            self::assertNotNull($record->recorded_at);
            self::assertNull($record->workspace_id);
        }
    }

    // --- REG-CONSENT-03: ticari ileti izni İSTEĞE BAĞLI, işaretlenirse yazılır --

    public function test_marketing_consent_is_optional_and_recorded_only_when_ticked(): void
    {
        $this->withHeaders($this->jsonHeaders())
            ->post('/register', $this->payload(['marketing_consent' => true]))
            ->assertSuccessful();

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

        $marketing = DB::table('consent_records')
            ->where('user_id', $user->id)
            ->where('document_key', 'marketing-consent')
            ->first();

        self::assertNotNull($marketing, 'REG-CONSENT-03: işaretlenen ticari ileti izni yazılmalı.');
        self::assertSame('marketing', $marketing->kind);
        self::assertSame(1, (int) $marketing->granted);
        self::assertSame(3, DB::table('consent_records')->where('user_id', $user->id)->count());
    }

    public function test_an_unticked_marketing_box_writes_nothing_because_silence_is_not_consent(): void
    {
        $this->withHeaders($this->jsonHeaders())
            ->post('/register', $this->payload(['marketing_consent' => false]))
            ->assertSuccessful();

        self::assertSame(0, DB::table('consent_records')->where('document_key', 'marketing-consent')->count());
    }

    // --- REG-CONSENT-04: ödeme adımının onayı DEFTERE YAZILIR -----------------

    public function test_the_recorder_writes_the_checkout_consents_and_the_immediate_performance_consent(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/checkout', 'POST', [], [], [], ['REMOTE_ADDR' => '198.51.100.9', 'HTTP_USER_AGENT' => 'PHPUnit/1.0']);

        app(ConsentRecorder::class)->recordCheckout($user->id, 42, $request, true);

        $rows = DB::table('consent_records')->where('user_id', $user->id)->orderBy('kind')->orderBy('document_key')->get();

        self::assertSame(
            [['checkout', 'distance-sales'], ['checkout', 'pre-information'], ['immediate_performance', 'distance-sales']],
            $rows->map(static fn ($row): array => [$row->kind, $row->document_key])->all(),
            'REG-CONSENT-04: ifaya derhâl başlama onayı AYRI bir satırdır.',
        );

        self::assertSame(42, (int) $rows->first()->workspace_id);

        // Sürüm çağırandan değil KÜTÜPHANEDEN okunur.
        $agreement = app(LegalLibraryPort::class)->find('distance-sales');
        self::assertNotNull($agreement);

        foreach ($rows as $row) {
            self::assertSame(1, (int) $row->granted);
        }

        self::assertSame(
            $agreement->version,
            $rows->firstWhere('kind', 'immediate_performance')->document_version,
        );
    }

    /**
     * SESSİZLİK ONAY DEĞİLDİR — işaretlenmemiş kutu için satır YAZILMAZ.
     *
     * Bu, ticari ileti izniyle aynı kural ama farklı bir sonuç taşır: bir
     * pazarlama satırının eksikliği yalnız e-posta göndermemek demektir;
     * ifaya derhâl başlama satırının eksikliği, cayma hakkının SÜRDÜĞÜ
     * anlamına gelir.
     */
    public function test_an_unticked_immediate_performance_box_writes_no_row(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/checkout', 'POST', [], [], [], ['REMOTE_ADDR' => '198.51.100.9']);

        app(ConsentRecorder::class)->recordCheckout($user->id, 42, $request, false);

        self::assertSame(
            0,
            DB::table('consent_records')->where('user_id', $user->id)->where('kind', 'immediate_performance')->count(),
        );
        self::assertSame(2, DB::table('consent_records')->where('user_id', $user->id)->count());
    }

    // --- REG-CONSENT-05: kayıt formu onay kutularını ve bağlantıları taşır -----

    public function test_the_register_screen_ships_the_consent_strings_in_its_catalog(): void
    {
        $catalog = (string) file_get_contents(resource_path('js/i18n/auth.ts'));

        foreach (['auth.register.terms', 'auth.register.marketing', 'auth.register.error.terms'] as $key) {
            self::assertStringContainsString("'{$key}'", $catalog, "REG-CONSENT-05: [{$key}] kayıt kataloğunda yok.");
        }
    }
}
