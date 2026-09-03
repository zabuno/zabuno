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
