<?php

declare(strict_types=1);

/*
    YEDEĞİN ÜRETİM AYARLARI — BACKUP-PRODUCE-01 (`docs/107` Faz 1.5).

    Bu depoda 2026-09-06'dan beri günlük bir yedek/geri yükleme TATBİKATI
    var (`security:evidence:backup-restore`, `docs/124`) ve o tatbikat kendi
    dökümünü işi bitince siler — doğrusu da budur, çünkü ölçtüğü şey "geri
    gelebiliyor mu", "duruyor mu" değil. Sonuç şuydu: `docker-compose`
    `db-backups` adlı kalıcı bir hacim tanımlıyordu, ona yazan hiçbir şey
    yoktu ve sunucu bugün kaybedilse geri dönülecek TEK BİR DOSYA yoktu.

    Buradaki değerler o dosyayı üreten komutun (`zabuno:backup:database`)
    ayarlarıdır. HİÇBİRİ `env()` OKUMAZ ve bu bilinçlidir: hedef yol ile
    `docker-compose.yml`'deki bağlama noktası aynı şeyi söylemek zorunda,
    kurulum başına ayrışabilen bir ortam değişkeni o zorunluluğu sessizce
    gevşetirdi — ve bir yedeğin nereye indiği, sunucu başına denenecek bir
    ayar değildir.

    Retention (eski yedeğin silinmesi), offsite kopya ve PITR bu paketin
    DIŞINDADIR: komutun silme yeteneği yoktur.
*/

return [

    'database' => [

        /*
            HEDEF, KONTEYNERİN İÇİNDE DEĞİL HACİMDE.

            `/backups`, `docker-compose.yml`'de `app` servisine bağlanan
            `db-backups` adlı kalıcı hacmin bağlanma noktasıdır. Bu satır
            ile o bağlama aynı şeyi söylemek zorundadır: konteyner katmanına
            yazılan bir döküm ilk `docker compose up -d` ile silinir ve
            kayıt yine de "başarılı" der.
        */
        'path' => '/backups',

        /*
            DOLAN DİSKTE DÖKÜM HİÇ BAŞLAMAZ.

            Yarım yazılan bir dosya iki kez zarar verir: yedek alınmamış
            olur ve aynı diskteki VERİTABANI da yazamaz hâle gelir — yani
            yedekleme işinin kendisi siteyi düşürür. 1 GiB ölçülmüş bir
            döküm boyutu değil, bir emniyet payıdır.
        */
        'minimum_free_bytes' => 1024 * 1024 * 1024,

        /*
            İSTEMCİ SUNUCUDAN ESKİ OLAMAZ — aynı ders `docs/124`'te bir kez
            öğrenildi. `pg_dump` kendinden yeni bir sunucuyu reddeder; eski
            bir istemcinin ürettiği arşiv geri yüklenemez ve dosyanın
            duruyor olması onu yedek yapmaz, yalnız yedeği olduğu
            YANILSAMASINI üretir.

            17 buradan uydurulmadı: `docker-compose.yml` `postgres:17-alpine`
            koşar ve imaj `postgresql-client-17` kurar
            (DEPLOY-PG-CLIENT-PARITY-14). Sunucu yükseltildiği gün bu sayı
            da yükselir.
        */
        'minimum_client_major' => 17,

        /*
            İkili yolları — üretimde ikisi de PATH üzerindedir ve `null`
            "PATH'te ara" demektir. Yalnız testler ve olağandışı kurulumlar
            için açık bir yol verilir.
        */
        'pg_dump_binary' => null,
        'pg_restore_binary' => null,

    ],

];
