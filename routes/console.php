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
