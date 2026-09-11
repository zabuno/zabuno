<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
    KUYRUK, CRON İLE YÜRÜR (`docs/38` §8 paylaşımlı barındırma, HOST-QUEUE-04).

    Toplu AI okuması (`docs/98` FF-75) sayfa başına bir kuyruk işi atar. Bu
    sunucularda kalıcı bir `queue:work` süreci yoktur; dakikada bir çalışan
    ve kuyruk boşalınca KENDİNİ DURDURAN bir worker vardır — süreç birikmez,
    iş de bekleyip kalmaz. `withoutOverlapping`: bir dakikalık koşu uzarsa
    ikincisi üstüne binmez.
*/
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1')
    ->everyMinute()
    ->withoutOverlapping();

/*
    ZAMANLANMIŞ YAYIN ("Planla", sahibin 2026-09-05 kararı).

    Dakikada bir: sahip "bu gece 03:00" dediğinde menü 03:00'te değişir,
    03:15'te değil. `withoutOverlapping` bir koşu uzarsa ikincisinin üstüne
    binmesini önler; komutun kendisi de her kaydı atomik olarak sahiplenir,
    yani üst üste binse bile aynı menü iki kez yayınlanmaz.
*/
Schedule::command('zabuno:publish-scheduled-menus')
    ->everyMinute()
    ->withoutOverlapping();

/*
    ÇÖP GERÇEKTEN BOŞALIR — çünkü ekranda verilen bir söz var.

    Medya ekranı sahibe planına göre bir süre söylüyor ("silinen dosya N gün
    burada bekler, sonra kalıcı silinir") ve komut da yazılmıştı — ama komutu
    ÇAĞIRAN hiçbir şey yoktu. Yani söz veriliyordu ve tutulmuyordu: dosya
    çöpte kalıyor, kotadan düşmüyor, sahip yer açmak istediğinde açamıyordu.
    Çelişki denetimi (FF-161) bunu ekrandaki söz ile zamanlayıcının sessizliği
    arasındaki fark olarak buldu.

    GÜNDE BİR, DAKİKADA BİR DEĞİL. Saklama süresi gün ölçeğindedir (plana
    göre 7/30/90); dakikada bir taramak aynı sorguyu bin dört yüz kez boşuna
    koşturmaktı. Gece yarısından sonra seçildi: silme geri alınamaz bir iştir
    ve sahibin ekrana bakmadığı saatte yapılması, "az önce oradaydı" anını
    doğurmaz.

    SÜRE BURADA YAZILI DEĞİL. `--days` verilmiyor: komut her çalışma alanına
    KENDİ planının süresini uygular. Buraya bir sayı yazmak, kota kararını
    zamanlayıcıya kopyalamak olurdu ve iki gün sonra ikisi ayrışırdı.

    Komutun kendisi yayınlanmış bir menünün hâlâ gösterdiği dosyayı ATLAR —
    yani bu zamanlama, misafirin gördüğü bir görseli silemez.
*/
Schedule::command('media:purge-trash')
    ->dailyAt('03:20')
    ->withoutOverlapping();

/*
    DURAN YEDEK GÜNDE BİR — "tatbikat edilen yedek, duran yedek değildir"
    (`docs/107` Faz 1.5, BACKUP-PRODUCE-01).

    Ölçülen boşluk şuydu: `db-backups` adlı kalıcı hacim 2026'nın başından
    beri tanımlıydı ve İÇİ BOŞTU. Aşağıdaki tatbikat kendi dökümünü işi
    bitince SİLER — doğrusu da budur, çünkü ölçtüğü şey "geri gelebiliyor
    mu", "duruyor mu" değil. Yani sunucu bugün kaybedilse geri dönülecek
    tek bir dosya yoktu ve hiçbir ekran bunu kırmızı göstermiyordu.

    SAAT SEÇİMİ BİLİNÇLİ. Çöp boşaltımından (03:20) SONRA: silinen dosya
    yedeğe girmesin. Tatbikattan (03:40) ÖNCE: tatbikat o gecenin yedeği
    alınmış hâli ölçsün. Aradaki pencere ikisinin de sırasını korur.

    `withoutOverlapping`: veritabanı büyüdükçe döküm uzar; ertesi gecenin
    koşusu üstüne binerse aynı diske iki `pg_dump` birden yazar.

    BU SATIR "YEDEK VAR" DEMEZ. Komut hedefi, boş alanı ve `pg_dump`
    sürümünü döküm başlamadan ölçer ve biri eksikse SIFIRDAN FARKLI çıkar;
    ne olduğu koşunun kendi raporundan (`--json`) okunur.
*/
Schedule::command('zabuno:backup:database')
    ->dailyAt('03:30')
    ->withoutOverlapping();

