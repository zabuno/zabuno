<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Domain\Tenancy\MembershipRole;

final class RolePermissions
{
    /**
     * Rol → izin listesi.
     *
     * Sınırlar planın tarif ettiği gibi (`docs/70`):
     *
     * - **Owner** her şeyi yapar.
     * - **Manager** menü, şube ve karekod yönetir; faturayı GÖRÜR ama
     *   yönetmez.
     * - **Editor** içerik düzenler; YAYINLAMAZ, şube/marka ayarlarına
     *   dokunmaz, faturayı hiç görmez.
     * - **Kitchen** yalnız alerjen ve "bugün bitti"; başka bir şey görmez
     *   (`docs/109` §6.4).
     * - **Member** salt okunurdur ve yalnız eski kayıtlar için vardır.
     *
     * Yayınlama iznini `Editor`'dan ayırmak kasıtlıdır: içerik düzenlemek
     * geri alınabilir bir iştir, yayınlamak ise misafirin gördüğü menüyü
     * değiştirir. İkisini aynı role vermek, en kolay yetkiyi en geniş
     * sonuçla eşleştirmek olurdu.
     *
     * SİPARİŞ EKSENİ (`docs/115` §4) bu listeye dördüncü bir soru ekliyor:
     * "servis anında kim ne yapar?". Editor ve Member'da HİÇBİR `order.*`
     * izni yoktur ve bu bilinçli bir boşluktur: Editor içerik düzenler,
     * servis anının işi değildir; Member ise yalnız eski kayıtlar için
     * yaşayan salt okunur bir roldür ve tarifedeki matriste hiç geçmez.
     * Sessizce "salt okunur olduğu için görsün" demek, masadaki misafirin ne
     * yediğini kimsenin vermediği bir role açmak olurdu.
     *
     * `MenuAllergensManage` ve `MenuStockManage`, `MenuManage`'in İÇİNDEN
     * çıkarılan iki dar eksendir. Bu yüzden `MenuManage` taşıyan üç rolün
     * (Owner/Manager/Editor) listesine de AÇIKÇA eklendiler: dünkü üründe
     * bu üç rol alerjen düzeltip "bitti" işaretleyebiliyordu ve yeni bir rol
     * doğarken onlardan bir yetenek alınamaz. Türetme yerine açık liste
     * tercih edildi — bu dosyanın tek işi "kim neyi yapabilir" sorusunu tek
     * bakışta cevaplamaktır; gizli bir kural, o cevabı okunamaz kılardı.
     *
     * @return list<Permission>
     */
    public static function for(MembershipRole $role): array
    {
        return match ($role) {
            MembershipRole::Owner => [
                Permission::WorkspaceView,
                Permission::WorkspaceManage,
                Permission::MenuView,
                Permission::MenuManage,
                Permission::MenuAllergensManage,
                Permission::MenuStockManage,
                Permission::MenuPublish,
                Permission::QrView,
                Permission::QrCreate,
                Permission::QrDisable,
                Permission::QrDesignManage,
                Permission::AnalyticsView,
                Permission::BillingView,
                Permission::BillingManage,
                Permission::SecurityEvidenceView,
                Permission::MediaManage,
                Permission::MediaDownloadOriginal,
                Permission::OrderView,
                Permission::OrderConfirm,
                Permission::OrderKitchen,
                // Hizmeti açıp kapatmak SAHİBİN kararıdır (`docs/115` §4).
                Permission::OrderSettings,
            ],
            MembershipRole::Manager => [
                Permission::WorkspaceView,
                Permission::WorkspaceManage,
                Permission::MenuView,
                Permission::MenuManage,
                Permission::MenuAllergensManage,
                Permission::MenuStockManage,
                Permission::MenuPublish,
                Permission::QrView,
                Permission::QrCreate,
                Permission::QrDisable,
                Permission::QrDesignManage,
                Permission::AnalyticsView,
                // Faturayı görebilir — planın yasağı YÖNETMEKle ilgili.
                // Görmeyi de engellemek, planın söylemediği bir kısıt eklemek
                // olurdu.
                Permission::BillingView,
                Permission::MediaManage,
                Permission::MediaDownloadOriginal,
                /*
                    Yönetici servisi YÜRÜTÜR: kuyruğu görür, onaylar, mutfak
                    monitörünü açar. `order.settings` burada YOK ve bu bir
                    unutkanlık değil — sipariş almayı kapatmak akşam
                    servisinin ortasında mutfağa gelen işi kesmektir ve
                    sahibinden habersiz yapılmamalıdır.
                */
                Permission::OrderView,
                Permission::OrderConfirm,
                Permission::OrderKitchen,
            ],
            MembershipRole::Editor => [
                Permission::WorkspaceView,
                Permission::MenuView,
                // Adının söylediği şey: içerik düzenler.
                Permission::MenuManage,
                Permission::MenuAllergensManage,
                Permission::MenuStockManage,
                Permission::QrView,
                Permission::AnalyticsView,
                // Görsel yüklemek içerik düzenlemektir; yayınlamak değildir.
                Permission::MediaManage,
                Permission::MediaDownloadOriginal,
            ],
            /*
                MUTFAK — kaynağın cümlesi: "Alerjen ve 'bugün bitti'. Başka
                bir şey görmez."

                Liste DÖRT satırdır ve dördü de zorunludur: `WorkspaceView`
                olmadan aşçı kabuğa hiç giremez, `MenuView` olmadan
                işaretleyeceği ürünü bulamaz. Kalan ikisi de işin kendisi.

                Burada OLMAYANLAR listenin kendisi kadar anlamlıdır:
                `MenuManage` (fiyat/ürün/kategori), `MenuPublish`, `QrView`,
                `AnalyticsView`, `BillingView`, `WorkspaceManage`,
                `MediaManage` ve `MediaDownloadOriginal`. Hiçbiri "unutulmuş"
                değil; her biri kaynağın "başka bir şey görmez" cümlesinin
                doğrudan sonucudur.
            */
            MembershipRole::Kitchen => [
                Permission::WorkspaceView,
                Permission::MenuView,
                Permission::MenuAllergensManage,
                Permission::MenuStockManage,
                /*
                    MUTFAK MONİTÖRÜ (`docs/115` §4, K1–K5). Aşçı onaylanmış
                    işi görür ve ilerletir; ONAYLAMAZ. Onay bir servis
                    kararıdır: masada kimin oturduğunu gören kişi verir.
                    `order.confirm` buraya eklenseydi, dışarıdan karekodu
                    okutan birinin talebi doğrudan ocağa iş açardı.
                */
                Permission::OrderView,
                Permission::OrderKitchen,
            ],
            MembershipRole::Member => [
                Permission::WorkspaceView,
                Permission::MenuView,
                Permission::QrView,
                Permission::AnalyticsView,
                // Salt okunur; yine de aslı indirebilir (sahip kararı).
                Permission::MediaDownloadOriginal,
            ],
        };
    }
}
