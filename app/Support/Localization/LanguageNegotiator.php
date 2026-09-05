<?php

declare(strict_types=1);

namespace App\Support\Localization;

use App\Support\Localization\Negotiation\LanguageResolver;
use Illuminate\Http\Request;

/**
 * Ağırlıklı tespit zinciri — `docs/120` §4.
 *
 * Sahibin yönlendirmesi (2026-09-05): "Drupal'da bahsettiğim ağırlık vardı ve
 * dil değiştirici buna göre otonom çalışıyordu."
 *
 * Deseni aynen alıyoruz. Dil seçimi bir `if/else` yığını değil, SIRALI BİR
 * ÇÖZÜCÜ KÜTÜĞÜDÜR: yöntemler ağırlığa göre sıralanır, ilk çözebilen kazanır,
 * çözemeyen sessizce sırayı bırakır. Sıra YAPILANDIRMADIR (`config/i18n.php`),
 * koda gömülü değildir — bir sıralama denemesi bir dağıtım değil, bir ayardır.
 *
 * İkinci ve daha önemli parçası: tek bir zincir değil, DİL TÜRÜ başına ayrı
 * zincir (`LanguageType`). Arayüz tarayıcıyla pazarlık eder, içerik etmez.
 */
final class LanguageNegotiator
{
    /**
     * İstenen türde dili çözer. Zincirin sonunda bile bir cevap yoksa `null`.
     *
     * `null` dönmesi çağıranın "hiçbir şey değiştirme" kararı vermesi
     * içindir; bugünkü yapılandırmada kaynak dil çözücüsü zincirin sonunda
     * durduğu için pratikte gerçekleşmez, ama zincir boşaltılabilir bir
     * ayardır ve boş bir zincirin uygulamayı çökertmesi kabul edilemez.
     */
    public function negotiate(LanguageType $type, Request $request): ?string
    {
        foreach ($this->chain($type) as $method) {
            $resolver = $this->resolverFor($method);

            if ($resolver === null) {
                continue;
            }

            /** @var array<string, mixed> $options */
            $options = $this->methodConfig($method)['options'] ?? [];

            $answer = $resolver->resolve($request, $options);

            if ($answer === null) {
                // `null` ZİNCİRİ KESMEZ. Çoğu istekte çoğu yöntem çözemez;
                // kesseydi çerezi olmayan her ziyaretçi kaynak dile düşerdi.
                continue;
            }

            $language = Language::tryFromTag($answer);

            if ($language === null) {
                /*
                    KÜTÜKTE OLMAYAN BİR DİL CEVAP DEĞİLDİR.

                    `?language=ja` ya da `/ja/` ile gelen bir istek,
                    altyapının hiç tanımadığı bir dili uygulamaya sokamaz:
                    `setLocale('ja')` olmayan bir kataloğu işaret ederdi ve
                    hata değil, sessiz bir boşluk üretirdi.
                */
                continue;
            }

            if (! $this->isAcceptable($type, $language)) {
                /*
                    SUNULMAYAN DİL DÜŞER, SIRA DEVAM EDER.

                    Arayüz zinciri `shipped_locales` ile süzülür. Aksi hâlde
                    2026-09-05'te kapatılan kusur geri gelirdi: Türkçe bir
                    tarayıcı Türkçe belge alır, katalog tam olmadığı için
                    ekranda "Menus" ile "Ürün ekle" yan yana durur. Yarım
                    çeviri çevirisizlikten kötüdür.

                    İçerik zinciri süzülmez: `/tr/` altındaki sayfa Türkçe
                    YAZILMIŞTIR ve onu İngilizce ilan etmek ekran okuyucuyu
                    ve arama motorunu yanıltır.
                */
                continue;
            }

            return $language->value;
        }

        return null;
    }

    /** @return list<string> */
    private function chain(LanguageType $type): array
    {
        /** @var array<int, string> $configured */
        $configured = config('i18n.negotiation.chains.'.$type->value, []);

        /*
            SIRALAMA AĞIRLIKTAN GELİR, yazılış sırasından değil. Zinciri
            yapılandırmada yeniden dizmek zorunda kalmak, ağırlık fikrinin
            kendisini boşa çıkarırdı: bir yöntemin ağırlığını değiştirmek
            tek başına yeterli olmalı.
        */
        $weighted = [];

        foreach ($configured as $method) {
            $weighted[$method] = $this->weightOf($method);
        }

        asort($weighted);

        return array_keys($weighted);
    }

    private function weightOf(string $method): int
    {
        $weight = $this->methodConfig($method)['weight'] ?? null;

        // Ağırlığı yazılmamış bir yöntem zincirin SONUNA düşer: sessizce öne
        // geçip her şeyi ezmesindense, hiç konuşmaması daha az zararlıdır.
        return is_int($weight) ? $weight : PHP_INT_MAX;
    }

    /** @return array<string, mixed> */
    private function methodConfig(string $method): array
    {
        $config = config('i18n.negotiation.methods.'.$method, []);

        return is_array($config) ? $config : [];
    }

    private function resolverFor(string $method): ?LanguageResolver
    {
        $class = $this->methodConfig($method)['resolver'] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $resolver = app($class);

        return $resolver instanceof LanguageResolver ? $resolver : null;
    }

    private function isAcceptable(LanguageType $type, Language $language): bool
    {
        /** @var array<int, string> $filtered */
        $filtered = config('i18n.negotiation.shipped_only', []);

        if (! in_array($type->value, $filtered, true)) {
            return true;
        }

        /** @var array<int, string> $shipped */
        $shipped = config('i18n.shipped_locales', []);

        return in_array($language->value, $shipped, true);
    }
}