/*
    YEDEK TATBİKATI GÜNDE BİR — "denenmemiş bir yedek, yedek değildir"
    (`docs/107` Faz 1.5, `docs/124`).

    Kanıt uçları 2026-08-26'dan beri vardı; onları dolduran şey bir insanın
    komutu hatırlamasıydı ve üretimde kimse hatırlamadı. Bu girdi, günlük
    tatbikatı (veritabanı: bağlantıya göre SQLite/PostgreSQL koşucusu;
    medya: `storage/app` medya kökü) zamanlayıcıya bağlar. Çöp boşaltımından
    (03:20) sonra: silinen dosyalar arşive girmesin.

    BU SATIR "ÇALIŞIYOR" DEMEZ. Çalışıp çalışmadığı yalnız kanıt kaydından
    okunur (`backup_restore_evidence`, `media_backup_restore_evidence` ve
    `/security/evidence/backup-restore` ucu). Uygulama imajında `pg_dump`
    yoksa kayıt "unknown" der; bu da bir kayıttır ve doğrudur.

    `withoutOverlapping`: gigabaytlık bir medya kökünde tatbikat uzayabilir;
    ertesi günün koşusu üstüne binmez.
*/
Schedule::command('security:evidence:backup-restore')
    ->dailyAt('03:40')
    ->withoutOverlapping();

/*
    VERİ SİLME TALEBİ VAKTİ GELİNCE YÜRÜR (FF-226, `docs/107` Faz 3.3,
    `docs/138`).

    Bu satır olmadan silme bir SÖZDEN ibaret kalırdı: ekran "şu tarihte
    silinecek" der, o tarih gelir ve hiçbir şey olmaz. Aynı ders bu depoda
    bir kez öğrenildi — medya çöp kutusu bir süre vaat ediyordu ve komutu
    çağıran hiçbir şey yoktu (FF-161).

    GÜNDE BİR, dakikada bir değil: pencere gün ölçeğindedir. Yedek
    tatbikatından (03:40) SONRA seçildi ve bu sıralama bilinçli — silinen
    veri o günün arşivine girmesin diye değil, tam tersine: tatbikat
    silmeden ÖNCEKİ hâli almış olsun ki bir arıza hâlinde geri dönülecek
    bir nokta bulunsun.

    Aynı koşu, süresi dolmuş dışa aktarma arşivlerini de diskten kaldırır:
    silme hakkının yanında sonsuza kadar duran bir kopya, o hakkı anlamsız
    kılar.
*/
Schedule::command('zabuno:run-due-erasures')
    ->dailyAt('04:10')
    ->withoutOverlapping();

/*
    ÖDEMESİZ SÜREYE GİRİŞ HABER VERİLİR (`docs/107` Faz 1.3, `docs/134`).

    ÖNCE ÇAĞIRAN, SONRA ÇAĞRILAN. Zamanlayıcıya bağlanmamış bir komut,
    ekranda verilmiş ama tutulmayan bir sözdür; bu depo o dersi medya çöp
    kutusunda bir kez öğrendi (FF-161). Ölçülen kusur şuydu: sahip ödemesiz
    süreye girdiğini yalnız panele bakarsa öğreniyordu — panele bakmayan
    sahip, sürenin dolduğunu ancak bir şey kapandığında fark ediyordu.

    GÜNDE BİR, DAKİKADA BİR DEĞİL: ödemesiz süre gün ölçeğindedir ve aynı
    taramayı bin dört yüz kez koşturmanın anlamı yok. Sabit saatte, çünkü
    komutun kendisi "bugün haber verildi mi?" sorusunu damgadan okur ve
    kayan bir saat aynı günü iki kez taramaya yol açardı.

    SABAH SEÇİLDİ ve bu, silme/arşiv işlerinden (03:20–04:10) FARKLI bir
    karardır: o işler geri alınamaz ve sahibin ekrana bakmadığı saatte
    yapılır. Bu iş ise tam tersini ister — sahip postayı açtığında gününün
    içinde olmalı ve ödemeyi aynı gün yapabilmeli.

    `withoutOverlapping`: paylaşımlı barındırmada bu makine dakikada bir
    koşan kuyrukla paylaşılır; çok sayıda çalışma alanında uzayan bir
    tarama ertesi koşunun üstüne binmemelidir.
*/
Schedule::command('zabuno:send-grace-reminders')
    ->dailyAt('08:30')
    ->withoutOverlapping();
