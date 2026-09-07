<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DEPLOY-PAGE-LEDGER-12 — kütüğü dolduran adım her dağıtımda koşar.
 *
 * `ContentPageIdentityTest` komutun NE ÜRETTİĞİNİ ölçüyor. Buradaki iki
 * kapı, komutun her dağıtımda tekrar tekrar koşmasının GÜVENLİ olduğunu
 * ölçer — çünkü `docs/128` ile komut artık `docker/entrypoint.sh`'ta
 * yaşıyor ve elle hatırlanan bir komut değil.
 *
 * Elle çalıştırılan bir komutta bu iki soru sorulmamıştı: bir insan
 * komutu yılda bir kez çalıştırır ve sonucuna bakar. Her konteyner
 * açılışında koşan bir komutta ise kimse bakmaz; o yüzden davranışın
 * kendisi sabitlenmeli.
 */
final class ImportSiteMapCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * YENİDEN DAĞITIM, BİR İNSANIN KARARINI GERİ ALMAZ.
     *
     * Komutun sınıf yorumu bunu zaten iddia ediyordu ("yayın durumuna,
     * yayın tarihine ya da geçmişine DOKUNMAZ") ama hiçbir test ölçmüyordu.
     * İddia ile ölçüm arasındaki fark, komut elle çalıştırılırken küçüktü;
     * komut her dağıtımda koştuğu andan itibaren bu iddia, sahibin yayına
     * aldığı her sayfayı ayakta tutan tek şeydir.
     *
     * Kırılma senaryosu somut: `$existing->fill($attributes)` çağrısına bir
     * gün `publication_status` eklenir — belgeden gelen alanları tazelemek
     * masum görünür — ve o günden sonra her deploy, sahibin yayına aldığı
     * her sayfayı `planned`'a düşürür. Site sessizce boşalır.
     */
    public function test_a_redeploy_never_undoes_a_publication_decision(): void
    {
        $this->artisan('site:import-map')->assertSuccessful();

        $page = ContentPage::query()
            ->where('page_key', 'urun.qr-menu')
            ->where('locale', 'en')
            ->first();

        self::assertNotNull($page, 'Kütükte kaynak dil satırı yok; senaryo kurulamıyor.');

        // Bir İNSAN bu sayfayı kalite kapısından geçirdi ve yayına aldı.
        $page->publication_status = PagePublicationStatus::Published->value;
        $page->was_ever_published = true;
        $page->published_at = now();
        $page->save();

        $publishedAt = $page->published_at;

        // Ertesi gün başka bir sebeple bir dağıtım daha yapılır.
        $this->artisan('site:import-map')->assertSuccessful();

        $page->refresh();

        self::assertSame(
            PagePublicationStatus::Published->value,
            $page->publication_status,
            'Dağıtım, sahibin yayına aldığı sayfayı geri aldı.'
        );
        self::assertTrue($page->was_ever_published, 'Yayın geçmişi silindi.');
        self::assertEquals($publishedAt, $page->published_at, 'Yayın tarihi ezildi.');
    }

    /**
     * KAYNAK YOKSA DAĞITIM DURUR — sessizce boş bir kütükle devam etmez.
     *
     * Bu, seçilen çözümün taşıyıcı direği. Site haritası girdisi
     * `.dockerignore` tarafından elenen `docs` dizininde yaşıyor ve imaja
     * yalnız açık bir geri alma satırıyla giriyor
     * (DEPLOY-PAGE-LEDGER-12). O satır bir gün silinirse komut dosyayı
     * bulamaz — ve bulamadığında BAŞARISIZ olmalı.
     *
     * Sessizce `SUCCESS` dönseydi, giriş betiği `set -e` ile devam eder,
     * konteyner açılır, sağlık kontrolü geçer ve deploy YEŞİL görünürdü —
     * bütün kurumsal sitesi olmayan bir sürüm için. Dışarıdan sağlıklı
     * görünen bir arıza, görünmeyen bir arızadan kötüdür.
     */
    public function test_a_missing_source_fails_the_deploy_instead_of_shipping_an_empty_ledger(): void
    {
        $this->artisan('site:import-map', ['--file' => 'docs/bu-dosya-yok.md'])
            ->assertFailed();

        self::assertSame(
            0,
            ContentPage::query()->count(),
            'Komut başarısız olduğunu söylerken yarım bir kütük bırakmış.'
        );
    }
}
