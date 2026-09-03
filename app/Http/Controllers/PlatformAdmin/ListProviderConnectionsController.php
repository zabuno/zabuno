<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Domain\Platform\Credential\CredentialConnection;
use App\Domain\Platform\Credential\CredentialField;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Bağlantı listesi + sağlayıcı ŞEMASI — `docs/95` Faz 3.
 *
 * Şema neden aynı cevapta: panelin "yeni bağlantı ekle" formu, henüz
 * hiçbir bağlantı YOKKEN çizilmek zorunda ve hangi sağlayıcının hangi
 * alanları istediğini bir yerden öğrenmeli. Ayrı bir uç, panelin her
 * açılışta iki istek atmasını gerektirirdi.
 *
 * Sır yine yok: her alan yalnız `••••son4` maskesiyle döner.
 */
final class ListProviderConnectionsController extends Controller
{
    public function __construct(private readonly PlatformConnectionAdminPort $vault) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'providers' => array_map(
                static fn (CredentialProvider $provider): array => [
                    'provider' => $provider->value,
                    'fields' => array_map(
                        static fn (CredentialField $field): array => [
                            'name' => $field->name,
                            'secret' => $field->secret,
                            'required' => $field->required,
                            'default' => $field->default,
                        ],
                        $provider->fields(),
                    ),
                ],
                CredentialProvider::cases(),
            ),
            'connections' => array_map(
                static fn (CredentialConnection $c): array => $c->toArray(),
                $this->vault->connections(),
            ),
        ]);
    }
}
