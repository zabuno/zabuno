<?php

declare(strict_types=1);

namespace App\Support\Localization\Negotiation;

use Illuminate\Http\Request;

/**
 * Bir tespit YÖNTEMİ — `docs/120` §4.2.
 *
 * Her yöntem bir çözücüdür: ya bir dil etiketi döndürür ya `null`. `null`
 * dönen yöntem zinciri KESMEZ, sırayı bir sonrakine bırakır. Kesseydi,
 * çerezi olmayan her ziyaretçi kaynak dile düşerdi ve tarayıcı ayarı hiç
 * okunmazdı.
 *
 * Yöntemin AĞIRLIĞI burada yaşamaz; ağırlık yapılandırmadır
 * (`config/i18n.php`). Bir sıralama denemesi bir dağıtım değil, bir ayardır
 * — Drupal'ın kararının asıl değeri budur.
 *
 * Bir gün bir platform klavye düzenini okunur kılarsa, `KeyboardLayoutResolver`
 * yazılır ve zincire bir ağırlıkla eklenir; bu arayüz değişmez. Bugün
 * eklenmedi, çünkü hiçbir zaman çözemeyen bir yöntem zincire yalancı bir
 * halka takmaktır (`docs/120` §4.3).
 */
interface LanguageResolver
{
    /**
     * @param  array<string, mixed>  $options  Yöntemin kendi ayarı (çerez adı, eşleme tablosu…).
     */
    public function resolve(Request $request, array $options): ?string;
}
