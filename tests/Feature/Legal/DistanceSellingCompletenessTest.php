<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use App\Application\Legal\Port\LegalLibraryPort;
use App\Domain\Legal\LegalDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * DISTANCE-SELLING-01…06 — uzaktan satışın belgeleri EKSİKSİZ (FF-216,
 * `docs/107` Faz 1.2, `docs/131`).
 *
 * ÜRÜN SORUNU. FF-198 sekiz belgeyi yayına aldı ve `/distance-sales` 200
 * dönmeye başladı. Ama Mesafeli Sözleşmeler Yönetmeliği'nin saydığı
 * başlıkların bir kısmı metinde YOKTU: cayma hakkının kullanılamayacağı
 * hâller ayrı bir başlık değildi, ifaya derhâl başlama onayının nasıl
 * alındığı yazmıyordu, sözleşmenin süresi ve yenilenmesi, uzaktan iletişim
 * aracının bedeli ve kabul edilen ödeme yöntemleri hiç geçmiyordu. Bir
 * kebapçı sözleşmeyi okuduğunda cayma hakkının ne zaman sona erdiğini
 * öğrenemiyordu.
 *
 * BU TEST BİR HUKUKİ İNCELEME DEĞİLDİR ve öyle okunmamalıdır. Ölçtüğü tek
 * şey, başlıkların VAR olduğu ve metnin uydurma bir rakam taşımadığıdır.
 * Metnin hukuken yeterli olup olmadığına bir hukukçu karar verir ve o gün
 * gelene kadar her sayfa üstte "pending legal review" der.
 */
