<?php

declare(strict_types=1);

namespace App\Infrastructure\DataRights\Export;

use App\Application\DataRights\Dto\ExportArtifact;
use App\Application\DataRights\Port\WorkspaceDataExporterPort;
use App\Domain\DataRights\TenantDataScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Tek arşiv, İKİ biçim — FF-226 (`docs/138` §3).
 *
 * Neden ikisi birden: "makine okunur" ile "insan okunur" aynı dosya
 * olamaz. JSON bir başka sisteme aktarılabilir ama sahibin kendisi onu
 * açıp okuyamaz; CSV bir hesap tablosunda açılır ama iç içe alanı taşımaz.
 * Birini seçmek, iki okuyucudan birini dışarıda bırakmak olurdu.
 *
 * `README.txt` bir nezaket değil bir GEREKLİLİK: arşivi açan kişi
 * (çoğunlukla bir hukuk birimi) neyin İÇERİDE, neyin DIŞARIDA ve NEDEN
 * dışarıda olduğunu dosyanın kendisinden okuyabilmeli. Bunu yalnız ürün
 * ekranında söylemek, arşiv e-postayla üçüncü bir kişiye gittiği an
 * kaybolurdu.
 *
 * MEDYANIN KENDİSİ ARŞİVDE YOKTUR (`docs/138` §3): fotoğrafların ÜST
 * VERİSİ (ad, boyut, sağlama, hangi üründe kullanıldığı) tam olarak
 * aktarılır, ikili dosyalar aktarılmaz. Sebep ölçülmüş: bir menü kütüphanesi
 * gigabaytlarca aslı taşır ve tek bir arşive konsa ne üretilebilir ne
 * indirilebilirdi. Asıllar bugün Medya ekranından tek tek iniyor
 * (`media.download_original`) ve README bunu adıyla söylüyor.
 */
