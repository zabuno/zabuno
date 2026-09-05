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
     * Alerjen bilgisini ve "bugün bitti"yi işaretler. BAŞKA BİR ŞEY GÖRMEZ.
     *
     * Kanonik kaynak `docs/reference/panel-v3/panel.dc.html`
     * (`data-screen-label="Takım"`), cümlesi `docs/109` §6.4: *"Mutfak —
     * Alerjen ve 'bugün bitti'. Başka bir şey görmez."* Değer de kaynağın
     * kendi anahtarıdır: `<option value="kitchen">Mutfak</option>`.
     *
     * NEDEN AYRI BİR ROL. Akşam servisinde balığın bittiğini ve tabakta
     * fıstık olduğunu bilen tek kişi mutfaktadır. Bu rol doğmadan önce
     * sahibin iki seçeneği vardı: ya servisin ortasında kendi telefonundan
     * girip işaretleyecek, ya da aşçıya `Editor` verip bütün menünün
     * fiyatlarını da açacaktı. Aradaki dar rol yoktu.
     *
     * SINIR SUNUCUDADIR. Ekranda düğmeyi çizmemek koruma değildir; bu rolün
     * yapamadığı her şey uçlarda 403/404 ile durur
     * (`tests/Feature/MenuCatalog/KitchenRoleMenuBoundaryTest`).
     */
    case Kitchen = 'kitchen';

    /**
     * Sahibin DAVET EDEBİLECEĞİ roller.
     *
     * `Owner` listede yok: sahiplik davetle verilmez, DEVREDİLİR — ayrı bir
     * akışı ve ayrı bir sonucu vardır. `Member` de yok: yeni kimse salt
     * okunur bir role davet edilmemeli, o rol yalnız eski kayıtlar için var.
     *
     * Sıra KAYNAĞIN sırasıdır (Editör · Yönetici · Mutfak), yetki
     * genişliğinin sırası değil: davet kartındaki haplar bu listeden çizilir
     * ve ilk sıradaki aynı zamanda VARSAYILANDIR. Varsayılanın en dar
     * rollerden biri olması kasıtlıdır — acele eden bir sahip, kazara en
     * geniş yetkiyi dağıtmamalı.
     *
     * @return list<self>
     */
    public static function invitable(): array
    {
        return [self::Editor, self::Manager, self::Kitchen];
    }
}
