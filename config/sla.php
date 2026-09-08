<?php

declare(strict_types=1);

return [

    /*
     * HİZMET SEVİYESİ RAKAMLARI — FF-228 (`docs/107` Faz 3.2, `docs/140`).
     *
     * ═══ BURAYA NEDEN BİR SAYI YAZILMADI ═══
     *
     * Bir SLA bir TAAHHÜTTÜR. "%99,9 çalışma süresi" cümlesi yazıldığı anda
     * sözleşmeye girer ve ilk kesintide sözleşmeye aykırılık doğurur. Bugün
     * bu üründe çalışma süresini ÖLÇEN hiçbir şey yok: durum sayfası
     * kurulmadı (`docs/107` Faz 3.4 ❌) ve üretimde tek bir kullanılabilirlik
     * kaydı bulunmuyor. Ölçülmeyen bir oranı taahhüt etmek, tutulup
     * tutulmadığı bilinemeyecek bir söz vermektir.
     *
     * Bu yüzden dört alanın da VARSAYILANI YOKTUR ve boş bırakılmıştır.
     * Boşken `/sla` sayfası hiçbir rakam göstermez ve neyin taahhüt
     * EDİLMEDİĞİNİ adıyla sayar (`ServiceLevelTerms`); bir rakam varmış gibi
     * görünmez. Aynı karar destek yanıt süresinde de verilmişti
     * (`config/support.php#response_commitment_hours`, `docs/125` §3).
     *
     * ═══ KİM DOLDURUR ═══
     *
     * Dördü de SAHİBİN ticari kararıdır ve hukuki incelemeden geçmeden
     * yayınlanmamalıdır. Bir yazılımcı bunları dolduramaz: her biri dışarıya
     * verilen bir söz ve bir maliyet taahhüdüdür.
     *
     * ═══ SIRA ÖNEMLİ ═══
     *
     * `measurement_source` olmadan bir oran yazmak, ölçüsü olmayan bir
     * terazide tartmaktır. Sayfa bu yüzden dördünü BİRLİKTE ister: biri bile
     * eksikken hiçbir taahhüt cümlesi yazılmaz.
     */

    /*
     * Takvim ayı başına hedeflenen kullanılabilirlik yüzdesi — ör. "99.5".
     *
     * Nokta ile yazılır ve yüzde işareti taşımaz; sayfa işareti kendisi
     * ekler. 0 ile 100 arasında olmayan ya da sayı olmayan bir değer
     * "girilmedi" sayılır: yanlış yazılmış bir ortam değişkeni sayfaya
     * "%0 kullanılabilirlik" yazdırmamalı.
     */
    'availability_target_percent' => env('SLA_AVAILABILITY_TARGET_PERCENT'),

    /*
     * Kullanılabilirliğin okunacağı KAMUYA AÇIK kaynak — durum sayfasının
     * adresi.
     *
     * Bir oran, müşterinin kendi başına doğrulayamadığı bir yerden okunuyorsa
     * taahhüt değil beyandır. Bu alan doldurulana kadar sayfa oranı yazmaz,
     * yazsa bile doğrulanamayacağını söylemek zorunda kalırdı.
     */
    'measurement_source' => env('SLA_MEASUREMENT_SOURCE'),

    /*
     * Bir kesinti fark edildikten sonra müşterinin haberdar edileceği azami
     * süre, SAAT olarak (tam sayı, sıfırdan büyük).
     */
    'incident_notification_hours' => env('SLA_INCIDENT_NOTIFICATION_HOURS'),

    /*
     * Hedef tutturulamadığında o ayın ücretine uygulanacak telafi oranı,
     * YÜZDE olarak (tam sayı, 1-100).
     *
     * Telafi PARA İADESİ DEĞİL, sonraki döneme mahsuptur ve bunun ne
     * olduğunu sayfa açıkça yazar; iade hâlleri İptal ve İade Politikasında
     * kalır — aynı olguyu iki belgeye yazmak, ikisinin bir gün ayrışması
     * demektir.
     */
    'service_credit_percent' => env('SLA_SERVICE_CREDIT_PERCENT'),

];
