@extends('public.layout')

{{-- YATIRIMCI İLETİŞİMİ (FF-251, `docs/149`).

     ── İKİNCİ BİR FORM ALTYAPISI YOK ─────────────────────────────────────

     Bu sayfa bir form ÇİZMEZ ve bu bir eksiklik değil bir karar. Sitede
     zaten çalışan bir iletişim akışı var (`/contact`: doğrulama, bal küpü,
     hız sınırı, referans numarası, alındı e-postası, kayıt). İkinci bir form
     ikinci bir kuyruk, ikinci bir hız sınırı ve bir gün birinin bakmayı
     unuttuğu ikinci bir kutu demekti — ve unutulan kutuya yazan kişi
     cevapsız kalırdı.

     ── AYRI BİR YATIRIMCI ADRESİ DE YOK ──────────────────────────────────

     "investors@…" gibi bir adres yazmak kolaydı; kimsenin okumadığı bir
     kutuya yazdırmak olurdu. Sayfa bunun yerine muhatabın GERÇEK kimliğini
     gösteriyor ve o kimlik `/about` ile AYNI kaynaktan (`CompanyProfile` →
     ortam) geliyor. Girilmemiş alan gizlenmez, "girilmedi" diye yazılır.

     Sahne: önsöz bandı `conduit` yüzünü taşıyor — iletişim bir MESAJIN gidip
     gelmesidir. Sayfanın gövdesinde hareket yok. --}}
@section('title', $st['investorsContactPageMetaTitle'])
@section('description', $st['investorsContactPageMetaDescription'])

@section('content')
    <main id="main-content" class="site-page">
        @include('public.partials.prologue', [
            'prologueHeading' => $st['investorsContactPageHeading'],
            'prologueLead' => $st['investorsContactPageLead'],
            'prologueVariant' => 'conduit',
            'prologueCta' => ['href' => '/contact', 'label' => $st['investorsContactPageCta']],
        ])

        <div class="site-measure-form site-page-body">
            <section aria-labelledby="investors-what-heading" class="site-page-body">
                <h2 id="investors-what-heading" class="site-display-3">{{ $st['investorsContactPageWhatHeading'] }}</h2>
                <p>{{ $st['investorsContactPageWhatBody'] }}</p>

                @if ($commitment !== null)
                    {{-- Yalnız yapılandırılmışsa (`docs/125` §3). --}}
                    <p class="site-pricing-note">{{ $commitment }}</p>
                @else
                    {{-- Boşken bir yedek cümle YOK; olmadığını SÖYLEMEK var.
                         Sessiz kalmak, okuyucunun kendi varsayımını
                         doldurmasına izin vermek olurdu. --}}
                    <p class="site-pricing-note">{{ $st['investorsContactPageCommitmentAbsent'] }}</p>
                @endif
            </section>

            <section aria-labelledby="investors-identity-heading" class="site-page-body">
                <h2 id="investors-identity-heading" class="site-display-3">{{ $st['investorsContactPageIdentityHeading'] }}</h2>
                <p>{{ $st['investorsContactPageIdentityBody'] }}</p>
                @include('public.partials.company-identity')
            </section>

            <p>
                <a href="/contact" class="site-action site-cta" data-emphasis="true">{{ $st['investorsContactPageCta'] }}</a>
            </p>
        </div>
    </main>
@endsection
