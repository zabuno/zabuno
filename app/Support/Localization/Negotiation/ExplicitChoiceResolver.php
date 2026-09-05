<?php

declare(strict_types=1);

namespace App\Support\Localization\Negotiation;

use Illuminate\Http\Request;

/**
 * AÇIK SEÇİM — zincirin en ağır halkası (`docs/120` §4.2).
 *
 * Almanya'da yaşayan bir Türk, tarayıcısı Almanca olsa da Türkçe okumak
 * isteyebilir. Bir kez seçtiyse sistem onu bir daha sorgulamaz; sorgulasaydı
 * her ziyarette kararını geri alırdı.
 *
 * Seçim bir ÇEREZDE yaşar, oturumda değil: giriş yapmamış bir ziyaretçi de
 * dil seçebilmeli ve seçimi bir sonraki ziyarete kalmalı. Çerez adı ayardan
 * gelir, çünkü çerez adı bir dağıtım kararıdır (alan adı, ön ek, çerez
 * politikası) ve koda gömülmemelidir.
 */
final class ExplicitChoiceResolver implements LanguageResolver
{
    public function resolve(Request $request, array $options): ?string
    {
        $cookie = is_string($options['cookie'] ?? null) ? $options['cookie'] : null;

        if ($cookie === null) {
            return null;
        }

        $value = $request->cookies->get($cookie);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
