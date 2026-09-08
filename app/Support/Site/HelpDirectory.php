<?php

declare(strict_types=1);

namespace App\Support\Site;

/**
 * Yardımın DİZİNİ — makalelerin listesi, makalelerin KENDİSİNDEN (FF-240).
 *
 * ═══ ÖLÇÜLEN BOŞLUK ═══
 *
 * `/help` bir makaledir ve öyle kalır (`docs/89`): panelin ilk-kez ipuçları
 * ona ve onun çıpalarına bağlanıyor. Eksik olan makale değil, makalenin
 * ETRAFIYDI — okuyucu başka ne yazıldığını göremiyordu. Yardım bir sayfa
 * olduğu sürece bu görünmüyordu; ikinci makale yazıldığı an, hiçbir yerden
 * bağlanmayan bir sayfaya dönüşecekti (`NAV-REGISTRY-05` bunun tersini
 * ölçer: 404'e bağlantı yok; bu sınıf onun eşini kapatır — yazılmış ama
 * bağlanmamış sayfa yok).
 *
 * ═══ NEDEN LİSTE BURADA DEĞİL, DOSYALARDA ═══
 *
 * Makale adları bir katalog anahtarı ALMAZ. Altbilginin içerik katıyla aynı
 * gerekçe (`docs/136` §6.2): her yeni makale için bir anahtar yazmak, bir
 * kod değişikliği ve bir çeviri borcu üretirdi. Ad ve tek satırlık tarif
 * makalenin KENDİ `@section` bildirimindedir — yani dizindeki başlık ile
 * makalenin başlığı ayrışamaz, çünkü ikisi aynı dizedir.
 *
 * Kaynak dil dizini taranır (`docs/118` E4): her makale o dilde VARDIR,
 * çevirisi olmayabilir. Çevrilmiş dosyaya göre listelemek, Türkçe bir
 * tarayıcıda dizini bir satıra düşürürdü.
 *
 * ═══ BAŞLIĞI OKUNAMAYAN MAKALE ═══
 *
 * Listelenmez — ve bunu bir KAPI görür (`CorporatePageMaturityTest`), ziyaretçi
 * değil. Slug'dan bir ad türetmek ("first-15-minutes" → "First 15 minutes")
 * makalenin yazarının seçmediği bir başlık uydurmak olurdu; sayfayı 500'e
 * düşürmek ise bir yazım hatasını ziyaretçinin sorunu yapardı. Eksik bir
 * başlık CI'da görünür, üretimde görünmez.
 *
 * ═══ SIRA ═══
 *
 * Giriş makalesi HER ZAMAN ilk: `/help` adresinin kendisidir ve okuyucu
 * oradan gelir. Kalanlar dosya adına göre — keyfî ama KARARLI bir sıra;
 * her açılışta değişen bir liste, aradığını iki kez arayan bir okuyucu
 * demektir.
 */
final class HelpDirectory
{
    /**
     * `/help` adresinin makalesi.
     *
     * `HelpLibrary::pathFor()` ile aynı dosya adı ve bu bir tekrar DEĞİL,
     * bir sözleşme: `CorporatePageMaturityTest` dizindeki her adresin 200 döndüğünü
     * ölçer, dolayısıyla ikisi ayrışırsa kapı kırılır.
     */
    public const ENTRY = 'first-15-minutes';

    /** Kaynak dil (`docs/118` E4). Her makale bu dilde vardır. */
    public const SOURCE = 'en';

    /**
     * Bugün gerçekten yazılmış makaleler.
     *
     * @param  string|null  $root  Taranacak dizin; testler kendi kümesini verir.
     * @return list<array{slug: string, title: string, summary: string|null, path: string}>
     */
    public static function articles(?string $root = null): array
    {
        $root = $root ?? resource_path('help/'.self::SOURCE);

        $files = glob(rtrim($root, '/').'/*.blade.php');

        if ($files === false) {
            return [];
        }

        sort($files);

        $articles = [];

        foreach ($files as $file) {
            $slug = basename($file, '.blade.php');
            $source = (string) file_get_contents($file);
            $title = self::section($source, 'title');

            // Başlığı okunamayan makale LİSTELENMEZ; kapı onu yakalar.
            if ($title === null) {
                continue;
            }

            $articles[] = [
                'slug' => $slug,
                'title' => $title,
                'summary' => self::section($source, 'description'),
                'path' => self::pathOf($slug),
            ];
        }

        usort(
            $articles,
            static fn (array $a, array $b): int => [$a['slug'] !== self::ENTRY, $a['slug']]
                <=> [$b['slug'] !== self::ENTRY, $b['slug']],
        );

        return $articles;
    }

    /**
     * Bir makalenin genel adresi.
     *
     * Giriş makalesi `/help`tir; kimse onu `/help/first-15-minutes` diye
     * yer imlerine eklemedi ve o adresi ikinci bir kapı olarak açmak, aynı
     * metne iki kanonik adres vermek olurdu (`docs/118` §2).
     */
    public static function pathOf(string $slug): string
    {
        return $slug === self::ENTRY ? '/help' : '/help/'.$slug;
    }

    /**
     * `@section('<ad>', '<değer>')` bildirimindeki dize.
     *
     * Blade'i DERLEMEDEN okur ve bu bilinçli: dizini çizmek için dokuz
     * makaleyi derlemek, her yardım sayfası açılışında dokuz derleme
     * demekti. Aranan şey bir bildirim, bir gövde değil.
     */
    private static function section(string $source, string $name): ?string
    {
        $pattern = '/@section\(\s*\''.preg_quote($name, '/')
            .'\'\s*,\s*(\'(?<single>(?:[^\'\\\\]|\\\\.)*)\'|"(?<double>(?:[^"\\\\]|\\\\.)*)")\s*\)/';

        if (preg_match($pattern, $source, $matches) !== 1) {
            return null;
        }

        $raw = ($matches['single'] ?? '') !== '' ? $matches['single'] : ($matches['double'] ?? '');

        // Kaçışlı tırnak, yazarın yazdığı tırnaktır.
        $value = str_replace(['\\\'', '\\"', '\\\\'], ['\'', '"', '\\'], $raw);

        return trim($value) === '' ? null : $value;
    }
}
