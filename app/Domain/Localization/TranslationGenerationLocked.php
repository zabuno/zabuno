<?php

declare(strict_types=1);

namespace App\Domain\Localization;

use RuntimeException;

/**
 * Çeviri üretimi KİLİTLİ — FF-117, yönerge §10.2.
 *
 * İstisna mesajı açıktır ve kimin açabileceğini söyler: bir geliştirici bu
 * hatayı gördüğünde "bir şey bozulmuş" diye onarmaya çalışmamalı; kilit
 * bilerek kapalıdır.
 */
final class TranslationGenerationLocked extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'Translation generation is locked by owner policy. '
            .'Only the project owner can unlock it by saying "ÇEVİRİLERE BAŞLA"; '
            .'no config flag, scheduled task or agent may flip it.',
        );
    }
}
