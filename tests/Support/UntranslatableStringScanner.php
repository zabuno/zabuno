<?php

declare(strict_types=1);

namespace Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Sunucuda üretilen görünümlerde KATALOG DIŞI kalan görünür metinleri bulur.
 *
 * Neden var: sahibi çeviriyi PO dosyasından yapar. Bir dize kaynak katalogda
 * yoksa PO'da satırı da yoktur — yani o dize hiçbir dile çevrilemez. React
 * tarafı katalogdan besleniyor; sunucu tarafı (Blade) besleneceğini kimse
 * garanti etmemişti. Bu tarayıcı o boşluğu sayılabilir yapar.
 *
 * Kapsam dışı bırakılanlar ve sebepleri:
 * - Blade direktifleri (`@include`, `@vite`) — kod, metin değil.
 * - Yorumlar — kullanıcıya görünmez.
 * - Çeviri çağrısı içeren ifadeler — zaten kataloğa bağlı.
 * - `<script>`/`<style>` gövdesi — kullanıcıya metin olarak görünmez.
 */
final class UntranslatableStringScanner
{
    /** Kullanıcıya metin olarak görünen öznitelikler. */
    private const VISIBLE_ATTRIBUTES = ['alt', 'placeholder', 'aria-label', 'title'];

    /**
     * @return array<string, list<string>> görünüm yolu => bulunan dizeler
     */
    public static function scanDirectory(string $directory): array
    {
        $found = [];

        foreach (self::bladeFiles($directory) as $path) {
            $hits = self::scanFile($path);

            if ($hits !== []) {
                $found[substr($path, strlen($directory) + 1)] = $hits;
            }
        }

        ksort($found);

        return $found;
    }

    /** @return list<string> */
    public static function scanFile(string $path): array
    {
        $source = (string) file_get_contents($path);

        // Kod ve görünmeyen gövdeler önce düşer, yoksa metin sanılırlar.
        $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
        $source = (string) preg_replace('/<!--.*?-->/s', '', $source);
        $source = (string) preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '<$1></$1>', $source);
        $source = (string) preg_replace('/@[a-zA-Z]+\s*(\((?:[^()]|(?1))*\))?/', '', $source);

        // Çeviriye bağlanmış ifadeler kapsam dışı: bunlar zaten kataloglu.
        $source = (string) preg_replace('/\{\{[^}]*(?:__\(|trans\(|@lang)[^}]*\}\}/', '', $source);

        $hits = [];

        // Sekme başlığı da ekranda görünen bir dizedir.
        if (preg_match('/<title>\s*([^<{]*[A-Za-z]{2,}[^<{]*)<\/title>/i', $source, $m) === 1) {
            $hits[] = 'title: '.self::normalise($m[1]);
            $source = (string) preg_replace('/<title>.*?<\/title>/is', '<title></title>', $source);
        }

        if (preg_match_all('/>([^<>{}]*[A-Za-z]{2,}[^<>{}]*)</u', $source, $m) === false) {
            $m = [1 => []];
        }

        foreach ($m[1] as $text) {
            $text = self::normalise($text);

            if ($text !== '' && preg_match('/[A-Za-z]{2,}/', $text) === 1) {
                $hits[] = 'text: '.$text;
            }
        }

        $attributes = implode('|', self::VISIBLE_ATTRIBUTES);

        if (preg_match_all('/\b('.$attributes.')="([^"{]*[A-Za-z]{2,}[^"{]*)"/', $source, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as $set) {
                $hits[] = $set[1].': '.self::normalise($set[2]);
            }
        }

        return $hits;
    }

    /** @return list<string> */
    private static function bladeFiles(string $directory): array
    {
        $files = [];

        /** @var iterable<\SplFileInfo> $iterator */
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private static function normalise(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
