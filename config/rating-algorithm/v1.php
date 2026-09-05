<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Puanlama algoritması — sürüm 1 (`docs/116` §2)
|--------------------------------------------------------------------------
|
| Sahibin kararı (2026-09-05): *"Puanlamanın KPI'ları, OKR'ları bir algoritma
| dosyasına bağlıdır. Bu algoritma mevcut verilerle zamanla geliştirilebilir."*
|
| BU DOSYA ÇALIŞMA ZAMANINDA DEĞİŞMEZ. Değiştirmek bir paket gerektirir:
| gözden geçirme, test ve yeni bir SÜRÜM NUMARASI. `v1.php` hiçbir zaman
| silinmez — `rating_scores.algorithm_version` bu dosyayı işaret eder ve
| dosya kaybolursa o sürümle hesaplanmış her puan açıklanamaz hâle gelir.
|
| BURADAKİ SAYILAR BUGÜNÜN ÖLÇÜMÜDÜR, EBEDÎ DOĞRU DEĞİL (`docs/109` §8.6).
| Veri biriktikçe değişmeleri beklenir; değiştikleri gün `v2.php` yazılır.
|
| METİNLER İNGİLİZCEDİR (KPI, OKR): bu depoda gönderilen tek dil İngilizce.
| Gerekçeler Türkçe yorumlarda yaşar — onlar sahibe yazılmıştır.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | KPI — neyi iyileştirmeye çalışıyoruz
    |----------------------------------------------------------------------
    |
    | Optimize edilen şey yazılı olmazsa, algoritma kimsenin kabul etmediği
    | bir hedefe doğru kayar: bir gün biri "ortalamayı yükseltelim" der ve
    | ağırlıklar yükselen ortalamaya göre ayarlanır. Yükselen ortalama bir
    | başarı değil, bir ayar hatasıdır.
    |
    */

    'kpi' => 'Rank the dishes a guest actually ate, in the order guests who were really there would rank them.',

    /*
    |----------------------------------------------------------------------
    | OKR — ölçülebilir hedef
    |----------------------------------------------------------------------
    |
    | Hedef yoksa "iyileşti" denemez. `current` bugün 0,0'dır ve bu
    | dürüsttür: henüz hiç sinyal toplanmadı. Kural, ilk oy toplanmadan
    | yazılıyor (`docs/116` §6) — tersi olsaydı kural, toplanan veriye göre
    | şekillenir ve ölçtüğü şeyi doğrulamış olurdu.
    |
    */

    'okr' => [
        'objective' => 'Show a rating on the menu only when it is backed by enough recent, at-the-table evidence.',
        // Menüdeki ürünlerin en az %60'ı gösterim eşiğini geçsin.
        'target' => 0.60,
        'current' => 0.0,
        'unit' => 'share_of_menu_items_above_display_threshold',
    ],

    /*
    |----------------------------------------------------------------------
    | Ağırlıklar — kaynak başına
    |----------------------------------------------------------------------
    |
    | EN SIK DEĞİŞECEK YER BURASI. Her kaynağın ağırlığı AÇIKÇA yazılır;
    | eksik bir kaynak `RatingAlgorithm` tarafından reddedilir, sessizce
    | sıfır sayılmaz.
    |
    | MASADAN GELEN OY EN AĞIRDIR ve bu bir tercih değil, elimizdeki tek
    | kanıttır: `guest_scan` için o kişinin o masadaki karekodu okuttuğunu
    | BİLİYORUZ. Zomato'daki bir yorumcunun restoranda olup olmadığını
    | bilmiyoruz; onun puanı bir bilgidir ama daha zayıf bir bilgidir.
    |
    | Dış kaynaklar arasındaki sıra hacim ve doğrulama gücüne göredir:
    | Google Haritalar en geniş ve konum doğrulamalı kitledir, Zomato
    | yemek odaklıdır, Swarm daha küçüktür. Sosyal uygulama en düşüktedir
    | çünkü henüz yoktur — ilk sürümünde ne kadar doğrulanabilir olacağını
    | bilmiyoruz ve bilmediğimiz bir kaynağa yüksek ağırlık vermek, onu
    | ölçmeden güvenmektir.
    |
    */

    'weights' => [
        'guest_scan' => 1.00,
        'external_google' => 0.35,
        'external_zomato' => 0.30,
        'external_swarm' => 0.25,
        'social_app' => 0.20,
    ],

    /*
    |----------------------------------------------------------------------
    | Zaman sönümü
    |----------------------------------------------------------------------
    |
    | 180 gün: bir oyun ağırlığı altı ayda yarıya iner. Bir restoran
    | menüsünün ve mutfağının altı ayda gözle görülür biçimde değişebildiği
    | varsayımına dayanır — mevsim değişir, tedarikçi değişir, şef değişir.
    |
    | Pencere değil yarı ömür seçildi: pencerede 90. gün ile 91. gün
    | arasında bir uçurum olur ve puan bir gecede sıçrar; sahip o sıçramanın
    | sebebini hiçbir yerde bulamaz.
    |
    */

    'recency' => [
        'half_life_days' => 180,
    ],

    /*
    |----------------------------------------------------------------------
    | Gösterim eşiği (`docs/116` §3)
    |----------------------------------------------------------------------
    |
    | Eşik altında puan GÖSTERİLMEZ; ekran "henüz yeterli değerlendirme yok"
    | der — sıfır yıldız DEĞİL, çünkü sıfır bir ölçümdür ve bilinmeyenin
    | yerine geçemez.
    |
    | İki koşul birden aranır ve ikisi farklı şeyi korur:
    | - `minimum_signals` = 8 → üç arkadaşın oyu bir ürünü listenin başına
    |   çıkaramaz.
    | - `minimum_weight` = 4.0 → sekiz oy VARSA ama hepsi üç yıllıksa,
    |   sönüm sonrası toplam ağırlık eşiği geçmez ve ölü bir puan ekranda
    |   kalmaz. (Sekiz taze masa oyu 8,0 ağırlık eder; eşiği rahat geçer.)
    |
    */

    'thresholds' => [
        'minimum_signals' => 8,
        'minimum_weight' => 4.0,
    ],

    /*
    |----------------------------------------------------------------------
    | Gösterilen ölçek
    |----------------------------------------------------------------------
    |
    | Beş üzerinden. Sinyalin KENDİ ölçeği satırında saklanır
    | (`rating_signals.score_scale_max`) ve hesaplama sırasında buna
    | çevrilir: bir sosyal uygulama beğen/beğenme (1 üzerinden) gönderse
    | bile, iki farklı birimdeki sayı aynı sütunda toplanmaz.
    |
    */

    'scale_max' => 5,

    /*
    |----------------------------------------------------------------------
    | Kötüye kullanım (`docs/116` §4)
    |----------------------------------------------------------------------
    |
    | KURALLAR BURADA YAZILI, UYGULAMASI P4'TE. Bugün yazılmalarının sebebi
    | şu: kuralı oy toplandıktan sonra yazmak, toplanan oya göre kural
    | yazmaktır.
    |
    | Bir sinyal kötüye kullanım sayıldığında SİLİNMEZ, işaretlenir
    | (`rating_signals.excluded_at`). Silmek, yanlış işaretlemenin geri
    | dönüşünü de silerdi: "bu oyu neden saymadık?" sorusunun cevabı, o oyun
    | kendisidir.
    |
    */

    'abuse' => [
        // Oy vermek için o masadan karekod okutmuş olmak gerekir. Ürünün
        // elindeki en güçlü sinyal budur; kapatılırsa ağırlık farkının
        // gerekçesi de ortadan kalkar.
        'require_table_scan_for_guest_signals' => true,

        // Ziyaretçi anahtarı + ürün başına tek oy.
        'one_signal_per_visitor_per_subject' => true,

        // Ani yığılma: bir masadan on beş dakikada altı oydan fazlası,
        // bir akşam yemeği değil bir kampanyadır.
        'burst_window_minutes' => 15,
        'burst_max_signals_per_table' => 6,

        // İşaretleme sebepleri — sütuna yazılan değerlerin kapalı listesi.
        // Serbest metin olsaydı, altı ay sonra aynı sebep dört farklı
        // yazımla kayıtlı olurdu ve "kaç oyu neden eledik?" sorusu
        // cevaplanamazdı.
        'exclusion_reasons' => [
            'burst_detected',
            'duplicate_visitor',
            'missing_table_scan',
            'owner_reported_fraud',
            'source_mapping_rejected',
            /*
                MİSAFİR FİKRİNİ DEĞİŞTİRDİ (P4).

                P1 göçü bu soruyu bilerek açık bıraktı: değişmez bir defterde
                "fikrimi değiştirdim"in tek karşılığı yeni bir satırdır ve o
                satır, eskisi hâlâ sayılıyorken tek oy kuralını bozar. Cevap
                şu: yeni satır sayılır, eskisi bu sebeple işaretlenir.

                `duplicate_visitor` İLE BİRLEŞTİRİLMEDİ. Fikrini değiştiren
                misafir bir kötüye kullanıcı değildir; ikisini tek sebepte
                toplasaydık "kaç oyu kötüye kullanım diye eledik?" sorusunun
                cevabı kalıcı olarak yanlış olurdu — ve o yanlış sayı, bir
                gün ağırlıkları ayarlarken kullanılırdı.

                BU SATIR v1'İ v2 YAPMAZ ve bunun ölçülebilir bir sebebi var:
                sebep sözlüğü hiçbir ağırlığın, sönümün ya da eşiğin girdisi
                değildir — v1 ile hesaplanmış hiçbir puan bu ekleme yüzünden
                değişmez. Sırf sözlük büyüdü diye v2 yazsaydık, v1 ile
                birebir aynı sayıları üreten bir sürüm doğardı ve "bu puan
                neden değişti?" sorusunun cevabı "değişmedi" olurdu — yani
                sürüm damgasının tek işini değersizleştirirdik.
            */
            'superseded',
        ],
    ],

];
