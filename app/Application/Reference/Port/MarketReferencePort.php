<?php

declare(strict_types=1);

namespace App\Application\Reference\Port;

/**
 * Ülke, saat dilimi ve para birimi referans verisi.
 *
 * Neden bir port: bu veri ICU'dan gelir ve ICU sürümüne göre değişir.
 * Uygulama katmanının "hangi para birimi TRY'dir" sorusunu bir kütüphaneye
 * doğrudan sorması, o kütüphaneyi iş kuralının içine yerleştirirdi.
 *
 * Neden var: marka formu kullanıcıdan `ISTANBUL`, `TRY`, `tr_TR` yazmasını
 * istiyordu. Bunlar kullanıcı dili değil, geliştirici kodu. Kullanıcının
 * bildiği tek şey hangi ülkede iş yaptığıdır; gerisi ondan TÜRETİLİR.
 */
interface MarketReferencePort
{
    /**
     * Seçilebilir pazarlar (ülkeler), görünen adlarıyla.
     *
     * @return list<array{code: string, name: string}>
     */
    public function markets(): array;

    /**
     * Bir ülkenin saat dilimleri. Çoğu ülkede tek; ABD'de 29 tane var,
     * bu yüzden liste her zaman liste döner.
     *
     * @return list<array{id: string, label: string}>
     */
    public function timezonesFor(string $countryCode): array;

    /**
     * Seçilebilir para birimleri: ad, kod ve sembol birlikte.
     *
     * @return list<array{code: string, name: string, symbol: string, fractionDigits: int}>
     */
    public function currencies(): array;

    /**
     * Bir IANA saat diliminden ülke kodu.
     *
     * Tarayıcı kendi saat dilimini biliyor. Kullanıcıya boş bir ülke listesi
     * sunup aşağı kaydırtmak yerine, muhtemel cevabı ÖNERİP değiştirmesine
     * izin vermek doğru olan: sorulabilecek her şeyi sorma, çıkarılabileni
     * çıkar.
     */
    public function countryForTimezone(string $timezone): ?string;

    /**
     * Bir ülkeden GÜVENİLİR biçimde türetilebilenler.
     *
     * Dil BURADA YOK ve bu bilinçli: ICU'nun likely-subtags tablosu PHP'de
     * erişilebilir değil ve ülkeye bakarak dil tahmin etmek yanlış sonuç
     * veriyor (Türkiye için alfabetik ilk aday `ku_TR`, Birleşik Krallık
     * için `cy_GB`). Menü içerik dili kullanıcıya SORULUR; uydurulmuş bir
     * varsayılan, menüyü sessizce yanlış dille etiketler.
     *
     * @return array{timezone: string, currency: string}
     */
    public function defaultsFor(string $countryCode): array;
}
