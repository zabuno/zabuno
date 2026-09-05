<?php

declare(strict_types=1);

namespace App\Application\Publication\Dto;

/**
 * ŞUBE ŞU ANDA KAPALI — menünün ÜSTÜNDEKİ dürüst şerit (FF-141).
 *
 * KAPALI OLMAK MENÜYÜ GİZLEMEZ. Gece 23:00'te karekodu okutan misafir çoğu
 * zaman yarını planlıyordur; menüyü saklamak ona hizmet etmez, yalnız
 * elimizdeki bilgiyi ondan gizler. Bu yüzden menü çizilir ve üstünde tek bir
 * şerit durur.
 *
 * SERVİS DIŞI HÂLİYLE KARIŞTIRILMAZ (`GuestMenuView`, FF-139). Orada
 * gösterilecek bir menü YOKTUR ve sayfa menüyü hiç çizmez; burada menü
 * vardır. İkisini tek duruma indirmek, misafire ya var olan menüyü
 * kaybettirir ya olmayan bir menü vaat ederdi.
 *
 * SAAT UYDURULMAZ. `nextOpeningClock` ancak şubenin kendi haftasından
 * çıkabiliyorsa doludur; yedi günü de kapalı bir şube için `null` kalır ve
 * ekranda saat satırı HİÇ çizilmez. "0", tahmini bir gün adı ya da varsayılan
 * bir "09:00" yazmak, tutulmayacak bir söz vermek olurdu.
 */
final class GuestClosedNotice
{
    public function __construct(
        /** Şubenin kendi saat diliminde `HH:MM`; veriden çıkmıyorsa `null`. */
        public readonly ?string $nextOpeningClock = null,
        /**
         * Açılışın ISO günü (1 = Pazartesi … 7 = Pazar).
         *
         * Cümlenin gün adını METİN KATALOĞU çözer; burada yalnız hangi gün
         * olduğu taşınır. Gün adını bu katmanda üretmek, misafirin dilini
         * bilmeyen bir yerde çeviri kararı vermek olurdu.
         */
        public readonly ?int $nextOpeningIsoWeekday = null,
        /**
         * Açılış BUGÜN mü?
         *
         * Gün numarasından türetilemez: bir hafta sonra aynı gün de aynı
         * numarayı taşır. "Bugün 09:00" ile "Pazartesi 09:00" masadaki
         * misafir için apayrı iki cümledir.
         */
        public readonly bool $nextOpeningIsToday = false,
    ) {}
}
