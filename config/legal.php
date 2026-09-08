<?php

declare(strict_types=1);

return [

    /*
     * ŞİRKETİN YASAL KİMLİĞİ — FF-198 (`docs/107` Faz 1.2, `docs/124`).
     *
     * Hizmet Koşulları, Gizlilik Politikası, KVKK aydınlatması, Mesafeli
     * Satış Sözleşmesi ve Ön Bilgilendirme Formu tarafı olan tüzel kişiyi
     * adıyla, adresiyle, MERSİS ve vergi bilgisiyle anmak zorunda.
     *
     * VARSAYILAN YOK. Hepsi `.env`'den gelir; girilmemiş alan sayfada
     * "not yet provided" olarak görünür (`CompanyProfile`). Buraya örnek bir
     * ünvan ya da adres yazmak, sözleşmenin tarafını yanlış göstermek olurdu
     * — `config/contact.php` ve aşağıdaki `data_request` ile aynı karar.
     */
    'company' => [
        'legal_name' => env('LEGAL_COMPANY_LEGAL_NAME'),
        'address' => env('LEGAL_COMPANY_ADDRESS'),
        'mersis' => env('LEGAL_COMPANY_MERSIS'),
        'tax_office' => env('LEGAL_COMPANY_TAX_OFFICE'),
        'tax_number' => env('LEGAL_COMPANY_TAX_NUMBER'),
        'email' => env('LEGAL_COMPANY_EMAIL'),
        'phone' => env('LEGAL_COMPANY_PHONE'),
    ],

    /*
     * HUKUKİ İNCELEME TARİHİ (Y-m-d).
     *
     * Boş ya da geçersizken her yasal sayfa üstte "This text is pending
     * legal review" notu taşır. Geçerli bir tarih girildiğinde not kalkar
     * (`LegalReview`). "yes" gibi bir değer inceleme sayılmaz.
     */
    'reviewed_at' => env('LEGAL_REVIEWED_AT'),

    /*
     * BARINDIRMA — verinin fiziksel olarak DURDUĞU yer (FF-228, `docs/140` §3).
     *
     * Veri işleme sözleşmesinin (DPA) alt işleyen listesindeki ilk satır ve
     * bir zincirin hukukçusunun ilk sorusu: *veri nerede tutuluyor?*
     *
     * ═══ NEDEN BURADA BİR VARSAYILAN VAR — ŞİRKET KİMLİĞİNDE YOKKEN ═══
     *
     * İkisi farklı türde olgular. Bir tüzel kişinin ünvanı BİLİNEMEZ: bu
     * yazılımı kuran kişinin kim olduğunu kod bilemez ve bir varsayılan
     * yazmak sözleşmenin tarafını uydurmak olurdu. Barındırma ise BU ürünün
     * üretim dağıtımı için ölçülmüş bir olgudur — netcup GmbH, Karlsruhe,
     * Almanya (sahip doğruladı 2026-09-08; `docs/42`, `docs/43`).
     *
     * Yine de env'den geliyor, çünkü bu yazılım tek bir kuruluma ait değil
     * (`SAAS-DOMAIN`): kendi sunucusuna kuran biri kendi sağlayıcısını yazar
     * ve DPA'sı doğru olur. Değeri BOŞALTAN bir dağıtımda sayfa "not yet
     * provided" der — uydurmaz.
     *
     * NOT: `location` içinde ülke ADIYLA geçmeli. DPA'nın yurt dışı aktarım
     * bölümü (KVKK madde 9) okuyucunun bu satırı okumasına dayanır; "Bir veri
     * merkezi" gibi bir değer o bölümü anlamsız kılar.
     */
    'hosting' => [
        'provider' => env('LEGAL_HOSTING_PROVIDER', 'netcup GmbH'),
        'location' => env('LEGAL_HOSTING_LOCATION', 'Karlsruhe, Germany — outside Turkey'),
    ],

    /*
     * HESAP VERİSİ TALEBİ — `docs/110` (P0-09), FF-169.
     *
     * Sahip "hesabımdaki her şeyi istiyorum" dediğinde talebi nereye
     * yazacağını bilmeli. Talebin YOLU üründe zaten var (`/contact`: mesajı
     * saklar, hız sınırlıdır); burada eksik olan tek şey, talebin muhatabı
     * olan ADRESTİR.
     */
    'data_request' => [

        /*
         * Talebin iletileceği adres — sahibin gireceği bir OLGU.
         *
         * BOŞ bırakılırsa sayfa bunu açıkça söyler ve hiçbir adres
         * göstermez. Buraya bir varsayılan yazmak — örnek bir e-posta ya da
         * bir posta adresi — sahibin cevap gelmeyen bir kutuya yazmasına yol
         * açardı; `config/contact.php` içindeki `notify` ile aynı karar.
         *
         * Bir SÜRE TAAHHÜDÜ burada yok ve olmamalı: "şu kadar gün içinde
         * dönülür" cümlesi hukuki bir taahhüttür ve yapılandırmadan değil,
         * sahibin hukuki incelemesinden gelir.
         */
        'address' => env('LEGAL_DATA_REQUEST_ADDRESS'),

    ],

];
