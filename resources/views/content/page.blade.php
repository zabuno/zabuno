@extends('public.layout')

{{-- YAYINLANMIŞ kurumsal içerik sayfası — FF-191, yönerge §15.

     Sayfa başına şablon YOKTUR. On sekiz sayfa da bu tek dosyadan çizilir;
     aralarındaki fark yalnız içeriktir. Yönergenin §13.4'te yasakladığı şey
     (kopya değer önermesi taşıyan yüzlerce sayfa) ancak böyle engellenir.

     Bunun tersi de doğru ve bu paketin bütün meselesi o: bir blok türünün
     görünümüne dokunmak, on sekiz sayfaya birden dokunmaktır. Hangi türün
     hangi okuma işini yaptığı ve neden öyle çizildiği `docs/148`da yazılı;
     çizimin kendisi `resources/css/site-content.css`te.

     Kabuk (`public.layout`, header, footer) BAŞKA bir pakete aittir ve
     buradan DEĞİŞTİRİLMEZ; bu sayfa yalnız onun bıraktığı boşluğu doldurur.

     Şablonda tek bir sabit kullanıcı metni yok (I18N-SSR-RATCHET-16). Bütün
     görünen metin — bölüm başlıkları dahil — içerik katmanından gelir. Bu bir
     kısıtın yan etkisi değil, `docs/118` E4'ün gereği: dil kararı değiştiğinde
     değişecek TEK katman içeriktir. --}}

@section('title', $content->metadata->seoTitle)
@section('description', $content->metadata->metaDescription)

@section('content')
    {{-- `site-main` kabuğun kolonu (genişlik, dolgu); `site-doc` bu sayfanın
         okuma ritmi. İkisi ayrı sınıf, çünkü ikisi ayrı pakete ait. --}}
    <main id="main-content" role="main" class="site-main site-doc">
        @if (count($trail) >= 2)
            {{-- Ekmek kırıntısı bir SIRALI listedir; sıra bilginin kendisidir.

                 Etiketli bir `nav` yerine düz `ol`: bu şablonda sabit metin
                 yazılamaz (I18N-SSR-RATCHET-16) ve `aria-label` de görünen
                 metin sayılır. Makine tarafındaki anlamı `BreadcrumbList`
                 taşıyor; bir katalog anahtarı açıldığında landmark eklenir.

                 SARMAZ. Ölçüldü (320×568): üç basamak sarınca kırıntı üç
                 satıra çıkıyor ve H1'i ekranın dışına itiyordu. Tek satır
                 kalıyor, sığmazsa kendi içinde kayıyor — ve kayan şey en
                 fazla son basamak, yani bir satır aşağıda H1 olarak tekrar
                 yazan ad. --}}
            <ol class="site-doc-trail">
                @foreach ($trail as $crumb)
                    <li>
                        @unless ($loop->first)
                            {{-- Ayıraç GÖRSELDİR, bilgi değil: ekran okuyucu
                                 basamakları liste olarak zaten duyurur ve her
                                 basamak arasında "bölü" okumak gürültüdür.
                                 Boşluk tek başına yetmiyordu — 320 pikselde iki
                                 basamak birbirine yapışık okunuyordu. --}}
                            <span class="site-doc-trail-separator" aria-hidden="true">/</span>
                        @endunless
                        @if ($crumb->isLinkable() && ! $loop->last)
                            {{-- DOKUNMA HEDEFİ 44 PİKSELDEN KÜÇÜK OLAMAZ
                                 (`docs/117` K1). Ölçüldü
                                 (`scripts/mobile-ux-audit`, 320×568,
                                 2026-09-08): kırıntı bağlantısı 112×20'ydi.
                                 20 piksel bir imleç için yeter, parmak için
                                 yetmez: hedefe basamayan kişi geri gidemez.

                                 Boy `site-doc-trail-link` içinde
                                 `--control-height` ile kuruluyor; şablonda
                                 ölçü YAZILMIYOR, çünkü bu ölçü on sekiz
                                 sayfanın ortak kararıdır ve tek yerde durur.
                                 Dokunulabilen tek basamak budur. --}}
                            <a href="{{ $crumb->path }}" class="site-doc-trail-link">{{ $crumb->label }}</a>
                        @elseif ($loop->last)
                            {{-- Bulunduğun sayfa bir BAĞLANTI DEĞİLDİR: tıklayınca
                                 aynı yerde kalan bir bağlantı, klavye ve ekran
                                 okuyucu kullanıcısı için gürültüdür. Tıklanamayan
                                 bir şeye dokunma hedefi vermek de öyle. --}}
                            <span class="site-doc-trail-step site-doc-trail-current" aria-current="page">{{ $crumb->label }}</span>
                        @else
                            {{-- Yayınlanmamış ata BAĞLANTI ALMAZ (`docs/105` §2.2(3)):
                                 hiçbir yere götürmeyen bağlantı bir yalandır. Basamak
                                 yine görünür, çünkü onu silmek hiyerarşiyi yanlış
                                 göstermek olurdu. --}}
                            <span class="site-doc-trail-step">{{ $crumb->label }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        {{-- SAYFANIN TEK H1'i. Şablon tek H1 üretir; ikincisini yazacak yer yok. --}}
        <h1 class="site-doc-title">{{ $content->metadata->h1 }}</h1>

        @foreach ($content->blocks as $block)
            @include('content.blocks.'.$block->type->value, ['block' => $block])
        @endforeach

        {{-- JSON-LD sunucuda üretilen HTML'in İÇİNDEDİR (yönerge §14): JavaScript
             çalıştırmayan bir bot yalnız bunu görür. Gövdede duruyor çünkü
             `<head>` kabuğun dosyasıdır ve bu paket ona dokunmuyor; JSON-LD'nin
             belgedeki yeri geçerliliğini etkilemez. --}}
        <script type="application/ld+json" nonce="{{ $cspNonce ?? '' }}">{!! $structuredData !!}</script>
    </main>
@endsection
