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
