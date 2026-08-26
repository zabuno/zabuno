<?php

declare(strict_types=1);

namespace App\Support\Localization;

/**
 * `<html lang>` ve `<html dir>` için tek kaynak.
 *
 * Bu değerler dört Blade şablonunda elle yazılıydı ve tutarsızdı: uygulama
 * kabukları `en`, public menü `tr` diyordu ve hiçbiri gerçek bir locale
 * ayarından türemiyordu. Bu yalnız bir tutarsızlık değil — istemci tarafı
 * çevirici locale'i `<html lang>`'den okur, yani sabit kodlanmış bir etiket
 * dil seçimini sessizce dondurur.
 *
 * Yön burada çözülür çünkü RTL bir locale özelliğidir; hiçbir şablonun veya
 * bileşenin bunu bilmesi gerekmez (`docs/37` §2.2, kesen eksen X3).
 */
final class DocumentLocale
{
    /** RTL yazılan diller. */
    private const RTL_LANGUAGES = ['ar', 'fa', 'he', 'ur'];

    public static function tag(?string $locale = null): string
    {
        return str_replace('_', '-', $locale ?? app()->getLocale());
    }

    public static function direction(?string $locale = null): string
    {
        $base = strtolower(explode('-', self::tag($locale))[0]);

        return in_array($base, self::RTL_LANGUAGES, true) ? 'rtl' : 'ltr';
    }
}
