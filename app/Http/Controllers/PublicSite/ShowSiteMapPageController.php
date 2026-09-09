<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\Site\SiteShell;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SİTE HARİTASI — İNSAN için (C3).
 *
 * ═══ `sitemap.xml` VARKEN NEDEN ═══
 *
 * O dosya arama motoru içindir ve bir ziyaretçi onu açmaz. Bu sayfa başka
 * bir soruyu yanıtlar: *"bu sitede ne var ve neyin altında?"* — gezintinin
 * KENDİSİNİ gösterir, hangi grubun neyi taşıdığını görünür kılar. Uzun bir
 * altbilgide katlanmış duran yapı burada tek bakışta okunur.
 *
 * ═══ AĞAÇ UYDURULMAZ — GEZİNTİDEN TÜRER ═══
 *
 * Tek kaynak `SiteNavigation::forShell()`. Bu, elle yazılmış bir liste
 * olsaydı ilk yayına alınan sayfada eskir ve ziyaretçiye 404'e giden bir
 * ağaç gösterirdi. Aynı süzgeçten geçtiği için burada YAYINLANMAMIŞ hiçbir
 * hedef görünemez: `ResolvePageDelivery` hayır dediği sürece bir yol bu
 * sayfaya da giremez (`docs/129` §3).
 *
 * ÇIPALAR DIŞARIDA: `#features` bir sayfa değil, bir sayfanın içindeki
 * başlıktır. Bir site haritası sayfaları sayar; başlıkları saymak, aynı
 * sayfayı üç kez listelemek olurdu.
 *
 * KÖK ANA SAYFADIR ve tıklanabilir: ağacın kökü bir etiket değil bir
 * adrestir, çünkü buraya "eve nasıl dönerim" diye gelen biri de vardır.
 *
 * VERİTABANINA DOKUNUR MU: `SiteNavigation` kütüğü okur ve okuyamazsa boş
 * liste döner; sayfa o durumda da açılır, yalnız kütükten türeyen kat
 * görünmez (aynı sınıftaki `linkableRegistryPaths` gerekçesi).
 */
final class ShowSiteMapPageController extends Controller
{
    public function __construct(private readonly SiteShell $shell) {}

    public function __invoke(Request $request): View
    {
        $shared = $this->shell->context($request, 'site-map', '/site-map');

        return view('public.site-map', $shared + [
            'siteMapGroups' => $this->groups($shared['nav']),
        ]);
    }

    /**
     * Ağacın dalları: bölge sırası okunma sırasıdır — önce gezinti, sonra
     * altbilgi grupları, sonra yasal belgeler, en sonra kütükten türeyen
     * içerik katı.
     *
     * Aynı hedef birden çok grupta geçebilir (fiyat hem üst çubukta hem
     * altbilgide) ve bu bir tekrar DEĞİL: harita gezintiyi olduğu gibi
     * gösterir, ondan farklı bir dünya çizmez.
     *
     * AYNI ADI TAŞIYAN DALLAR BİRLEŞTİRİLİR: "Hesap" hem üst çubukta
     * (`/login`) hem altbilgide (`/login`, `/register`) var ve ikisini yan
     * yana çizmek okuyana iki ayrı şey varmış gibi gösterirdi. Ad bir
     * ziyaretçi için grubun KİMLİĞİDİR; iki dal aynı adı taşıyorsa onun
     * için tek daldır. Birleşme kaybettirmez: ikinci dalın fazladan
     * maddesi birincinin altına eklenir.
     *
     * @param  array<string, mixed>  $nav
     * @return list<array{id: string, label: string, items: list<array{label: string, href: string}>}>
     */
    private function groups(array $nav): array
    {
        $groups = [];

        /** @var array<string, int> $seenGroups  Ad → `$groups` içindeki yeri. */
        $seenGroups = [];

        foreach (['header', 'footer', 'legal', 'content'] as $region) {
            /** @var list<array{id: string, label: string, items: list<array{label: string, href: string}>}> $regionGroups */
            $regionGroups = $nav[$region] ?? [];

            foreach ($regionGroups as $group) {
                $items = [];
                $seen = [];

                foreach ($group['items'] as $item) {
                    // Çıpa bir sayfa değildir; ana sayfanın kendisi kökte zaten var.
                    if (str_contains($item['href'], '#')) {
                        continue;
                    }

                    if (isset($seen[$item['href']])) {
                        continue;
                    }

                    $seen[$item['href']] = true;
                    $items[] = ['label' => $item['label'], 'href' => $item['href']];
                }

                if ($items === []) {
                    continue;
                }

                if (isset($seenGroups[$group['label']])) {
                    $position = $seenGroups[$group['label']];
                    $known = array_column($groups[$position]['items'], 'href');

                    foreach ($items as $item) {
                        if (! in_array($item['href'], $known, true)) {
                            $groups[$position]['items'][] = $item;
                        }
                    }

                    continue;
                }

                $seenGroups[$group['label']] = count($groups);

                $groups[] = [
                    'id' => $region.'-'.$group['id'],
                    'label' => $group['label'],
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }
}
