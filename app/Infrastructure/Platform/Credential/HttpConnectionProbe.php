<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform\Credential;

use App\Application\Platform\Port\AccountRoutingPort;
use App\Application\Platform\Port\ConnectionProbePort;
use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\ProbeResult;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Uyumluluk yoklaması — TEK, UCUZ, TOKEN HARCAMAYAN bir çağrı.
 *
 * Her sağlayıcının "kim olduğumu söyle" niteliğinde bir uç noktası var ve
 * yoklama onu kullanır. Bir "merhaba" tamamlaması istemek de anahtarı
 * doğrulardı ama her denemede küçük de olsa fatura üretirdi — superadmin
 * bir anahtarı üç kez denediğinde üç kez ödemesi için bir sebep yok.
 *
 * SIRRI OKUR ama HİÇBİR YERE YAZMAZ: ne cevaba, ne log'a, ne denetime.
 * Sağlığa yalnız sonuç (`healthy`/`unhealthy`) yazılır.
 *
 * Bağlantının sırrına erişmek için kasa satırını doğrudan okur; resolver
 * portu sağlayıcı düzeyinde çalışır ve BELİRLİ bir bağlantıyı yoklamak
 * için yeterli değildir — yoklamanın bütün anlamı "şu kart çalışıyor mu"
 * sorusudur.
 */
final readonly class HttpConnectionProbe implements ConnectionProbePort
{
    private const TABLE = 'platform_credential_connections';

    public function __construct(
        private PlatformConnectionAdminPort $connections,
        private AccountRoutingPort $routing,
        private Encrypter $encrypter,
        private HttpFactory $http,
    ) {}

    public function probe(int $connectionId): ProbeResult
    {
        $connection = $this->connections->connection($connectionId);

        if ($connection === null) {
            return ProbeResult::unsupported('Bağlantı yok.');
        }

        $row = DB::table(self::TABLE)->where('id', $connectionId)->first();
        $creds = $this->credentials($row);

        $result = $this->call($connection->provider, $creds);

        if ($result->changesHealth()) {
            $result->isReachable()
                ? $this->routing->markHealthy($connectionId)
                : $this->routing->markUnhealthy($connectionId);
        }

        return $result;
    }

    /** @param array<string, string> $creds */
    private function call(CredentialProvider $provider, array $creds): ProbeResult
    {
        $apiKey = $creds['api_key'] ?? '';
        $baseUrl = rtrim((string) ($creds['base_url'] ?? ''), '/');

        [$url, $headers] = match ($provider) {
            CredentialProvider::OpenAi => [
                ($baseUrl !== '' ? $baseUrl : 'https://api.openai.com/v1').'/models',
                ['Authorization' => 'Bearer '.$apiKey],
            ],
            CredentialProvider::Gemini => [
                ($baseUrl !== '' ? $baseUrl : 'https://generativelanguage.googleapis.com').'/v1beta/models',
                ['x-goog-api-key' => $apiKey],
            ],
            CredentialProvider::Anthropic => [
                ($baseUrl !== '' ? $baseUrl : 'https://api.anthropic.com').'/v1/models',
                ['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01'],
            ],
            CredentialProvider::Kimi => [
                ($baseUrl !== '' ? $baseUrl : 'https://api.moonshot.ai/v1').'/models',
                ['Authorization' => 'Bearer '.$apiKey],
            ],
            CredentialProvider::CustomEndpoint => [
                $baseUrl.'/models',
                // Anahtar opsiyonel: ağ sınırında korunan bir sunucu için
                // boş bir `Bearer` başlığı göndermek, bazı sunucularda
                // isteği geçersiz kılar.
                $apiKey === '' ? [] : ['Authorization' => 'Bearer '.$apiKey],
            ],
            // Posta/ödeme sağlayıcılarının "model listesi" yok. Bunları
            // yoklanamadı diye SAĞLIKSIZ işaretlemek, çalışan bir Mailgun
            // hesabını havuzdan düşürmek olurdu.
            default => ['', []],
        };

        if ($url === '' || $url === '/models') {
            return ProbeResult::unsupported(
                'Bu sağlayıcı için yoklanacak bir uç nokta yok ya da adres girilmemiş.',
            );
        }

        try {
            $response = $this->http->withHeaders($headers)->acceptJson()->timeout(15)->get($url);
        } catch (Throwable) {
            return ProbeResult::rejected(null, 'Adrese ulaşılamadı.');
        }

        if ($response->successful()) {
            return ProbeResult::reachable($response->status());
        }

        return ProbeResult::rejected($response->status(), 'Sağlayıcı isteği reddetti.');
    }

    /** @return array<string, string> */
    private function credentials(?object $row): array
    {
        if ($row === null) {
            return [];
        }

        $plain = $row->plain_fields === null
            ? []
            : (array) json_decode((string) $row->plain_fields, true);

        $secrets = $row->secret_ciphertext === null
            ? []
            : (array) json_decode($this->encrypter->decryptString((string) $row->secret_ciphertext), true);

        $out = [];
        foreach (array_merge($plain, $secrets) as $name => $value) {
            $out[(string) $name] = (string) $value;
        }

        return $out;
    }
}
