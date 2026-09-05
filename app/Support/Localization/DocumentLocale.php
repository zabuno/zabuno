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
    /**
     * Kütükte OLMAYAN ama sağdan sola yazılan diller.
     *
     * Dokuz dilin yönü artık `Language` kütüğünden gelir (`docs/120` §2) ve
     * burada tekrar edilmez — iki liste bir gün ayrışır ve ayrıştığı gün
     * Arapça bir sayfa soldan sağa çizilir.
     *
     * İbranice ve Urduca kütükte yok çünkü sahibin dokuz diline dahil
     * değiller; ama `<html dir>` yanlış yazmaktansa doğru yazmalı: bir gün
     * bir kiracı içeriği o dillerde gelirse, belge yönü sessizce yanlış
     * olmasın diye burada kalıyorlar.
     */
    private const EXTRA_RTL_LANGUAGES = ['he', 'ur'];

    public static function tag(?string $locale = null): string
    {
        return str_replace('_', '-', $locale ?? app()->getLocale());
    }

    public static function direction(?string $locale = null): string
    {
        $tag = self::tag($locale);
        $known = Language::tryFromTag($tag);

        if ($known !== null) {
            return $known->direction();
        }

        $base = strtolower(explode('-', $tag)[0]);

        return in_array($base, self::EXTRA_RTL_LANGUAGES, true) ? 'rtl' : 'ltr';
    }
}
