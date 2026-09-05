<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Maturity;

use App\Application\Media\Port\MediaEvidencePort;
use Illuminate\Routing\Router;

/**
 * KANIT, DEPONUN KENDİSİNDEN OKUNUR — bir listeden değil.
 *
 * Olgunluk ekranının tek değeri, gösterdiği rozetin arkasında gerçekten
 * bir şey olmasıdır. Bu sınıf o "gerçekten"in karşılığıdır:
 *
 *   - UÇ, yönlendirici koleksiyonuna sorulur. `config:cache` altında da
 *     çalışır: koleksiyon her istekte belleğe yüklenir.
 *   - GEREKSİNİM KİMLİĞİ ve TEST YÖNTEMİ, `tests/Feature/Media` altındaki
 *     dosyalardan aranır. Testin ADI bir kanıttır: `docs/108`'in
 *     adlandırılmış gereksinim geleneği tam da bu yüzden var.
 *
 * ═══ DAĞITIMDA `tests/` OLMAYABİLİR ═══
 *
 * Ve bu bir hata değildir. O durumda cevap `false` DEĞİL `null`'dır:
 * "test yok" ile "buradan bakınca göremiyorum" aynı şey değildir.
 * Birincisi ürünün eksiği, ikincisi bakış açısının sınırıdır; ikisini
 * karıştıran ekran, sunucuda yanlış bir düşüş gösterirdi.
 *
 * ═══ MALİYET ═══
 *
 * Dosyalar İSTEK BAŞINA BİR KEZ okunur ve bellekte tutulur. Bu uç yalnız
 * sahip olgunluk bölümünü açtığında çağrılır; yükleme yolunda hiç
 * çalışmaz. Yirmi yedi test dosyasını bir kez okumak, ekranın söylediği
 * her cümlenin doğrulanmasının bedelidir.
 */
final class RepositoryMediaEvidence implements MediaEvidencePort
{
    /** Test paketinin tek gövde hâli; `null` = henüz okunmadı. */
    private ?string $corpus = null;

    /** Test paketi bu ortamda okunabiliyor mu? `null` = henüz bakılmadı. */
    private ?bool $readable = null;

    public function __construct(
        private readonly Router $router,
        private readonly string $testDirectory,
    ) {}

    public function hasEndpoint(string $method, string $uri): bool
    {
        foreach ($this->router->getRoutes() as $route) {
            if ($route->uri() === $uri && in_array(strtoupper($method), $route->methods(), true)) {
                return true;
            }
        }

        return false;
    }

    public function hasRequirement(string $requirementId): ?bool
    {
        $corpus = $this->corpus();

        if ($corpus === null) {
            return null;
        }

        /*
            Gereksinim kimlikleri tüm medya testlerine dağılmıştır; hangisinde
            durduğu bir uygulama ayrıntısıdır ve zamanla değişir. Aranan şey
            kimliğin PAKETTE olmasıdır, belli bir dosyada olması değil.
        */
        return str_contains($corpus, $requirementId);
    }

    public function hasTestMethod(string $class, string $method): ?bool
    {
        if ($this->corpus() === null) {
            return null;
        }

        /*
            Sınıf adı DOSYA adına çevrilir ve yol ayracı kabul edilmez:
            referans bir kanıt adıdır, bir dosya yolu değil. Aksi hâlde bir
            gün kataloğa yazılan `../../` desenli bir ad, bu okuyucuyu test
            klasörünün dışına çıkarırdı.
        */
        if ($class === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $class)) {
            return false;
        }

        $path = $this->testDirectory.DIRECTORY_SEPARATOR.$class.'.php';

        if (! is_file($path)) {
            return false;
        }

        $body = file_get_contents($path);

        if ($body === false) {
            return null;
        }

        return str_contains($body, 'function '.$method.'(');
    }

    /**
     * Medya test dosyalarının tek gövdesi; klasör okunamıyorsa `null`.
     */
    private function corpus(): ?string
    {
        if ($this->readable !== null) {
            return $this->corpus;
        }

        if (! is_dir($this->testDirectory)) {
            $this->readable = false;

            return null;
        }

        $files = glob($this->testDirectory.DIRECTORY_SEPARATOR.'*.php');

        if ($files === false) {
            $this->readable = false;

            return null;
        }

        $corpus = '';

        foreach ($files as $file) {
            $body = file_get_contents($file);

            if ($body !== false) {
                $corpus .= $body;
            }
        }

        $this->readable = true;
        $this->corpus = $corpus;

        return $corpus;
    }
}