final class DistanceSellingCompletenessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Yönetmeliğin saydığı başlıklar → o başlığın metinde aranacak izi.
     *
     * ANAHTAR KELİME DEĞİL, CÜMLE PARÇASI aranıyor: "withdrawal" kelimesi
     * cayma hakkının VAR olduğunu değil, kelimenin geçtiğini kanıtlar. Aranan
     * şey, o başlığın altında söylenmesi gereken OLGU.
     *
     * @return array<string, array{0:string}>
     */
    public static function mandatoryTopics(): array
    {
        return [
            'satıcının kimliği' => ['MERSIS number'],
            'hizmetin temel nitelikleri' => ['permanent QR code'],
            'vergiler dâhil toplam fiyat' => ['with taxes shown as stated in that summary'],
            'ek maliyet yok' => ['no packaging cost'],
            'kabul edilen ödeme yöntemi' => ['not accept bank transfer'],
            'uzaktan iletişim aracının bedeli' => ['ordinary tariff'],
            'ifa' => ['becomes usable in the buyer\'s workspace'],
            'sözleşmenin süresi ve yenilenmesi' => ['moves the end of the subscription forward'],
            'cayma hakkı ve süresi' => ['within fourteen days of its conclusion'],
            'cayma hakkının kullanım usulü' => ['say that you are withdrawing'],
            'cayma hakkının kullanılamayacağı hâller' => ['services performed instantly in an electronic environment'],
            'ifaya derhâl başlama onayı' => ['separate tick box that is never pre-ticked'],
            'şikâyet başvurusu' => ['Complaints about this agreement are sent to the seller'],
            'hakem heyeti ve tüketici mahkemesi' => ['consumer arbitration committee or to the consumer court'],
            'uygulanacak hukuk ve yetkili merci' => ['courts and enforcement offices at the registered seat'],
            'sözleşmenin kaydı' => ['is recorded with the buyer\'s account'],
            'metnin dili' => ['A Turkish text of this agreement is not published yet'],
        ];
    }

    private static function textOf(LegalDocument $document): string
    {
        $parts = [];

        foreach ($document->sections as $section) {
            $parts[] = $section->heading;

            foreach ($section->paragraphs as $paragraph) {
                $parts[] = $paragraph;
            }
        }

        return implode("\n", $parts);
    }

    // --- DISTANCE-SELLING-01: yönetmeliğin başlıkları TAMAM ---------------

    #[DataProvider('mandatoryTopics')]
    public function test_the_distance_sales_agreement_covers_every_mandatory_topic(string $needle): void
    {
        $document = app(LegalLibraryPort::class)->find('distance-sales');

        self::assertNotNull($document);
        self::assertStringContainsString($needle, self::textOf($document),
            'DISTANCE-SELLING-01: mesafeli satış sözleşmesinde bu başlığın karşılığı yok.');
    }

    /**
     * Ön bilgilendirme formu, sözleşmenin ÖZETİ değil ÖNCESİDİR ve aynı
     * olguları kendi diliyle taşır. Burada aranan izler o metnin kendi
     * cümleleridir; sözleşmeninkiler değil.
     *
     * @return array<string, array{0:string}>
     */
    public static function preInformationTopics(): array
    {
        return [
            'satıcı' => ['MERSIS number'],
            'hizmet' => ['permanent QR code'],
            'toplam fiyat' => ['including the taxes shown'],
            'ödeme yöntemi' => ['not bank transfer, not cash, not payment on delivery'],
            'iletişim aracının bedeli' => ['ordinary tariff'],
            'ifa' => ['starts in your workspace as soon as the payment service provider confirms'],
            'süre ve yenileme' => ['moves its end forward by one such period'],
            'cayma hakkı' => ['within fourteen days of its conclusion'],
            'cayma hakkının kullanılamayacağı hâller' => ['services performed instantly in an electronic environment'],
            'derhâl ifa onayı' => ['separate tick box that is never pre-ticked'],
            'şikâyet ve hakem heyeti' => ['consumer arbitration committee or to the consumer court'],
            'formun geçerliliği' => ['This form is shown to you before payment'],
            'metnin dili' => ['A Turkish text is not published yet'],
        ];
    }

    #[DataProvider('preInformationTopics')]
    public function test_the_preliminary_information_form_covers_every_mandatory_topic(string $needle): void
    {
        $document = app(LegalLibraryPort::class)->find('pre-information');

        self::assertNotNull($document);
        self::assertStringContainsString($needle, self::textOf($document),
            'DISTANCE-SELLING-01: ön bilgilendirme formunda bu başlığın karşılığı yok.');
    }

    // --- DISTANCE-SELLING-02: teslimat/ifa AYRI ve BULUNABİLİR ------------

    public function test_the_delivery_terms_are_their_own_document_and_say_nothing_is_shipped(): void
    {
        $document = app(LegalLibraryPort::class)->find('delivery');

        self::assertNotNull($document, 'DISTANCE-SELLING-02: teslimat/ifa belgesi yok.');
        self::assertTrue($document->requiresSellerIdentity);

        $text = self::textOf($document);

        self::assertStringContainsString('no courier and no shipping address', $text);
        self::assertStringContainsString('the moment the paid plan becomes usable in your workspace', $text);

        // Sözleşme bu belgeye ATIF yapar; aynı olguyu ikinci kez tanımlamaz.
        $agreement = app(LegalLibraryPort::class)->find('distance-sales');
        self::assertNotNull($agreement);
        self::assertStringContainsString('Delivery and Performance Terms', self::textOf($agreement));
    }

    // --- DISTANCE-SELLING-03: UYDURMA RAKAM YOK ---------------------------

    /**
     * Metin, yıla göre değişen ya da yapılandırmadan gelen hiçbir sayıyı
     * kendi içine gömmez.
     *
     * Hakem heyeti parasal sınırı her yıl ilan edilir; KDV oranı bir karardır
     * ve sipariş özetinden gelir; abonelik dönemi bir ayardır. Üçünden birini
     * metne yazmak, değiştiği gün sessizce yanlış bir sözleşme bırakırdı.
     */
    public function test_no_document_hardcodes_a_number_that_changes_outside_the_text(): void
    {
        foreach (app(LegalLibraryPort::class)->all() as $document) {
            $text = strtolower(self::textOf($document));

            foreach (['kdv', 'vat rate', '% vat', 'turkish lira', '₺', ' tl.', '18%', '20%', '30 days', '30-day', 'per month'] as $claim) {
                self::assertStringNotContainsString($claim, $text,
                    "DISTANCE-SELLING-03: [{$document->key}] \"{$claim}\" — metnin dışından gelen bir sayı metne gömülmüş.");
            }

            // MERSİS ve vergi numarası şeklinde uzun rakam dizisi: uydurulmuş
            // bir sicil numarası bir tüzel kişiyi YANLIŞ gösterir.
            self::assertDoesNotMatchRegularExpression('/\b\d{10,}\b/', $text,
                "DISTANCE-SELLING-03: [{$document->key}] uzun bir rakam dizisi taşıyor.");
        }
    }

    // --- DISTANCE-SELLING-04: kart markası UYDURULMAZ ---------------------

    /**
     * Kabul edilen ödeme yöntemi sitede GÖRÜNÜR, ama bir kart ya da banka
     * markası YAZILMAZ: hangi kartların kabul edildiği ödeme sağlayıcısının
     * yapılandırmasından türer ve bu depoda öyle bir liste yoktur.
     */
    public function test_the_site_names_the_payment_provider_but_invents_no_card_brand(): void
    {
        // Ödeme yöntemi cümlesi, SATIN ALINABİLİR bir plan varken çizilir:
        // hiç plan yokken "kartla ödenir" demek, olmayan bir yolun sözünü
        // vermek olurdu (`public/partials/pricing.blade.php`).
        DB::table('plans')->insert([
            'name' => 'Pro',
            'code' => 'pro',
            'version' => 1,
            'is_active' => true,
            'sort_order' => 1,
            'entitlements' => json_encode(['menu.publish']),
            'amount_minor' => 149900,
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/pricing')->assertOk()
            ->assertSee('data-payment-methods', false)
            ->assertSee('payment service provider Iyzico', false);

        foreach (['/about', '/pre-information', '/distance-sales'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();

            self::assertStringContainsString('Iyzico', $html, "DISTANCE-SELLING-04: [{$path}] sağlayıcıyı söylemiyor.");

            foreach (['Visa', 'Mastercard', 'Troy', 'American Express', 'Maestro'] as $brand) {
                self::assertStringNotContainsString($brand, $html,
                    "DISTANCE-SELLING-04: [{$path}] uydurulmuş bir kart markası taşıyor.");
            }
        }
    }

    // --- DISTANCE-SELLING-05: kimlik TEK kaynaktan, HER sayfaya ------------

    /**
     * Sahip yedi değeri BİR KEZ girer; `/about`, `/contact` ve sözleşme aynı
     * anda doğru olur. İkinci bir giriş noktası yok.
     */
    public function test_one_entry_point_feeds_every_page_that_names_the_seller(): void
    {
        config([
            'legal.company' => [
                'legal_name' => 'Örnek Yazılım A.Ş.',
                'address' => 'Örnek Mah. 1, İstanbul',
                'mersis' => '0123456789012345',
                'tax_office' => 'Kadıköy',
                'tax_number' => '1234567890',
                'email' => 'legal@example.test',
                'phone' => '+90 212 000 00 00',
            ],
        ]);

        foreach (['/about', '/contact', '/distance-sales', '/pre-information', '/delivery'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();

            foreach (['Örnek Yazılım A.Ş.', '0123456789012345', '+90 212 000 00 00'] as $fact) {
                self::assertStringContainsString($fact, $html,
                    "DISTANCE-SELLING-05: [{$path}] tek kaynaktan gelen [{$fact}] görünmüyor.");
            }
        }

        // `/about` ve `/contact` aynı parçayı çizer: iki liste, iki gerçek olurdu.
        foreach (['/about', '/contact'] as $path) {
            $this->get($path)->assertOk()->assertSee('data-company-identity="complete"', false);
        }
    }

    public function test_the_seller_pages_say_what_is_missing_instead_of_inventing_it(): void
    {
        config(['legal.company' => array_fill_keys(
            ['legal_name', 'address', 'mersis', 'tax_office', 'tax_number', 'email', 'phone'],
            null,
        )]);

        $about = $this->get('/about')->assertOk();
        $about->assertSee('data-company-identity="incomplete"', false);
        $about->assertSee('Not entered yet', false);
        self::assertSame('noindex, nofollow', $about->headers->get('X-Robots-Tag'));

        // İletişim sayfası ARAMA MOTORUNA AÇIK kalır: bir iletişim formu,
        // şirket bilgisi girilmemişken de çalışan bir yoldur ve onu
        // gizlemek, tıkanan birinin bize hiç ulaşamaması demekti.
        $contact = $this->get('/contact')->assertOk();
        $contact->assertSee('data-company-identity="incomplete"', false);
        self::assertNull($contact->headers->get('X-Robots-Tag'));
    }
}
