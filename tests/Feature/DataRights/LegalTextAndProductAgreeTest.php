<?php

declare(strict_types=1);

namespace Tests\Feature\DataRights;

use App\Application\Legal\Port\LegalLibraryPort;
use App\Domain\DataRights\TenantDataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VAAT EDİLEN İLE YAPILAN ÖRTÜŞMELİ — FF-226 (`docs/138` §7).
 *
 * Bu paketten önce ölçülen İKİ çelişki:
 *
 * 1. Gizlilik politikası "When you ask us to delete your account we remove
 *    the data we are not legally required to keep" diyordu; ürünün içinde
 *    silmenin hiçbir yolu yoktu ve "yasal olarak saklamak zorunda
 *    olduklarımız" hiçbir yerde SAYILMIYORDU.
 * 2. KVKK aydınlatması yalnız SAĞLAYICILARIN yurt dışında olabileceğini
 *    söylüyordu; oysa hizmetin kendi sunucusu Almanya'da (2026-09-08
 *    ölçüldü) ve yedekler aynı sunucuda. Yani madde 9 sürekli devredeydi
 *    ve metin bunu söylemiyordu.
 *
 * Bu test o iki cümlenin metinden düşmesini imkânsız kılar. Yön tektir ve
 * bilinçlidir: KOD METNE UYAR. Ama metnin bir OLGUYU eksik söylediği
 * yerde — sunucunun nerede olduğu — düzeltilmesi gereken metindir, çünkü
 * sunucu koda uydurulamaz.
 *
 * Gereksinim: LEGAL-PRODUCT-ERASURE-PATH-01, LEGAL-PRODUCT-RETAINED-02,
 * LEGAL-PRODUCT-HOSTING-03, LEGAL-PRODUCT-NO-EVERYTHING-CLAIM-04.
 */
final class LegalTextAndProductAgreeTest extends TestCase
{
    use RefreshDatabase;

    private function documentText(string $key): string
    {
        foreach (app(LegalLibraryPort::class)->all() as $document) {
            if ($document->key !== $key) {
                continue;
            }

            return strtolower(implode(' ', array_merge(...array_map(
                static fn ($section) => $section->paragraphs,
                $document->sections,
            ))));
        }

        $this->fail("Yasal belge bulunamadı: {$key}.");
    }

    public function test_both_texts_say_the_data_is_stored_outside_turkey(): void
    {
        // Sunucu koda uydurulamaz; metin olguyu söylemek zorundadır.
        foreach (['kvkk', 'privacy'] as $key) {
            $text = $this->documentText($key);

            $this->assertStringContainsString('germany', $text, "[{$key}] barındırma ülkesi yazılı değil.");
            $this->assertStringContainsString('backup', $text, "[{$key}] yedeklerin nerede durduğu yazılı değil.");
        }
    }

    public function test_both_texts_point_at_the_in_product_path_that_now_exists(): void
    {
        foreach (['kvkk', 'privacy'] as $key) {
            $text = $this->documentText($key);

            $this->assertStringContainsString('settings', $text, "[{$key}] ürün içindeki yol adıyla yazılı değil.");
            $this->assertStringContainsString('eras', $text, "[{$key}] silme hakkı ürün içinde anılmıyor.");
        }
    }

    public function test_the_privacy_policy_names_what_survives_an_erasure(): void
    {
        $text = $this->documentText('privacy');

        // "Yasal olarak saklamak zorunda olduklarımız" belirsiz bir
        // ifadeydi. Bugün silici gerçekten bunları saklıyor; metin de
        // adlarını söylüyor.
        $retained = TenantDataScope::retained();

        $this->assertArrayHasKey('invoices', $retained);
        $this->assertArrayHasKey('ledger_entries', $retained);
        $this->assertArrayHasKey('consent_records', $retained);

        $this->assertStringContainsString('invoices', $text);
        $this->assertStringContainsString('accounting entries', $text);
        $this->assertStringContainsString('consent', $text);
    }

    public function test_no_text_claims_that_everything_is_deleted(): void
    {
        foreach (['kvkk', 'privacy', 'terms'] as $key) {
            $text = $this->documentText($key);

            foreach (['delete everything', 'erase everything', 'all your data is deleted'] as $claim) {
                $this->assertStringNotContainsString(
                    $claim,
                    $text,
                    "[{$key}] \"{$claim}\" — mali kayıtlar ve onay defteri silinmiyor; bu cümle yalan olurdu.",
                );
            }
        }
    }

    public function test_no_text_invents_a_retention_period(): void
    {
        /*
            SAKLAMA SÜRESİ HÂLÂ YAZILMIYOR (`docs/124` §6.4, `docs/131`
            §12.6): yasal süreler sahibin hukuki incelemesinden gelir.
            Silmenin geri alınabilir penceresi ise bir ÜRÜN kararıdır ve
            yapılandırmadan gelir; metne bir gün sayısı yazmak, o sayıyı
            iki yerde yaşatmak olurdu.
        */
        foreach (['kvkk', 'privacy'] as $key) {
            $text = $this->documentText($key);

            foreach (['10 years', '5 years', '30 days', '90 days'] as $invented) {
                $this->assertStringNotContainsString($invented, $text, "[{$key}] uydurma süre: {$invented}.");
            }
        }
    }
}
