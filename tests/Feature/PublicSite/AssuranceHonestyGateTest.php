<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Application\Assurance\Port\AssuranceLibraryPort;
use App\Domain\Assurance\AssuranceStatement;
use App\Domain\Assurance\ClaimState;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * DÜRÜSTLÜK KAPISI — FF-252.
 *
 * ══ NEDEN BU KAPI VAR ══════════════════════════════════════════════════
 *
 * Güven merkezi ve erişilebilirlik beyanı, bir ürünün en kolay yalan
 * söylediği iki sayfadır ve yalanın biçimi ikisinde de aynıdır: ölçülmemiş
 * bir şey, ölçülmüş bir şeyle aynı cümle yapısında yazılır.
 *
 *   · *"WCAG 2.1 AA uyumludur"* — kimse ölçmemiştir.
 *   · Bir ISO ya da SOC rozeti — kimse denetlememiştir.
 *
 * Bu iki cümlenin ortak özelliği, yazıldıkları gün kimsenin itiraz
 * etmemesidir: ikisi de doğru GÖRÜNÜR ve ikisini de bir insan dikkati
 * yakalamak zorundadır. Bir insan dikkati, altı ay sonra bir başkasının
 * eklediği tek bir paragrafta bulunmaz.
 *
 * ══ KURAL: BİR STANDARDIN ADI YALNIZ İNKÂRDA GEÇEBİLİR ═════════════════
 *
 * Kapı sözcük saymaz, YAPI okur. Çizilen HTML'den `not-held` ve
 * `not-measured` hâlindeki iddialar ÇIKARILIR; kalan metinde yasaklı
 * kalıplardan biri bulunursa test kırılır. Yani:
 *
 *   ✓ "ISO 27001 belgemiz yok"       — `not-held` düğümünün içinde, serbest
 *   ✗ "ISO 27001 belgeliyiz"         — düz metinde, KIRILIR
 *   ✓ "WCAG 2.1 AA compliant demek…" — inkâr eden iddianın içinde, serbest
 *   ✗ "WCAG 2.1 AA compliant"        — bir bölüm paragrafında, KIRILIR
 *
 * Ayrım bir sıfat değil bir TÜRDÜR (`ClaimState`) ve bir kapı türü okuyabilir.
 *
 * ══ İKİNCİ KURAL: "ÖLÇÜLÜYOR" DEMEK, ÖLÇENİ GÖSTERMEKTİR ═══════════════
 *
 * `Measured` ve `KnownGap` hâlindeki her iddia bir kanıt adı taşır ve bu
 * kapı o adı DOSYA SİSTEMİNDE ya da kayıtlı artisan komutları arasında
 * arar. Bir kapı silindiğinde ya da yeniden adlandırıldığında sayfa
 * sessizce eskimez — burası kırılır.
 *
 * Requirement ID'leri: GÜVENCE-DÜRÜST-01…05.
 */
