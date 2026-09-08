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

@php
    /*
        SAHNE, 386 SAYFAYA TEK YERDEN (`docs/146` §12).

        Döngü 2 bu sayfaları bilerek dışarıda bırakmıştı: *"kütük sayfalarının
        kompozisyonu hiç ölçülmedi ve 386 sayfayı tek seferde giydirmek,
        sahibin göreceği ilk kusuru üretirdi."* Cevap sayfa sayısını
        azaltmak değil, KARARI TEKİLLEŞTİRMEK: bant burada bir kez isteniyor
        ve 386 sayfanın hepsi aynı anda aynı şeyi giyiyor.

        ── BANDIN YÜZÜ SAYFANIN ANLAMINDAN TÜRER, RASTGELE DEĞİL ──

        `docs/146` §10.1'in kuralı: aynı bandı üst üste görmek, bandın
        kendisini görünmez yapar. Ama 386 sayfaya elle yüz seçilemez. Seçim
        ekmek kırıntısının DERİNLİĞİNDEN geliyor ve derinlik sayfanın
        hiyerarşideki rolüdür:

          · kök seviyesi  → `orbit`   — bir SİSTEMİN kendisi
          · ikinci seviye → `grid`    — o sistemin üstünde durduğu ZEMİN
          · daha derin    → `conduit` — bir İŞLEMİN anlatıldığı yer

        Rastgele bir seçim (yol karması) de üç yüzü dağıtırdı, ama hiçbir
        şey ANLATMAZDI; ve iki komşu sayfa aynı yüzü rastgele alabilirdi.
    */
    $prologueVariant = match (true) {
        count($trail) <= 1 => 'orbit',
        count($trail) === 2 => 'grid',
        default => 'conduit',
    };
@endphp

@section('content')
    <main id="main-content" role="main" class="site-page">
        @if (count($trail) >= 2)
            {{-- Ekmek kırıntısı bir SIRALI listedir; sıra bilginin kendisidir.

                 Etiketli bir `nav` yerine düz `ol`: bu şablonda sabit metin
                 yazılamaz (I18N-SSR-RATCHET-16) ve `aria-label` de görünen
                 metin sayılır. Makine tarafındaki anlamı `BreadcrumbList`
                 taşıyor; bir katalog anahtarı açıldığında landmark eklenir. --}}
            <ol class="site-measure-prose flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-fg-secondary">
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
                            {{-- DOKUNMA HEDEFİ 44 PİKSELDEN KÜÇÜK OLAMAZ.

                                 Ölçüldü (`scripts/mobile-ux-audit`, 320×568,
                                 2026-09-08): kırıntı bağlantısı 112×20'ydi.
                                 Bu sayfalar yayına alınana kadar kusur hiçbir
                                 yerde görünmüyordu — ölçülen tek şey ekranda
                                 GERÇEKTEN olan sayfalardır ve bu sayfaların
                                 hiçbiri açılmamıştı.

                                 20 piksel bir imleç için yeter, parmak için
                                 yetmez: hedefe basamayan kişi geri gidemez.
                                 Aynı çözüm bu şablon ailesinde zaten var
                                 (`content.blocks.related`, `content.blocks.cta`);
                                 ikinci bir yol açmak, iki farklı hedef boyu
                                 üretirdi. --}}
                            <a href="{{ $crumb->path }}"
                               class="inline-flex min-h-[44px] items-center underline">{{ $crumb->label }}</a>
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

        {{-- SAYFANIN TEK H1'i, ARTIK SAHNENİN İÇİNDE. Şablon tek H1 üretir;
             ikincisini yazacak yer yok — bant onu taşıyınca da öyle kaldı. --}}
        {{-- ÖLÇÜ KABI: OKUMA GENİŞLİĞİ, SAYFA GENİŞLİĞİ DEĞİL.

             Bu sayfalar paragraf okunan sayfalardır. 1280 pikselde sayfa
             ölçüsü (`--zc-measure-page`) satırı 1200 piksele kadar uzatıyordu
             ve o uzunlukta göz satır başını kaybeder. Bant tam kanamalı kalır
             ama İÇİ, gövdeyle AYNI sütunda durur: başlık ile ilk paragrafın
             sol kenarı hizalanmazsa sayfa ikiye bölünmüş gibi okunur. --}}
        @include('public.partials.prologue', [
            'prologueHeading' => $content->metadata->h1,
            'prologueVariant' => $prologueVariant,
            'prologueMeasure' => 'site-measure-prose',
        ])

        <div class="site-measure-prose site-page-body">
            @foreach ($content->blocks as $block)
                @include('content.blocks.'.$block->type->value, ['block' => $block])
            @endforeach

        {{-- JSON-LD sunucuda üretilen HTML'in İÇİNDEDİR (yönerge §14): JavaScript
             çalıştırmayan bir bot yalnız bunu görür. Gövdede duruyor çünkü
             `<head>` kabuğun dosyasıdır ve bu paket ona dokunmuyor; JSON-LD'nin
             belgedeki yeri geçerliliğini etkilemez. --}}
        <script type="application/ld+json" nonce="{{ $cspNonce ?? '' }}">{!! $structuredData !!}</script>
        </div>
    </main>
@endsection
