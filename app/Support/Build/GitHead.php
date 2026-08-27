<?php

declare(strict_types=1);

namespace App\Support\Build;

/**
 * `.git` dizinini DOSYA olarak okuyup HEAD sürümünü çözer — süreç doğurmadan.
 *
 * Neden `git rev-parse` çağırmıyoruz: bu kod bir web isteği içinde çalışır.
 * Her sayfa yüklemesinde süreç doğurmak, çözdüğü sorundan pahalıdır; üretim
 * sunucusunda `git` çoğu zaman kurulu değildir; ve `shell_exec` üretimde
 * genellikle kapalıdır. Dosyaları okumak üçünde de çalışır.
 *
 * Bu okuyucunun bilerek desteklediği ÜÇ yerleşim var ve üçü de Zabuno'da
 * fiilen kullanılıyor:
 *
 * 1. Normal depo — `.git` bir dizindir.
 * 2. Worktree — `.git` bir DOSYADIR ve `gitdir: ...` satırıyla asıl yeri
 *    gösterir. Yanlış sürümü sunan localhost çalışma zamanı tam olarak
 *    budur, yani bu dal desteklenmezse dedektör hedefini ıskalar.
 * 3. Ayrık HEAD — HEAD doğrudan SHA taşır. Çalışma zamanı worktree'si
 *    `origin/main` üzerine ayrık duruyor, yani en sık karşılaşılan hâl bu.
 *
 * Çözemediğinde `null` döner. Tahmin yürütmez.
 */
final class GitHead
{
    public static function read(string $basePath): ?string
    {
        $gitPath = rtrim($basePath, '/').'/.git';

        $gitDir = self::resolveGitDir($gitPath);

        if ($gitDir === null) {
            return null;
        }

        $head = self::readFile($gitDir.'/HEAD');

        if ($head === null) {
            return null;
        }

        // Ayrık HEAD: dosyanın içeriği doğrudan commit kimliğidir.
        if (self::looksLikeSha($head)) {
            return $head;
        }

        if (! str_starts_with($head, 'ref: ')) {
            return null;
        }

        $ref = trim(substr($head, 5));

        return self::resolveRef($gitDir, $ref);
    }

    private static function resolveGitDir(string $gitPath): ?string
    {
        if (is_dir($gitPath)) {
            return $gitPath;
        }

        if (! is_file($gitPath)) {
            return null;
        }

        $contents = self::readFile($gitPath);

        if ($contents === null || ! str_starts_with($contents, 'gitdir: ')) {
            return null;
        }

        $resolved = trim(substr($contents, 8));

        return is_dir($resolved) ? $resolved : null;
    }

    /**
     * Bir dalı SHA'ya çevirir.
     *
     * İki yere bakmak ZORUNLU: git referansları ya tek tek dosyalar hâlinde
     * (`refs/heads/main`) ya da `packed-refs` içinde tek bir dosyada toplu
     * durur ve `git gc` istediği zaman ikincisine taşır. Yalnız ilkine bakan
     * bir okuyucu, deponun bakım görmesiyle birlikte SESSİZCE çalışmayı
     * bırakırdı — sonra da uyarı vermeyi.
     *
     * Worktree'de referanslar ortak dizindedir; `commondir` oraya götürür.
     */
    private static function resolveRef(string $gitDir, string $ref): ?string
    {
        $searchDirs = [$gitDir];

        $commonDir = self::readFile($gitDir.'/commondir');

        if ($commonDir !== null) {
            $candidate = str_starts_with($commonDir, '/')
                ? $commonDir
                : $gitDir.'/'.$commonDir;

            $real = realpath($candidate);

            if ($real !== false) {
                $searchDirs[] = $real;
            }
        }

        foreach ($searchDirs as $dir) {
            $loose = self::readFile($dir.'/'.$ref);

            if ($loose !== null && self::looksLikeSha($loose)) {
                return $loose;
            }
        }

        foreach ($searchDirs as $dir) {
            $packed = self::readFile($dir.'/packed-refs');

            if ($packed === null) {
                continue;
            }

            foreach (explode("\n", $packed) as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '^')) {
                    continue;
                }

                $parts = preg_split('/\s+/', $line, 2);

                if ($parts === false || count($parts) !== 2) {
                    continue;
                }

                if ($parts[1] === $ref && self::looksLikeSha($parts[0])) {
                    return $parts[0];
                }
            }
        }

        return null;
    }

    private static function looksLikeSha(string $value): bool
    {
        return preg_match('/^[0-9a-f]{40}$/', $value) === 1;
    }

    private static function readFile(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false ? null : trim($contents);
    }
}
