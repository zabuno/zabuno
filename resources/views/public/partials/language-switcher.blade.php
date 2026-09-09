{{-- DİL DENETİMİ — betiksiz çalışan TEK denetim (`SHELL-SINGLE-SOURCE-04`).

     ── C1: DAĞINIK İKİ DÜĞMEDEN TEK BİR DENETİME ────────────────────────

     Önce: fieldset'in başlığı ("English / Türkçe") ile düğmelerin sözcükleri
     AYNI iki sözcüktü ve alt alta iki kez yazılıyordu; iki düğme de kendi
     çerçevesiyle duruyordu. Ziyaretçi dört kutu görüyordu, bir denetim değil.

     Şimdi: başlık erişilebilir adı taşımaya devam eder ama GÖRSEL olarak
     yerini bir küre glifi alır — grubun ne olduğunu tek bir işaretle söyler
     ve iki dil sözcüğü tek bir bölünmüş denetimin içinde yan yana durur.
     Sözcükler kaldırılmadı: küre tek başına "dil" demez, `ICON-03` de tek
     başına duran bir ikonu yasaklar. Ekran okuyucu hâlâ grubun adını duyar.

     Etiketler `endonym`dur (her dil KENDİ adıyla yazılır) ve çeviriden
     geçmez — bu bilinçli: Türkçesini bilmeyen biri "Turkish" yazısını
     aramaz, "Türkçe" yazısını arar. --}}
@php
    $siteLanguages = array_intersect_key(
        [
            'en' => \App\Support\Localization\Language::English->endonym(),
            'tr' => \App\Support\Localization\Language::Turkish->endonym(),
        ],
        array_flip((array) config('i18n.shipped_locales', [])),
    );
@endphp
<form method="POST" action="{{ route('public.language') }}" class="site-language-switcher">
    @csrf
    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
    <fieldset class="site-language-fieldset">
        <legend class="site-language-legend">
            @foreach ($siteLanguages as $code => $name)
                @unless ($loop->first) / @endunless
                <span lang="{{ $code }}">{{ $name }}</span>
            @endforeach
        </legend>
        <div class="site-language-control">
            <x-phosphor name="globe" class="site-language-globe" />
            <div class="site-language-options">
                @foreach ($siteLanguages as $code => $name)
                    <button type="submit" name="language" value="{{ $code }}" lang="{{ $code }}"
                            class="dz-btn site-language-option" aria-pressed="{{ $lang->ui === $code ? 'true' : 'false' }}">
                        {{ $name }}
                    </button>
                @endforeach
            </div>
        </div>
    </fieldset>
</form>
