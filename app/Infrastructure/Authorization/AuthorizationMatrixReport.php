<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization;

use App\Domain\Authorization\Permission;
use App\Domain\Authorization\RolePermissionMatrix;
use App\Domain\Platform\PlatformRole;
use App\Infrastructure\Platform\PlatformAdminSurfaceScan;
use Illuminate\Routing\RouteCollectionInterface;

/**
 * MATRİSİN İKİ YÜZÜ — belge ve ekran, TEK kaynaktan.
 *
 * `docs/139` bir Markdown belgesidir ve zincirin hukuk birimi onu okur;
 * ekip ekranındaki rol kartı bir React bileşenidir ve restoran sahibi ona
 * bakar. İki ayrı okuyucu, iki ayrı yüzey — ama tek bir gerçek. Bu sınıf o
 * gerçeği bir kez okur ve iki biçimde yazar; ikisinin ayrışabileceği bir
 * ara adım yoktur.
 *
 * ÜRETİLEN HER BAYT BURADAN ÇIKAR. Elle düzeltilebilecek hiçbir hücre
 * bırakılmadı: belgede kaynağı olan her satır işaretli bölgenin İÇİNDE,
 * insanın yazdığı gerekçe bölgenin DIŞINDA durur (`docs/111` §4 deseni).
 */
final class AuthorizationMatrixReport
{
    /** Belgede üretilen bölgeyi çevreleyen işaretler. */
    public const REGION_START = '<!-- KODDAN-URETILDI: BASLANGIC -->';

    public const REGION_END = '<!-- KODDAN-URETILDI: SON -->';

    public const DOCUMENT_PATH = 'docs/139-ROL-VE-YETKI-MATRISI.md';

    public const DATA_PATH = 'resources/js/generated/role-permission-matrix.json';

    /**
     * ADI KONMAMIŞ YETENEKLER.
     *
     * Bir yetkinin kimseye VERİLMEMESİ ile o yetkinin HİÇ VAR OLMAMASI
     * farklı iki güvencedir ve ikincisi daha güçlüdür: verilmeyen bir yetki
     * bir gün bir role eklenebilir, adlandırılmamış bir yetki için önce onu
     * yazmak gerekir. `docs/116` §4 bunu tek cümleyle donduruyor: *"Sahip
     * puanı silemez. Yanıt verebilir, kaldıramaz."*
     *
     * Buradaki her satır, `Permission` enum'unda BULUNMAMASI gereken bir
     * anahtardır. Kapı yokluğu sınar; biri eklendiği gün kırılır ve karar
     * konuşulur.
     */
    public const ABSENT_BY_DESIGN = [
        'rating.delete' => 'docs/116 §4 — puana yanıt verilir, puan kaldırılmaz.',
    ];

    /**
     * KİRACI ADRESLİ YAZMA UÇLARI — dondurulmuş liste.
     *
     * İlk ölçüm bir varsayımı yıktı: `api/admin/workspaces/{workspace}`
     * önekinin tamamı salt okunur SANILIYORDU, ama iki yazma ucu var.
     * İkisi de kiracının İÇERİĞİNE değil ÖDEME DEFTERİNE yazar; yine de
     * "destek penceresinde yazma yoktur" cümlesi olduğu gibi doğru değildi
     * ve bu belge onu düzeltiyor.
     *
     * Liste dondurulmuş olmasının sebebi tam olarak budur: bugün ikisi
     * biliniyor ve gerekçeleri yazılı. Üçüncüsü eklendiği gün kapı kırılır
     * ve yeni ucun kiracı içeriğine dokunup dokunmadığı KONUŞULUR. Sessizce
     * eklenmesi mümkün değildir.
     */
    public const TENANT_ADDRESSED_WRITES = [
        'POST /api/admin/workspaces/{workspace}/manual-payments' => 'Elle tahsilat kaydı — abonelik defterine yazar (docs/123).',
        'POST /api/admin/workspaces/{workspace}/transactions/{transaction}/refund' => 'İade — ödeme defterine ters kayıt (docs/107 Faz 1.1).',
    ];

    public function __construct(
        private readonly string $basePath,
        private readonly RouteCollectionInterface $routes,
    ) {}

