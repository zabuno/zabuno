<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Entitlement\Entitlement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Yayımlanmış plan kataloğu — `docs/90`.
 *
 * GÖÇ DEĞİL TOHUM. İlk hâlinde bunu bir göçe yazmıştım ve dokuz mevcut test
 * kırıldı: hepsi `plans` tablosunun boş başladığını varsayıyordu ve biri tam
 * olarak BOŞ tablo davranışını ölçüyordu. Testler haklıydı — göç ŞEMA
 * içindir, iş verisi için değil. Fiyat şema değildir; sahibi onu yarın
 * değiştirir.
 *
 * KADEMELER UYDURULMADI, UYGULANANDAN TÜRETİLDİ. Plana bağlı yetenek kümesi
 * `Entitlement` enum'udur ve BURASI ONUN TEK ALICISIDIR: enum'a eklenip
 * hiçbir kademeye konmayan bir yetenek, çalışsa bile satılamaz
 * (`PlanCatalogueSellsEveryCapabilityTest`). Temel zincir
 * (kayıt → menü → yayın → QR → misafir sayfası) PLANSIZ çalışır ve
 * `RestaurantCriticalJourneyTest` bunu dondurur. Dördüncü bir kademe icat
 * etmek, parası alınan ama kapanmayan bir kapı satmak olurdu.
 *
 * Merdiven yalnız BÜYÜR: üst kademe alt kademenin her şeyini içerir. Aksi
 * hâlde "yükselt" düğmesi bazı şeyleri kaybettirirdi.
 *
 * ÇALIŞTIRMASI GÜVENLİDİR: var olan bir koda dokunmaz, dolayısıyla sahibin
 * sonradan yaptığı fiyat düzenlemesini geri almaz ve her dağıtımda yeniden
 * çalışabilir.
 */
final class PlanCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $sort = 0;

        foreach (self::catalogue() as $code => $plan) {
            if (DB::table('plans')->where('code', $code)->exists()) {
                $sort++;

                continue;
            }

            DB::table('plans')->insert([
                'name' => $plan['name'],
                'code' => $code,
                'version' => 1,
                'is_active' => true,
                'sort_order' => $sort++,
                'entitlements' => json_encode($plan['entitlements']),
                'amount_minor' => $plan['amount_minor'],
                'currency' => 'TRY',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @return array<string, array{name: string, amount_minor: int, entitlements: list<string>}> */
    public static function catalogue(): array
    {
        return [
            /*
                ÜCRETSİZ — çünkü zaten ücretsiz.

                Temel zincir plansız çalışıyor ve bir test bunu donduruyor.
                Buna "Starter" demek, var olan gerçeği adlandırmaktır;
                yokmuş gibi davranmak ise ziyaretçiye ödemeden önce yalan
                söylemek olurdu.
            */
            'starter' => [
                'name' => 'Starter',
                'amount_minor' => 0,
                'entitlements' => [],
            ],

            /*
                Kırk masalık bir restoranın ilk gün ihtiyacı: kodları TEK TEK
                değil toplu üretmek, menüsünde neyin işe yaradığını görmek ve
                masadaki menünün kendi markası gibi görünmesi.

                Marka görünümü burada açılır çünkü ayırt edici olan tam da
                budur: ücretsiz kademe menüyü yayınlar ve karekod basar, ama
                misafirin gördüğü sayfa nötrdür (`docs/113` §10.1).

                MASADAN SİPARİŞ DE BURADA AÇILIR, en üstte değil. Sipariş
                hattı uçtan uca çalışıyordu ama hiçbir kademede satılmıyordu
                (`docs/122` §2): yazılmış, para hattına hiç bağlanmamıştı.
                Kademesi ölçümden geldi — `docs/115` §4 `order.view`,
                `order.confirm` ve `order.kitchen` izinlerinin üçünü de
                Sahip'e veriyor, yani sipariş almak için ekip daveti
                GEREKMİYOR. `team`e koymak, tam da bu kademenin tarif ettiği
                sahip-işletmeli restorana çalışan bir yeteneği satılamaz
                kılardı. Ücretsiz kademede olmamasının sebebi de ölçüm:
                sipariş kalıcı kayıt, mutfak yoklaması ve denetim izi üretir;
                menüyü göstermek üretmez.

                ZENGİN GÖRSEL de burada başlar ve kademe UYDURULMADI:
                `config/media-quota.php` ücretsiz kademeye 200 MB, buraya
                2 GB veriyor. Zengin görseli 200 MB'ın üstünde satmak,
                kotanın ilk günde bozacağı bir söz olurdu. Hakkın misafir
                yüzeyi Dalga 6'da yazılır (`docs/122` Y6) ve o güne kadar
                fiyat sayfasında DUYURULMAZ — hak burada, reklamı orada
                değil.
            */
            'restaurant' => [
                'name' => 'Restaurant',
                'amount_minor' => 49900,
                'entitlements' => [
                    Entitlement::QrBulkGeneration->value,
                    Entitlement::AnalyticsReporting->value,
                    Entitlement::BrandingCustom->value,
                    Entitlement::OrderingBasic->value,
                    Entitlement::MenuRichMedia->value,
                ],
            ],

            /*
                Sahibin menüyü tek başına yönetmediği yer: garson, müdür,
                muhasebeci. Ekip daveti burada açılır.
            */
            'team' => [
                'name' => 'Team',
                'amount_minor' => 99900,
                'entitlements' => [
                    Entitlement::QrBulkGeneration->value,
                    Entitlement::AnalyticsReporting->value,
                    Entitlement::BrandingCustom->value,
                    Entitlement::OrderingBasic->value,
                    Entitlement::MenuRichMedia->value,
                    Entitlement::TeamInvitations->value,
                ],
            ],
        ];
    }
}
