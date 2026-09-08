<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\DependencyInventory;
use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

final class ThirdPartyLicenses
{
    public static function document(array $inventories): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'third-party-licenses',
            version: '0.1',
            effectiveDate: '2026-09-08',
            title: 'Üçüncü Taraf Lisansları',
            summary: 'Bu kurulumun kendi bağımlılık bildirimlerinden üretilen, Zabuno\'nun dayandığı açık kaynak bileşenler ve her birinin yayımlandığı lisans.',
            sections: array_merge(
                [
                    new LegalSection('Bu sayfa nedir?', [
                        'Zabuno başkalarının yayımladığı açık kaynak bileşenler üzerine kuruludur. Bu sayfa hizmetin doğrudan bağımlı olduğu bileşenleri ve her birinin lisansını belirtir; böylece satın alma veya hukuk incelemesinde bu soru için elektronik tablo istenmesi gerekmez.',
                        'Aşağıdaki listeler elle yazılmaz. Sayfa açıldığında bu kurulumun kendi bağımlılık bildirimlerinden ve kilit dosyalarından okunur; böylece bir sürümle eklenen veya kaldırılan bileşen, belge güncellemesini kimsenin hatırlaması gerekmeden buraya yansır.',
                        'Lisans adları, paket yazarlarının beyan ettiği şekliyle bu kilit dosyalarından gelir. Bir paket lisans beyan etmiyorsa kayıt, lisans tahmin etmek yerine bunu belirtir.',
                        'Her lisansın tam metni, ilgili paketle birlikte yayımlanır; buraya kopyalanmaz, çünkü kopya zamanla aslıyla eşleşmemeye başlayabilir.',
                    ]),
                ],
                array_map(static fn (DependencyInventory $inventory): LegalSection => self::section($inventory), $inventories),
                [
                    new LegalSection('Burada neler listelenmez?', [
                        'Dolaylı bağımlılıklar yukarıda sayılır ancak tek tek adlandırılmaz. Bir sayfada yüzlerce paketi sıralamak önemli olanları görünmez kılardı; sürümleriyle birlikte çözümlenmiş tam liste, bu yazılımın herkese açık kaynak deposundaki kilit dosyalarındadır.',
                        'Ziyaretçinin tarayıcısına veya çalışan sunucuya hiç ulaşmayan derleme ve geliştirme araçları — test çalıştırıcıları, kod denetleyicileri, bileşen atölyesi, tür denetleyicisi — listelenmez. Bunlar hizmetin parçası olarak dağıtılmaz.',
                        'Hizmetin ihtiyaç duyduğu çalışma ortamı — PHP yorumlayıcısı ve eklentileri, veritabanı motoru, web sunucusu ve işletim sistemi — de listelenmez. Bunlar yazılımın içinde dağıtılan bileşenler değil, çalıştığı ortamdır.',
                    ]),
                    new LegalSection('Zabuno\'nun kendi kodu', [
                        'Zabuno\'nun kendi kaynak kodu, herkese açık depoda orada belirtilen lisansla yayımlanır. Bu sayfa Zabuno\'nun kendi lisans koşullarını değil, kullandığı bileşenleri ele alır; hizmetle neler yapabileceğiniz Hizmet Koşullarında düzenlenir.',
                    ]),
                    new LegalSection('Sorular ve düzeltmeler', [
                        'Bir bileşenin yanlış lisansla listelendiğini veya eksik olduğunu düşünüyorsanız sitedeki iletişim formundan veya {company.email} adresine yazın. Hangi paketi kastettiğinizi belirtin.',
                    ]),
                ],
            ),
        );
    }

    private static function section(DependencyInventory $inventory): LegalSection
    {
        if (! $inventory->readable) {

            return new LegalSection($inventory->ecosystem, [
                'Bu ekosistemin bağımlılık bildirimi ('.$inventory->manifest.') bu kurulumda okunamadığı için burada liste üretilemiyor. "Üçüncü taraf kod yok" anlamına gelecek boş liste göstermek yanlış olacağından, durum açıkça belirtilir.',
                'Yetkili liste, bu yazılımın herkese açık kaynak deposundaki bu dosyalardadır. Başka biçimde ihtiyacınız varsa iletişim formundan isteyin.',
            ]);
        }

        $paragraphs = [
            'Doğrudan bağımlılıkların bildirildiği dosya: '.$inventory->manifest.'. Her birinin beyan ettiği lisansla ('.count($inventory->packages).' paket; dolaylı olarak eklenen '.$inventory->transitiveCount.' paket daha vardır ve burada tek tek belirtilmez):',
        ];

        foreach ($inventory->packages as $package) {
            $version = trim($package->version) === '' ? 'sürüm kaydedilmemiş' : $package->version;
            $paragraphs[] = $package->name.' '.$version.' — '.($package->license ?? 'kilit dosyasında lisans belirtilmemiş');
        }

        if ($inventory->packages === []) {
            $paragraphs[] = 'Bu kurulumda bu ekosistem için doğrudan bağımlılık beyan edilmemiştir.';
        }

        return new LegalSection($inventory->ecosystem, $paragraphs);
    }
}
