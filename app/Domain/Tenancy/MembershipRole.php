<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

enum MembershipRole: string
{
    case Owner = 'owner';

    /**
     * Eski kayıtların taşıdığı SALT OKUNUR üyelik.
     *
     * Yeni davetler bu rolü kullanmaz. Kaldırılmadı çünkü veritabanında bu
     * değeri taşıyan satırlar var ve enum'dan silmek onları okunamaz hâle
     * getirirdi.
     */
    case Member = 'member';

    /**
     * İçerik düzenler; YAYINLAMAZ ve ekip yönetmez.
     *
     * Bu rol bir süre yalnız bir ETİKETTİ: izin listesi `Member` ile
     * aynıydı, yani "editör" olarak davet edilen kişi hiçbir şeyi
     * düzenleyemiyordu — adı, yapabildiği şeyi yanlış anlatıyordu
     * (`docs/70`).
     */
    case Editor = 'editor';

    /**
     * Menü, şube ve karekod yönetir; FATURALAMAYI yönetmez.
     *
     * Planın tarif ettiği üç rolden eksik olanıydı: sahibin, faturaya
     * dokunamayan ama günlük operasyonu yürütebilen birini davet etmesinin
     * yolu yoktu.
     */
    case Manager = 'manager';

    /**
     * Sahibin DAVET EDEBİLECEĞİ roller.
     *
     * `Owner` listede yok: sahiplik davetle verilmez, DEVREDİLİR — ayrı bir
     * akışı ve ayrı bir sonucu vardır. `Member` de yok: yeni kimse salt
     * okunur bir role davet edilmemeli, o rol yalnız eski kayıtlar için var.
     *
     * @return list<self>
     */
    public static function invitable(): array
    {
        return [self::Editor, self::Manager];
    }
}
