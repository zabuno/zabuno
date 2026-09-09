<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use App\Application\Legal\ConsentRecorder;
use App\Application\Legal\Port\LegalLibraryPort;
use App\Models\User;
use DOMDocument;
use DOMXPath;
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
            'privacy_acknowledged' => true,
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

        /*
            İKİ SATIR, İKİ KİP (REG-LEGAL-01). Hizmet Koşulları KABUL
            EDİLİR; aydınlatma metni yalnız OKUNDUĞU BEYAN EDİLİR. Defterde
            aynı kipi taşısalardı, aydınlatmaya onay alınmış görünürdü —
            KVKK Kurulu'nun 2026/347 sayılı ilke kararının yasakladığı şey
            tam olarak budur.
        */
        self::assertSame(
            ['privacy_acknowledgement', 'registration'],
            $records->pluck('kind')->all(),
        );

        $library = app(LegalLibraryPort::class);

        foreach ($records as $record) {
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

    // --- REG-LEGAL-01: aydınlatma beyanı AYRI bir alan ve AYRI bir kip ---------

    public function test_registration_without_the_privacy_acknowledgement_is_rejected(): void
    {
        $payload = $this->payload();
        unset($payload['privacy_acknowledged']);

        $this->withHeaders($this->jsonHeaders())->post('/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('privacy_acknowledged');

        self::assertSame(0, User::query()->count());
        self::assertSame(0, DB::table('consent_records')->count());
    }

    public function test_the_privacy_acknowledgement_is_recorded_under_its_own_kind_not_as_a_registration_consent(): void
    {
        $this->withHeaders($this->jsonHeaders())
            ->post('/register', $this->payload())
            ->assertSuccessful();

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

        $privacy = DB::table('consent_records')
            ->where('user_id', $user->id)
            ->where('document_key', 'privacy')
            ->firstOrFail();

        self::assertSame(ConsentRecorder::KIND_PRIVACY_ACKNOWLEDGEMENT, $privacy->kind);
        self::assertSame('privacy_acknowledgement', $privacy->kind);

        $terms = DB::table('consent_records')
            ->where('user_id', $user->id)
            ->where('document_key', 'terms')
            ->firstOrFail();

        self::assertSame(ConsentRecorder::KIND_REGISTRATION, $terms->kind);
    }

    /**
     * REG-LEGAL-02: kayıt SAYFASI metni yanında taşır.
     *
     * Kebapçı, kabul ettiği metni okumak için formu terk etmek zorunda
     * değil: metin sayfayla birlikte geliyor, ayrı bir istekle değil. Sayfa
     * metinsiz çizilirse kutular kalır ama okunacak bir şey kalmaz.
     */
    public function test_the_register_page_ships_the_legal_texts_it_asks_people_to_read(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);

        $payload = $this->legalPayloadFrom((string) $response->getContent());

        // İnceleme notu kayıt ekranına da taşınır (`LegalReview`).
        self::assertIsBool($payload['reviewPending']);
        self::assertSame(
            ['terms', 'privacy', 'marketing-consent'],
            array_keys($payload['documents']),
        );

        $privacy = $payload['documents']['privacy'];
        $library = app(LegalLibraryPort::class);

        // Ekranda okunan sürüm ile deftere yazılan sürüm AYNI olmalı.
        self::assertSame($library->find('privacy')?->version, $privacy['version']);
        self::assertSame('/privacy', $privacy['url']);
        self::assertNotSame([], $privacy['sections']);

        // Şirket yer tutucuları DOLDURULMUŞ olmalı: `{company.legal_name}`
        // gören biri kimle sözleşme yaptığını göremez.
        self::assertStringNotContainsString('{company.', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array{reviewPending: bool, documents: array<string, mixed>} */
    private function legalPayloadFrom(string $html): array
    {
        // Blok, ÖZNİTELİKLERİYLE değil KİMLİĞİYLE bulunur. Etikete bir
        // `nonce` eklendiğinde (CSP) ya da öznitelik sırası değiştiğinde bu
        // yardımcı körleşmemeli; aranan şey hep aynı: `application/json`
        // türünde, `register-legal` kimlikli TEK blok.
        $document = new DOMDocument;

        self::assertTrue(
            $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING),
            'REG-LEGAL-02: kayıt sayfası ayrıştırılamadı.',
        );

        $blocks = (new DOMXPath($document))->query(
            '//script[@id="register-legal" and @type="application/json"]',
        );

        self::assertNotFalse($blocks);
        self::assertSame(
            1,
            $blocks->length,
            'REG-LEGAL-02: kayıt sayfası yasal metin bloğunu taşımalı (tam olarak bir kez).',
        );

        /** @var array{reviewPending: bool, documents: array<string, mixed>} $decoded */
        $decoded = json_decode(
            html_entity_decode((string) $blocks->item(0)?->textContent, ENT_QUOTES),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return $decoded;
    }

    // --- REG-CONSENT-05: kayıt formu onay kutularını ve bağlantıları taşır -----

    public function test_the_register_screen_ships_the_consent_strings_in_its_catalog(): void
    {
        $catalog = (string) file_get_contents(resource_path('js/i18n/auth.ts'));

        $keys = [
            'auth.register.terms',
            'auth.register.privacy',
            'auth.register.marketing',
            'auth.register.error.terms',
            'auth.register.error.privacy',
        ];

        foreach ($keys as $key) {
            self::assertStringContainsString("'{$key}'", $catalog, "REG-CONSENT-05: [{$key}] kayıt kataloğunda yok.");
        }
    }
}
