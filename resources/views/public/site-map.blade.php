@extends('public.layout')

{{-- SİTE HARİTASI — İNSAN İÇİN (C3).

     ── NEDEN İÇ İÇE LİSTE, NEDEN GÖRSEL DEĞİL ──

     Bir site haritası şeması resim olarak çizilebilirdi; o resim ekran
     okuyucuda tek bir "görsel" olur, büyütülünce bulanıklaşır, çevrilemez ve
     içindeki hiçbir adrese TIKLANAMAZ. Bir gezinti ağacı zaten bir listedir:
     `<ul>` içinde `<ul>`. Ekran okuyucu "5 öğe" der, klavye kullanıcısı
     kaçının kaldığını bilir, arama motoru her bağlantıyı görür.

     ── BAĞLAYICI ÇİZGİLER CSS'TEN, KÜTÜPHANEDEN DEĞİL ──

     Çizgiler `site-directory.css` içinde çiziliyor: her düğüm kendi dikey
     gövde parçasını (`::before`) ve gövdeden kendine gelen yatay kolu
     (`::after`) taşır. Geniş ekranda birinci kat kartlara girer ve kollar
     çizilmez — sarılan bir ızgarada kol bağlanacak gövde bulamaz.

     Ağır bir çizim kütüphanesi yüklemek, bir listeyi çizmek için sayfaya
     yüzlerce kilobayt eklemek olurdu — üstelik kurumsal site React YÜKLEMEZ
     (HOME-NO-REACT-05) ve bu kararı bir süs için bozmak, ölçülmüş bir
     kazancı geri vermektir.

     Çizgiler DEKORDUR: `aria-hidden` gerekmez çünkü hiçbiri bir öğe değil,
     hepsi sözde-öğe. Betik olmadan da, çizgiler hiç boyanmasa da liste
     okunur ve her bağlantı çalışır (`docs/118` E8).

     ── HEDEFLER UYDURULMAZ ──

     Ağacın tamamı `SiteNavigation`den gelir: yayınlanmamış ya da metni
     yazılmamış bir sayfa buraya HİÇ giremez. "İleride olacak" sayfaların
     envanteri BU SAYFANIN İŞİ DEĞİLDİR — o liste ziyaretçiye 404 vaadidir. --}}

@section('title', $st['siteMapHeading'])
@section('description', $st['siteMapLead'])

@section('content')
    <main id="main-content" class="site-page">
        @include('public.partials.prologue', [
            'prologueHeading' => $st['siteMapHeading'],
            'prologueLead' => $st['siteMapLead'],
            'prologueVariant' => 'conduit',
        ])

        <div class="site-measure-page site-page-body">
            <nav class="site-map-tree" aria-label="{{ $st['siteMapHeading'] }}" data-site-map>
                <ul class="site-map-root">
                    <li>
                        {{-- KÖK TIKLANABİLİR: ağacın kökü bir etiket değil bir
                             adrestir. Buraya "eve nasıl dönerim" diye gelen
                             ziyaretçi de var. --}}
                        <a href="/" class="site-map-node site-map-home">{{ $st['siteMapHome'] }}</a>

                        <ul class="site-map-branch">
                            @foreach ($siteMapGroups as $group)
                                <li>
                                    {{-- GRUP BİR SAYFA DEĞİL: adı bir bağlantı
                                         değil bir başlıktır. Grup adını
                                         tıklanabilir göstermek, olmayan bir
                                         sayfanın sözünü vermek olurdu. --}}
                                    <span class="site-map-node site-map-group" data-site-map-group="{{ $group['id'] }}">{{ $group['label'] }}</span>

                                    <ul class="site-map-leaves">
                                        @foreach ($group['items'] as $item)
                                            <li>
                                                <a href="{{ $item['href'] }}" class="site-map-node site-map-page">{{ $item['label'] }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                </ul>
            </nav>
        </div>
    </main>
@endsection
