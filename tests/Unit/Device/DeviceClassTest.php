<?php

declare(strict_types=1);

namespace Tests\Unit\Device;

use App\Support\Device\DeviceClass;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cihaz sınıflandırması — adaptive yüklemenin kararı (docs/54).
 *
 * Sahibin kuralı: "responsive kod yazarak tüm kodları her cihazda fazlaca
 * yükleyen değil; cihazı sorgulayıp, cihaza uygun frontend kodunu, cihaza
 * yükleyen." Bu sınıf o kararı verir ve kararın sonucu farklı bir JavaScript
 * paketidir — bir CSS kuralı değil.
 */
final class DeviceClassTest extends TestCase
{
    private function requestWith(array $headers): Request
    {
        $request = Request::create('/app');

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    /**
     * İstemci İpucu, User-Agent metnini EZER.
     *
     * İpucu tarayıcının yapılandırılmış beyanıdır; User-Agent ise tahmin
     * gerektiren serbest metindir. İkisi çeliştiğinde beyan kazanır.
     */
    #[Test]
    public function the_client_hint_outranks_the_user_agent_string(): void
    {
        $desktopAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)';

        $this->assertSame(
            DeviceClass::Mobile,
            DeviceClass::detect($this->requestWith([
                'Sec-CH-UA-Mobile' => '?1',
                'User-Agent' => $desktopAgent,
            ])),
        );

        $this->assertSame(
            DeviceClass::Desktop,
            DeviceClass::detect($this->requestWith([
                'Sec-CH-UA-Mobile' => '?0',
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
            ])),
        );
    }

    #[Test]
    public function it_falls_back_to_the_user_agent_when_no_hint_is_sent(): void
    {
        $this->assertSame(
            DeviceClass::Mobile,
            DeviceClass::detect($this->requestWith([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148',
            ])),
        );

        $this->assertSame(
            DeviceClass::Desktop,
            DeviceClass::detect($this->requestWith([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
            ])),
        );
    }

    /**
     * Bilinmeyen cihaz MOBİL sayılır — ve bu simetrik bir tercih değil.
     *
     * Telefona masaüstü paketi göndermek, dar ekranda kullanılamayan bir
     * arayüz ve boşa harcanmış indirme demektir. Masaüstüne mobil paket
     * göndermek ise yalnız daha sade bir düzen demektir; çalışır. Belirsizlikte
     * çalışan tarafa düşülür.
     */
    #[Test]
    public function an_unknown_device_is_served_the_mobile_bundle(): void
    {
        $this->assertSame(DeviceClass::Mobile, DeviceClass::detect($this->requestWith([])));
        $this->assertSame(
            DeviceClass::Mobile,
            DeviceClass::detect($this->requestWith(['User-Agent' => 'SomeBrandNewThing/1.0'])),
        );
    }

    #[Test]
    public function it_names_the_vite_entry_for_a_surface(): void
    {
        $this->assertSame(
            'resources/js/workspace.mobile.tsx',
            DeviceClass::Mobile->entryFor('workspace'),
        );
        $this->assertSame(
            'resources/js/workspace.desktop.tsx',
            DeviceClass::Desktop->entryFor('workspace'),
        );
    }
}
