<?php

declare(strict_types=1);

namespace App\Domain\Support;

/**
 * Destek referansının BİÇİMİ — FF-201 (`docs/125` §2).
 *
 * `ZB-` + beş karakter. Alfabe bilerek daraltılmış: 0 ile O, 1 ile I ve L
 * telefonda ve el yazısında birbirine karışır; bir müşteri destek hattında
 * "sıfır mı O mu?" diye sormamalı. Otuz bir karakterlik alfabeyle beş
 * basamak yaklaşık 28,6 milyon numara verir — bu ürünün ömründe
 * tükenmeyecek, ama tekil indeks yine de vardır ve çarpışma kodda çözülür.
 *
 * Üretim burada DEĞİL: rastgelelik bir altyapı meselesidir
 * (`RandomSupportReferenceGenerator`) ve test edilebilirlik için port
 * arkasındadır. Domain yalnız "geçerli bir referans nedir" sorusunu bilir.
 */
final class SupportReference
{
    public const PREFIX = 'ZB-';

    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const LENGTH = 5;

    public const PATTERN = '/^ZB-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{5}$/';

    public static function isValid(string $candidate): bool
    {
        return preg_match(self::PATTERN, $candidate) === 1;
    }
}
