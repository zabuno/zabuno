<?php

declare(strict_types=1);

namespace App\Support\Localization;

use App\Models\ContentPage;

/**
 * Dil değiştiricinin SUNUCU tarafı — `docs/120` §5.
 *
 * Dokuz dilin her biri için tek bir soruya cevap üretir: "kullanıcı bu dile
 * geçebilir mi, geçerse nereye gider?"
 *
 * ═══ NEDEN DOKUZUNU DA DÖNDÜRÜYOR ═══
 *
 * Bir dili listeden düşürmek de bir karardır ve o kararı BİLEŞEN verir
 * (`docs/120` §5.8: "gösterilmez ya da açıkça 'henüz hazır değil' der").
 * Karar verebilmesi için her dil hakkında bir cevaba ihtiyacı var; eksik
 * bırakmak, bileşeni "bu dili hiç duymadım" durumuna sokardı ve iki farklı
 * yüzey iki farklı liste çizerdi.
 */
final class LanguageSwitcher
{
    /**
     * @param  string  $pageKey  Sayfanın dilden bağımsız kimliği.
     * @param  list<string>|null  $offeredLanguages  Bu yüzeyin sunabildiği diller; `null` ise `shipped_locales`.
     * @return list<LanguageChoice>
     */
    public function choicesFor(string $pageKey, string $currentLanguage, ?array $offeredLanguages = null): array
    {
        $offered = $offeredLanguages ?? $this->shippedLanguages();
        $paths = $this->counterpartPaths($pageKey);

        $choices = [];

        foreach (Language::cases() as $language) {
            $code = $language->value;

            /*
                SUNULMAYAN DİL "SEÇİLEBİLİR" GÖRÜNMEZ.

                Sayfası olsa bile. Sıra önemli: önce dil uzayı sorulur, sonra
                sayfa. Tersi olsaydı sunulmayan bir dil "sayfası yok" derdi ve
                kullanıcı sayfanın eksik olduğunu sanardı — oysa eksik olan
                ürünün o dildeki hâli.
            */
            if (! in_array($code, $offered, true)) {
                $choices[] = new LanguageChoice($language, null, false, false, 'not-offered');

                continue;
            }

            $href = $paths[$code] ?? null;

            if ($href === null) {
                // Olmayan bir sayfaya bağlantı vermek 404 üretirdi; sessizce
                // ana sayfaya bağlamak ise kullanıcıyı okuduğu yerden koparırdı.
                $choices[] = new LanguageChoice($language, null, false, false, 'no-counterpart');

                continue;
            }

            $choices[] = new LanguageChoice($language, $href, $code === $currentLanguage, true);
        }

        return $choices;
    }

    /**
     * Aynı sayfanın dillere göre adresleri.
     *
     * `SiteMapParser::pageKeyFor` dil dizinini anahtardan ATAR — yani aynı
     * sayfanın Türkçesi ve İngilizcesi aynı kök anahtarı paylaşır. Ama
     * `page_key` sütunu TEKİLDİR ve bir sayfanın iki dili tek satıra sığmaz:
     * yayın durumu, başlık ve yayın tarihi dile göre ayrı yaşar. Bu yüzden
     * kütükte diller anahtara eklenen dil ekiyle ayrışır
     * (`urun-qr-menu`, `urun-qr-menu-en`).
     *
     * Arama bu yüzden önce KÖK anahtarı bulur, sonra dokuz dilin ekli
     * hâllerini birlikte sorar: tek sorgu, dokuz olası kardeş. Dil başına bir
     * sorgu atmak, dokuz dilli bir menüde her sayfa görüntülemesine dokuz
     * gidiş dönüş eklerdi.
     *
     * @return array<string, string>
     */
    private function counterpartPaths(string $pageKey): array
    {
        $base = $pageKey;

        foreach (Language::cases() as $language) {
            $suffix = '-'.$language->value;

            if (str_ends_with($base, $suffix)) {
                $base = substr($base, 0, -strlen($suffix));

                break;
            }
        }

        $keys = [$base];

        foreach (Language::cases() as $language) {
            $keys[] = $base.'-'.$language->value;
        }

        return ContentPage::query()
            ->whereIn('page_key', $keys)
            ->pluck('canonical_path', 'locale')
            ->all();
    }

    /** @return list<string> */
    private function shippedLanguages(): array
    {
        /** @var array<int, string> $shipped */
        $shipped = config('i18n.shipped_locales', []);

        return array_values($shipped);
    }
}
