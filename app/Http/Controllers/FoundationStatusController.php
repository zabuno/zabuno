<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Site\HomeStory;
use App\Support\Site\PublicPlans;
use App\Support\Site\SiteShell;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Herkese açık pazarlama ve yasal sayfalar.
 *
 * Bu sayfalar SUNUCUDA üretilir ve React paketini hiç yüklemez. Karar
 * ölçümle alındı: istemcide üretildiklerinde bir tarayıcı botunun gördüğü
 * gövde 1.736 bayttı ve içeriği `<div id="app"></div>` ibaretti — yani
 * ürünün kendi tanıtımı ne arama motorunda ne de JavaScript çalıştırmayan
 * AI botlarında görünüyordu.
 *
 * Etkileşim gerektiren yüzeyler (`/app`, `/platform`) React olarak kalır;
 * burada etkileşim yok, yalnız metin ve bağlantı var.
 */
final class FoundationStatusController extends Controller
{
    /** Adres → ölçüm kimliği. Rapor bu adları okur, adresleri değil. */
    // Yasal sayfalar artık burada DEĞİL: sekiz belge kendi denetleyicisinde
    // (`ShowLegalDocumentController`, FF-198). Bu sınıfta yalnız ana sayfa
    // ve fiyat kaldı.
    private const PAGE_KEYS = [
        '' => 'home',
        'pricing' => 'pricing',
    ];

    public function __construct(
        private readonly PublicPlans $plans,
        private readonly SiteShell $shell,
        private readonly HomeStory $story,
    ) {}

    public function __invoke(Request $request): View
    {
        $path = trim($request->getPathInfo(), '/');

        /*
            KABUK VERİSİ TEK YERDEN (`SiteShell`).

            Metin kataloğu, kanonik adres, çıpa öneki, ölçüm kimliği ve
            gezinti aynı yerde üretilir. Ölçüm kimliği sabit bir sözlükten
            gelir, adresten türetilmez: adres yarın değişirse geçmiş raporlar
            ikiye bölünmemeli.
        */
        $shell = $this->shell->context($request, self::PAGE_KEYS[$path] ?? 'unknown');

        /*
            DİL KABUKTAN GELİR, burada İKİNCİ KEZ pazarlık edilmez (FF-249).

            Aşağıdaki iki satır `SiteText::pick($request->getPreferredLanguage(
            ['en', 'tr']))` çağırıyordu — elle yazılmış, `i18n.shipped_locales`e
            hiç bağlı olmayan bir liste. Sonucu ölçüldü (2026-09-08): sunulan
            tek dil `en` iken Türkçe bir tarayıcı Türkçe plan etiketleri ve
            Türkçe bir başlık alıyor, aynı ekrandaki "Create an account"
            İngilizce kalıyordu. Aynı ekranda iki dil, `<html lang="en">`
            altında.

            Kabuğun seçtiği dil zaten SUNULAN bir dildir; ikinci bir seçim
            yapmak, o kararı sessizce ezmek olurdu.
        */
        $locale = $shell['lang']->ui;

        $shared = $shell + [
            /*
                FİYAT KAYDOLMADAN GÖRÜLÜR — `docs/88` (P1-01).

                Plan listesi bugüne kadar `auth` + çalışma alanı bağlamı
                ardındaydı: fiyatı görmek için kaydolmak gerekiyordu, yani
                ürün kaydolmayı fiyatı görmeye bağlı kılıyordu.
            */
            /*
                Projeksiyon `App\Support\Site\PublicPlans`e taşındı (FF-251):
                yatırımcı sayfası da fiyatı gösterince ikinci bir müşteri
                doğdu ve "ücretsiz mi, fiyatlanmamış mı, fiyatlı mı" ayrımının
                iki yerde iki cevabı olamaz. Dil yine KABUKTAN (FF-249).
            */
            'plans' => $this->plans->forLocale($locale),
        ];

        if ($path === 'pricing') {
            return view('public.pricing', $shared);
        }

        /*
            ANA SAYFANIN ÜÇ LİSTESİ (`docs/138`).

            Fiyat sayfasına GEÇMEZ: orada zincir de parça listesi de
            görünmüyor ve okunmayacak 25 maddeyi her istekte çözmek,
            hiçbir şeyin görünmediği bir iş demekti.
        */
        return view('public.home', $shared + [
            // Aynı gerekçe (FF-249): tek dil kaynağı kabuğun kendisi.
            'story' => $this->story->lists($locale),
        ]);
    }
}