final class AssuranceHonestyGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ÖLÇÜLMEDEN YAZILAMAYACAK KALIPLAR.
     *
     * Hepsi bir DENETİM SONUCU iddia eder. Bu depoda öyle bir denetim yok;
     * bir gün olursa, o gün kaldırılacak olan şey bu satır değil, iddianın
     * hâli olur (`ClaimState::NotHeld` → `Measured`, kanıtıyla birlikte) ve
     * kapı kendiliğinden izin verir.
     *
     * @return list<array{0:string,1:string}> desen → neden yasak
     */
    private function forbiddenClaims(): array
    {
        return [
            ['/\bISO(?:\/IEC)?[\s-]?27\d{3}\b/i', 'bir bilgi güvenliği sertifikası iddiası'],
            ['/\bSOC[\s-]?2\b/i', 'bir hizmet denetim raporu iddiası'],
            ['/\bPCI[\s-]?DSS\b/i', 'bir kart verisi standardı iddiası'],
            ['/\bHIPAA\b/i', 'bir sağlık verisi rejimi iddiası'],
            ['/\bcertified\b/i', 'belgelendirilmiş olma iddiası'],
            ['/\baccredited\b/i', 'akredite olma iddiası'],
            ['/\bcompliant\b/i', 'uyumluluk iddiası'],
            ['/\bconformant\b/i', 'uygunluk iddiası'],
            ['/\bconformance\b/i', 'uygunluk beyanı'],
            ['/\bfully accessible\b/i', 'ölçülmemiş bir bütünlük iddiası'],
            ['/\bWCAG[^.]{0,60}\b(?:meets?|satisfies)\b/i', 'ölçülmemiş bir ölçüt karşılama iddiası'],
            /*
                BELİRSİZ GÜVENCE DİLİ. Hiçbiri bir ölçüm değildir ve hepsi
                ölçülmemiş bir şeyi ölçülmüş gibi okutur — sahibin "uydurma
                yok" kuralının en sinsi ihlali, sayı uydurmak değil sıfat
                uydurmaktır.
            */
            ['/\bindustry[- ]standard\b/i', 'ölçüsü olmayan bir kıyas'],
            ['/\bbest practices?\b/i', 'ölçüsü olmayan bir kıyas'],
            ['/\b(?:bank|military|enterprise)[- ]grade\b/i', 'ölçüsü olmayan bir kıyas'],
            ['/\bcoming soon\b/i', 'bir "yakında" vaadi'],
        ];
    }

    /** @return list<array{0:string}> */
    public static function assurancePaths(): array
    {
        return [['/trust'], ['/accessibility']];
    }

    /** @return list<array{0:string}> */
    public static function statementKeys(): array
    {
        return [['trust'], ['accessibility']];
    }

    private function statement(string $key): AssuranceStatement
    {
        $library = app(AssuranceLibraryPort::class);

        return $key === 'trust' ? $library->trustCentre() : $library->accessibilityStatement();
    }

    // --- GÜVENCE-DÜRÜST-01 -------------------------------------------------

    #[DataProvider('assurancePaths')]
    public function test_no_unmeasured_claim_survives_outside_a_denial(string $path): void
    {
        $response = $this->get($path);
        $response->assertOk();

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.((string) $response->getContent()));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);

        /*
            İNKÂR EDEN DÜĞÜMLERİ ÇIKAR.

            "Sahip değiliz" ve "ölçmedik" hâlleri, bir standardın adının
            geçebildiği TEK yerdir. Onları taramanın dışında bırakmak bir
            gevşetme değil, kuralın kendisi: sayfa neye sahip olmadığını
            söyleyebilmeli, yoksa dürüst olamaz.
        */
        $allowed = [];

        foreach (ClaimState::cases() as $state) {
            if ($state->mayNameAStandard()) {
                $allowed[] = '@data-claim-state="'.$state->value.'"';
            }
        }

        $denials = $xpath->query('//*['.implode(' or ', $allowed).']');
        self::assertNotFalse($denials);
        self::assertGreaterThan(
            0,
            $denials->length,
            "GÜVENCE-DÜRÜST-01: [{$path}] tek bir eksiklik itirafı bile taşımıyor. ".
            'Her şeyin ölçüldüğü bir sayfa, ölçümü değil itirafı eksik olan bir sayfadır.',
        );

        foreach (iterator_to_array($denials) as $denial) {
            if ($denial instanceof DOMElement) {
                $denial->parentNode?->removeChild($denial);
            }
        }

        $remaining = (string) $dom->textContent;

        foreach ($this->forbiddenClaims() as [$pattern, $why]) {
            self::assertSame(
                0,
                preg_match($pattern, $remaining),
                "GÜVENCE-DÜRÜST-01: [{$path}] ölçülmemiş bir iddia taşıyor ({$why}, desen {$pattern}). ".
                'Böyle bir cümle yalnız "sahip değiliz" ya da "ölçmedik" hâlindeki bir iddianın '.
                'içinde geçebilir; ölçüldüğü gün hâli ve kanıtı değişir, bu liste değil.',
            );
        }
    }

    // --- GÜVENCE-DÜRÜST-02 -------------------------------------------------

    #[DataProvider('statementKeys')]
    public function test_every_measured_claim_names_evidence_that_actually_exists(string $key): void
    {
        $commands = array_keys(Artisan::all());
        $checked = 0;

        foreach ($this->statement($key)->claims() as $claim) {
            if (! $claim->state->requiresEvidence()) {
                continue;
            }

            $evidence = (string) $claim->evidence;
            $checked++;

            if (str_starts_with($evidence, 'artisan ')) {
                self::assertContains(
                    substr($evidence, strlen('artisan ')),
                    $commands,
                    "GÜVENCE-DÜRÜST-02: [{$key}] \"{$claim->subject}\" kanıt olarak {$evidence} gösteriyor ".
                    'ama böyle bir komut kayıtlı değil. Var olmayan bir kanıt, kanıt değildir.',
                );

                continue;
            }

            self::assertFileExists(
                base_path($evidence),
                "GÜVENCE-DÜRÜST-02: [{$key}] \"{$claim->subject}\" kanıt olarak {$evidence} gösteriyor ".
                'ama o dosya depoda yok. Bir kapı silindiğinde ya da yeniden adlandırıldığında bu '.
                'sayfa sessizce eskiyemez.',
            );
        }

        self::assertGreaterThan(0, $checked, "GÜVENCE-DÜRÜST-02: [{$key}] tek bir ölçülmüş iddia taşımıyor.");
    }

    // --- GÜVENCE-DÜRÜST-03 -------------------------------------------------

    public function test_the_accessibility_statement_refuses_the_sentence_it_would_be_easiest_to_write(): void
    {
        /*
            Beyanın kendisi, uyum iddiasında BULUNMADIĞINI söylemek zorunda.
            Susmak yetmez: bir okuyucu, uyum cümlesi olmayan bir sayfayı
            "yazmayı unutmuşlar" diye de okuyabilir. Söylenmemiş bir "yok"
            her zaman okuyucunun aleyhine çalışır.
        */
        $denials = [];

        foreach ($this->statement('accessibility')->claims() as $claim) {
            if ($claim->state === ClaimState::NotHeld) {
                $denials[] = $claim->detail;
            }
        }

        self::assertNotSame([], $denials, 'GÜVENCE-DÜRÜST-03: beyan hiçbir şeyi inkâr etmiyor.');

        $joined = implode(' ', $denials);

        self::assertMatchesRegularExpression(
            '/\bWCAG\b/i',
            $joined,
            'GÜVENCE-DÜRÜST-03: beyan, yapmadığı uyum iddiasını ADIYLA reddetmiyor.',
        );
    }

    // --- GÜVENCE-DÜRÜST-04 -------------------------------------------------

    public function test_the_trust_centre_says_out_loud_that_it_holds_no_badge(): void
    {
        $held = [];

        foreach ($this->statement('trust')->claims() as $claim) {
            if ($claim->state === ClaimState::NotHeld) {
                $held[] = $claim->detail;
            }
        }

        self::assertNotSame([], $held, 'GÜVENCE-DÜRÜST-04: güven merkezi neye sahip OLMADIĞINI hiç söylemiyor.');

        self::assertMatchesRegularExpression(
            '/\bISO(?:\/IEC)?[\s-]?27\d{3}\b/i',
            implode(' ', $held),
            'GÜVENCE-DÜRÜST-04: sahip olmadığımız belgeler adıyla sayılmıyor. '.
            '"Sertifikamız yok" cümlesi, hangi sertifikadan bahsedildiği yazılmadan bir bilgi değildir.',
        );
    }

    // --- GÜVENCE-DÜRÜST-05 -------------------------------------------------

    #[DataProvider('statementKeys')]
    public function test_a_statement_carries_both_what_is_known_and_what_is_not(string $key): void
    {
        /*
            YALNIZ ÖLÇÜLENLERDEN OLUŞAN BİR SAYFA, EKSİĞİ SAKLAYAN BİR
            SAYFADIR. Bu kapı bir denge şartı koymaz — sadece iki kümenin de
            BOŞ OLMADIĞINI ister. Bir gün gerçekten hiçbir eksik kalmazsa, o
            gün bu satır ölçümle birlikte değişecek olan yerdir.
        */
        $states = [];

        foreach ($this->statement($key)->claims() as $claim) {
            $states[$claim->state->value] = true;
        }

        self::assertArrayHasKey(
            ClaimState::Measured->value,
            $states,
            "GÜVENCE-DÜRÜST-05: [{$key}] hiçbir şeyi ölçmüyor.",
        );

        self::assertTrue(
            isset($states[ClaimState::NotMeasured->value]) || isset($states[ClaimState::KnownGap->value]),
            "GÜVENCE-DÜRÜST-05: [{$key}] ne bir eksik ne bir kusur sayıyor. ".
            'Her şeyin yolunda olduğunu söyleyen bir güvence sayfası, ölçmediğini saklıyordur.',
        );
    }
}
