<?php

declare(strict_types=1);

namespace App\Infrastructure\Localization;

use App\Application\Localization\Port\TranslationPort;
use RuntimeException;

/**
 * Derlenmiş MO dosyalarını okuyan çevirici — CORE-08.
 *
 * `ext-gettext` KASTEN kullanılmaz: paylaşımlı barındırmada bu eklentinin
 * varlığı garanti değildir ve `setlocale` süreç genelinde durum değiştirir —
 * çok kiracılı bir istekte bir tenant'ın dili diğerine sızabilir. MO ikili
 * formatı burada saf PHP ile okunur; hangi tenant hangi dili isterse o
 * tablo yüklenir, süreç durumu değişmez.
 */
final class MoFileTranslator implements TranslationPort
{
    /** @var array<string, array<string, string>> */
    private array $tables = [];

    public function __construct(private readonly string $basePath) {}

    /** @param  array<string, string>  $params */
    public function translate(string $domain, string $key, string $locale, array $params = []): string
    {
        // Düşme sırası: istenen dil → kaynak dil → anahtarın kendisi.
        // Anahtarı göstermek son çaredir ve kasten çirkindir: eksik çeviri
        // fark edilmeli, boş bir arayüzün arkasına saklanmamalı.
        $template = $this->table($domain, $locale)[$key]
            ?? $this->table($domain, $this->fallbackLocale())[$key]
            ?? $key;

        foreach ($params as $name => $value) {
            $template = str_replace('{'.$name.'}', $value, $template);
        }

        return $template;
    }

    public function missingCount(string $domain, string $locale): int
    {
        $source = $this->table($domain, $this->fallbackLocale());
        $target = $this->table($domain, $locale);

        return count(array_diff_key($source, $target));
    }

    private function fallbackLocale(): string
    {
        $configured = config('app.fallback_locale');

        return is_string($configured) && $configured !== '' ? $configured : 'en';
    }

    /** @return array<string, string> */
    private function table(string $domain, string $locale): array
    {
        $cacheKey = $locale.'/'.$domain;

        if (isset($this->tables[$cacheKey])) {
            return $this->tables[$cacheKey];
        }

        $path = $this->basePath.'/'.$locale.'/'.$domain.'.mo';

        return $this->tables[$cacheKey] = is_file($path)
            ? self::parse((string) file_get_contents($path))
            : [];
    }

    /**
     * GNU MO ikili formatını çözer.
     *
     * @return array<string, string>
     */
    public static function parse(string $binary): array
    {
        if (strlen($binary) < 28) {
            throw new RuntimeException('MO file is too short to contain a header.');
        }

        $magic = unpack('V', substr($binary, 0, 4));

        // Sihirli sayı bayt sırasını da söyler: ters sırayla yazılmış bir
        // dosyayı sessizce yanlış okumak, hiç okumamaktan kötüdür.
        $format = match ($magic[1]) {
            0x950412DE => 'V',
            0xDE120495 => 'N',
            default => throw new RuntimeException('Not a MO file: bad magic number.'),
        };

        $header = unpack($format.'revision/'.$format.'count/'.$format.'originals/'.$format.'translations', substr($binary, 4, 16));

        $table = [];

        for ($index = 0; $index < $header['count']; $index++) {
            $originalMeta = unpack($format.'length/'.$format.'offset', substr($binary, $header['originals'] + $index * 8, 8));
            $translationMeta = unpack($format.'length/'.$format.'offset', substr($binary, $header['translations'] + $index * 8, 8));

            $original = substr($binary, $originalMeta['offset'], $originalMeta['length']);
            $translation = substr($binary, $translationMeta['offset'], $translationMeta['length']);

            if ($original === '') {
                continue; // başlık girdisi
            }

            $table[$original] = $translation;
        }

        return $table;
    }
}
