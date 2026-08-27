<?php

declare(strict_types=1);

namespace App\Support\Build;

/**
 * Hangi kodun çalıştığının tek doğruluk kaynağı — "Preview Truth" (docs/52).
 *
 * Bu sınıf bir sürüm etiketi üretmek için yazılmadı. Somut bir olayı
 * engellemek için yazıldı: geliştirme checkout'u `1fea9b6` iken localhost
 * `6a923a3` sunuyordu ve EKRANDA bunu gösteren hiçbir şey yoktu. Sahip
 * güncel olmayan bir arayüze bakıp güncel sandı; o tur boyunca yapılan her
 * görsel değerlendirme boşa gitti.
 *
 * Buradaki asıl karar, kimliğin nasıl bulunduğu değil, NEREDE aranmadığıdır:
 *
 * - `git` KOMUTU ÇAĞRILMAZ. Her istekte bir süreç doğurmak üretimde
 *   kabul edilemez; dahası üretim sunucusunda çoğu zaman `git` yoktur ve
 *   olsa bile deploy edilen dizin bir çalışma kopyası değildir.
 * - `.git` bir DİZİN VARSAYILMAZ. Çalışma zamanı worktree'sinde `.git` bir
 *   DOSYADIR ve asıl depoyu işaret eder. Tam da yanlış sürümü sunan ortam
 *   bu olduğu için, burada yapılacak bir varsayım hatayı göremeyen bir
 *   dedektör üretirdi.
 *
 * Çözülemediğinde `null` döner ve BUNU söyler. Uydurulmuş bir sürüm
 * numarası, sürüm numarası olmamasından daha kötüdür: yanlış bir güven verir.
 */
final class BuildIdentity
{
    private function __construct(
        private readonly ?string $revision,
        private readonly ?int $builtAt,
        private readonly ?int $sourceChangedAt,
    ) {}

    public static function resolve(): self
    {
        return new self(
            self::resolveRevision(),
            self::resolveBuiltAt(),
            self::resolveSourceChangedAt(),
        );
    }

    public static function fromValues(?string $revision, ?int $builtAt, ?int $sourceChangedAt): self
    {
        return new self($revision, $builtAt, $sourceChangedAt);
    }

    public function revision(): ?string
    {
        return $this->revision;
    }

    /**
     * Kısa sürüm — insanın okuyup karşılaştırabileceği biçim.
     *
     * Yedi hane git'in kendi kısaltmasıyla aynı uzunlukta; sahibin terminalde
     * gördüğü metinle ekranda gördüğü metin böylece harfi harfine eşleşir.
     * Karşılaştırmayı gözle yapan biri için bu, biçim tercihi değil,
     * karşılaştırmanın mümkün olup olmaması meselesidir.
     */
    public function shortRevision(): ?string
    {
        if ($this->revision === null) {
            return null;
        }

        return substr($this->revision, 0, 7);
    }

    public function builtAt(): ?int
    {
        return $this->builtAt;
    }

    /**
     * Derlenmiş varlıklar kaynaktan ESKİ mi?
     *
     * Sürüm karşılaştırmasının YAKALAYAMADIĞI bayatlık türü budur ve
     * geliştirmede çok daha sık olanı da budur: bir `.tsx` dosyası
     * düzenlenir, `npm run build` çalıştırılmaz. Commit oluşmadığı için
     * sürüm kimliği zerre değişmez — iki taraf da aynı SHA'yı söyler ve
     * kontrol "her şey yolunda" der. Oysa ekrandaki JavaScript diskteki
     * kaynak değildir.
     *
     * Bu yüzden burada sürüme değil ZAMANA bakılır.
     */
    public function isBuildStale(): bool
    {
        if ($this->builtAt === null || $this->sourceChangedAt === null) {
            return false;
        }

        return $this->sourceChangedAt > $this->builtAt;
    }

    /**
     * @return array{revision: ?string, short_revision: ?string, built_at: ?int, source_changed_at: ?int, build_stale: bool}
     */
    public function toArray(): array
    {
        return [
            'revision' => $this->revision,
            'short_revision' => $this->shortRevision(),
            'built_at' => $this->builtAt,
            'source_changed_at' => $this->sourceChangedAt,
            'build_stale' => $this->isBuildStale(),
        ];
    }

    private static function resolveRevision(): ?string
    {
        $configured = config('build.revision');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return GitHead::read(base_path());
    }

    /**
     * Derleme anı = manifest'in değiştirilme anı.
     *
     * Ayrı bir zaman damgası dosyası yazmıyoruz, çünkü o dosya derlemeden
     * bağımsız olarak eskiyebilir veya elde güncellenebilir. Manifest ise
     * Vite'ın HER derlemede yeniden ürettiği dosyadır: varlıklar ile bu
     * damga aynı işlemde oluşur, ayrışamaz.
     */
    private static function resolveBuiltAt(): ?int
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            return null;
        }

        $mtime = @filemtime($manifest);

        return $mtime === false ? null : $mtime;
    }

    /**
     * En son değişen ön yüz kaynağının zamanı — YALNIZ üretim dışında.
     *
     * Üretimde bu tarama hem anlamsız hem zararlıdır: orada kaynak dosyalar
     * derlemeden sonra hiç değişmez ve her istekte binlerce dosyayı gezmek
     * saf israftır. Bayatlık geliştirme döngüsünün sorunudur, orada ölçülür.
     */
    private static function resolveSourceChangedAt(): ?int
    {
        if (app()->environment('production')) {
            return null;
        }

        $roots = [resource_path('js'), resource_path('css')];
        $newest = null;

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                // Testler derlenen pakete girmez; bir test dosyasını
                // düzenlemek varlıkları bayatlatmaz. Bunları saymak, hiç
                // var olmayan bir bayatlık uyarısı üretirdi — ve sürekli
                // yanlış alarm veren bir uyarı, kapatılan bir uyarıdır.
                $path = $file->getPathname();

                if (str_contains($path, '.test.') || str_contains($path, '/test/')) {
                    continue;
                }

                $mtime = $file->getMTime();

                if ($newest === null || $mtime > $newest) {
                    $newest = $mtime;
                }
            }
        }

        return $newest;
    }
}
