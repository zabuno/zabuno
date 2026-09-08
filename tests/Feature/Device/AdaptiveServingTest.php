<?php

declare(strict_types=1);

namespace Tests\Feature\Device;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Adaptive yükleme — cihaza göre AYRI paket (docs/54).
 *
 * Bu testlerin ölçtüğü şey bir görsel düzen değil: aynı adresin farklı
 * cihazlara farklı JavaScript göndermesi. Fark önemsiz değildir — medya
 * sorgusuyla yapılan uyarlamada telefon masaüstü düzeninin kodunu da indirir,
 * ayrıştırır ve sonra gizler.
 */
final class AdaptiveServingTest extends TestCase
{
    private function renderWorkspaceShellFor(string $userAgent): string
    {
        $request = Request::create('/app', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => $userAgent,
        ]);

        $this->app->instance('request', $request);

        return view('workspace-app')->render();
    }

    #[Test]
    public function a_phone_is_served_the_mobile_entry_and_never_the_desktop_one(): void
    {
        $html = $this->renderWorkspaceShellFor(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148',
        );

        $this->assertStringContainsString('workspace.mobile', $html);
        $this->assertStringNotContainsString('workspace.desktop', $html);
    }

    #[Test]
    public function a_desktop_is_served_the_desktop_entry_and_never_the_mobile_one(): void
    {
        $html = $this->renderWorkspaceShellFor(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0',
        );

        $this->assertStringContainsString('workspace.desktop', $html);
        $this->assertStringNotContainsString('workspace.mobile', $html);
    }

    /**
     * STİL DE CİHAZA GÖRE AYRILIR (`docs/151`).
     *
     * `docs/54` JavaScript'i ayırmıştı; stil hâlâ tekti. Masaüstünün kendi
     * stil katmanı ayrı bir Vite girişidir ve yalnız masaüstü belgesinden
     * istenir — yani telefon onun tek baytını indirmez.
     *
     * Bu test bir dosya adı aramıyor gibi görünebilir ama ölçtüğü şey somut:
     * `@vite` derlenmiş varlığın adını belgeye yazar ve masaüstü katmanının
     * adı `app.desktop` ile başlar. Telefonun belgesinde o ad geçiyorsa,
     * telefon o dosyayı İSTİYOR demektir; hiçbir kuralı çizilmese bile
     * indirilmiş ve ayrıştırılmıştır.
     */
    #[Test]
    public function a_phone_is_never_served_the_desktop_style_layer(): void
    {
        $html = $this->renderWorkspaceShellFor(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148',
        );

        $this->assertStringNotContainsString('app-desktop', $html);
    }

    #[Test]
    public function a_desktop_is_served_the_desktop_style_layer(): void
    {
        $html = $this->renderWorkspaceShellFor(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0',
        );

        $this->assertStringContainsString('app-desktop', $html);
    }

    /**
     * CİHAZ KARARI BELGEDE OKUNABİLİR.
     *
     * Masaüstü katmanı bütün kurallarını `[data-device='desktop']` kapsamında
     * yazar. Nitelik yazılmazsa katman inen bir dosya olarak durur ama HİÇBİR
     * kuralı uygulanmaz — ve bu, ekranda "stil yok" diye değil, "sanki hiç
     * eklenmemiş" diye görünür. En pahalı arıza türü: sessiz.
     */
    #[Test]
    public function the_document_carries_the_server_side_device_decision(): void
    {
        $desktop = $this->renderWorkspaceShellFor(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0',
        );
        $phone = $this->renderWorkspaceShellFor(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148',
        );

        $this->assertStringContainsString('data-device="desktop"', $desktop);
        $this->assertStringContainsString('data-device="mobile"', $phone);
    }

    /**
     * `Vary` olmadan adaptive yükleme SESSİZCE bozulur.
     *
     * Aynı adres cihaza göre farklı HTML döndürüyor. Araya giren herhangi bir
     * önbellek — tarayıcı, vekil, CDN — `Vary` yokken ilk gelen yanıtı herkese
     * servis eder. Ortaya çıkan arıza teşhis edilmesi en zor türdendir:
     * masaüstü kullanıcısı mobil düzeni görür, yenileyince düzelir, ve
     * kayıtlarda hiçbir iz kalmaz.
     */
    #[Test]
    public function every_html_response_declares_what_it_varies_on(): void
    {
        $response = $this->get('/login');

        $response->assertOk();

        $vary = implode(',', $response->headers->all('Vary'));

        $this->assertStringContainsString('Sec-CH-UA-Mobile', $vary);
        $this->assertStringContainsString('User-Agent', $vary);
    }

    /**
     * İpucu İSTENİR — tarayıcı onu kendiliğinden göndermez.
     *
     * İlk istekte karar User-Agent metnine dayanır; `Accept-CH` sonraki
     * isteklerde yapılandırılmış ipuca yükseltir.
     */
    #[Test]
    public function the_response_asks_the_browser_for_the_mobile_client_hint(): void
    {
        $this->get('/login')->assertHeader('Accept-CH', 'Sec-CH-UA-Mobile');
    }
}
