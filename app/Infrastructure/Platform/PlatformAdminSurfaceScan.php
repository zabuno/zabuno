<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform;

use App\Http\Middleware\EnsurePlatformSuperAdmin;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollectionInterface;

/**
 * SÜPERADMİN NEYE ERİŞEBİLİR — yönlendiriciye SORULARAK bulunur.
 *
 * ═══ NEDEN İZİN LİSTESİ DEĞİL ═══
 *
 * Kiracı içindeki roller bir izin kümesi taşır (`RolePermissions`);
 * süperadmin TAŞIMAZ. Onun yetkisi tek bir kapıdır:
 * `EnsurePlatformSuperAdmin`, ve o kapının arkasında ne varsa yetkisi
 * odur. Bu yüzden süperadmin satırını izin sütunlarıyla çizmek yanlış
 * olurdu — hiçbiri onda "var" değildir, o başka bir kapıdan girer.
 *
 * Doğru gözlem, o kapının arkasındaki UÇLARIN kendisidir. Elle sayılsaydı
 * bir uç eklendiği gün belge eksik kalırdı; burada liste yönlendiricinin
 * kendisinden okunur.
 *
 * ═══ DESTEK PENCERESİ AYRI SAYILIR ═══
 *
 * "Süperadmin her şeyi görebiliyor mu?" ile "destekçi benim restoranıma
 * bakarken ne yapabiliyor?" iki ayrı sorudur ve zincirin hukukçusu
 * İKİNCİSİNİ sorar. Kiracıya bakılan pencere `api/admin/workspaces/{id}`
 * altındadır ve orada YAZMA olmaması bir iddia değil, ölçülebilir bir
 * olgudur: o önekteki her ucun yöntemine bakılır.
 *
 * Kiracı olarak OTURUM AÇMA (impersonation) bu depoda hiç yoktur ve
 * yokluğu da ölçülür (`docs/122` §5, `ShowManagedWorkspaceController`
 * sınıf yorumu). Bir gün eklenirse arama onu bulur ve belge kapısı kırılır.
 */
final class PlatformAdminSurfaceScan
{
    /**
     * Kiracıya bakılan pencerenin adres öneki.
     *
     * `{workspace}` parametresi ADRESİN PARÇASIDIR ve bu tesadüf değil:
     * bakış tek bir kiracıya çivilidir, "bütün kiracılar" diye bir görünüm
     * bu önekin altında yoktur.
     */
    private const TENANT_WINDOW_PREFIX = 'api/admin/workspaces/{workspace}';

    /** Kiracı kimliğine bürünme arayan kalıplar — bugün hiçbiri eşleşmemeli. */
    private const IMPERSONATION_PATTERNS = ['impersonat', 'login-as', 'act-as', 'become', 'switch-user'];

    private const READ_METHODS = ['GET', 'HEAD'];

    public function __construct(private readonly RouteCollectionInterface $routes) {}

    /**
     * @return array{
     *     read: list<string>,
     *     write: list<string>,
     *     tenantWindowRead: list<string>,
     *     tenantWindowWrite: list<string>,
     *     impersonation: list<string>,
     * }
     */
    public function surface(): array
    {
        $read = [];
        $write = [];
        $tenantWindowRead = [];
        $tenantWindowWrite = [];
        $impersonation = [];

        foreach ($this->guardedRoutes() as $route) {
            foreach ($this->methods($route) as $method) {
                $line = $method.' /'.ltrim($route->uri(), '/');
                $isRead = in_array($method, self::READ_METHODS, true);

                if ($isRead) {
                    $read[] = $line;
                } else {
                    $write[] = $line;
                }

                if (str_starts_with($route->uri(), self::TENANT_WINDOW_PREFIX)) {
                    if ($isRead) {
                        $tenantWindowRead[] = $line;
                    } else {
                        $tenantWindowWrite[] = $line;
                    }
                }

                foreach (self::IMPERSONATION_PATTERNS as $pattern) {
                    if (str_contains(strtolower($route->uri()), $pattern)) {
                        $impersonation[] = $line;

                        break;
                    }
                }
            }
        }

        sort($read);
        sort($write);
        sort($tenantWindowRead);
        sort($tenantWindowWrite);
        sort($impersonation);

        return [
            'read' => array_values(array_unique($read)),
            'write' => array_values(array_unique($write)),
            'tenantWindowRead' => array_values(array_unique($tenantWindowRead)),
            'tenantWindowWrite' => array_values(array_unique($tenantWindowWrite)),
            'impersonation' => array_values(array_unique($impersonation)),
        ];
    }

    /** @return list<Route> */
    private function guardedRoutes(): array
    {
        $guarded = [];

        foreach ($this->routes->getRoutes() as $route) {
            if (in_array(EnsurePlatformSuperAdmin::class, $route->gatherMiddleware(), true)) {
                $guarded[] = $route;
            }
        }

        return $guarded;
    }

    /**
     * `HEAD` sayılmaz: Laravel her `GET` için otomatik bir `HEAD` üretir ve
     * onu ayrı bir uç gibi saymak, okuma ucu sayısını sebepsiz ikiye
     * katlardı.
     *
     * @return list<string>
     */
    private function methods(Route $route): array
    {
        return array_values(array_filter(
            $route->methods(),
            static fn (string $method): bool => $method !== 'HEAD',
        ));
    }
}
