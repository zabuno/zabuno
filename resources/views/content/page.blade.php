@extends('public.layout')

{{-- YAYINLANMIŞ kurumsal içerik sayfası — FF-191, yönerge §15.

     Sayfa başına şablon YOKTUR. Beş ürün sayfası da bu tek dosyadan çizilir;
     aralarındaki fark yalnız içeriktir. Yönergenin §13.4'te yasakladığı şey
     (kopya değer önermesi taşıyan yüzlerce sayfa) ancak böyle engellenir.

     Kabuk (`public.layout`, header, footer) BAŞKA bir pakete aittir ve
     buradan DEĞİŞTİRİLMEZ; bu sayfa yalnız onun bıraktığı boşluğu doldurur.

     Şablonda tek bir sabit kullanıcı metni yok (I18N-SSR-RATCHET-16). Bütün
     görünen metin — bölüm başlıkları dahil — içerik katmanından gelir. Bu bir
     kısıtın yan etkisi değil, `docs/118` E4'ün gereği: dil kararı değiştiğinde
     değişecek TEK katman içeriktir. --}}

@section('title', $content->metadata->seoTitle)
@section('description', $content->metadata->metaDescription)

@section('content')
    <main id="main-content" role="main" class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-8">
        @if (count($trail) >= 2)
            {{-- Ekmek kırıntısı bir SIRALI listedir; sıra bilginin kendisidir.

                 Etiketli bir `nav` yerine düz `ol`: bu şablonda sabit metin
                 yazılamaz (I18N-SSR-RATCHET-16) ve `aria-label` de görünen
                 metin sayılır. Makine tarafındaki anlamı `BreadcrumbList`
                 taşıyor; bir katalog anahtarı açıldığında landmark eklenir. --}}
            <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-fg-secondary">
                @foreach ($trail as $crumb)
                    <li class="flex items-center gap-2">
                        @unless ($loop->first)
                            {{-- Ayıraç GÖRSELDİR, bilgi değil: ekran okuyucu
                                 basamakları liste olarak zaten duyurur ve her
                                 basamak arasında "bölü" okumak gürültüdür.
                                 Boşluk tek başına yetmiyordu — 320 pikselde iki
                                 basamak birbirine yapışık okunuyordu. --}}
                            <span aria-hidden="true">/</span>
                        @endunless
                        @if ($crumb->isLinkable() && ! $loop->last)
                            <a href="{{ $crumb->path }}" class="underline">{{ $crumb->label }}</a>
                        @elseif ($loop->last)
                            {{-- Bulunduğun sayfa bir BAĞLANTI DEĞİLDİR: tıklayınca
                                 aynı yerde kalan bir bağlantı, klavye ve ekran
                                 okuyucu kullanıcısı için gürültüdür. --}}
                            <span aria-current="page">{{ $crumb->label }}</span>
                        @else
                            {{-- Yayınlanmamış ata BAĞLANTI ALMAZ (`docs/105` §2.2(3)):
                                 hiçbir yere götürmeyen bağlantı bir yalandır. Basamak
                                 yine görünür, çünkü onu silmek hiyerarşiyi yanlış
                                 göstermek olurdu. --}}
                            <span>{{ $crumb->label }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        {{-- SAYFANIN TEK H1'i. Şablon tek H1 üretir; ikincisini yazacak yer yok. --}}
        <h1 class="text-3xl font-semibold leading-tight text-fg">{{ $content->metadata->h1 }}</h1>

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
