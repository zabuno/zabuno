<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Domain\Legal\CompanyProfile;

/**
 * Şirket kimliğinin SAYFAYA çıkan hâli — tek yer (FF-216).
 *
 * ═══ NEDEN BU SINIF VAR ═══
 *
 * FF-198'de şirket bilgisi yalnız yasal belgelerin `{company.*}` yer
 * tutucularını dolduruyordu. Ödeme kuruluşunun üye iş yeri incelemesi ise
 * aynı olguları belge metninde değil, "iletişim" ve "hakkımızda"
 * sayfalarında ARIYOR — adres, telefon, e-posta, ünvan, vergi bilgisi.
 *
 * Sahibin isteği açıktı: sekiz değeri BİR KEZ girsin, bütün sayfalara
 * yayılsın. Bu sınıf o yayılmanın tek noktasıdır: alan adı → etiket eşlemesi
 * BURADA yaşar. İkinci bir yere kopyalansaydı, bir alan eklendiğinde bir
 * sayfa onu göstermeye devam eder, diğeri sessizce atlardı.
 *
 * ═══ DEĞER NEREDEN OKUNUR VE NEDEN ═══
 *
 * `.env` → `config/legal.php#company` → `CompanyProfile`. Sağlayıcı
 * anahtarları bu depoda şifreli kasadan giriliyor (`docs/94`); şirket
 * kimliği oraya KONMADI ve konmayacak, üç ölçülmüş sebeple:
 *
 * 1. Bir kasa SIR içindir. Bir tüzel kişinin ünvanı, adresi, MERSİS ve
 *    vergi numarası sır değildir — kanun onları YAYINLAMAYI emreder. Bir
 *    kimliği sır kasasına koymak, kasanın ne için var olduğunu bulanıklaştırır.
 * 2. Kasa veritabanıdır. Kurumsal sayfalar bilerek veritabanına dokunmuyor
 *    (`SiteNavigation::linkableRegistryPaths` bir veritabanı düşüşünde
 *    tanıtım sitesini ayakta tutmak için boş liste döner). Sözleşmenin
 *    tarafını veritabanına bağlamak, veritabanı tökezlediğinde sözleşmeyi
 *    tarafsız bırakırdı.
 * 3. Statik önizleme (`site:export-static`) veritabanı olmadan çizilir.
 *    Kasadan okunan bir ünvan o çıktıda hep boş görünürdü.
 *
 * Yani: sır kasadan, kimlik ortamdan. Sahip yedi değeri `.env`'e bir kez
 * girer; yasal belgeler, `/about`, `/contact` ve sitemap kararı aynı anda
 * doğru olur.
 */
final class CompanyIdentity
{
    /**
     * Alan adı → metin kataloğu anahtarının `SiteText::all()` içindeki adı.
     *
     * Sıra ANLAMLIDIR: bir kimlik önce kim olduğunu, sonra nerede olduğunu,
     * sonra hangi sicile kayıtlı olduğunu, en sonra nasıl ulaşılacağını
     * söyler.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'legal_name' => 'companyLegalName',
        'address' => 'companyAddress',
        'mersis' => 'companyMersis',
        'tax_office' => 'companyTaxOffice',
        'tax_number' => 'companyTaxNumber',
        'email' => 'companyEmail',
        'phone' => 'companyPhone',
    ];

    /**
     * Sayfada çizilecek satırlar: etiket + değer (girilmemişse `null`).
     *
     * @param  array<string, string>  $siteText  `SiteText::all()` çıktısı.
     * @return list<array{field: string, label: string, value: string|null}>
     */
    public static function rows(CompanyProfile $company, array $siteText): array
    {
        $rows = [];

        foreach (self::LABELS as $field => $textKey) {
            $rows[] = [
                'field' => $field,
                'label' => $siteText[$textKey] ?? $textKey,
                // UYDURMA YOK: girilmemiş alan boş döner ve şablon bunu
                // "girilmedi" diye YAZAR — sessizce atlamaz. Atlanan bir
                // satır, o alanın hiç istenmediği izlenimi verirdi.
                'value' => $company->field($field),
            ];
        }

        return $rows;
    }

    /**
     * Girilmemiş alanların okunabilir adları — uyarı bandı için.
     *
     * @param  list<string>  $missingFields  `CompanyProfile::missing()`.
     * @param  array<string, string>  $siteText
     * @return list<string>
     */
    public static function labelsFor(array $missingFields, array $siteText): array
    {
        $labels = [];

        foreach (self::LABELS as $field => $textKey) {
            if (in_array($field, $missingFields, true)) {
                $labels[] = $siteText[$textKey] ?? $textKey;
            }
        }

        return $labels;
    }
}
