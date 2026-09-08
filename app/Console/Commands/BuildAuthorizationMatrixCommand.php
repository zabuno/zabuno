<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Authorization\AuthorizationMatrixReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * `php artisan authorization:matrix` — koddan belgeye ve ekrana.
 *
 * Zinciri tamamlayan halka: `RolePermissionMatrix` gerçeği okur,
 * `AuthorizationMatrixReport` iki biçime çevirir, bu komut da onları
 * dosyaya yazar. Elle yazılan tek şey GEREKÇEDİR ve o da belgenin
 * işaretlenmiş bölgesinin dışında durur.
 *
 * `--check` hiçbir şey yazmaz, yalnız ayrışma var mı diye bakar ve varsa
 * sıfırdan farklı bir kodla döner. Kapının kendisi test tarafındadır
 * (`AuthorizationMatrixArtifactTest`); bu bayrak, kapıyı beklemeden elle
 * bakmak isteyen içindir.
 */
final class BuildAuthorizationMatrixCommand extends Command
{
    protected $signature = 'authorization:matrix {--check : Yazma, yalnız ayrışmayı bildir}';

    protected $description = 'Rol ve yetki matrisini koddan üretir (docs/139 + ekip ekranı verisi).';

    public function handle(): int
    {
        $base = base_path();
        $report = new AuthorizationMatrixReport($base, Route::getRoutes());

        $documentPath = $base.'/'.AuthorizationMatrixReport::DOCUMENT_PATH;
        $dataPath = $base.'/'.AuthorizationMatrixReport::DATA_PATH;

        $document = $this->splice($this->read($documentPath), $report->markdown());
        $data = $report->json();

        if ($this->option('check')) {
            $drift = [];

            if ($this->read($documentPath) !== $document) {
                $drift[] = AuthorizationMatrixReport::DOCUMENT_PATH;
            }

            if (! is_file($dataPath) || file_get_contents($dataPath) !== $data) {
                $drift[] = AuthorizationMatrixReport::DATA_PATH;
            }

            if ($drift !== []) {
                $this->components->error('Kod ile üretilmiş yüzey ayrıştı: '.implode(', ', $drift));
                $this->line('Düzeltme: php artisan authorization:matrix');

                return self::FAILURE;
            }

            $this->components->info('Matris koddan üretilmiş hâliyle aynı.');

            return self::SUCCESS;
        }

        $directory = dirname($dataPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        file_put_contents($documentPath, $document);
        file_put_contents($dataPath, $data);

        $this->components->info('Yazıldı: '.AuthorizationMatrixReport::DOCUMENT_PATH);
        $this->components->info('Yazıldı: '.AuthorizationMatrixReport::DATA_PATH);

        return self::SUCCESS;
    }

    private function read(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Okunamadı: %s', $path));
        }

        return $contents;
    }

    /**
     * Üretilen bölgeyi belgenin içine yerleştirir.
     *
     * İşaretler yoksa komut SESSİZCE eklemez, DURUR: bir üreticinin
     * yapabileceği en tehlikeli şey, beklediği yeri bulamayınca kendi
     * kararıyla bir yer seçmektir.
     */
    private function splice(string $document, string $region): string
    {
        $start = strpos($document, AuthorizationMatrixReport::REGION_START);
        $end = strpos($document, AuthorizationMatrixReport::REGION_END);

        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException(sprintf(
                '%s içinde "%s" … "%s" işaretleri bulunamadı.',
                AuthorizationMatrixReport::DOCUMENT_PATH,
                AuthorizationMatrixReport::REGION_START,
                AuthorizationMatrixReport::REGION_END,
            ));
        }

        $tail = substr($document, $end + strlen(AuthorizationMatrixReport::REGION_END));

        // Üretilen bölge kendi satır sonuyla biter; işaretten sonraki satır
        // sonunu bir kez daha taşımak, her koşuda bir boş satır büyüyen bir
        // belge üretirdi.
        if (str_starts_with($tail, "\n")) {
            $tail = substr($tail, 1);
        }

        return substr($document, 0, $start).$region.$tail;
    }
}
