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

    /**
     * Sahibin EKİPTEN ÇIKARABİLECEĞİ roller.
     *
     * `invitable()` DEĞİLDİR ve ondan türetilmiş bir kısaltma da değildir —
     * ikisi ayrı iki soruya cevap verir: "kimi yeni alabilirim" ile "kimi
     * çıkarabilirim". Kaldırma koşulu bir süre davet listesini ödünç aldı ve
     * bu, kimsenin kastetmediği bir hapishane kurdu: `member` davet
     * edilemediği için ÇIKARILAMAZ da olmuştu. Veritabanında o rolü taşıyan
     * gerçek insanlar var; sahip "Çıkar" diyemiyordu, dese sunucu 404
     * döndürüyordu ve işten ayrılan biri çalışma alanını görmeye devam
     * ediyordu — kalıcı olarak.
     *
     * Bu yüzden `member` BURADA var ama `invitable()`'da yok ve olmamalı: o
     * role yeni kimse davet edilmemeli. Bir role kimseyi almamak, o rolü
     * taşıyanı içeride tutmak anlamına gelmez.
     *
     * `Owner` ise burada da yok, ve bu listenin tek gerçek sınırıdır:
     * sahiplik silinmez, DEVREDİLİR (`transferOwnership`). Silinseydi çalışma
     * alanı sahipsiz kalır ve kimse onaramazdı.
     *
     * Sıra: önce davet edilebilenler kaynağın kendi sırasıyla (Editör ·
     * Yönetici · Mutfak), sonra miras rol — okuyan kişi listenin nereden
     * geldiğini tek bakışta görsün.
     *
     * @return list<self>
     */
    public static function removable(): array
    {
        return [...self::invitable(), self::Member];
    }

    /**
     * Sahibin SAHİPLİĞİ DEVREDEBİLECEĞİ roller.
     *
     * Üçüncü ayrı soru, üçüncü ayrı liste: "kimi işe alırım" (`invitable()`),
     * "kimi çıkarırım" (`removable()`) ve "dükkânı kime bırakırım". İlk
     * ikisinden türetilmedi çünkü türetilseydi yanlış cevap verirdi — mutfak
     * ikisinde de var ama devralamaz, `member` çıkarılabilir ama devralamaz.
     *
     * NEDEN İKİ ROL. Devrin doğal adayı, işi ertesi gün fiilen çevirecek
     * kişidir: Yönetici menüyü, şubeyi ve karekodu zaten HER GÜN yürütür.
     * Editör de listededir çünkü çalışma alanının içeriğini o taşır.
     *
     * Bu liste bir süre yalnız `Editor`'dan ibaretti ve bu bir SEÇİM
     * DEĞİLDİ: kısıt yazıldığında (2026-08-24) enum yalnız `owner`, salt
     * okunur `member` ve `editor` taşıyordu — editör, devredilebilecek tek
     * adaydı. `Manager` dört gün sonra doğdu ve koşula kimse geri dönmedi;
     * sonuç, sahibin dükkânı devrederken günlük operasyonu yürüten kişiyi
     * seçemeyip yalnız içerik düzenleyeni seçebilmesiydi.
     *
     * MUTFAK BİLEREK DIŞARIDA. O rol ürünün tamamında iki şey görür: alerjen
     * ve "bugün bitti". Devir GERİ DÖNÜLEMEZ — eski sahip aynı işlemde
     * editöre iner ve geri alamaz — bu yüzden yanlış tıklama onarılamaz.
     * Aşçı sahip olacaksa önce yürütebileceği bir role yükseltilir; o yol
     * zaten var.
     *
     * `Owner` yok: kendine devir bir işlem değil, boş bir cümledir.
     * `Member` de yok: yeni kimsenin davet edilmediği, hiçbir şeyi
     * değiştiremeyen miras rol sahipliği devralamaz.
     *
     * @return list<self>
     */
    public static function ownershipTransferable(): array
    {
        return [self::Editor, self::Manager];
    }
}
