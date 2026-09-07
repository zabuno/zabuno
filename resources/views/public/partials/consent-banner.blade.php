{{-- Ölçüm KASADAN (yoksa env'den) okunur — `docs/135`. Bu satır env'de
     kalsaydı, sahip kimliği panelden girdiğinde şerit HİÇ çıkmaz, onay
     alınamaz ve konteyner de hiç yüklenmezdi: ölçüm "açıldı" sanılırken
     kapalı kalırdı ve bunu gösteren tek bir hata olmazdı. --}}
@php($zabunoConsentConfigured = app(\App\Infrastructure\Analytics\VaultAnalyticsSettings::class)->configuration()->isEnabled())
@php($zabunoConsentDecision = \App\Support\Analytics\MeasurementConsent::fromRequest(request()))
{{-- ÇEREZ SEÇİM ŞERİDİ — FF-198.

     ÜÇ KOŞUL BİRDEN: ölçüm yapılandırılmış (kap kimliği var), karar
     verilmemiş, ve sayfa şeridi gizlemek istememiş (`/cookies` kendi
     tercih bölümünü taşır). Yapılandırılmamış bir ölçüm için izin istemek,
     olmayan bir şeye onay toplamak olurdu.

     JAVASCRIPT YOK: düz bir form, iki düğme, CSRF. Kabuk betiksiz çalışmak
     zorunda (SHELL-SINGLE-SOURCE-04); soru da öyle.

     DAR EKRANDA ALTTA VE KOMPAKT (`docs/118` E1, E3): şerit yapışkan,
     içeriğin üstüne değil altına gelir, ilk ekranı içerikten önce
     doldurmaz. Bu bir ALTBİLGİ DEĞİLDİR (`aside`) — kabuğun tek altbilgisi var
     (SHELL-SINGLE-SOURCE-01). İKON YOK (`docs/118` E6). --}}
@if ($zabunoConsentConfigured && ! $zabunoConsentDecision->isDecided() && ! ($hideConsentBanner ?? false))
<aside class="site-consent" data-consent-banner aria-label="{{ $st['consentLabel'] }}">
    <form method="post" action="/consent/measurement" class="site-shell-inner site-consent-inner">
        @csrf
        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
        <p class="site-consent-body">{{ $st['consentBody'] }}</p>
        <div class="site-consent-actions">
            <button type="submit" name="decision" value="accept" class="site-consent-button" data-emphasis="true">{{ $st['consentAccept'] }}</button>
            <button type="submit" name="decision" value="decline" class="site-consent-button">{{ $st['consentDecline'] }}</button>
            {{-- Politika bağlantısı da 44 piksellik bir hedef: cümle içine
                 gömülü bir bağlantı dar ekranda parmakla vurulamaz. --}}
            <a href="/cookies" class="site-consent-button">{{ $st['consentLink'] }}</a>
        </div>
    </form>
</aside>
@endif