    /**
     * Ekranın okuduğu veri.
     *
     * Belgedeki her şey BURADA YOK ve bu bilerek: ekip ekranı bir restoran
     * sahibinindir, uç sayıları ve dosya yolları ona hiçbir şey söylemez
     * (`docs/53`). Ekran kiracı rollerini çizer, belge yüzeyin tamamını
     * anlatır.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'generatedBy' => 'php artisan authorization:matrix',
            'source' => [
                'app/Domain/Authorization/Permission.php',
                'app/Domain/Authorization/RolePermissions.php',
                'app/Domain/Tenancy/MembershipRole.php',
            ],
        ] + RolePermissionMatrix::build();
    }

    /** Ekranın okuduğu verinin dosyaya yazılacak hâli. */
    public function json(): string
    {
        return json_encode(
            $this->data(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        )."\n";
    }

    /** İzin → onu okuyan `app/` dosyaları. */
    public function enforcement(): array
    {
        return (new PermissionEnforcementScan($this->basePath))->sites();
    }

    /**
     * Süperadmin ve destek penceresi.
     *
     * @return array{read: list<string>, write: list<string>, tenantWindowRead: list<string>, tenantWindowWrite: list<string>, impersonation: list<string>}
     */
    public function platformSurface(): array
    {
        return (new PlatformAdminSurfaceScan($this->routes))->surface();
    }

    /** Belgenin üretilen bölgesi — işaretler dâhil. */
    public function markdown(): string
    {
        $matrix = RolePermissionMatrix::build();

        $lines = [
            self::REGION_START,
            '',
            '<!--',
            '    BU BÖLÜM ELLE DÜZENLENMEZ.',
            '',
            '    Üreten: `php artisan authorization:matrix`',
            '    Kapı:   `tests/Feature/Authorization/AuthorizationMatrixArtifactTest.php`',
            '',
            '    Buradaki bir hücreyi elle değiştirmek, kodla belgeyi ayrıştırır ve',
            '    kapı bir sonraki koşuda kırılır. Bir hücre yanlışsa düzeltilecek',
            '    yer koddur; belge onun aynasıdır.',
            '-->',
            '',
        ];

        $lines = [
            ...$lines,
            ...$this->roleLifecycleSection($matrix['roles']),
            ...$this->gridSection($matrix),
            ...$this->cannotSection($matrix['roles']),
            ...$this->enforcementSection(),
            ...$this->platformSection(),
            ...$this->absentSection(),
        ];

        $lines[] = self::REGION_END;

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<array<string, mixed>>  $roles
     * @return list<string>
     */
    private function roleLifecycleSection(array $roles): array
    {
        $lines = [
            '### Kiracı rolleri ve yaşam döngüsü',
            '',
            'Kaynak: `MembershipRole::invitable()`, `::removable()`,',
            '`::ownershipTransferable()`.',
            '',
            '| Rol | Taşıdığı izin | Davet edilebilir | Ekipten çıkarılabilir | Sahipliği devralabilir |',
            '| --- | ---: | --- | --- | --- |',
        ];

        foreach ($roles as $role) {
            $lines[] = sprintf(
                '| `%s` | %d / %d | %s | %s | %s |',
                $role['key'],
                count($role['granted']),
                count($role['granted']) + count($role['denied']),
                $this->mark($role['invitable']),
                $this->mark($role['removable']),
                $this->mark($role['ownershipTransferable']),
            );
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array{permissions: list<array<string, mixed>>, roles: list<array<string, mixed>>}  $matrix
     * @return list<string>
     */
    private function gridSection(array $matrix): array
    {
        $roleKeys = array_map(static fn (array $role): string => (string) $role['key'], $matrix['roles']);

        $header = '| İzin | Eksen |';
        $divider = '| --- | --- |';

        foreach ($roleKeys as $key) {
            $header .= ' `'.$key.'` |';
            $divider .= ' :-: |';
        }

        $lines = [
            '### Rol × izin ızgarası',
            '',
            sprintf(
                '%d izin × %d rol. Kaynak: `Permission::cases()` ve `RolePermissions::for()`.',
                count($matrix['permissions']),
                count($roleKeys),
            ),
            '',
            $header,
            $divider,
        ];

        foreach ($matrix['permissions'] as $permission) {
            $row = sprintf('| `%s` | %s |', $permission['key'], $permission['group']);

            foreach ($roleKeys as $key) {
                $row .= ' '.$this->mark(in_array($key, $permission['roles'], true)).' |';
            }

            $lines[] = $row;
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $roles
     * @return list<string>
     */
    private function cannotSection(array $roles): array
    {
        $lines = [
            '### Her rolün YAPAMADIKLARI',
            '',
            'Aynı döngünün öteki yarısı: bir rol için "yapabilir" listesine',
            'girmeyen her izin buraya girer. İki liste birbirinden ayrışamaz.',
            '',
        ];

        foreach ($roles as $role) {
            $lines[] = sprintf('#### `%s` — yapamadığı %d iş', $role['key'], count($role['denied']));
            $lines[] = '';

            if ($role['denied'] === []) {
                $lines[] = 'Bu rolün erişemediği adlandırılmış bir yetenek yok.';
                $lines[] = '';

                continue;
            }

            foreach ($role['denied'] as $permission) {
                $lines[] = '- `'.$permission.'`';
            }

            $lines[] = '';
        }

        return $lines;
    }

    /** @return list<string> */
    private function enforcementSection(): array
    {
        $sites = $this->enforcement();

        $lines = [
            '### İznin kodda karşılığı var mı (ölçüm)',
            '',
            '`app/` altında o izni okuyan dosya sayısı. İzni TANIMLAYAN dizin',
            '(`app/Domain/Authorization/`) sayılmaz: bir iznin kendi tanımı, o',
            'iznin uygulandığının kanıtı değildir. Sıfır, bir yeteneğin adı',
            'konmuş ama hiçbir kapıya bağlanmamış olduğunu söyler.',
            '',
            '| İzin | Okuyan dosya | İlk okuyan |',
            '| --- | ---: | --- |',
        ];

        foreach ($sites as $key => $files) {
            $lines[] = sprintf(
                '| `%s` | %d | %s |',
                $key,
                count($files),
                $files === [] ? '—' : '`'.$files[0].'`',
            );
        }

        $lines[] = '';

        return $lines;
    }

    /** @return list<string> */
    private function platformSection(): array
    {
        $surface = $this->platformSurface();

        $lines = [
            '### Süperadmin ve destek penceresi',
            '',
            sprintf(
                'Platform rolü tek: `%s`. Kiracı rolleri gibi bir izin listesi',
                PlatformRole::SuperAdmin->value,
            ),
            'TAŞIMAZ; yetkisi `EnsurePlatformSuperAdmin` kapısının arkasındaki',
            'uçların kendisidir ve bu liste yönlendiriciye sorularak üretildi.',
            '',
            '| Ölçüm | Sayı |',
            '| --- | ---: |',
            sprintf('| Kapının arkasındaki okuma ucu | %d |', count($surface['read'])),
            sprintf('| Kapının arkasındaki yazma ucu | %d |', count($surface['write'])),
            sprintf('| Tek kiracıyı adlandıran okuma ucu | %d |', count($surface['tenantWindowRead'])),
            sprintf('| Tek kiracıyı adlandıran YAZMA ucu | %d |', count($surface['tenantWindowWrite'])),
            sprintf('| Bunlardan gerekçesi KAYITSIZ olan | %d |', count(array_diff(
                $surface['tenantWindowWrite'],
                array_keys(self::TENANT_ADDRESSED_WRITES),
            ))),
            sprintf('| Kiracı kimliğine bürünme ucu | %d |', count($surface['impersonation'])),
            '',
            '**Destek penceresi** — tek bir kiracıyı adlandıran her uç, yöntemiyle',
            've yazan uçların kayıtlı gerekçesiyle:',
            '',
            '| Uç | Kip | Gerekçe |',
            '| --- | --- | --- |',
        ];

        foreach ($surface['tenantWindowRead'] as $line) {
            $lines[] = sprintf('| `%s` | okuma | — |', $line);
        }

        foreach ($surface['tenantWindowWrite'] as $line) {
            $lines[] = sprintf(
                '| `%s` | **YAZMA** | %s |',
                $line,
                self::TENANT_ADDRESSED_WRITES[$line] ?? '**KAYITSIZ — bu uç dondurulmuş listede yok.**',
            );
        }

        $lines[] = '';
        $lines[] = '**Kapının arkasındaki bütün yazma uçları:**';
        $lines[] = '';

        foreach ($surface['write'] as $line) {
            $lines[] = '- `'.$line.'`';
        }

        $lines[] = '';

        return $lines;
    }

    /** @return list<string> */
    private function absentSection(): array
    {
        $named = array_map(static fn (Permission $p): string => $p->value, Permission::cases());

        $lines = [
            '### Adı konmamış yetenekler',
            '',
            'Bir yetkiyi kimseye vermemek ile o yetkiyi hiç adlandırmamak aynı',
            'şey değildir. Aşağıdaki anahtarlar `Permission` enum\'unda YOKTUR ve',
            'yokluğu kapıyla korunur.',
            '',
            '| Anahtar | Bugün var mı | Kaynak |',
            '| --- | :-: | --- |',
        ];

        foreach (self::ABSENT_BY_DESIGN as $key => $why) {
            $lines[] = sprintf(
                '| `%s` | %s | %s |',
                $key,
                in_array($key, $named, true) ? 'VAR' : 'yok',
                $why,
            );
        }

        $lines[] = '';

        return $lines;
    }

    private function mark(bool $value): string
    {
        return $value ? '✓' : '—';
    }
}
