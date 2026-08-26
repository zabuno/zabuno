<?php

declare(strict_types=1);

namespace App\Application\Localization\Port;

/**
 * Çeviri sözleşmesi — CORE-08.
 *
 * `domain` bir sorumluluk alanının kataloğudur (`auth`, `menu`, `workspace`…).
 * Çeviri bulunamazsa kaynak dizeye düşülür: eksik çeviri, boş bir arayüzden
 * her zaman iyidir ve `missingCount()` ile ölçülebilir kalır.
 */
interface TranslationPort
{
    /** @param  array<string, string>  $params */
    public function translate(string $domain, string $key, string $locale, array $params = []): string;

    /** Bir alan/locale çiftinde kaç dizenin çevrilmediği. */
    public function missingCount(string $domain, string $locale): int;
}