final class ZipWorkspaceDataExporter implements WorkspaceDataExporterPort
{
    public function export(int $workspaceId, int $requestId): ExportArtifact
    {
        $disk = Storage::disk($this->diskName());
        $reader = new TenantRowReader($workspaceId);

        $staging = $this->makeStagingDirectory($requestId);
        $counts = [];

        try {
            $workspace = DB::table('workspaces')->where('id', $workspaceId)->first();

            if ($workspace === null) {
                throw new RuntimeException('workspace-not-found');
            }

            $counts['workspaces'] = $this->writeSection(
                $staging,
                'workspaces',
                static function (callable $write) use ($workspace): void {
                    $write([(array) $workspace]);
                },
            );

            foreach (TenantDataScope::exportedTables() as $table) {
                $counts[$table->name] = $this->writeSection(
                    $staging,
                    $table->name,
                    static function (callable $write) use ($reader, $table): void {
                        $reader->eachRow($table, $write);
                    },
                );
            }

            $manifest = $this->manifest($workspaceId, $counts);
            file_put_contents(
                $staging.'/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            );
            file_put_contents($staging.'/README.txt', $this->readme($manifest));

            $archive = $this->zip($staging, $workspaceId, $requestId);
            $bytes = (int) filesize($archive);
            $checksum = (string) hash_file('sha256', $archive);

            $relative = sprintf('data-exports/%d/%s', $workspaceId, basename($archive));
            $stream = fopen($archive, 'rb');

            if ($stream === false) {
                throw new RuntimeException('archive-unreadable');
            }

            $disk->put($relative, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            @unlink($archive);

            return new ExportArtifact($relative, $bytes, $checksum, $counts);
        } finally {
            $this->removeDirectory($staging);
        }
    }

    public function forget(string $path): void
    {
        Storage::disk($this->diskName())->delete($path);
    }

    private function diskName(): string
    {
        $disk = config('data-rights.export.disk');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    /**
     * Bölümü PARÇA PARÇA yazar ve satır sayısını döner.
     *
     * Tabloyu önce diziye toplayıp sonra yazmak kolay olurdu; ama bir
     * restoranın misafir olayları yüz binlerce satırdır ve o dizi belleğe
     * sığmaz. Kuyrukta düşen bir dışa aktarma, kullanıcıya sebebi
     * söylenmeyen bir "başarısız" damgası olarak görünürdü.
     *
     * @param  callable(callable(list<array<string, mixed>>): void): void  $produce
     */
    private function writeSection(string $staging, string $section, callable $produce): int
    {
        $json = fopen($staging.'/data/'.$section.'.json', 'wb');
        $csv = fopen($staging.'/data/'.$section.'.csv', 'wb');

        if ($json === false || $csv === false) {
            throw new RuntimeException('section-unwritable');
        }

        fwrite($json, "[\n");

        $written = 0;

        $produce(function (array $rows) use ($json, $csv, &$written): void {
            foreach ($rows as $row) {
                /*
                    BAŞLIK SATIRI İLK SATIRDAN GELİR, şemadan değil: dışa
                    aktarılan şey satırın kendisidir ve başlık ile değerler
                    aynı kaynaktan gelmezse bir gün kayarlar.

                    BOŞ BÖLÜM DE YAZILIR (başlıksız, sıfır satırlı): dosyanın
                    hiç olmaması "bu bölüm dışarıda bırakıldı" gibi okunur,
                    boş dosya ise "bakıldı, hiçbir şey yoktu" der.
                */
                if ($written === 0) {
                    fputcsv($csv, array_keys($row), ',', '"', '\\');
                } else {
                    fwrite($json, ",\n");
                }

                fwrite($json, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

                fputcsv($csv, array_map(
                    static fn ($value): string => $value === null
                        ? ''
                        : (is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE)),
                    $row,
                ), ',', '"', '\\');

                $written++;
            }
        });

        fwrite($json, "\n]\n");
        fclose($json);
        fclose($csv);

        return $written;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function manifest(int $workspaceId, array $counts): array
    {
        $excluded = [];

        foreach (TenantDataScope::tables() as $table) {
            if (! $table->exported) {
                $excluded[$table->name] = (string) $table->exclusionReason;
            }
        }

        return [
            'workspaceId' => $workspaceId,
            'generatedAt' => Carbon::now()->toIso8601String(),
            'format' => ['machineReadable' => 'data/*.json', 'humanReadable' => 'data/*.csv'],
            'sections' => $counts,
            'rowTotal' => array_sum($counts),
            'excludedTenantTables' => $excluded,
            'outOfScope' => TenantDataScope::outOfScope(),
            'mediaFiles' => 'metadata-only',
            'hosting' => [
                'provider' => (string) config('data-rights.hosting.provider'),
                'country' => (string) config('data-rights.hosting.country'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function readme(array $manifest): string
    {
        /** @var array<string, int> $sections */
        $sections = $manifest['sections'];
        /** @var array<string, string> $excluded */
        $excluded = $manifest['excludedTenantTables'];
        /** @var array<string, string> $outOfScope */
        $outOfScope = $manifest['outOfScope'];

        $lines = [
            'Workspace data export',
            '=====================',
            '',
            'Workspace: '.$manifest['workspaceId'],
            'Generated: '.$manifest['generatedAt'],
            'Rows in this archive: '.$manifest['rowTotal'],
            'Hosted by: '.$manifest['hosting']['provider'].' ('.$manifest['hosting']['country'].')',
            '',
            'What is in here',
            '---------------',
            'data/<section>.json  the same rows, machine readable',
            'data/<section>.csv   the same rows, one spreadsheet file per section',
            'manifest.json        this summary, machine readable',
            '',
            'Sections and row counts:',
        ];

        foreach ($sections as $name => $count) {
            $lines[] = sprintf('  %-40s %d', $name, $count);
        }

        $lines[] = '';
        $lines[] = 'What is NOT in here, and why';
        $lines[] = '----------------------------';
        $lines[] = 'Image, and other uploaded files: this archive carries the metadata of every';
        $lines[] = 'uploaded file (name, size, checksum, where it is used) but not the files';
        $lines[] = 'themselves. A menu library is measured in gigabytes and a single archive';
        $lines[] = 'carrying it could be neither built nor downloaded. Originals can be';
        $lines[] = 'downloaded one by one from the Media screen.';
        $lines[] = '';

        foreach ($excluded as $name => $reason) {
            $lines[] = sprintf('  %s: %s', $name, $reason);
        }

        $lines[] = '';
        $lines[] = 'Records that belong to the platform, not to this workspace:';

        foreach ($outOfScope as $name => $reason) {
            $lines[] = sprintf('  %s: %s', $name, $reason);
        }

        $lines[] = '';

        return implode("\n", $lines)."\n";
    }

    private function zip(string $staging, int $workspaceId, int $requestId): string
    {
        $archive = $staging.'.zip';
        $zip = new ZipArchive;

        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('zip-unwritable');
        }

        $prefix = sprintf('zabuno-workspace-%d-export-%d', $workspaceId, $requestId);
        $zip->addFile($staging.'/manifest.json', $prefix.'/manifest.json');
        $zip->addFile($staging.'/README.txt', $prefix.'/README.txt');

        foreach (glob($staging.'/data/*') ?: [] as $file) {
            $zip->addFile($file, $prefix.'/data/'.basename($file));
        }

        $zip->close();

        return $archive;
    }

    private function makeStagingDirectory(int $requestId): string
    {
        $path = storage_path('app/data-export-staging/'.$requestId.'-'.bin2hex(random_bytes(8)));

        if (! mkdir($path.'/data', 0775, true) && ! is_dir($path.'/data')) {
            throw new RuntimeException('staging-unwritable');
        }

        return $path;
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (glob($path.'/data/*') ?: [] as $file) {
            @unlink($file);
        }

        @unlink($path.'/manifest.json');
        @unlink($path.'/README.txt');
        @rmdir($path.'/data');
        @rmdir($path);
        @unlink($path.'.zip');
    }
}
