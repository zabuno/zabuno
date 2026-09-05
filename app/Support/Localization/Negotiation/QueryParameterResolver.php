<?php

declare(strict_types=1);

namespace App\Support\Localization\Negotiation;

use Illuminate\Http\Request;

/**
 * OTURUM PARAMETRESİ — `?language=tr` (`docs/120` §4.2).
 *
 * Önizleme ve paylaşım içindir: bir sayfayı belirli bir dilde göstermek
 * istediğinde adres tek başına yeter, kimsenin çerez kurması gerekmez.
 *
 * Açık seçimden HAFİFTİR ve bu kasıtlı: bir bağlantı paylaşımı, kullanıcının
 * kendi kalıcı tercihini ezmemeli. Ayrıca arayüz zinciri sunulan dillerle
 * süzüldüğü için, bu parametre üzerinden sunulmayan bir dil dayatılamaz.
 */
final class QueryParameterResolver implements LanguageResolver
{
    public function resolve(Request $request, array $options): ?string
    {
        $name = is_string($options['parameter'] ?? null) ? $options['parameter'] : null;

        if ($name === null) {
            return null;
        }

        $value = $request->query($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
